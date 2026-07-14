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
 * External function: get one revision including content when permitted.
 *
 * Access rules: published revisions need the base read capabilities;
 * superseded ones additionally need viewhistory; working revisions
 * (draft/in_review/changes_requested/approved) need edit or review.
 * AI-access rules apply on top (specification 17.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_revision extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'revisionid' => new external_value(PARAM_INT, 'Revision id'),
        ]);
    }

    /**
     * Get a revision.
     *
     * @param int $revisionid Revision id.
     * @return array
     */
    public static function execute(int $revisionid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['revisionid' => $revisionid]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $revision = $DB->get_record('local_handbook_revision',
            ['id' => $params['revisionid']], '*', MUST_EXIST);
        $page = $DB->get_record('local_handbook_page',
            ['id' => $revision->pageid], '*', MUST_EXIST);
        helper::assert_not_excluded($page);

        if ($revision->status === page_service::STATUS_SUPERSEDED
                || $revision->status === page_service::STATUS_REJECTED) {
            require_capability('local/handbook:viewhistory', $context);
        } else if ($revision->status !== page_service::STATUS_PUBLISHED) {
            if (!has_any_capability(['local/handbook:edit', 'local/handbook:review'], $context)) {
                require_capability('local/handbook:edit', $context);
            }
        }

        return helper::export_revision($revision, true, helper::content_allowed($page));
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
