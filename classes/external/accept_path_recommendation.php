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
use local_handbook\local\service\recommendation_service;

/**
 * External function: accept a recommendation into a change set as a draft
 * reading-path revision (spec 10.5, 11.2).
 *
 * Draft authority only: the active path is NOT modified. The recommendation is
 * applied to a copy of the path and staged as a reading-path change item, which
 * a human then reviews, approves and publishes. Requires the reading-path
 * proposal capability.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accept_path_recommendation extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'recommendationid' => new external_value(PARAM_INT, 'Recommendation id'),
            'changesetid' => new external_value(PARAM_INT, 'Change set to draft the revision into'),
        ]);
    }

    /**
     * Draft the accepted recommendation into the change set.
     *
     * @param int $recommendationid Recommendation id.
     * @param int $changesetid Change-set id.
     * @return array
     */
    public static function execute(int $recommendationid, int $changesetid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'recommendationid' => $recommendationid,
            'changesetid' => $changesetid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_propose_paths($context);

        return recommendation_service::accept_into_changeset(
            $params['recommendationid'], $params['changesetid']);
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
