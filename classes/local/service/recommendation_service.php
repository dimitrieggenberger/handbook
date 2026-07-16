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
 * Reading-path recommendations and audits (specification 10).
 *
 * Recommendations are ADVISORY: they never alter an active path automatically
 * (spec 2.6, 10.5). Accepting one prepares a reading-path revision inside a
 * change set — a draft that still goes through the human approve/publish path.
 * Coverage and audit are read-only and expose no individual completion data
 * (spec 11.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recommendation_service {

    /** @var string Recommendation states (spec 10.3). */
    public const STATUS_OPEN = 'open';
    /** @var string */
    public const STATUS_ACCEPTED = 'accepted';
    /** @var string */
    public const STATUS_DISMISSED = 'dismissed';
    /** @var string */
    public const STATUS_DEFERRED = 'deferred';
    /** @var string */
    public const STATUS_ALREADY_COVERED = 'already_covered';
    /** @var string */
    public const STATUS_INTENTIONAL = 'intentional_omission';
    /** @var string */
    public const STATUS_RESOLVED = 'resolved';

    /** @var int Active paths larger than this earn a split suggestion (spec 10). */
    private const OVERSIZE_ITEMS = 12;

    /** @var string[] Relation types that make a page a strong path candidate (spec 10.2). */
    private const STRONG_RELATIONS = ['quickguidefor', 'templatefor', 'implements',
        'procedurefor', 'assessmentfor', 'exceptionto'];

    /**
     * Recommendation type keys (spec 10.3). Labels: rectype_<key>.
     *
     * @return string[]
     */
    public static function rec_types(): array {
        return ['add', 'remove', 'reorder', 'replace', 'split_path', 'merge_paths',
            'update_required_status'];
    }

    /**
     * Status keys. Labels: recstatus_<key>.
     *
     * @return string[]
     */
    public static function statuses(): array {
        return [self::STATUS_OPEN, self::STATUS_ACCEPTED, self::STATUS_DISMISSED,
            self::STATUS_DEFERRED, self::STATUS_ALREADY_COVERED, self::STATUS_INTENTIONAL,
            self::STATUS_RESOLVED];
    }

    /**
     * Deterministic path candidates for a page, from typed relations and
     * category (spec 10.2). Read-only; nothing is persisted. Typed relations to
     * a canonical target already in a path are strong; sharing a category is weak.
     *
     * @param int $pageid Page id.
     * @return stdClass[] Each: {pathid, pathname, rectype, confidence, rationale}.
     */
    public static function candidates_for_page(int $pageid): array {
        global $DB;

        $page = $DB->get_record('local_handbook_page', ['id' => $pageid]);
        if (!$page || !(int)$page->publishedrevisionid || (int)$page->archived) {
            return [];
        }

        $candidates = [];

        // Strong: a typed relation to a canonical target already in an active
        // path, where this page is not yet a member.
        [$insql, $params] = $DB->get_in_or_equal(self::STRONG_RELATIONS, SQL_PARAMS_NAMED, 'rt');
        $params['src'] = $pageid;
        $relations = $DB->get_records_select('local_handbook_relation',
            "sourcepageid = :src AND relationtype $insql", $params);
        foreach ($relations as $rel) {
            $targettitle = (string)$DB->get_field('local_handbook_page', 'title',
                ['id' => (int)$rel->targetpageid]);
            $paths = $DB->get_records_sql(
                "SELECT DISTINCT p.id, p.name
                   FROM {local_handbook_path} p
                   JOIN {local_handbook_pathitem} i ON i.pathid = p.id
                  WHERE p.active = 1 AND i.pageid = :target
                    AND NOT EXISTS (SELECT 1 FROM {local_handbook_pathitem} j
                                     WHERE j.pathid = p.id AND j.pageid = :self)",
                ['target' => (int)$rel->targetpageid, 'self' => $pageid]);
            foreach ($paths as $p) {
                $candidates[(int)$p->id] = (object)[
                    'pathid' => (int)$p->id,
                    'pathname' => (string)$p->name,
                    'rectype' => 'add',
                    'confidence' => 'high',
                    'rationale' => get_string('recreason_relation', 'local_handbook', (object)[
                        'relation' => get_string('relation_' . $rel->relationtype, 'local_handbook'),
                        'target' => $targettitle,
                    ]),
                ];
            }
        }

        // Weak: an active path already containing other pages of this category.
        $catpaths = $DB->get_records_sql(
            "SELECT DISTINCT p.id, p.name
               FROM {local_handbook_path} p
               JOIN {local_handbook_pathitem} i ON i.pathid = p.id
               JOIN {local_handbook_page} pg ON pg.id = i.pageid
              WHERE p.active = 1 AND pg.categoryid = :cat AND i.pageid <> :self
                AND NOT EXISTS (SELECT 1 FROM {local_handbook_pathitem} j
                                 WHERE j.pathid = p.id AND j.pageid = :self2)",
            ['cat' => (int)$page->categoryid, 'self' => $pageid, 'self2' => $pageid]);
        foreach ($catpaths as $p) {
            if (!isset($candidates[(int)$p->id])) {
                $candidates[(int)$p->id] = (object)[
                    'pathid' => (int)$p->id,
                    'pathname' => (string)$p->name,
                    'rectype' => 'add',
                    'confidence' => 'low',
                    'rationale' => get_string('recreason_category', 'local_handbook'),
                ];
            }
        }

        return array_values($candidates);
    }

    /**
     * Create (persist) a recommendation. Idempotent for an open recommendation
     * of the same (pathid, pageid, rectype): the existing one is returned.
     *
     * @param stdClass $data pathid, pageid, rectype (required) plus optional
     *        revisionid, confidence, rationale, suggestedsection,
     *        suggestedrequired, suggestedafterpageid, triggerkind, source.
     * @param int $userid Acting user (0 = current).
     * @return stdClass The recommendation record.
     */
    public static function create(stdClass $data, int $userid = 0): stdClass {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $now = time();

        $rectype = (string)($data->rectype ?? '');
        if (!in_array($rectype, self::rec_types(), true)) {
            throw new moodle_exception('errorrectype', 'local_handbook');
        }
        $pathid = (int)($data->pathid ?? 0);
        if ($pathid && !$DB->record_exists('local_handbook_path', ['id' => $pathid])) {
            throw new moodle_exception('errorpathnotfound', 'local_handbook');
        }
        $pageid = (int)($data->pageid ?? 0);
        if ($pageid && !$DB->record_exists('local_handbook_page', ['id' => $pageid])) {
            throw new moodle_exception('errorpagenotfound', 'local_handbook');
        }

        // Reuse an existing open recommendation for the same target/type.
        $existing = $DB->get_record('local_handbook_pathrec', [
            'pathid' => $pathid, 'pageid' => $pageid, 'rectype' => $rectype,
            'status' => self::STATUS_OPEN,
        ]);
        if ($existing) {
            return $existing;
        }

        $rec = new stdClass();
        $rec->pathid = $pathid;
        $rec->pageid = $pageid;
        $rec->revisionid = (int)($data->revisionid ?? 0);
        $rec->rectype = $rectype;
        $rec->confidence = in_array($data->confidence ?? '', ['low', 'medium', 'high'], true)
            ? $data->confidence : 'medium';
        $rec->rationale = (string)($data->rationale ?? '');
        $rec->suggestedsection = \core_text::substr((string)($data->suggestedsection ?? ''), 0, 255);
        $rec->suggestedrequired = (int)((bool)($data->suggestedrequired ?? true));
        $rec->suggestedafterpageid = (int)($data->suggestedafterpageid ?? 0);
        $rec->status = self::STATUS_OPEN;
        $rec->triggerkind = (string)($data->triggerkind ?? 'manual');
        $rec->source = in_array($data->source ?? '', ['system', 'ai', 'human'], true)
            ? $data->source : 'system';
        $rec->changesetid = 0;
        $rec->timecreated = $now;
        $rec->timemodified = $now;
        $rec->createdby = $userid;
        $rec->reviewedby = 0;
        $rec->reviewnote = '';
        $rec->id = $DB->insert_record('local_handbook_pathrec', $rec);

        return $rec;
    }

    /**
     * List recommendations, newest first.
     *
     * @param array $filters Optional 'status', 'pathid', 'pageid'.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size (0 = all).
     * @return stdClass[]
     */
    public static function list_recommendations(array $filters = [], int $limitfrom = 0,
            int $limitnum = 0): array {
        global $DB;

        $conditions = [];
        foreach (['status', 'pathid', 'pageid'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $conditions[$key] = $filters[$key];
            }
        }
        return $DB->get_records('local_handbook_pathrec', $conditions,
            'timecreated DESC', '*', $limitfrom, $limitnum);
    }

    /**
     * Change a recommendation's status (a human triage action).
     *
     * @param int $recid Recommendation id.
     * @param string $status Target status.
     * @param string $note Review note.
     * @param int $userid Acting user (0 = current).
     * @return void
     */
    public static function set_status(int $recid, string $status, string $note = '',
            int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        if (!in_array($status, self::statuses(), true)) {
            throw new moodle_exception('errorrecstatus', 'local_handbook');
        }
        $DB->get_record('local_handbook_pathrec', ['id' => $recid], '*', MUST_EXIST);

        $DB->update_record('local_handbook_pathrec', (object)[
            'id' => $recid,
            'status' => $status,
            'reviewnote' => $note,
            'reviewedby' => $userid,
            'timemodified' => time(),
        ]);
    }

    /**
     * Accept a recommendation by drafting a reading-path revision into a change
     * set (spec 10.5). The active path is NOT modified: the change becomes a
     * draft path proposal awaiting human approval and publication.
     *
     * @param int $recid Recommendation id.
     * @param int $changesetid Change set to draft into.
     * @param int $userid Acting user (0 = current).
     * @return array The change-item result from changeset_service.
     */
    public static function accept_into_changeset(int $recid, int $changesetid, int $userid = 0): array {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $rec = $DB->get_record('local_handbook_pathrec', ['id' => $recid], '*', MUST_EXIST);
        if (!(int)$rec->pathid) {
            throw new moodle_exception('errorrecnopath', 'local_handbook');
        }

        // Start from the current path snapshot, then apply the recommendation
        // in memory. The snapshot carries timemodified for optimistic concurrency.
        $snapshot = changeset_service::reading_path_snapshot((int)$rec->pathid);
        $snapshot['expectedtimemodified'] = $snapshot['timemodified'];
        $snapshot = self::apply_to_snapshot($snapshot, $rec);

        $result = changeset_service::upsert_reading_path($changesetid, $snapshot, $userid);

        $DB->update_record('local_handbook_pathrec', (object)[
            'id' => (int)$rec->id,
            'status' => self::STATUS_ACCEPTED,
            'changesetid' => $changesetid,
            'reviewedby' => $userid,
            'timemodified' => time(),
        ]);

        return $result;
    }

    /**
     * Apply a recommendation to a path snapshot array (in memory).
     *
     * @param array $snapshot Snapshot from changeset_service::reading_path_snapshot().
     * @param stdClass $rec Recommendation record.
     * @return array Mutated snapshot.
     */
    private static function apply_to_snapshot(array $snapshot, stdClass $rec): array {
        $pageid = (int)$rec->pageid;
        $sections = $snapshot['sections'] ?: [['name' => '', 'items' => []]];

        switch ($rec->rectype) {
            case 'add':
            case 'replace':
                // Drop any existing membership first (replace = re-place cleanly).
                $sections = self::remove_page($sections, $pageid);
                $item = [
                    'pageid' => $pageid,
                    'pagetempkey' => '',
                    'required' => (int)$rec->suggestedrequired,
                    'rationale' => (string)$rec->rationale,
                    'quizcmid' => 0,
                ];
                $sections = self::insert_page($sections, $item,
                    (string)$rec->suggestedsection, (int)$rec->suggestedafterpageid);
                break;

            case 'remove':
                $sections = self::remove_page($sections, $pageid);
                break;

            case 'update_required_status':
                foreach ($sections as &$section) {
                    foreach ($section['items'] as &$it) {
                        if ((int)$it['pageid'] === $pageid) {
                            $it['required'] = (int)$rec->suggestedrequired;
                        }
                    }
                    unset($it);
                }
                unset($section);
                break;

            case 'reorder':
                $item = self::extract_page($sections, $pageid);
                if ($item !== null) {
                    $sections = self::remove_page($sections, $pageid);
                    $sections = self::insert_page($sections, $item,
                        (string)$rec->suggestedsection, (int)$rec->suggestedafterpageid);
                }
                break;

            default:
                // split_path / merge_paths need editorial judgement; the draft is
                // the current snapshot for a human to shape.
                break;
        }

        $snapshot['sections'] = array_values($sections);
        return $snapshot;
    }

    /**
     * Remove a page from every section of a snapshot.
     *
     * @param array $sections Sections array.
     * @param int $pageid Page id.
     * @return array
     */
    private static function remove_page(array $sections, int $pageid): array {
        foreach ($sections as &$section) {
            $section['items'] = array_values(array_filter($section['items'],
                static fn(array $it): bool => (int)$it['pageid'] !== $pageid));
        }
        unset($section);
        return $sections;
    }

    /**
     * Find and return a page's item from a snapshot (null if absent).
     *
     * @param array $sections Sections array.
     * @param int $pageid Page id.
     * @return array|null
     */
    private static function extract_page(array $sections, int $pageid): ?array {
        foreach ($sections as $section) {
            foreach ($section['items'] as $it) {
                if ((int)$it['pageid'] === $pageid) {
                    return $it;
                }
            }
        }
        return null;
    }

    /**
     * Insert an item into a snapshot: into the named section (created if
     * missing) after $afterpageid, else at the end of that section.
     *
     * @param array $sections Sections array.
     * @param array $item Item to insert.
     * @param string $sectionname Target section name ('' = first section).
     * @param int $afterpageid Place after this page (0 = end).
     * @return array
     */
    private static function insert_page(array $sections, array $item, string $sectionname,
            int $afterpageid): array {
        if (!$sections) {
            $sections = [['name' => $sectionname, 'items' => []]];
        }

        // Locate the target section (by name), or fall back to the first.
        $targetindex = null;
        if ($sectionname !== '') {
            foreach ($sections as $index => $section) {
                if ((string)$section['name'] === $sectionname) {
                    $targetindex = $index;
                    break;
                }
            }
            if ($targetindex === null) {
                $sections[] = ['name' => $sectionname, 'items' => []];
                $targetindex = array_key_last($sections);
            }
        } else {
            $targetindex = array_key_first($sections);
        }

        $items = $sections[$targetindex]['items'];
        if ($afterpageid) {
            $insertat = count($items);
            foreach ($items as $pos => $it) {
                if ((int)$it['pageid'] === $afterpageid) {
                    $insertat = $pos + 1;
                    break;
                }
            }
            array_splice($items, $insertat, 0, [$item]);
        } else {
            $items[] = $item;
        }
        $sections[$targetindex]['items'] = $items;
        return $sections;
    }

    /**
     * Aggregate reading-path coverage — no individual completion data (spec 11.4).
     *
     * @return stdClass {totalpages, pagescovered, orphans, requiredpages,
     *         requiredcovered, overlap, activepaths, paths[]}
     */
    public static function coverage(): stdClass {
        global $DB;

        $totalpages = (int)$DB->count_records_select('local_handbook_page',
            'publishedrevisionid > 0 AND archived = 0');
        $requiredpages = (int)$DB->count_records_select('local_handbook_page',
            'publishedrevisionid > 0 AND archived = 0 AND requiredreading = 1');

        // Distinct published, non-archived pages that belong to any active path.
        $pagescovered = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT p.id)
               FROM {local_handbook_page} p
               JOIN {local_handbook_pathitem} i ON i.pageid = p.id
               JOIN {local_handbook_path} pa ON pa.id = i.pathid
              WHERE p.publishedrevisionid > 0 AND p.archived = 0 AND pa.active = 1");
        $requiredcovered = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT p.id)
               FROM {local_handbook_page} p
               JOIN {local_handbook_pathitem} i ON i.pageid = p.id AND i.required = 1
               JOIN {local_handbook_path} pa ON pa.id = i.pathid
              WHERE p.publishedrevisionid > 0 AND p.archived = 0 AND pa.active = 1
                AND p.requiredreading = 1");

        // Pages appearing in more than one active path.
        $overlap = (int)$DB->count_records_sql(
            "SELECT COUNT(*) FROM (
                SELECT p.id
                  FROM {local_handbook_page} p
                  JOIN {local_handbook_pathitem} i ON i.pageid = p.id
                  JOIN {local_handbook_path} pa ON pa.id = i.pathid
                 WHERE p.publishedrevisionid > 0 AND p.archived = 0 AND pa.active = 1
              GROUP BY p.id HAVING COUNT(DISTINCT pa.id) > 1
             ) sub");

        $paths = [];
        foreach ($DB->get_records('local_handbook_path', ['active' => 1], 'schoolyear DESC, name ASC') as $path) {
            $items = (int)$DB->count_records_sql(
                "SELECT COUNT(1) FROM {local_handbook_pathitem} i
                   JOIN {local_handbook_page} p ON p.id = i.pageid
                  WHERE i.pathid = ? AND p.archived = 0", [$path->id]);
            $required = (int)$DB->count_records_sql(
                "SELECT COUNT(1) FROM {local_handbook_pathitem} i
                   JOIN {local_handbook_page} p ON p.id = i.pageid
                  WHERE i.pathid = ? AND i.required = 1 AND p.archived = 0", [$path->id]);
            $paths[] = (object)[
                'id' => (int)$path->id,
                'name' => (string)$path->name,
                'items' => $items,
                'required' => $required,
                'reviewdue' => (int)$path->reviewdate > 0 && (int)$path->reviewdate < time(),
            ];
        }

        return (object)[
            'totalpages' => $totalpages,
            'pagescovered' => $pagescovered,
            'orphans' => max(0, $totalpages - $pagescovered),
            'requiredpages' => $requiredpages,
            'requiredcovered' => $requiredcovered,
            'overlap' => $overlap,
            'activepaths' => count($paths),
            'paths' => $paths,
        ];
    }

    /**
     * Deterministic handbook-wide path audit (spec 10.4). Read-only; returns
     * advisory findings rather than persisting anything.
     *
     * @return stdClass[] Each: {kind, severity, pathid, pathname, pageid,
     *         pagetitle, message}.
     */
    public static function audit(): array {
        global $DB;

        $findings = [];
        $now = time();

        // Required-reading pages that belong to no active path (spec 8/10).
        $orphans = $DB->get_records_sql(
            "SELECT p.id, p.title
               FROM {local_handbook_page} p
              WHERE p.publishedrevisionid > 0 AND p.archived = 0 AND p.requiredreading = 1
                AND NOT EXISTS (
                    SELECT 1 FROM {local_handbook_pathitem} i
                      JOIN {local_handbook_path} pa ON pa.id = i.pathid
                     WHERE i.pageid = p.id AND pa.active = 1)
           ORDER BY p.title ASC");
        foreach ($orphans as $page) {
            $findings[] = (object)[
                'kind' => 'orphan_required', 'severity' => 'medium',
                'pathid' => 0, 'pathname' => '', 'pageid' => (int)$page->id,
                'pagetitle' => (string)$page->title,
                'message' => get_string('auditorphanrequired', 'local_handbook'),
            ];
        }

        foreach ($DB->get_records('local_handbook_path', ['active' => 1], 'name ASC') as $path) {
            $itemcount = (int)$DB->count_records_sql(
                "SELECT COUNT(1) FROM {local_handbook_pathitem} i
                   JOIN {local_handbook_page} p ON p.id = i.pageid
                  WHERE i.pathid = ? AND p.archived = 0", [$path->id]);
            $requiredcount = (int)$DB->count_records_sql(
                "SELECT COUNT(1) FROM {local_handbook_pathitem} i
                   JOIN {local_handbook_page} p ON p.id = i.pageid
                  WHERE i.pathid = ? AND i.required = 1 AND p.archived = 0", [$path->id]);

            if ((int)$path->reviewdate > 0 && (int)$path->reviewdate < $now) {
                $findings[] = (object)[
                    'kind' => 'path_review_due', 'severity' => 'medium',
                    'pathid' => (int)$path->id, 'pathname' => (string)$path->name,
                    'pageid' => 0, 'pagetitle' => '',
                    'message' => get_string('auditreviewdue', 'local_handbook'),
                ];
            }
            if ($requiredcount === 0 && $itemcount > 0) {
                $findings[] = (object)[
                    'kind' => 'path_no_required', 'severity' => 'low',
                    'pathid' => (int)$path->id, 'pathname' => (string)$path->name,
                    'pageid' => 0, 'pagetitle' => '',
                    'message' => get_string('auditnorequired', 'local_handbook'),
                ];
            }
            if ($itemcount > self::OVERSIZE_ITEMS) {
                $findings[] = (object)[
                    'kind' => 'path_oversized', 'severity' => 'low',
                    'pathid' => (int)$path->id, 'pathname' => (string)$path->name,
                    'pageid' => 0, 'pagetitle' => '',
                    'message' => get_string('auditoversized', 'local_handbook', $itemcount),
                ];
            }
        }

        return $findings;
    }
}
