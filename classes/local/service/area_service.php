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
}
