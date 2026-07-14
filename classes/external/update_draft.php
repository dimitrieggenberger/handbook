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
 * External function: update a draft revision's content.
 *
 * expectedtimemodified is mandatory (specification 17.4): pass the
 * timemodified returned when the draft was fetched or created. A mismatch
 * fails clearly instead of overwriting concurrent work.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_draft extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'revisionid' => new external_value(PARAM_INT, 'Draft revision id'),
            'content' => new external_value(PARAM_RAW, 'New content HTML (headings start at h2)'),
            'expectedtimemodified' => new external_value(PARAM_INT,
                'timemodified of the revision as last read by the caller'),
            'changesummary' => new external_value(PARAM_RAW, 'Change summary', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Update the draft.
     *
     * @param int $revisionid Draft revision id.
     * @param string $content New content HTML.
     * @param int $expectedtimemodified Concurrency token.
     * @param string $changesummary Change summary.
     * @return array
     */
    public static function execute(int $revisionid, string $content, int $expectedtimemodified,
            string $changesummary = ''): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'revisionid' => $revisionid,
            'content' => $content,
            'expectedtimemodified' => $expectedtimemodified,
            'changesummary' => $changesummary,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_write($context);

        $revision = $DB->get_record('local_handbook_revision',
            ['id' => $params['revisionid']], '*', MUST_EXIST);
        $page = $DB->get_record('local_handbook_page',
            ['id' => $revision->pageid], '*', MUST_EXIST);
        helper::assert_writable($page);

        if ((int)$revision->timemodified !== $params['expectedtimemodified']) {
            throw new moodle_exception('errorrevisionconflict', 'local_handbook');
        }

        page_service::update_draft($revision, $params['content'], FORMAT_HTML,
            $params['changesummary']);

        $revision = $DB->get_record('local_handbook_revision',
            ['id' => $revision->id], '*', MUST_EXIST);
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
