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
 * External function: list handbook categories.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_categories extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * List all visible categories.
     *
     * @return array
     */
    public static function execute(): array {
        global $DB;

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $categories = $DB->get_records('local_handbook_category', ['visible' => 1],
            'parentid ASC, sortorder ASC, name ASC');

        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id' => (int)$category->id,
                'parentid' => (int)$category->parentid,
                'slug' => $category->slug,
                'name' => $category->name,
                'description' => (string)$category->description,
                'sortorder' => (int)$category->sortorder,
            ];
        }
        return $result;
    }

    /**
     * Return definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Category id'),
            'parentid' => new external_value(PARAM_INT, 'Parent category id (0 = top level)'),
            'slug' => new external_value(PARAM_ALPHANUMEXT, 'Stable slug'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'description' => new external_value(PARAM_RAW, 'Description HTML'),
            'sortorder' => new external_value(PARAM_INT, 'Sort order'),
        ]));
    }
}
