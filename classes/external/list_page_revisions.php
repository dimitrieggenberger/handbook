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
 * External function: list a page's revisions (metadata only, no content).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_page_revisions extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Page id or slug'),
        ]);
    }

    /**
     * List revisions of a page, newest first.
     *
     * @param string $identifier Page id or slug.
     * @return array
     */
    public static function execute(string $identifier): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['identifier' => $identifier]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $page = helper::get_page_by_identifier($params['identifier']);
        helper::assert_not_excluded($page);

        $revisions = $DB->get_records('local_handbook_revision', ['pageid' => $page->id],
            'versionnumber DESC');

        $result = [];
        foreach ($revisions as $revision) {
            $result[] = helper::export_revision($revision, false);
        }
        return $result;
    }

    /**
     * Return definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(helper::revision_structure(false));
    }
}
