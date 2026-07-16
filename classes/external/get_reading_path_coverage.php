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
use local_handbook\local\service\recommendation_service;

/**
 * External function: aggregate reading-path coverage (spec 10, 11.4).
 *
 * Read-only. Describes handbook coverage and overlap. Returns NO individual
 * completion data — only aggregate counts and per-path composition.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_reading_path_coverage extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return aggregate coverage.
     *
     * @return array
     */
    public static function execute(): array {
        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $coverage = recommendation_service::coverage();
        $paths = [];
        foreach ($coverage->paths as $path) {
            $paths[] = [
                'id' => (int)$path->id,
                'name' => (string)$path->name,
                'items' => (int)$path->items,
                'required' => (int)$path->required,
                'reviewdue' => (bool)$path->reviewdue,
            ];
        }

        return [
            'totalpages' => $coverage->totalpages,
            'pagescovered' => $coverage->pagescovered,
            'orphans' => $coverage->orphans,
            'requiredpages' => $coverage->requiredpages,
            'requiredcovered' => $coverage->requiredcovered,
            'overlap' => $coverage->overlap,
            'activepaths' => $coverage->activepaths,
            'paths' => $paths,
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'totalpages' => new external_value(PARAM_INT, 'Published, non-archived pages'),
            'pagescovered' => new external_value(PARAM_INT, 'Pages in at least one active path'),
            'orphans' => new external_value(PARAM_INT, 'Pages in no active path'),
            'requiredpages' => new external_value(PARAM_INT, 'Required-reading pages'),
            'requiredcovered' => new external_value(PARAM_INT, 'Required pages required in an active path'),
            'overlap' => new external_value(PARAM_INT, 'Pages appearing in more than one active path'),
            'activepaths' => new external_value(PARAM_INT, 'Active path count'),
            'paths' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Path id'),
                'name' => new external_value(PARAM_TEXT, 'Path name'),
                'items' => new external_value(PARAM_INT, 'Item count'),
                'required' => new external_value(PARAM_INT, 'Required item count'),
                'reviewdue' => new external_value(PARAM_BOOL, 'Whether the path is past its review date'),
            ])),
        ]);
    }
}
