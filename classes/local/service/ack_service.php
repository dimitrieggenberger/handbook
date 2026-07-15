<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_handbook\local\service;

use context_system;
use moodle_exception;
use stdClass;

/**
 * Required-reading acknowledgements (specification 16).
 *
 * An acknowledgement records that a user confirmed reading a particular
 * published revision. Status logic: an older acknowledgement stays valid
 * until a later revision is published with "requires re-acknowledgement";
 * then the page returns to pending as a reconfirmation.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ack_service {

    /** @var string Page is not required reading. */
    public const STATUS_NOT_REQUIRED = 'not_required';
    /** @var string Never acknowledged. */
    public const STATUS_PENDING = 'pending';
    /** @var string Acknowledged an older version; material change published since. */
    public const STATUS_RECONFIRM = 'reconfirm';
    /** @var string Acknowledgement covers the current published revision. */
    public const STATUS_CONFIRMED = 'confirmed';

    /** @var int Wording version of the confirmation text (bump when it changes). */
    public const CONFIRMATION_VERSION = 1;

    /**
     * Acknowledgement status of a user for a page.
     *
     * @param int $userid User id.
     * @param stdClass $page Page record.
     * @return stdClass {status, ack|null} where ack is the newest ack record.
     */
    public static function get_status(int $userid, stdClass $page): stdClass {
        global $DB;

        $result = (object)['status' => self::STATUS_NOT_REQUIRED, 'ack' => null];

        if (!(int)$page->requiredreading || !(int)$page->publishedrevisionid) {
            return $result;
        }

        $acks = $DB->get_records('local_handbook_ack',
            ['userid' => $userid, 'pageid' => $page->id], 'timeacknowledged DESC', '*', 0, 1);
        $ack = $acks ? reset($acks) : null;
        $result->ack = $ack;

        if (!$ack) {
            $result->status = self::STATUS_PENDING;
            return $result;
        }

        if ((int)$ack->revisionid === (int)$page->publishedrevisionid) {
            $result->status = self::STATUS_CONFIRMED;
            return $result;
        }

        // Acknowledged an older revision: still valid unless a later
        // published revision demanded re-acknowledgement (spec 16).
        $ackedversion = (int)$DB->get_field('local_handbook_revision', 'versionnumber',
            ['id' => $ack->revisionid]);

        $sql = "SELECT COUNT(1)
                  FROM {local_handbook_revision}
                 WHERE pageid = :pageid
                   AND versionnumber > :ackedversion
                   AND timepublished > 0
                   AND requiresreacknowledgement = 1";
        $needsreack = (int)$DB->count_records_sql($sql, [
            'pageid' => $page->id,
            'ackedversion' => $ackedversion,
        ]) > 0;

        $result->status = $needsreack ? self::STATUS_RECONFIRM : self::STATUS_CONFIRMED;
        return $result;
    }

    /**
     * Record an acknowledgement of the current published revision.
     *
     * Idempotent per (user, revision). Records user, page, revision, path,
     * confirmation wording version and timestamp (spec 16).
     *
     * @param int $userid User id.
     * @param stdClass $page Page record.
     * @param int $pathid Reading path context (0 = none).
     * @return stdClass The acknowledgement record.
     */
    public static function acknowledge(int $userid, stdClass $page, int $pathid = 0): stdClass {
        global $DB;

        if (!(int)$page->requiredreading) {
            throw new moodle_exception('errornotrequiredreading', 'local_handbook');
        }
        if (!(int)$page->publishedrevisionid) {
            throw new moodle_exception('notpublished', 'local_handbook');
        }

        $existing = $DB->get_record('local_handbook_ack',
            ['userid' => $userid, 'revisionid' => $page->publishedrevisionid]);
        if ($existing) {
            return $existing;
        }

        $ack = new stdClass();
        $ack->userid = $userid;
        $ack->pageid = (int)$page->id;
        $ack->revisionid = (int)$page->publishedrevisionid;
        $ack->pathid = $pathid;
        $ack->confirmationversion = self::CONFIRMATION_VERSION;
        $ack->timeacknowledged = time();
        $ack->id = $DB->insert_record('local_handbook_ack', $ack);

        $event = \local_handbook\event\page_acknowledged::create([
            'context' => context_system::instance(),
            'objectid' => $ack->id,
            'relateduserid' => $userid,
            'other' => ['pageid' => (int)$page->id, 'revisionid' => (int)$page->publishedrevisionid],
        ]);
        $event->trigger();

        return $ack;
    }

    /**
     * Count pending/reconfirm required pages for a user in one query.
     *
     * Uses the same validity rule as get_status(): an acknowledgement
     * counts while its version is at least the highest published version
     * that demanded re-acknowledgement.
     *
     * @param int $userid User id.
     * @return int
     */
    public static function count_pending_for_user(int $userid): int {
        global $DB;

        $sql = "SELECT COUNT(1)
                  FROM {local_handbook_page} p
                 WHERE p.requiredreading = 1 AND p.publishedrevisionid > 0 AND p.archived = 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {local_handbook_ack} a
                         JOIN {local_handbook_revision} r ON r.id = a.revisionid
                        WHERE a.userid = :userid AND a.pageid = p.id
                          AND r.versionnumber >= COALESCE((
                              SELECT MAX(r2.versionnumber)
                                FROM {local_handbook_revision} r2
                               WHERE r2.pageid = p.id AND r2.timepublished > 0
                                 AND r2.requiresreacknowledgement = 1), 0)
                   )";
        return (int)$DB->count_records_sql($sql, ['userid' => $userid]);
    }

    /**
     * Pending and reconfirm items for a user across all required pages.
     *
     * @param int $userid User id.
     * @param int $limit Maximum entries.
     * @return stdClass[] Page records with ->ackstatus.
     */
    public static function get_pending_for_user(int $userid, int $limit = 10): array {
        global $DB;

        $pages = $DB->get_records_select('local_handbook_page',
            'requiredreading = 1 AND publishedrevisionid > 0 AND archived = 0',
            [], 'title ASC');

        $pending = [];
        foreach ($pages as $page) {
            $status = self::get_status($userid, $page);
            if ($status->status === self::STATUS_PENDING || $status->status === self::STATUS_RECONFIRM) {
                $page->ackstatus = $status->status;
                $pending[] = $page;
                if (count($pending) >= $limit) {
                    break;
                }
            }
        }
        return $pending;
    }
}
