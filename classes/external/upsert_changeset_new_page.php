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
 * External function: propose a brand-new page inside a change set (spec 13).
 *
 * Draft authority only: the page is created and its first revision published
 * exclusively by the human publish path. The proposal is identified within the
 * change set by a stable tempkey so later items (e.g. relations) can reference
 * it before it exists. There is no approve/publish/apply function.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upsert_changeset_new_page extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
            'tempkey' => new external_value(PARAM_TEXT, 'Stable id within the change set (e.g. newpage:slug)'),
            'page' => new external_single_structure([
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'categoryid' => new external_value(PARAM_INT, 'Existing category id'),
                'content' => new external_value(PARAM_RAW, 'Content HTML (headings start at h2)'),
                'slug' => new external_value(PARAM_TEXT, 'Preferred slug', VALUE_OPTIONAL),
                'summary' => new external_value(PARAM_RAW, 'Summary', VALUE_OPTIONAL),
                'contenttype' => new external_value(PARAM_ALPHANUMEXT, 'Content type', VALUE_OPTIONAL),
                'authoritylevel' => new external_value(PARAM_INT, 'Authority level (1-5)', VALUE_OPTIONAL),
                'criticality' => new external_value(PARAM_ALPHANUMEXT, 'Criticality', VALUE_OPTIONAL),
                'responsiblearea' => new external_value(PARAM_TEXT, 'Responsible area', VALUE_OPTIONAL),
                'reviewdate' => new external_value(PARAM_INT, 'Review date (unix time)', VALUE_OPTIONAL),
                'requiredreading' => new external_value(PARAM_BOOL, 'Required-reading flag', VALUE_OPTIONAL),
                'language' => new external_value(PARAM_ALPHANUMEXT, 'Language code', VALUE_OPTIONAL),
            ], 'New page fiche and content'),
        ]);
    }

    /**
     * Stage the new-page proposal.
     *
     * @param int $changesetid Change-set id.
     * @param string $tempkey Stable id within the change set.
     * @param array $page New-page fields.
     * @return array
     */
    public static function execute(int $changesetid, string $tempkey, array $page): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'changesetid' => $changesetid,
            'tempkey' => $tempkey,
            'page' => $page,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_write($context);

        return changeset_service::upsert_new_page($params['changesetid'],
            $params['tempkey'], $params['page']);
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
