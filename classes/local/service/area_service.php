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
 * Controlled vocabulary of responsible areas (specification 9).
 *
 * Pages store the display name; this catalogue governs which names are valid
 * so proposals reference a stable, controlled set rather than free text. When
 * the catalogue is empty (e.g. a fresh site before seeding) any non-empty name
 * is accepted, preserving backward compatibility.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class area_service {

    /**
     * Active areas, ordered for display and selection.
     *
     * @return \stdClass[]
     */
    public static function list_active(): array {
        global $DB;
        return $DB->get_records('local_handbook_area', ['active' => 1], 'sortorder ASC, name ASC');
    }

    /**
     * Whether the catalogue currently governs the vocabulary.
     *
     * @return bool True once at least one area exists.
     */
    public static function is_governed(): bool {
        global $DB;
        return $DB->record_exists('local_handbook_area', []);
    }

    /**
     * Resolve a proposed responsible-area value to its canonical display name.
     *
     * Accepts either an area key or a display name (case-insensitive). When the
     * catalogue is empty, returns the trimmed value unchanged. Throws when the
     * catalogue is governed and the value matches no active area.
     *
     * @param string $value Proposed area key or name.
     * @return string Canonical display name.
     */
    public static function resolve_name(string $value): string {
        global $DB;

        $value = trim($value);
        if ($value === '') {
            throw new moodle_exception('errormetadatavalue', 'local_handbook', '', 'responsiblearea');
        }
        if (!self::is_governed()) {
            return $value;
        }

        // Match by key first, then by name (case-insensitive), active only.
        $bykey = $DB->get_record('local_handbook_area', ['areakey' => $value, 'active' => 1]);
        if ($bykey) {
            return $bykey->name;
        }
        foreach (self::list_active() as $area) {
            if (\core_text::strtolower($area->name) === \core_text::strtolower($value)) {
                return $area->name;
            }
        }
        throw new moodle_exception('errorunknownarea', 'local_handbook', '', $value);
    }

    /**
     * All areas, active first then by sort order (for management).
     *
     * @return stdClass[]
     */
    public static function all(): array {
        global $DB;
        return $DB->get_records('local_handbook_area', null, 'active DESC, sortorder ASC, name ASC');
    }

    /**
     * Fetch one area.
     *
     * @param int $id Area id.
     * @return stdClass|null
     */
    public static function get(int $id): ?stdClass {
        global $DB;
        $record = $DB->get_record('local_handbook_area', ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Create or update an area. The key is generated from the name when none is
     * given and stays stable once set; names/keys are kept unique.
     *
     * @param stdClass $data id (0 = create), name, areakey (optional), active.
     * @param int $userid Acting user.
     * @return int Area id.
     */
    public static function save(stdClass $data, int $userid): int {
        global $DB;

        $now = time();
        $name = trim((string)($data->name ?? ''));
        if ($name === '') {
            throw new moodle_exception('errormetadatavalue', 'local_handbook', '', 'name');
        }

        $record = new stdClass();
        $record->name = \core_text::substr($name, 0, 255);
        $record->active = !empty($data->active) ? 1 : 0;
        $record->timemodified = $now;
        $record->modifiedby = $userid;

        $id = (int)($data->id ?? 0);
        if ($id) {
            $record->id = $id;
            if (!empty($data->areakey)) {
                $record->areakey = self::unique_key(page_service::slugify((string)$data->areakey), $id);
            }
            $DB->update_record('local_handbook_area', $record);
            return $id;
        }

        $keysource = !empty($data->areakey) ? (string)$data->areakey : $name;
        $record->areakey = self::unique_key(page_service::slugify($keysource), 0);
        $record->sortorder = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {local_handbook_area}');
        $record->timecreated = $now;
        $record->createdby = $userid;
        return (int)$DB->insert_record('local_handbook_area', $record);
    }

    /**
     * Activate or deactivate an area.
     *
     * @param int $id Area id.
     * @param bool $active Target state.
     * @param int $userid Acting user.
     * @return void
     */
    public static function set_active(int $id, bool $active, int $userid): void {
        global $DB;
        $DB->update_record('local_handbook_area', (object)[
            'id' => $id, 'active' => $active ? 1 : 0,
            'timemodified' => time(), 'modifiedby' => $userid,
        ]);
    }

    /**
     * Delete an area from the catalogue. Pages keep their stored area name;
     * only the governed vocabulary entry is removed.
     *
     * @param int $id Area id.
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records('local_handbook_area', ['id' => $id]);
    }

    /**
     * Ensure an area key is unique, appending -2, -3, ... when needed.
     *
     * @param string $key Candidate key.
     * @param int $excludeid Area id to ignore (when updating).
     * @return string
     */
    private static function unique_key(string $key, int $excludeid): string {
        global $DB;

        $key = $key !== '' ? $key : 'area';
        $candidate = $key;
        $suffix = 2;
        while ($DB->record_exists_select('local_handbook_area',
                'areakey = :k AND id <> :id', ['k' => $candidate, 'id' => $excludeid])) {
            $candidate = $key . '-' . $suffix++;
        }
        return $candidate;
    }
}
