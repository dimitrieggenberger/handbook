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
 * External function: propose a whole reading path as a change-set draft (spec 7).
 *
 * Draft authority only: the path and its items are written solely by the human
 * publish path. A complete snapshot is submitted — applying it makes the path
 * match the snapshot exactly. Omit pathid to propose a new path; pass it (with
 * expectedtimemodified for concurrency) to propose an edit. Items reference an
 * existing page (pageid) or a page proposed in the same set (pagetempkey).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upsert_changeset_reading_path extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
            'pathid' => new external_value(PARAM_INT,
                'Existing path id to edit (0 = propose a new path)', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Path name'),
            'slug' => new external_value(PARAM_TEXT, 'Slug (empty = derive from name)', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'pathtype' => new external_value(PARAM_ALPHANUMEXT,
                'Path type: onboarding, calendar_phase, role_based, situational, refresher, compliance',
                VALUE_DEFAULT, ''),
            'schoolyear' => new external_value(PARAM_TEXT, 'School year (empty = evergreen)', VALUE_DEFAULT, ''),
            'active' => new external_value(PARAM_BOOL, 'Active', VALUE_DEFAULT, true),
            'reviewdate' => new external_value(PARAM_INT, 'Next review date (0 = unset)', VALUE_DEFAULT, 0),
            'estimatedminutes' => new external_value(PARAM_INT, 'Estimated minutes (0 = unset)', VALUE_DEFAULT, 0),
            'audiencecohorts' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Cohort id'),
                'Audience cohorts (empty = everyone)', VALUE_DEFAULT, []),
            'audienceroles' => new external_multiple_structure(
                new external_value(PARAM_INT, 'System role id'),
                'Audience roles (empty = everyone)', VALUE_DEFAULT, []),
            'expectedtimemodified' => new external_value(PARAM_INT,
                'Path timemodified when read (0 = skip the check)', VALUE_DEFAULT, 0),
            'sections' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Section name (empty = default section)',
                    VALUE_DEFAULT, ''),
                'items' => new external_multiple_structure(new external_single_structure([
                    'pageid' => new external_value(PARAM_INT, 'Existing page id (0 = use pagetempkey)',
                        VALUE_DEFAULT, 0),
                    'pagetempkey' => new external_value(PARAM_TEXT,
                        'Tempkey of a page proposed in this set (instead of pageid)', VALUE_DEFAULT, ''),
                    'required' => new external_value(PARAM_BOOL, 'Required item', VALUE_DEFAULT, true),
                    'rationale' => new external_value(PARAM_RAW,
                        'Why this page belongs in the path', VALUE_DEFAULT, ''),
                    'quizcmid' => new external_value(PARAM_INT, 'Linked quiz cmid (0 = none)', VALUE_DEFAULT, 0),
                ])),
            ])),
        ]);
    }

    /**
     * Stage the reading-path proposal.
     *
     * @param int $changesetid Change-set id.
     * @param int $pathid Existing path id (0 = new).
     * @param string $name Name.
     * @param string $slug Slug.
     * @param string $description Description.
     * @param string $pathtype Path type key.
     * @param string $schoolyear School year.
     * @param bool $active Active.
     * @param int $reviewdate Review date.
     * @param int $estimatedminutes Estimated minutes.
     * @param array $audiencecohorts Cohort ids.
     * @param array $audienceroles Role ids.
     * @param int $expectedtimemodified Concurrency token.
     * @param array $sections Sections with items.
     * @return array
     */
    public static function execute(int $changesetid, int $pathid = 0, string $name = '',
            string $slug = '', string $description = '', string $pathtype = '',
            string $schoolyear = '', bool $active = true, int $reviewdate = 0,
            int $estimatedminutes = 0, array $audiencecohorts = [], array $audienceroles = [],
            int $expectedtimemodified = 0, array $sections = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'changesetid' => $changesetid,
            'pathid' => $pathid,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'pathtype' => $pathtype,
            'schoolyear' => $schoolyear,
            'active' => $active,
            'reviewdate' => $reviewdate,
            'estimatedminutes' => $estimatedminutes,
            'audiencecohorts' => $audiencecohorts,
            'audienceroles' => $audienceroles,
            'expectedtimemodified' => $expectedtimemodified,
            'sections' => $sections,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_propose_paths($context);

        $data = [
            'pathid' => $params['pathid'],
            'name' => $params['name'],
            'slug' => $params['slug'],
            'description' => $params['description'],
            'pathtype' => $params['pathtype'],
            'schoolyear' => $params['schoolyear'],
            'active' => $params['active'],
            'reviewdate' => $params['reviewdate'],
            'estimatedminutes' => $params['estimatedminutes'],
            'audiencecohorts' => $params['audiencecohorts'],
            'audienceroles' => $params['audienceroles'],
            'expectedtimemodified' => $params['expectedtimemodified'],
            'sections' => $params['sections'],
        ];

        return changeset_service::upsert_reading_path($params['changesetid'], $data);
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
