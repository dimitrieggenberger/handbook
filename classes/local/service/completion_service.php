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

use moodle_exception;
use stdClass;

/**
 * Article-level reading completion, shared across reading paths (spec 8).
 *
 * Completion belongs to a user and a published revision, NOT to a path: reading
 * a revision once satisfies every path that contains it (spec 8.1). This service
 * records a read receipt and reports completion independently of a page's global
 * required-reading flag, so a path may require an article that is not globally
 * mandatory (spec 8.3).
 *
 * Two records can evidence completion of a revision:
 * - a read receipt (local_handbook_readreceipt) — ordinary path completion;
 * - a compliance acknowledgement (local_handbook_ack) — formal required reading.
 * Either counts here, so historical acknowledgements made before this model keep
 * counting (spec 8.4, acceptance: historical evidence intact). Compliance
 * acknowledgements remain governed solely by ack_service.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_service {

    /** @var string The page is not published, so there is nothing to complete. */
    public const STATUS_NOT_PUBLISHED = 'not_published';
    /** @var string No completion recorded for any revision of the page. */
    public const STATUS_NOT_STARTED = 'not_started';
    /** @var string Completion covers the current published revision. */
    public const STATUS_COMPLETED = 'completed';
    /** @var string Completed an older revision; a material change needs one renewed read. */
    public const STATUS_RECONFIRM = 'reconfirm';

    /**
     * Record a read receipt for the current published revision of a page.
     *
     * Idempotent per (user, revision): reading the same revision again returns
     * the existing receipt. Does not touch compliance acknowledgements.
     *
     * @param int $userid User id.
     * @param stdClass $page Page record (must expose id, publishedrevisionid).
     * @param string $method How completion was recorded (reading_path, manual, ...).
     * @return stdClass The read-receipt record.
     */
    public static function record_receipt(int $userid, stdClass $page,
            string $method = 'reading_path'): stdClass {
        global $DB;

        if (!(int)$page->publishedrevisionid) {
            throw new moodle_exception('notpublished', 'local_handbook');
        }

        $existing = $DB->get_record('local_handbook_readreceipt',
            ['userid' => $userid, 'revisionid' => (int)$page->publishedrevisionid]);
        if ($existing) {
            return $existing;
        }

        $receipt = new stdClass();
        $receipt->userid = $userid;
        $receipt->pageid = (int)$page->id;
        $receipt->revisionid = (int)$page->publishedrevisionid;
        $receipt->completionmethod = \core_text::substr($method, 0, 32) ?: 'reading_path';
        $receipt->confirmationversion = ack_service::CONFIRMATION_VERSION;
        $receipt->timecompleted = time();
        $receipt->id = $DB->insert_record('local_handbook_readreceipt', $receipt);

        return $receipt;
    }

    /**
     * Completion status of a user for a page, independent of the page's global
     * required-reading flag (spec 8.3).
     *
     * @param int $userid User id.
     * @param stdClass $page Page record (must expose id, publishedrevisionid).
     * @return stdClass {status, record|null} where record is the newest
     *         completion (receipt or acknowledgement) with ->revisionid,
     *         ->versionnumber and ->timecompleted.
     */
    public static function completion_status(int $userid, stdClass $page): stdClass {
        global $DB;

        $result = (object)['status' => self::STATUS_NOT_PUBLISHED, 'record' => null];
        if (!(int)$page->publishedrevisionid) {
            return $result;
        }

        $record = self::newest_completion($userid, (int)$page->id);
        $result->record = $record;
        if (!$record) {
            $result->status = self::STATUS_NOT_STARTED;
            return $result;
        }

        if ((int)$record->revisionid === (int)$page->publishedrevisionid) {
            $result->status = self::STATUS_COMPLETED;
            return $result;
        }

        // Completed an older revision: still complete unless a later published
        // revision demanded a renewed read (mirrors ack_service, spec 16/8.1).
        $needsreread = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_handbook_revision}
              WHERE pageid = :pageid
                AND versionnumber > :done
                AND timepublished > 0
                AND requiresreacknowledgement = 1",
            ['pageid' => (int)$page->id, 'done' => (int)$record->versionnumber]) > 0;

        $result->status = $needsreread ? self::STATUS_RECONFIRM : self::STATUS_COMPLETED;
        return $result;
    }

    /**
     * Whether a user has completed the current published revision of a page.
     *
     * @param int $userid User id.
     * @param stdClass $page Page record.
     * @return bool
     */
    public static function is_completed(int $userid, stdClass $page): bool {
        return self::completion_status($userid, $page)->status === self::STATUS_COMPLETED;
    }

    /**
     * The newest completion (read receipt OR compliance acknowledgement) for a
     * user and page, by revision version. Null when there is none.
     *
     * @param int $userid User id.
     * @param int $pageid Page id.
     * @return stdClass|null {revisionid, versionnumber, timecompleted}
     */
    private static function newest_completion(int $userid, int $pageid): ?stdClass {
        global $DB;

        $sql = "SELECT x.revisionid, r.versionnumber, MAX(x.timecompleted) AS timecompleted
                  FROM (
                        SELECT revisionid, timecompleted
                          FROM {local_handbook_readreceipt}
                         WHERE userid = :u1 AND pageid = :p1
                        UNION ALL
                        SELECT revisionid, timeacknowledged AS timecompleted
                          FROM {local_handbook_ack}
                         WHERE userid = :u2 AND pageid = :p2
                       ) x
                  JOIN {local_handbook_revision} r ON r.id = x.revisionid
              GROUP BY x.revisionid, r.versionnumber
              ORDER BY r.versionnumber DESC, timecompleted DESC";
        $rows = $DB->get_records_sql($sql,
            ['u1' => $userid, 'p1' => $pageid, 'u2' => $userid, 'p2' => $pageid], 0, 1);
        return $rows ? reset($rows) : null;
    }
}
