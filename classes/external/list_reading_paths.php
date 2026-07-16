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

namespace local_handbook\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: list reading paths (spec 7.2).
 *
 * Read-only. Lets a proposing agent discover existing paths (and their item
 * counts) before proposing a create or an edit. Full contents are fetched with
 * get_reading_path.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_reading_paths extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'activeonly' => new external_value(PARAM_BOOL, 'Only active paths', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * List reading paths, newest school year first.
     *
     * @param bool $activeonly Only active paths.
     * @return array
     */
    public static function execute(bool $activeonly = false): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'activeonly' => $activeonly,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $conditions = $params['activeonly'] ? ['active' => 1] : [];
        $paths = $DB->get_records('local_handbook_path', $conditions, 'schoolyear DESC, name ASC');

        $out = [];
        foreach ($paths as $path) {
            $out[] = [
                'id' => (int)$path->id,
                'name' => (string)$path->name,
                'slug' => (string)$path->slug,
                'pathtype' => (string)$path->pathtype,
                'schoolyear' => (string)$path->schoolyear,
                'active' => (bool)$path->active,
                'estimatedminutes' => (int)$path->estimatedminutes,
                'reviewdate' => (int)$path->reviewdate,
                'itemcount' => (int)$DB->count_records('local_handbook_pathitem', ['pathid' => $path->id]),
                'timemodified' => (int)$path->timemodified,
            ];
        }
        return $out;
    }

    /**
     * Return definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Path id'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'slug' => new external_value(PARAM_ALPHANUMEXT, 'Slug'),
            'pathtype' => new external_value(PARAM_ALPHANUMEXT, 'Path type key (empty = unset)'),
            'schoolyear' => new external_value(PARAM_TEXT, 'School year (empty = evergreen)'),
            'active' => new external_value(PARAM_BOOL, 'Active'),
            'estimatedminutes' => new external_value(PARAM_INT, 'Estimated minutes (0 = unset)'),
            'reviewdate' => new external_value(PARAM_INT, 'Next review date (0 = unset)'),
            'itemcount' => new external_value(PARAM_INT, 'Number of items in the path'),
            'timemodified' => new external_value(PARAM_INT,
                'Last modification time; pass back as expectedtimemodified on update'),
        ]));
    }
}
