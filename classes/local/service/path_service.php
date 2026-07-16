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
 * Reading-path audiences (specification 15.3; resolves open decision 32.1
 * for paths: audiences are Moodle cohorts and/or system-level roles).
 *
 * audiencejson format: {"cohorts": [cohortid, ...], "roles": [roleid, ...]}.
 * Empty/absent audience = the path is visible to every handbook viewer.
 * A user matches when they belong to ANY listed cohort OR hold ANY listed
 * role at system context. Managers always see every path.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_service {

    /**
     * Decode a path's audience definition.
     *
     * @param stdClass $path Path record.
     * @return stdClass {cohorts: int[], roles: int[]}
     */
    public static function get_audience(stdClass $path): stdClass {
        $decoded = json_decode((string)$path->audiencejson);
        return (object)[
            'cohorts' => array_map('intval', (array)($decoded->cohorts ?? [])),
            'roles' => array_map('intval', (array)($decoded->roles ?? [])),
        ];
    }

    /**
     * Encode an audience definition ('' when unrestricted).
     *
     * @param int[] $cohortids Cohort ids.
     * @param int[] $roleids Role ids.
     * @return string
     */
    public static function encode_audience(array $cohortids, array $roleids): string {
        $cohortids = array_values(array_filter(array_map('intval', $cohortids)));
        $roleids = array_values(array_filter(array_map('intval', $roleids)));
        if (!$cohortids && !$roleids) {
            return '';
        }
        return json_encode(['cohorts' => $cohortids, 'roles' => $roleids]);
    }

    /**
     * Whether a path is visible to a user.
     *
     * @param stdClass $path Path record.
     * @param int $userid User id.
     * @return bool
     */
    public static function is_visible(stdClass $path, int $userid): bool {
        global $DB;

        $audience = self::get_audience($path);
        if (!$audience->cohorts && !$audience->roles) {
            return true;
        }

        if ($audience->cohorts) {
            [$insql, $params] = $DB->get_in_or_equal($audience->cohorts, SQL_PARAMS_NAMED, 'coh');
            $params['userid'] = $userid;
            if ($DB->record_exists_select('cohort_members',
                    "cohortid $insql AND userid = :userid", $params)) {
                return true;
            }
        }

        if ($audience->roles) {
            [$insql, $params] = $DB->get_in_or_equal($audience->roles, SQL_PARAMS_NAMED, 'rol');
            $params['userid'] = $userid;
            $params['contextid'] = context_system::instance()->id;
            if ($DB->record_exists_select('role_assignments',
                    "roleid $insql AND userid = :userid AND contextid = :contextid", $params)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Active paths visible to a user, ordered like path.php expects.
     *
     * @param int $userid User id.
     * @param bool $manager Managers see everything.
     * @return stdClass[]
     */
    public static function visible_paths(int $userid, bool $manager = false): array {
        global $DB;

        $paths = $DB->get_records('local_handbook_path', ['active' => 1],
            'schoolyear DESC, name ASC');
        if ($manager) {
            return $paths;
        }

        return array_filter($paths, static fn(stdClass $path): bool
            => self::is_visible($path, $userid));
    }

    /**
     * A user's progress and next pending item on a path.
     *
     * Path completion counts every required, published, non-archived item —
     * regardless of the page's global required-reading flag (spec 8.3). An item
     * counts as done when the user has completed its current published revision
     * (a read receipt or a compliance acknowledgement), which carries across
     * every path containing the article.
     *
     * @param stdClass $path Path record.
     * @param int $userid User id.
     * @return stdClass {confirmed: int, total: int, nextitem: ?stdClass}
     *         where nextitem carries slug/title/sectionname of the first
     *         required item still pending or needing a renewed read.
     */
    public static function user_progress(stdClass $path, int $userid): stdClass {
        global $DB;

        $sql = "SELECT i.id, i.sectionname, i.required, p.id AS pageid, p.slug, p.title,
                       p.requiredreading, p.publishedrevisionid
                  FROM {local_handbook_pathitem} i
                  JOIN {local_handbook_page} p ON p.id = i.pageid
                 WHERE i.pathid = :pathid AND p.archived = 0
              ORDER BY i.sortorder ASC, i.id ASC";
        $items = $DB->get_records_sql($sql, ['pathid' => $path->id]);

        $confirmed = 0;
        $total = 0;
        $nextitem = null;
        foreach ($items as $item) {
            if (!(int)$item->required || !(int)$item->publishedrevisionid) {
                // Optional items never block completion (spec 8.3).
                continue;
            }
            $total++;
            $status = completion_service::completion_status($userid, (object)[
                'id' => $item->pageid,
                'publishedrevisionid' => $item->publishedrevisionid,
            ]);
            if ($status->status === completion_service::STATUS_COMPLETED) {
                $confirmed++;
            } else if ($nextitem === null) {
                $nextitem = $item;
            }
        }

        return (object)['confirmed' => $confirmed, 'total' => $total, 'nextitem' => $nextitem];
    }

    /**
     * Whether a page is a required item in at least one active reading path
     * (spec 8.3). Used to let a reader mark completion of a path-required
     * article even when it is not globally required reading.
     *
     * @param int $pageid Page id.
     * @return bool
     */
    public static function is_required_in_active_path(int $pageid): bool {
        global $DB;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {local_handbook_pathitem} i
               JOIN {local_handbook_path} p ON p.id = i.pathid
              WHERE i.pageid = :pageid AND i.required = 1 AND p.active = 1",
            ['pageid' => $pageid]);
    }

    /**
     * Users in a path's audience (for completion reports). Unrestricted
     * paths fall back to all staff (report_service::get_staff_users).
     *
     * @param stdClass $path Path record.
     * @return stdClass[] User records keyed by id.
     */
    public static function get_audience_users(stdClass $path): array {
        global $DB;

        $audience = self::get_audience($path);
        if (!$audience->cohorts && !$audience->roles) {
            return report_service::get_staff_users();
        }

        $namefields = implode(', ', array_map(
            static fn(string $field): string => 'u.' . $field,
            \core_user\fields::get_name_fields()
        ));
        $fields = 'u.id, u.email, ' . $namefields;
        $users = [];

        if ($audience->cohorts) {
            [$insql, $params] = $DB->get_in_or_equal($audience->cohorts, SQL_PARAMS_NAMED, 'coh');
            $sql = "SELECT DISTINCT $fields
                      FROM {user} u
                      JOIN {cohort_members} cm ON cm.userid = u.id
                     WHERE cm.cohortid $insql AND u.deleted = 0 AND u.suspended = 0";
            foreach ($DB->get_records_sql($sql, $params) as $user) {
                $users[(int)$user->id] = $user;
            }
        }

        if ($audience->roles) {
            [$insql, $params] = $DB->get_in_or_equal($audience->roles, SQL_PARAMS_NAMED, 'rol');
            $params['contextid'] = context_system::instance()->id;
            $sql = "SELECT DISTINCT $fields
                      FROM {user} u
                      JOIN {role_assignments} ra ON ra.userid = u.id
                     WHERE ra.roleid $insql AND ra.contextid = :contextid
                       AND u.deleted = 0 AND u.suspended = 0";
            foreach ($DB->get_records_sql($sql, $params) as $user) {
                $users[(int)$user->id] = $user;
            }
        }

        \core_collator::asort_objects_by_property($users, 'lastname');
        return $users;
    }
}
