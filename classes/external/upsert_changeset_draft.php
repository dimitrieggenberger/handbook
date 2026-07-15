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
use local_handbook\local\service\changeset_service;

/**
 * External function: create or update a change set's draft for one page (36.4).
 *
 * Conservative by design: it reuses this change set's editable draft when one
 * exists (no version churn) and otherwise returns a structured conflict —
 * never overwriting a human draft, another change set's draft, an in-review
 * revision, a stale published base, or a concurrent edit. It never publishes.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upsert_changeset_draft extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Page id or slug'),
            'content' => new external_value(PARAM_RAW, 'Proposed content HTML (headings start at h2)'),
            'changesummary' => new external_value(PARAM_RAW, 'Change summary', VALUE_DEFAULT, ''),
            'expectedpublishedrevisionid' => new external_value(PARAM_INT,
                'Published revision id the caller based the draft on (0 = skip for a new draft)',
                VALUE_DEFAULT, 0),
            'expectedtimemodified' => new external_value(PARAM_INT,
                'timemodified of the draft being updated (0 = skip; required to update safely)',
                VALUE_DEFAULT, 0),
            'requiresreack' => new external_value(PARAM_BOOL,
                'Whether publishing this revision should demand renewed acknowledgements',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Upsert the draft.
     *
     * @param int $changesetid Change-set id.
     * @param string $identifier Page id or slug.
     * @param string $content Proposed content HTML.
     * @param string $changesummary Change summary.
     * @param int $expectedpublishedrevisionid Base check for a new draft.
     * @param int $expectedtimemodified Concurrency token for an update.
     * @param bool $requiresreack Renewed-acknowledgement flag.
     * @return array
     */
    public static function execute(int $changesetid, string $identifier, string $content,
            string $changesummary = '', int $expectedpublishedrevisionid = 0,
            int $expectedtimemodified = 0, bool $requiresreack = false): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'changesetid' => $changesetid,
            'identifier' => $identifier,
            'content' => $content,
            'changesummary' => $changesummary,
            'expectedpublishedrevisionid' => $expectedpublishedrevisionid,
            'expectedtimemodified' => $expectedtimemodified,
            'requiresreack' => $requiresreack,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_write($context);

        $page = helper::get_page_by_identifier($params['identifier']);
        helper::assert_writable($page);

        return changeset_service::upsert_draft(
            $params['changesetid'], (int)$page->id, $params['content'], FORMAT_HTML,
            $params['changesummary'], $params['expectedpublishedrevisionid'],
            $params['expectedtimemodified'], (bool)$params['requiresreack']);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::changeitem_result_structure();
    }
}
