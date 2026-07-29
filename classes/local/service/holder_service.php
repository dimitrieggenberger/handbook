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

use stdClass;

/**
 * Responsible people (position holders) of a page.
 *
 * An assignment binds a page to a real Moodle account with a per-page role
 * label ("Directora General", "Representante docente · Primaria") and
 * optional institutional contact fields. The rendered card pulls photo,
 * profile link and platform messaging from the account, so a personnel
 * change is one assignment edit — never an article edit.
 *
 * Assignments live outside the editorial workflow (like banners and
 * attachments) and are managed by humans only: the external API and the
 * MCP adapter expose no surface for them.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class holder_service {

    /**
     * All assignments of a page in display order.
     *
     * @param int $pageid Page id.
     * @return stdClass[]
     */
    public static function get_holders(int $pageid): array {
        global $DB;
        return array_values($DB->get_records('local_handbook_holder',
            ['pageid' => $pageid], 'sortorder ASC, id ASC'));
    }

    /**
     * One assignment.
     *
     * @param int $id Assignment id.
     * @return stdClass
     */
    public static function get_holder(int $id): stdClass {
        global $DB;
        return $DB->get_record('local_handbook_holder', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Number of assignments on a page.
     *
     * @param int $pageid Page id.
     * @return int
     */
    public static function count_for_page(int $pageid): int {
        global $DB;
        return $DB->count_records('local_handbook_holder', ['pageid' => $pageid]);
    }

    /**
     * Insert or update an assignment.
     *
     * @param stdClass $data id (0 = new), pageid, userid, rolelabel,
     *                       contactemail, contactphone, whatsapp,
     *                       sincedate, sortorder.
     * @param int $modifiedby Acting user id.
     * @return int Assignment id.
     */
    public static function save(stdClass $data, int $modifiedby): int {
        global $DB;

        $record = new stdClass();
        $record->pageid = (int)$data->pageid;
        $record->userid = (int)$data->userid;
        $record->rolelabel = trim((string)$data->rolelabel);
        $record->contactemail = trim((string)($data->contactemail ?? ''));
        $record->contactphone = trim((string)($data->contactphone ?? ''));
        $record->whatsapp = trim((string)($data->whatsapp ?? ''));
        $record->sincedate = (int)($data->sincedate ?? 0);
        $record->sortorder = (int)($data->sortorder ?? 0);
        $record->timemodified = time();
        $record->modifiedby = $modifiedby;

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record('local_handbook_holder', $record);
            return $record->id;
        }
        $record->timecreated = $record->timemodified;
        return $DB->insert_record('local_handbook_holder', $record);
    }

    /**
     * Remove an assignment.
     *
     * @param int $id Assignment id.
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records('local_handbook_holder', ['id' => $id]);
    }

    /**
     * Next free display position on a page.
     *
     * @param int $pageid Page id.
     * @return int
     */
    public static function next_sortorder(int $pageid): int {
        global $DB;
        $max = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {local_handbook_holder} WHERE pageid = ?', [$pageid]);
        return $max === null ? 1 : ((int)$max + 1);
    }

    /**
     * wa.me URL for a WhatsApp number, or null when the number is too
     * short to be real after stripping formatting.
     *
     * @param string $number Number as typed (+504 9911-2233 etc.).
     * @return \moodle_url|null
     */
    public static function wa_url(string $number): ?\moodle_url {
        $digits = preg_replace('/\D+/', '', $number);
        if (strlen($digits) < 8) {
            return null;
        }
        return new \moodle_url('https://wa.me/' . $digits);
    }
}
