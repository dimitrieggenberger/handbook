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
use local_handbook\local\service\changeset_service;

/**
 * External function: propose edits to a page's outgoing relations (spec 10).
 *
 * Draft authority only: relation rows change exclusively via the human publish
 * path. A target is an existing page (by slug or id) or a new page proposed in
 * the same change set (by tempkey). There is no approve/publish/apply function.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upsert_changeset_relations extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Source page id or slug'),
            'operations' => new external_multiple_structure(new external_single_structure([
                'op' => new external_value(PARAM_ALPHA, 'create or remove'),
                'relationtype' => new external_value(PARAM_ALPHANUMEXT, 'Relation type'),
                'target' => new external_value(PARAM_ALPHANUMEXT,
                    'Target page id or slug (omit when using tempkey)', VALUE_OPTIONAL),
                'targettempkey' => new external_value(PARAM_TEXT,
                    'Tempkey of a new page proposed in this change set', VALUE_OPTIONAL),
            ]), 'Relation operations'),
        ]);
    }

    /**
     * Stage the relation proposal.
     *
     * @param int $changesetid Change-set id.
     * @param string $identifier Source page id or slug.
     * @param array $operations Relation operations.
     * @return array
     */
    public static function execute(int $changesetid, string $identifier, array $operations): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'changesetid' => $changesetid,
            'identifier' => $identifier,
            'operations' => $operations,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_propose_relations($context);

        $source = helper::get_page_by_identifier($params['identifier']);
        helper::assert_not_excluded($source);

        // Resolve each operation's target page identifier to a page id; a
        // tempkey (new page in this set) is passed through for apply-time
        // resolution.
        $ops = [];
        foreach ($params['operations'] as $op) {
            $targetpageid = 0;
            if (!empty($op['target'])) {
                $targetpageid = (int)helper::get_page_by_identifier($op['target'])->id;
            }
            $ops[] = [
                'op' => $op['op'],
                'relationtype' => $op['relationtype'],
                'targetpageid' => $targetpageid,
                'targettempkey' => $op['targettempkey'] ?? '',
            ];
        }

        return changeset_service::upsert_relations($params['changesetid'], (int)$source->id, $ops);
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
