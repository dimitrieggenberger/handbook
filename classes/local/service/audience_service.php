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
 * Reader audiences: the multi-audience handbook's vocabulary and tags.
 *
 * Audiences are LABELS on articles, never copies of the manual: one page
 * tagged for three audiences appears in three portals with one editorial
 * history. Untagged pages are internal to staff — nothing is exposed by
 * accident. Membership resolves through each audience's matcher: 'staff'
 * (anyone holding the handbook's staff capability) or 'profile' (a user
 * profile field — core city/department/institution or a custom-field
 * shortname — equal to a configured value, case-insensitively).
 *
 * Vocabulary and tagging are human-managed; the AI may only propose.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience_service {

    /** @var string[] Core user columns a profile matcher may read. */
    const CORE_FIELDS = ['city', 'department', 'institution'];

    /**
     * All audiences in display order.
     *
     * @param bool $activeonly Only active ones.
     * @return stdClass[] Keyed by id.
     */
    public static function get_all(bool $activeonly = false): array {
        global $DB;
        $conditions = $activeonly ? ['active' => 1] : [];
        return $DB->get_records('local_handbook_audience', $conditions, 'sortorder ASC, id ASC');
    }

    /**
     * One audience.
     *
     * @param int $id Audience id.
     * @return stdClass
     */
    public static function get(int $id): stdClass {
        global $DB;
        return $DB->get_record('local_handbook_audience', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Insert or update an audience.
     *
     * @param stdClass $data id (0 = new), audiencekey, name, matchtype,
     *                       profilefield, profilevalue, colorhex,
     *                       sortorder, active.
     * @param int $userid Acting user.
     * @return int Audience id.
     */
    public static function save(stdClass $data, int $userid): int {
        global $DB;

        $record = new stdClass();
        $record->audiencekey = trim((string)$data->audiencekey);
        $record->name = trim((string)$data->name);
        $record->matchtype = $data->matchtype === 'staff' ? 'staff' : 'profile';
        $record->profilefield = trim((string)($data->profilefield ?? ''));
        $record->profilevalue = trim((string)($data->profilevalue ?? ''));
        $record->colorhex = trim((string)($data->colorhex ?? ''));
        $record->sortorder = (int)($data->sortorder ?? 0);
        $record->active = (int)!empty($data->active);
        $record->timemodified = time();
        $record->modifiedby = $userid;

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record('local_handbook_audience', $record);
            return $record->id;
        }
        return $DB->insert_record('local_handbook_audience', $record);
    }

    /**
     * Delete an audience unless pages still carry its tag.
     *
     * @param int $id Audience id.
     * @return bool True when deleted, false when still in use.
     */
    public static function delete(int $id): bool {
        global $DB;
        if ($DB->record_exists('local_handbook_pageaud', ['audienceid' => $id])) {
            return false;
        }
        $DB->delete_records('local_handbook_audience', ['id' => $id]);
        return true;
    }

    /**
     * Number of pages tagged with an audience.
     *
     * @param int $id Audience id.
     * @return int
     */
    public static function page_count(int $id): int {
        global $DB;
        return $DB->count_records('local_handbook_pageaud', ['audienceid' => $id]);
    }

    /**
     * Audience ids tagged on a page.
     *
     * @param int $pageid Page id.
     * @return int[]
     */
    public static function page_audience_ids(int $pageid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('local_handbook_pageaud',
            'audienceid', 'pageid = ?', [$pageid]));
    }

    /**
     * Audience records per page for a set of pages, one query.
     *
     * @param int[] $pageids Page ids.
     * @return array pageid => stdClass[] audience records in display order.
     */
    public static function page_audience_map(array $pageids): array {
        global $DB;

        if (!$pageids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED, 'pa');
        $sql = "SELECT pa.id AS tagid, pa.pageid, a.*
                  FROM {local_handbook_pageaud} pa
                  JOIN {local_handbook_audience} a ON a.id = pa.audienceid
                 WHERE pa.pageid $insql
              ORDER BY a.sortorder ASC, a.id ASC";
        $map = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $map[(int)$row->pageid][] = $row;
        }
        return $map;
    }

    /**
     * Replace a page's audience tags.
     *
     * @param int $pageid Page id.
     * @param int[] $audienceids Desired audience ids.
     * @return void
     */
    public static function set_page_audiences(int $pageid, array $audienceids): void {
        global $DB;

        $audienceids = array_unique(array_map('intval', $audienceids));
        $current = self::page_audience_ids($pageid);

        foreach (array_diff($current, $audienceids) as $remove) {
            $DB->delete_records('local_handbook_pageaud',
                ['pageid' => $pageid, 'audienceid' => $remove]);
        }
        foreach (array_diff($audienceids, $current) as $add) {
            if ($DB->record_exists('local_handbook_audience', ['id' => $add])) {
                $DB->insert_record('local_handbook_pageaud',
                    (object)['pageid' => $pageid, 'audienceid' => $add]);
            }
        }
    }

    /**
     * The audiences a user belongs to, by each audience's matcher.
     *
     * @param stdClass $user Full user record.
     * @return stdClass[] Matching active audiences keyed by id.
     */
    public static function user_audiences(stdClass $user): array {
        global $CFG;

        $matches = [];
        $custom = null;
        foreach (self::get_all(true) as $audience) {
            if ($audience->matchtype === 'staff') {
                if (has_capability('local/handbook:acknowledge',
                        context_system::instance(), $user)) {
                    $matches[(int)$audience->id] = $audience;
                }
                continue;
            }
            $field = trim((string)$audience->profilefield);
            $expected = trim((string)$audience->profilevalue);
            if ($field === '' || $expected === '') {
                continue;
            }
            if (in_array($field, self::CORE_FIELDS, true)) {
                $value = trim((string)($user->$field ?? ''));
            } else {
                if ($custom === null) {
                    require_once($CFG->dirroot . '/user/profile/lib.php');
                    $custom = profile_user_record((int)$user->id, false);
                }
                $value = trim((string)($custom->$field ?? ''));
            }
            if ($value !== ''
                    && \core_text::strtolower($value) === \core_text::strtolower($expected)) {
                $matches[(int)$audience->id] = $audience;
            }
        }
        return $matches;
    }
}
