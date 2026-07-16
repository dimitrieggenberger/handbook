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
 * External function: propose moving a page to another category (spec 4.1).
 *
 * Draft authority only: the page id, slug, revisions, acknowledgements and
 * relations are preserved; only categoryid changes, applied by the human
 * publish path. Pass expectedcategoryid / expectedpagetimemodified for
 * optimistic concurrency.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upsert_changeset_page_move extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Page id or slug'),
            'targetcategoryid' => new external_value(PARAM_INT, 'Destination category id', VALUE_DEFAULT, 0),
            'targetcategorytempkey' => new external_value(PARAM_TEXT,
                'Tempkey of a category proposed in this set (instead of targetcategoryid)',
                VALUE_DEFAULT, ''),
            'expectedcategoryid' => new external_value(PARAM_INT,
                'Category the page was in when read (0 = skip the check)', VALUE_DEFAULT, 0),
            'expectedpagetimemodified' => new external_value(PARAM_INT,
                'Page timemodified when read (0 = skip the check)', VALUE_DEFAULT, 0),
            'changesummary' => new external_value(PARAM_RAW, 'Optional summary', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Stage the page-move proposal.
     *
     * @param int $changesetid Change-set id.
     * @param string $identifier Page id or slug.
     * @param int $targetcategoryid Destination category id.
     * @param int $expectedcategoryid Concurrency token.
     * @param int $expectedpagetimemodified Concurrency token.
     * @param string $changesummary Summary.
     * @return array
     */
    public static function execute(int $changesetid, string $identifier, int $targetcategoryid = 0,
            string $targetcategorytempkey = '', int $expectedcategoryid = 0,
            int $expectedpagetimemodified = 0, string $changesummary = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'changesetid' => $changesetid,
            'identifier' => $identifier,
            'targetcategoryid' => $targetcategoryid,
            'targetcategorytempkey' => $targetcategorytempkey,
            'expectedcategoryid' => $expectedcategoryid,
            'expectedpagetimemodified' => $expectedpagetimemodified,
            'changesummary' => $changesummary,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_propose_taxonomy($context);

        $page = helper::get_page_by_identifier($params['identifier']);
        helper::assert_not_excluded($page);

        return changeset_service::upsert_page_move($params['changesetid'], (int)$page->id,
            $params['targetcategoryid'], $params['expectedcategoryid'],
            $params['expectedpagetimemodified'], $params['changesummary'], 0,
            $params['targetcategorytempkey']);
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
