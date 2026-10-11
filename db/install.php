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

/**
 * Fresh-install seeding for local_handbook.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Seed the starting audience vocabulary (mirrors the 2026071596 upgrade
 * step, for sites installing from scratch). Profile values start empty —
 * never matching — until leadership fills in the real field values.
 *
 * @return void
 */
function xmldb_local_handbook_install(): void {
    global $DB;

    $now = time();
    $seed = [
        ['personal', 'Personal', 'staff', '', '', '#6c757d', 1],
        ['estudiantescasa', 'Estudiantes en casa', 'profile', 'city', '', '#7c5cbf', 2],
        ['estudianteshibrido', 'Estudiantes híbridos', 'profile', 'city', '', '#b0592b', 3],
        ['estudiantescampus', 'Estudiantes en campus', 'profile', 'city', '', '#0078c3', 4],
        ['familias', 'Familias', 'profile', 'city', '', '#1e7d43', 5],
    ];
    foreach ($seed as [$key, $name, $matchtype, $field, $value, $color, $order]) {
        if (!$DB->record_exists('local_handbook_audience', ['audiencekey' => $key])) {
            $DB->insert_record('local_handbook_audience', (object)[
                'audiencekey' => $key, 'name' => $name, 'matchtype' => $matchtype,
                'profilefield' => $field, 'profilevalue' => $value, 'colorhex' => $color,
                'sortorder' => $order, 'active' => 1, 'timemodified' => $now,
                'modifiedby' => 0,
            ]);
        }
    }
}
