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
 * Reading, review and quality reports (specification 12.5, 15.3).
 *
 * The report audience is "staff who can view the handbook"
 * (get_users_by_capability on local/handbook:view); per-path audience
 * assignment is a deferred decision (spec 32.1).
 *
 * Ack validity uses the same rule as ack_service, computed set-wise: a
 * user's acknowledgement of a page is valid iff its version number is at
 * least the highest published version that required re-acknowledgement
 * (no such version = any acknowledgement counts).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_service {

    /**
     * Staff users considered by the reports.
     *
     * @return stdClass[] User records keyed by id (name fields included).
     */
    public static function get_staff_users(): array {
        $namefields = implode(', ', array_map(
            static fn(string $field): string => 'u.' . $field,
            \core_user\fields::get_name_fields()
        ));
        return get_users_by_capability(context_system::instance(), 'local/handbook:view',
            'u.id, u.email, ' . $namefields, 'u.lastname ASC, u.firstname ASC');
    }

    /**
     * Users whose acknowledgement of a page is currently valid.
     *
     * @param int $pageid Page id.
     * @return array Map userid => (object) ['version' => int, 'time' => int].
     */
    public static function valid_ack_users(int $pageid): array {
        global $DB;

        // The re-acknowledgement boundary (see class docs).
        $boundary = (int)$DB->get_field_sql(
            "SELECT COALESCE(MAX(versionnumber), 0)
               FROM {local_handbook_revision}
              WHERE pageid = :pageid AND timepublished > 0 AND requiresreacknowledgement = 1",
            ['pageid' => $pageid]);

        // Each user's newest acknowledged version of this page.
        $sql = "SELECT a.userid, MAX(r.versionnumber) AS ackversion, MAX(a.timeacknowledged) AS acktime
                  FROM {local_handbook_ack} a
                  JOIN {local_handbook_revision} r ON r.id = a.revisionid
                 WHERE a.pageid = :pageid
              GROUP BY a.userid";

        $valid = [];
        foreach ($DB->get_records_sql($sql, ['pageid' => $pageid]) as $row) {
            if ((int)$row->ackversion >= $boundary) {
                $valid[(int)$row->userid] = (object)[
                    'version' => (int)$row->ackversion,
                    'time' => (int)$row->acktime,
                ];
            }
        }
        return $valid;
    }

    /**
     * Path completion per staff user (spec 15.3).
     *
     * @param stdClass $path Path record.
     * @return stdClass {totalrequired: int, users: [{user, confirmed, percent}]}
     */
    public static function path_completion(stdClass $path): stdClass {
        global $DB;

        $sql = "SELECT p.id
                  FROM {local_handbook_pathitem} i
                  JOIN {local_handbook_page} p ON p.id = i.pageid
                 WHERE i.pathid = :pathid AND i.required = 1
                   AND p.requiredreading = 1 AND p.publishedrevisionid > 0 AND p.archived = 0";
        $pageids = array_keys($DB->get_records_sql($sql, ['pathid' => $path->id]));

        $validbypage = [];
        foreach ($pageids as $pageid) {
            $validbypage[$pageid] = self::valid_ack_users((int)$pageid);
        }

        $users = [];
        foreach (path_service::get_audience_users($path) as $user) {
            $confirmed = 0;
            foreach ($pageids as $pageid) {
                if (isset($validbypage[$pageid][(int)$user->id])) {
                    $confirmed++;
                }
            }
            $users[] = (object)[
                'user' => $user,
                'confirmed' => $confirmed,
                'percent' => $pageids ? (int)round($confirmed / count($pageids) * 100) : 0,
            ];
        }

        return (object)['totalrequired' => count($pageids), 'users' => $users];
    }

    /**
     * Acknowledgement state of one page across all staff.
     *
     * @param stdClass $page Page record.
     * @return stdClass {confirmed: [{user, version, time}], pending: [user]}
     */
    public static function page_acknowledgements(stdClass $page): stdClass {
        $valid = self::valid_ack_users((int)$page->id);

        $confirmed = [];
        $pending = [];
        foreach (self::get_staff_users() as $user) {
            if (isset($valid[(int)$user->id])) {
                $confirmed[] = (object)[
                    'user' => $user,
                    'version' => $valid[(int)$user->id]->version,
                    'time' => $valid[(int)$user->id]->time,
                ];
            } else {
                $pending[] = $user;
            }
        }

        return (object)['confirmed' => $confirmed, 'pending' => $pending];
    }

    /**
     * Compact editorial counters for dashboard cards (spec 12.1).
     *
     * @return stdClass {inreview, changesrequested, overduereview, openfindings}
     */
    public static function editorial_counts(): stdClass {
        global $DB;

        return (object)[
            'inreview' => $DB->count_records('local_handbook_revision',
                ['status' => page_service::STATUS_IN_REVIEW]),
            'changesrequested' => $DB->count_records('local_handbook_revision',
                ['status' => page_service::STATUS_CHANGES_REQUESTED]),
            'overduereview' => $DB->count_records_select('local_handbook_page',
                'archived = 0 AND reviewdate > 0 AND reviewdate < :now AND publishedrevisionid > 0',
                ['now' => time()]),
            'openfindings' => finding_service::count_open(),
        ];
    }

    /**
     * Editorial health lists (spec 12.4, 12.5).
     *
     * @param int $limit Max rows per list.
     * @return stdClass Lists: overduereview, missingowner, neverpublished,
     *         agingdrafts; counters: openfindings.
     */
    public static function editorial_health(int $limit = 20): stdClass {
        global $DB;

        $now = time();

        $overduereview = $DB->get_records_select('local_handbook_page',
            'archived = 0 AND reviewdate > 0 AND reviewdate < :now AND publishedrevisionid > 0',
            ['now' => $now], 'reviewdate ASC', '*', 0, $limit);

        $missingowner = $DB->get_records_select('local_handbook_page',
            'archived = 0 AND owneruserid = 0',
            [], 'title ASC', '*', 0, $limit);

        $neverpublished = $DB->get_records_select('local_handbook_page',
            'archived = 0 AND publishedrevisionid = 0',
            [], 'timemodified ASC', '*', 0, $limit);

        $agingdrafts = $DB->get_records_sql(
            "SELECT r.id, r.pageid, r.versionnumber, r.status, r.timemodified, p.title, p.slug
               FROM {local_handbook_revision} r
               JOIN {local_handbook_page} p ON p.id = r.pageid
              WHERE r.status IN (:s1, :s2)
           ORDER BY r.timemodified ASC",
            ['s1' => page_service::STATUS_IN_REVIEW, 's2' => page_service::STATUS_APPROVED],
            0, $limit);

        return (object)[
            'overduereview' => $overduereview,
            'missingowner' => $missingowner,
            'neverpublished' => $neverpublished,
            'agingdrafts' => $agingdrafts,
            'openfindings' => finding_service::count_open(),
        ];
    }
}
