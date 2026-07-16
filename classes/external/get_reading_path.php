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
 * External function: fetch a reading path's complete snapshot (spec 7.2).
 *
 * Read-only. Returns the path in the shape upsert_changeset_reading_path
 * accepts, plus timemodified for optimistic concurrency, so an agent can base
 * an edit on the current state without guessing the item order.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_reading_path extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid' => new external_value(PARAM_INT, 'Path id'),
        ]);
    }

    /**
     * Return a path snapshot.
     *
     * @param int $pathid Path id.
     * @return array
     */
    public static function execute(int $pathid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['pathid' => $pathid]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $snapshot = changeset_service::reading_path_snapshot($params['pathid']);

        // Flatten sections into an ordered list carrying section names, which is
        // simpler for a caller to read and echo back.
        $sections = [];
        foreach ($snapshot['sections'] as $section) {
            $items = [];
            foreach ($section['items'] as $it) {
                $items[] = [
                    'pageid' => (int)$it['pageid'],
                    'required' => (bool)$it['required'],
                    'rationale' => (string)$it['rationale'],
                    'quizcmid' => (int)$it['quizcmid'],
                ];
            }
            $sections[] = ['name' => (string)$section['name'], 'items' => $items];
        }

        return [
            'pathid' => (int)$snapshot['pathid'],
            'name' => (string)$snapshot['name'],
            'slug' => (string)$snapshot['slug'],
            'description' => (string)$snapshot['description'],
            'pathtype' => (string)$snapshot['pathtype'],
            'schoolyear' => (string)$snapshot['schoolyear'],
            'active' => (bool)$snapshot['active'],
            'reviewdate' => (int)$snapshot['reviewdate'],
            'estimatedminutes' => (int)$snapshot['estimatedminutes'],
            'audiencecohorts' => array_map('intval', $snapshot['audiencecohorts']),
            'audienceroles' => array_map('intval', $snapshot['audienceroles']),
            'timemodified' => (int)$snapshot['timemodified'],
            'sections' => $sections,
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pathid' => new external_value(PARAM_INT, 'Path id'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'slug' => new external_value(PARAM_ALPHANUMEXT, 'Slug'),
            'description' => new external_value(PARAM_RAW, 'Description'),
            'pathtype' => new external_value(PARAM_ALPHANUMEXT, 'Path type key (empty = unset)'),
            'schoolyear' => new external_value(PARAM_TEXT, 'School year (empty = evergreen)'),
            'active' => new external_value(PARAM_BOOL, 'Active'),
            'reviewdate' => new external_value(PARAM_INT, 'Next review date (0 = unset)'),
            'estimatedminutes' => new external_value(PARAM_INT, 'Estimated minutes (0 = unset)'),
            'audiencecohorts' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Cohort id'), 'Audience cohorts (empty = everyone)'),
            'audienceroles' => new external_multiple_structure(
                new external_value(PARAM_INT, 'System role id'), 'Audience roles (empty = everyone)'),
            'timemodified' => new external_value(PARAM_INT,
                'Last modification time; pass back as expectedtimemodified on update'),
            'sections' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Section name (empty = default section)'),
                'items' => new external_multiple_structure(new external_single_structure([
                    'pageid' => new external_value(PARAM_INT, 'Page id'),
                    'required' => new external_value(PARAM_BOOL, 'Required item'),
                    'rationale' => new external_value(PARAM_RAW, 'Why this page belongs in the path'),
                    'quizcmid' => new external_value(PARAM_INT, 'Linked quiz cmid (0 = none)'),
                ])),
            ])),
        ]);
    }
}
