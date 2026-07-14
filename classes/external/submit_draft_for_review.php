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

/**
 * External function: submit a draft revision for human review.
 *
 * This is the end of the API's authority: review, approval and
 * publication are human UI actions (specification 17.3, 18.3).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_draft_for_review extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'revisionid' => new external_value(PARAM_INT, 'Draft revision id'),
            'changesummary' => new external_value(PARAM_RAW, 'Change summary (required)'),
        ]);
    }

    /**
     * Submit the draft.
     *
     * @param int $revisionid Draft revision id.
     * @param string $changesummary Change summary.
     * @return array
     */
    public static function execute(int $revisionid, string $changesummary): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'revisionid' => $revisionid,
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

        page_service::submit_for_review($revision, $params['changesummary']);

        $revision = $DB->get_record('local_handbook_revision',
            ['id' => $revision->id], '*', MUST_EXIST);
        return helper::export_revision($revision, false);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::revision_structure(false);
    }
}
