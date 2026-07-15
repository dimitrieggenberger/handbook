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
use stdClass;

/**
 * External function: create a change set for grouped draft proposals (36.4).
 *
 * API-created change sets are marked source "ai"; the technical creator is the
 * calling service account. This carries draft authority only — there is no
 * approve or publish operation anywhere in the API.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_changeset extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'title' => new external_value(PARAM_TEXT, 'Short title for the change set'),
            'instructionsummary' => new external_value(PARAM_TEXT,
                'Concise approved instruction summary (never a full transcript)', VALUE_DEFAULT, ''),
            'externalreference' => new external_value(PARAM_TEXT,
                'Optional conversation/task id (never a secret)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Create the change set.
     *
     * @param string $title Title.
     * @param string $instructionsummary Instruction summary.
     * @param string $externalreference External reference.
     * @return array
     */
    public static function execute(string $title, string $instructionsummary = '',
            string $externalreference = ''): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'title' => $title,
            'instructionsummary' => $instructionsummary,
            'externalreference' => $externalreference,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_write($context);

        $data = new stdClass();
        $data->title = $params['title'];
        $data->instructionsummary = $params['instructionsummary'];
        $data->externalreference = $params['externalreference'];
        $data->source = 'ai';

        $changeset = changeset_service::create($data);
        $itemcount = $DB->count_records('local_handbook_changeitem', ['changesetid' => $changeset->id]);

        return helper::export_changeset($changeset, $itemcount);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::changeset_structure();
    }
}
