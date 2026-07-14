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
use moodle_exception;

/**
 * External function: get one page with its published content.
 *
 * metadata_only pages return metadata without body content; excluded
 * pages return a clear access-denied error (specification 17.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_page extends external_api {

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
     * Get a page with its published revision.
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

        if (!$page->publishedrevisionid) {
            throw new moodle_exception('notpublished', 'local_handbook');
        }

        $revision = $DB->get_record('local_handbook_revision',
            ['id' => $page->publishedrevisionid], '*', MUST_EXIST);

        return [
            'page' => helper::export_page_summary($page),
            'published' => helper::export_revision($revision, true, helper::content_allowed($page)),
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'page' => helper::page_summary_structure(),
            'published' => helper::revision_structure(true),
        ]);
    }
}
