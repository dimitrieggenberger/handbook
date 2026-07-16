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

/**
 * External function: the handbook content style guide (the "hb-*" patterns).
 *
 * Read-only. Returns the same catalogue the editor style guide shows, so a
 * drafting agent formats new content with the house patterns (hb-steps,
 * hb-note, hb-org, ...) and the manual looks uniform. Advisory formatting
 * only — it grants no authority and changes nothing.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_style_guide extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return the pattern catalogue.
     *
     * @return array
     */
    public static function execute(): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/handbook/locallib.php');

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $out = [];
        foreach (local_handbook_style_patterns() as $pattern) {
            $out[] = [
                'key' => $pattern->key,
                'title' => $pattern->title,
                'whenuse' => $pattern->whenuse,
                'html' => $pattern->html,
            ];
        }
        return $out;
    }

    /**
     * Return definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'key' => new external_value(PARAM_ALPHANUMEXT, 'Stable pattern key'),
            'title' => new external_value(PARAM_TEXT, 'Human-readable name'),
            'whenuse' => new external_value(PARAM_TEXT, 'When to use this pattern'),
            'html' => new external_value(PARAM_RAW, 'Example HTML to adapt (uses the hb-* classes)'),
        ]));
    }
}
