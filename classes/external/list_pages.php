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

/**
 * External function: list handbook pages (summaries, no content).
 *
 * Excluded-AI pages are omitted (specification 17.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_pages extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Filter by category id (0 = all)',
                VALUE_DEFAULT, 0),
            'includearchived' => new external_value(PARAM_BOOL, 'Include archived pages',
                VALUE_DEFAULT, false),
            'page' => new external_value(PARAM_INT, 'Zero-based page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Page size (max 200)', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * List pages.
     *
     * @param int $categoryid Category filter (0 = all).
     * @param bool $includearchived Include archived pages.
     * @param int $page Zero-based page number.
     * @param int $perpage Page size.
     * @return array
     */
    public static function execute(int $categoryid = 0, bool $includearchived = false,
            int $page = 0, int $perpage = 50): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'includearchived' => $includearchived,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        [$limitfrom, $limitnum] = helper::paginate($params['page'], $params['perpage']);

        $where = "p.aiaccess <> 'excluded'";
        $sqlparams = [];
        if ($params['categoryid']) {
            $where .= ' AND p.categoryid = :categoryid';
            $sqlparams['categoryid'] = $params['categoryid'];
        }
        if (!$params['includearchived']) {
            $where .= ' AND p.archived = 0';
        }

        $sql = "SELECT p.*, r.versionnumber AS pubversion, r.timepublished AS pubtime,
                       r.contenthash AS pubhash
                  FROM {local_handbook_page} p
             LEFT JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
                 WHERE $where
              ORDER BY p.categoryid ASC, p.sortorder ASC, p.title ASC";

        $result = [];
        foreach ($DB->get_records_sql($sql, $sqlparams, $limitfrom, $limitnum) as $record) {
            $result[] = helper::export_page_summary($record);
        }
        return $result;
    }

    /**
     * Return definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(helper::page_summary_structure());
    }
}
