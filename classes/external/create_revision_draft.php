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
use local_handbook\local\service\page_service;
use moodle_exception;

/**
 * External function: create a draft revision based on the published one.
 *
 * Callers should pass the published revision id they based their work on
 * (specification 17.4); a mismatch fails clearly instead of overwriting
 * newer work.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_revision_draft extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Page id or slug'),
            'expectedpublishedrevisionid' => new external_value(PARAM_INT,
                'Published revision id the caller has read (0 = skip the check)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create the draft.
     *
     * @param string $identifier Page id or slug.
     * @param int $expectedpublishedrevisionid Base-revision check (0 = skip).
     * @return array
     */
    public static function execute(string $identifier, int $expectedpublishedrevisionid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'identifier' => $identifier,
            'expectedpublishedrevisionid' => $expectedpublishedrevisionid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_write($context);

        $page = helper::get_page_by_identifier($params['identifier']);
        helper::assert_writable($page);

        if ($params['expectedpublishedrevisionid']
                && (int)$page->publishedrevisionid !== $params['expectedpublishedrevisionid']) {
            throw new moodle_exception('errorbasemismatch', 'local_handbook');
        }

        $revision = page_service::create_revision_draft($page);

        return helper::export_revision($revision, true, true);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::revision_structure(true);
    }
}
