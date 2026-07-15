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
use local_handbook\local\service\changeset_service;

/**
 * External function: list change sets with optional filters (specification 36.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_changesets extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'status' => new external_value(PARAM_ALPHANUMEXT,
                'Filter by status (empty = all)', VALUE_DEFAULT, ''),
            'source' => new external_value(PARAM_ALPHANUMEXT,
                'Filter by source: human or ai (empty = all)', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Page size (max 200)', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * List change sets.
     *
     * @param string $status Status filter.
     * @param string $source Source filter.
     * @param int $page Zero-based page number.
     * @param int $perpage Page size.
     * @return array
     */
    public static function execute(string $status = '', string $source = '',
            int $page = 0, int $perpage = 50): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'status' => $status,
            'source' => $source,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        [$limitfrom, $limitnum] = helper::paginate($params['page'], $params['perpage']);

        $filters = [];
        if ($params['status'] !== '') {
            $filters['status'] = $params['status'];
        }
        if ($params['source'] !== '') {
            $filters['source'] = $params['source'];
        }

        $result = [];
        foreach (changeset_service::list_changesets($filters, $limitfrom, $limitnum) as $changeset) {
            $itemcount = $DB->count_records('local_handbook_changeitem',
                ['changesetid' => $changeset->id]);
            $result[] = helper::export_changeset($changeset, $itemcount);
        }
        return $result;
    }

    /**
     * Return definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(helper::changeset_structure());
    }
}
