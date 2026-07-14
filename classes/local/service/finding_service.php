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
 * Quality findings (specification 19).
 *
 * Findings are advisory: they never change page status, authority or
 * published content automatically (19.3), regardless of source.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finding_service {

    /** @var string Finding states (specification 19.3). */
    public const STATUS_OPEN = 'open';
    /** @var string */
    public const STATUS_UNDER_REVIEW = 'under_review';
    /** @var string */
    public const STATUS_ACCEPTED = 'accepted';
    /** @var string */
    public const STATUS_DISMISSED = 'dismissed';
    /** @var string */
    public const STATUS_RESOLVED = 'resolved';
    /** @var string */
    public const STATUS_INTENTIONAL = 'intentional_difference';

    /** @var string[] Closed states record who resolved them and when. */
    public const CLOSED_STATUSES = [self::STATUS_DISMISSED, self::STATUS_RESOLVED,
        self::STATUS_INTENTIONAL];

    /**
     * Finding type keys (specification 19.1; the spec list is open-ended).
     * Labels: findingtype_<key>.
     *
     * @return string[]
     */
    public static function finding_types(): array {
        return ['contradiction', 'duplicate', 'ambiguous_responsibility', 'missing_escalation',
            'missing_record', 'outdated_reference', 'incorrect_content', 'inconsistent_terminology',
            'broken_link', 'missing_owner', 'review_overdue', 'procedure_without_policy',
            'policy_without_procedure', 'modality_difference', 'assessment_outdated',
            'accessibility', 'other'];
    }

    /**
     * Reader-facing subset for the report-a-problem form (12.2).
     *
     * @return string[]
     */
    public static function report_types(): array {
        return ['outdated_reference', 'incorrect_content', 'contradiction',
            'ambiguous_responsibility', 'broken_link', 'accessibility', 'other'];
    }

    /**
     * Severity/confidence scale keys. Labels: scale_<key>.
     *
     * @return string[]
     */
    public static function scale(): array {
        return ['low', 'medium', 'high'];
    }

    /**
     * All status keys. Labels: findingstatus_<key>.
     *
     * @return string[]
     */
    public static function statuses(): array {
        return [self::STATUS_OPEN, self::STATUS_UNDER_REVIEW, self::STATUS_ACCEPTED,
            self::STATUS_DISMISSED, self::STATUS_RESOLVED, self::STATUS_INTENTIONAL];
    }

    /**
     * Create a finding with its affected pages.
     *
     * @param stdClass $data Finding fields: findingtype, summary, and
     *        optionally severity, confidence, explanation, recommendation,
     *        source, externalreference.
     * @param array $pages List of ['pageid' => int, 'revisionid' => int,
     *        'anchor' => string, 'excerpt' => string].
     * @param int $userid Acting user (0 = current user).
     * @return stdClass The finding record.
     */
    public static function create(stdClass $data, array $pages, int $userid = 0): stdClass {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $now = time();

        if (!in_array($data->findingtype ?? '', self::finding_types(), true)) {
            throw new moodle_exception('invalidparameter', 'debug', '', null, 'findingtype');
        }
        if (trim((string)($data->summary ?? '')) === '') {
            throw new moodle_exception('invalidparameter', 'debug', '', null, 'summary');
        }

        $transaction = $DB->start_delegated_transaction();

        $finding = new stdClass();
        $finding->findingtype = $data->findingtype;
        $finding->severity = in_array($data->severity ?? '', self::scale(), true)
            ? $data->severity : 'medium';
        $finding->confidence = in_array($data->confidence ?? '', self::scale(), true)
            ? $data->confidence : 'medium';
        $finding->status = self::STATUS_OPEN;
        $finding->summary = \core_text::substr(trim($data->summary), 0, 255);
        $finding->explanation = (string)($data->explanation ?? '');
        $finding->recommendation = (string)($data->recommendation ?? '');
        $finding->source = (string)($data->source ?? 'human');
        $finding->externalreference = (string)($data->externalreference ?? '');
        $finding->assigneduserid = 0;
        $finding->resolutionnote = '';
        $finding->timecreated = $now;
        $finding->timemodified = $now;
        $finding->createdby = $userid;
        $finding->modifiedby = $userid;
        $finding->resolvedby = 0;
        $finding->timeresolved = 0;
        $finding->id = $DB->insert_record('local_handbook_finding', $finding);

        foreach ($pages as $page) {
            $pageid = (int)($page['pageid'] ?? 0);
            if (!$pageid || !$DB->record_exists('local_handbook_page', ['id' => $pageid])) {
                throw new moodle_exception('errorpagenotfound', 'local_handbook');
            }
            $DB->insert_record('local_handbook_findpage', (object)[
                'findingid' => $finding->id,
                'pageid' => $pageid,
                'revisionid' => (int)($page['revisionid'] ?? 0),
                'anchor' => \core_text::substr((string)($page['anchor'] ?? ''), 0, 255),
                'excerpt' => (string)($page['excerpt'] ?? ''),
            ]);
        }

        $transaction->allow_commit();

        $event = \local_handbook\event\finding_created::create([
            'context' => context_system::instance(),
            'objectid' => $finding->id,
            'other' => ['findingtype' => $finding->findingtype, 'source' => $finding->source],
        ]);
        $event->trigger();

        return $finding;
    }

    /**
     * Change a finding's status, optionally recording a resolution note.
     *
     * @param stdClass $finding Finding record.
     * @param string $status Target status (specification 19.3).
     * @param string $note Resolution note.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function set_status(stdClass $finding, string $status, string $note = '',
            int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        if (!in_array($status, self::statuses(), true)) {
            throw new moodle_exception('invalidparameter', 'debug', '', null, 'status');
        }

        $update = new stdClass();
        $update->id = $finding->id;
        $update->status = $status;
        $update->timemodified = time();
        $update->modifiedby = $userid;
        if ($note !== '') {
            $update->resolutionnote = $note;
        }
        if (in_array($status, self::CLOSED_STATUSES, true)) {
            $update->resolvedby = $userid;
            $update->timeresolved = time();
        }
        $DB->update_record('local_handbook_finding', $update);
    }

    /**
     * Affected pages of a finding.
     *
     * @param int $findingid Finding id.
     * @return stdClass[] findpage rows joined with page slug and title.
     */
    public static function get_pages(int $findingid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT fp.id, fp.pageid, fp.revisionid, fp.anchor, fp.excerpt, p.slug, p.title
               FROM {local_handbook_findpage} fp
               JOIN {local_handbook_page} p ON p.id = fp.pageid
              WHERE fp.findingid = :findingid
           ORDER BY fp.id ASC", ['findingid' => $findingid]);
    }

    /**
     * Count open findings (open + under review), e.g. for dashboards.
     *
     * @return int
     */
    public static function count_open(): int {
        global $DB;

        return $DB->count_records_select('local_handbook_finding',
            'status IN (:s1, :s2)', ['s1' => self::STATUS_OPEN, 's2' => self::STATUS_UNDER_REVIEW]);
    }
}
