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
 * External function: propose restoring an archived page in a change set (26).
 *
 * Draft authority only: the page becomes visible again solely by the human
 * publish path.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upsert_changeset_restore extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Archived page id or slug to restore'),
            'note' => new external_value(PARAM_RAW, 'Optional explanation', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Stage the restore proposal.
     *
     * @param int $changesetid Change-set id.
     * @param string $identifier Page id or slug.
     * @param string $note Explanation.
     * @return array
     */
    public static function execute(int $changesetid, string $identifier, string $note = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'changesetid' => $changesetid,
            'identifier' => $identifier,
            'note' => $note,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_propose_lifecycle($context);

        $page = helper::get_page_by_identifier($params['identifier']);
        helper::assert_not_excluded($page);

        return changeset_service::upsert_page_restore($params['changesetid'],
            (int)$page->id, $params['note']);
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
