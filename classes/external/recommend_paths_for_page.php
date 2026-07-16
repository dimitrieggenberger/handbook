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
use local_handbook\local\service\recommendation_service;

/**
 * External function: deterministic path candidates for a page (spec 10.2, 11.3).
 *
 * Read-only. Returns which active paths a page is a good candidate for, from
 * typed relations and category — nothing is persisted or changed. Use
 * create_path_recommendation to record one for human triage.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recommend_paths_for_page extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Page id or slug'),
        ]);
    }

    /**
     * Return path candidates for a page.
     *
     * @param string $identifier Page id or slug.
     * @return array
     */
    public static function execute(string $identifier): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['identifier' => $identifier]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $page = helper::get_page_by_identifier($params['identifier']);
        helper::assert_not_excluded($page);

        $out = [];
        foreach (recommendation_service::candidates_for_page((int)$page->id) as $candidate) {
            $out[] = [
                'pathid' => (int)$candidate->pathid,
                'pathname' => (string)$candidate->pathname,
                'rectype' => (string)$candidate->rectype,
                'confidence' => (string)$candidate->confidence,
                'rationale' => (string)$candidate->rationale,
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
            'pathid' => new external_value(PARAM_INT, 'Candidate path id'),
            'pathname' => new external_value(PARAM_TEXT, 'Candidate path name'),
            'rectype' => new external_value(PARAM_ALPHANUMEXT, 'Recommendation type (add)'),
            'confidence' => new external_value(PARAM_ALPHANUMEXT, 'low, medium or high'),
            'rationale' => new external_value(PARAM_RAW, 'Why this path is a candidate'),
        ]));
    }
}
