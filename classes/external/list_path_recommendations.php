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
use core_external\external_value;
use local_handbook\local\service\recommendation_service;

/**
 * External function: list reading-path recommendations (spec 10, 11.1).
 *
 * Read-only. Advisory records only; contains no individual completion data.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_path_recommendations extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Filter by status (empty = all)',
                VALUE_DEFAULT, 'open'),
            'pathid' => new external_value(PARAM_INT, 'Filter by path id (0 = any)', VALUE_DEFAULT, 0),
            'pageid' => new external_value(PARAM_INT, 'Filter by page id (0 = any)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * List recommendations.
     *
     * @param string $status Status filter.
     * @param int $pathid Path filter.
     * @param int $pageid Page filter.
     * @return array
     */
    public static function execute(string $status = 'open', int $pathid = 0, int $pageid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'status' => $status, 'pathid' => $pathid, 'pageid' => $pageid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $filters = [];
        if ($params['status'] !== '') {
            $filters['status'] = $params['status'];
        }
        if ($params['pathid']) {
            $filters['pathid'] = $params['pathid'];
        }
        if ($params['pageid']) {
            $filters['pageid'] = $params['pageid'];
        }

        $out = [];
        foreach (recommendation_service::list_recommendations($filters, 0, 200) as $rec) {
            $out[] = helper::export_recommendation($rec);
        }
        return $out;
    }

    /**
     * Return definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(helper::recommendation_structure());
    }
}
