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
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: fetch one reading-path recommendation (spec 11.1).
 *
 * Read-only advisory record.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_path_recommendation extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'recommendationid' => new external_value(PARAM_INT, 'Recommendation id'),
        ]);
    }

    /**
     * Return a recommendation.
     *
     * @param int $recommendationid Recommendation id.
     * @return array
     */
    public static function execute(int $recommendationid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            ['recommendationid' => $recommendationid]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $rec = $DB->get_record('local_handbook_pathrec',
            ['id' => $params['recommendationid']], '*', MUST_EXIST);
        return helper::export_recommendation($rec);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::recommendation_structure();
    }
}
