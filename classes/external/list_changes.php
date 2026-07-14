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
 * External function: incremental change listing (specification 17.4).
 *
 * Returns pages modified since a timestamp (publication, archive and
 * metadata changes all bump the page's timemodified). Callers store the
 * returned servertime and pass it back as "since" on the next sync.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_changes extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'since' => new external_value(PARAM_INT, 'Return pages modified at/after this timestamp'),
            'page' => new external_value(PARAM_INT, 'Zero-based page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Page size (max 200)', VALUE_DEFAULT, 100),
        ]);
    }

    /**
     * List changed pages.
     *
     * @param int $since Timestamp cursor.
     * @param int $page Zero-based page number.
     * @param int $perpage Page size.
     * @return array
     */
    public static function execute(int $since, int $page = 0, int $perpage = 100): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'since' => $since,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        [$limitfrom, $limitnum] = helper::paginate($params['page'], $params['perpage']);

        $sql = "SELECT p.*, r.versionnumber AS pubversion, r.timepublished AS pubtime,
                       r.contenthash AS pubhash
                  FROM {local_handbook_page} p
             LEFT JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
                 WHERE p.aiaccess <> 'excluded' AND p.timemodified >= :since
              ORDER BY p.timemodified ASC, p.id ASC";

        $pages = [];
        foreach ($DB->get_records_sql($sql, ['since' => $params['since']], $limitfrom, $limitnum) as $record) {
            $pages[] = helper::export_page_summary($record);
        }

        return ['servertime' => time(), 'pages' => $pages];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'servertime' => new external_value(PARAM_INT,
                'Server time of this response; pass back as "since" on the next call'),
            'pages' => new external_multiple_structure(helper::page_summary_structure()),
        ]);
    }
}
