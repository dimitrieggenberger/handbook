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
 * External function: propose a category operation in a change set (spec 11).
 *
 * Draft authority only: the category is created/updated/moved/merged solely by
 * the human publish path. Validation (unique slug at apply, no cycles, no
 * orphaned pages via merge) happens in the service.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upsert_changeset_category extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
            'operation' => new external_single_structure([
                'op' => new external_value(PARAM_ALPHA, 'create, update, move, merge or delete_empty'),
                'tempkey' => new external_value(PARAM_TEXT, 'Stable id for a new category', VALUE_OPTIONAL),
                'name' => new external_value(PARAM_TEXT, 'Category name', VALUE_OPTIONAL),
                'slug' => new external_value(PARAM_TEXT, 'Preferred slug (create/update; old slug kept)', VALUE_OPTIONAL),
                'parentid' => new external_value(PARAM_INT, 'Parent category id (create)', VALUE_OPTIONAL),
                'parenttempkey' => new external_value(PARAM_TEXT,
                    'Tempkey of a parent category proposed in this set (create)', VALUE_OPTIONAL),
                'newparenttempkey' => new external_value(PARAM_TEXT,
                    'Tempkey of a new parent category proposed in this set (move)', VALUE_OPTIONAL),
                'description' => new external_value(PARAM_RAW, 'Description', VALUE_OPTIONAL),
                'icon' => new external_value(PARAM_ALPHANUMEXT, 'Font Awesome icon (fa-*)', VALUE_OPTIONAL),
                'visible' => new external_value(PARAM_BOOL, 'Visible', VALUE_OPTIONAL),
                'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_OPTIONAL),
                'categoryid' => new external_value(PARAM_INT, 'Category id (update/move)', VALUE_OPTIONAL),
                'newparentid' => new external_value(PARAM_INT, 'New parent id (move)', VALUE_OPTIONAL),
                'sourceid' => new external_value(PARAM_INT, 'Source category id (merge)', VALUE_OPTIONAL),
                'targetid' => new external_value(PARAM_INT, 'Target category id (merge)', VALUE_OPTIONAL),
            ], 'Category operation'),
        ]);
    }

    /**
     * Stage the category proposal.
     *
     * @param int $changesetid Change-set id.
     * @param array $operation Category operation.
     * @return array
     */
    public static function execute(int $changesetid, array $operation): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'changesetid' => $changesetid,
            'operation' => $operation,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_propose_taxonomy($context);

        return changeset_service::upsert_category($params['changesetid'], $params['operation']);
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
