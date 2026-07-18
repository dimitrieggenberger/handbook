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
use stdClass;

/**
 * Person-centered reading dashboard: who has confirmed how much of the
 * required reading, per audience and scope.
 *
 * Reads the same receipts (local_handbook_readreceipt) and required-reading
 * acknowledgements (local_handbook_ack) the reader UI writes. Per person and
 * page: green = confirmed on the current published revision, amber = a
 * confirmation exists but only for an earlier revision, gray = never.
 *
 * The hide-list (local_handbook_readerhide) is a reversible view filter for
 * staff on leave: hidden people leave the list and the aggregates but no
 * reading data is touched, and the entry records who hid whom, when and why.
 *
 * Read-only over reading data; no external API or MCP surface uses this.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reading_dashboard {

    /**
     * Audience choices: whole staff, one cohort, or one system role.
     *
     * @return string[] Keyed by audience token ('staff', 'cohort_N', 'role_N').
     */
    public static function audience_options(): array {
        global $DB;

        $options = ['staff' => get_string('dashaudiencestaff', 'local_handbook')];
        foreach ($DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name') as $cohort) {
            $options['cohort_' . $cohort->id] = format_string($cohort->name);
        }
        $roles = role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL);
        foreach ($roles as $role) {
            $options['role_' . $role->id] = $role->localname;
        }
        return $options;
    }

    /**
     * Resolve an audience token to users (deleted/suspended excluded).
     *
     * @param string $audience Audience token.
     * @return stdClass[] Keyed by user id.
     */
    public static function audience_users(string $audience): array {
        global $DB;

        $namefields = implode(', ', array_map(
            static fn(string $field): string => 'u.' . $field,
            \core_user\fields::get_name_fields()
        ));
        $fields = 'u.id, u.email, ' . $namefields;

        $users = [];
        if (preg_match('/^cohort_(\d+)$/', $audience, $matches)) {
            $sql = "SELECT DISTINCT $fields
                      FROM {user} u
                      JOIN {cohort_members} cm ON cm.userid = u.id
                     WHERE cm.cohortid = :cohortid AND u.deleted = 0 AND u.suspended = 0";
            $users = $DB->get_records_sql($sql, ['cohortid' => (int)$matches[1]]);
        } else if (preg_match('/^role_(\d+)$/', $audience, $matches)) {
            $sql = "SELECT DISTINCT $fields
                      FROM {user} u
                      JOIN {role_assignments} ra ON ra.userid = u.id
                     WHERE ra.roleid = :roleid AND u.deleted = 0 AND u.suspended = 0";
            $users = $DB->get_records_sql($sql, ['roleid' => (int)$matches[1]]);
        } else {
            $users = report_service::get_staff_users();
        }

        \core_collator::asort_objects_by_property($users, 'lastname');
        return $users;
    }

    /**
     * Scope choices: all required reading, each active path, each category.
     *
     * @return string[] Keyed by scope token ('required', 'path_N', 'cat_N').
     */
    public static function scope_options(): array {
        global $DB;

        $options = ['required' => get_string('dashscoperequired', 'local_handbook')];
        foreach ($DB->get_records('local_handbook_path', ['active' => 1],
                'schoolyear DESC, name ASC', 'id, name, schoolyear') as $path) {
            $label = format_string($path->name)
                . ($path->schoolyear !== '' ? ' (' . $path->schoolyear . ')' : '');
            $options['path_' . $path->id] = get_string('dashscopepath', 'local_handbook') . ': ' . $label;
        }
        foreach (local_handbook_get_categories(0, true) as $top) {
            $options['cat_' . $top->id] = get_string('dashscopecategory', 'local_handbook')
                . ': ' . format_string($top->name);
            foreach (local_handbook_get_categories((int)$top->id, true) as $child) {
                $options['cat_' . $child->id] = get_string('dashscopecategory', 'local_handbook')
                    . ': ' . format_string($top->name) . ' › ' . format_string($child->name);
            }
        }
        return $options;
    }

    /**
     * The pages a scope covers: published, non-archived.
     *
     * @param string $scope Scope token.
     * @return array Map pageid => current published revision id.
     */
    public static function page_set(string $scope): array {
        global $DB;

        if (preg_match('/^path_(\d+)$/', $scope, $matches)) {
            // Required items of one path.
            $sql = "SELECT p.id, p.publishedrevisionid
                      FROM {local_handbook_pathitem} i
                      JOIN {local_handbook_page} p ON p.id = i.pageid
                     WHERE i.pathid = :pathid AND i.required = 1
                           AND p.publishedrevisionid > 0 AND p.archived = 0";
            $records = $DB->get_records_sql($sql, ['pathid' => (int)$matches[1]]);
        } else if (preg_match('/^cat_(\d+)$/', $scope, $matches)) {
            // Every published page of one category (subcategories included).
            $categoryid = (int)$matches[1];
            $categoryids = [$categoryid];
            foreach ($DB->get_records('local_handbook_category', ['parentid' => $categoryid],
                    '', 'id') as $child) {
                $categoryids[] = (int)$child->id;
            }
            [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
            $records = $DB->get_records_select('local_handbook_page',
                "categoryid $insql AND publishedrevisionid > 0 AND archived = 0",
                $params, '', 'id, publishedrevisionid');
        } else {
            // All required-reading pages.
            $records = $DB->get_records_select('local_handbook_page',
                'requiredreading = 1 AND publishedrevisionid > 0 AND archived = 0',
                [], '', 'id, publishedrevisionid');
        }

        $set = [];
        foreach ($records as $record) {
            $set[(int)$record->id] = (int)$record->publishedrevisionid;
        }
        return $set;
    }

    /**
     * Per-user reading rows over a page set, sorted most-read first.
     *
     * @param stdClass[] $users Audience (keyed by user id).
     * @param int[] $pageset Map pageid => current published revision id.
     * @return stdClass[] Rows: {user, confirmed, stale, pending, total, percent, lastactivity}.
     */
    public static function build_rows(array $users, array $pageset): array {
        global $DB;

        $total = count($pageset);
        $rows = [];
        foreach ($users as $user) {
            $rows[(int)$user->id] = (object)[
                'user' => $user,
                'confirmed' => 0,
                'stale' => 0,
                'pending' => $total,
                'total' => $total,
                'percent' => 0,
                'lastactivity' => 0,
            ];
        }
        if (!$rows || !$pageset) {
            return $rows;
        }

        [$pagesql, $pageparams] = $DB->get_in_or_equal(array_keys($pageset), SQL_PARAMS_NAMED, 'pg');
        [$usersql, $userparams] = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED, 'us');

        // Receipts and acknowledgements, unified: per (user, page) whether any
        // confirmation targets the CURRENT published revision, and the most
        // recent confirmation time.
        $sql = "SELECT src.userid, src.pageid,
                       MAX(CASE WHEN src.revisionid = p.publishedrevisionid THEN 1 ELSE 0 END) AS iscurrent,
                       MAX(src.confirmtime) AS lasttime
                  FROM (
                       SELECT r.userid, r.pageid, r.revisionid, r.timecompleted AS confirmtime
                         FROM {local_handbook_readreceipt} r
                        UNION ALL
                       SELECT a.userid, a.pageid, a.revisionid, a.timeacknowledged AS confirmtime
                         FROM {local_handbook_ack} a
                       ) src
                  JOIN {local_handbook_page} p ON p.id = src.pageid
                 WHERE src.pageid $pagesql AND src.userid $usersql
              GROUP BY src.userid, src.pageid";

        $recordset = $DB->get_recordset_sql($sql, $pageparams + $userparams);
        foreach ($recordset as $record) {
            $row = $rows[(int)$record->userid];
            if ((int)$record->iscurrent) {
                $row->confirmed++;
            } else {
                $row->stale++;
            }
            $row->pending--;
            $row->lastactivity = max($row->lastactivity, (int)$record->lasttime);
        }
        $recordset->close();

        foreach ($rows as $row) {
            $row->percent = $total > 0 ? (int)round($row->confirmed * 100 / $total) : 0;
        }

        return $rows;
    }

    /**
     * The hide-list, with hider names resolved.
     *
     * @return stdClass[] Keyed by hidden user id.
     */
    public static function hidden_map(): array {
        global $DB;

        $namefields = implode(', ', array_map(
            static fn(string $field): string => 'u.' . $field,
            \core_user\fields::get_name_fields()
        ));
        $sql = "SELECT h.userid, h.note, h.timecreated, h.createdby, $namefields
                  FROM {local_handbook_readerhide} h
                  JOIN {user} u ON u.id = h.createdby";
        return $DB->get_records_sql($sql);
    }

    /**
     * Hide a user from the dashboard (idempotent).
     *
     * @param int $userid User to hide.
     * @param string $note Optional reason, e.g. leave.
     * @param int $actorid Who hides.
     * @return void
     */
    public static function hide(int $userid, string $note, int $actorid): void {
        global $DB;

        if ($existing = $DB->get_record('local_handbook_readerhide', ['userid' => $userid])) {
            if ($note !== '' && $note !== $existing->note) {
                $DB->set_field('local_handbook_readerhide', 'note', $note, ['id' => $existing->id]);
            }
            return;
        }
        $DB->insert_record('local_handbook_readerhide', (object)[
            'userid' => $userid,
            'note' => $note,
            'timecreated' => time(),
            'createdby' => $actorid,
        ]);
    }

    /**
     * Restore a hidden user to the dashboard.
     *
     * @param int $userid User to show again.
     * @return void
     */
    public static function unhide(int $userid): void {
        global $DB;
        $DB->delete_records('local_handbook_readerhide', ['userid' => $userid]);
    }
}
