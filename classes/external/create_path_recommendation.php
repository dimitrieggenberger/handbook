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
 * External function: record a reading-path recommendation for human triage
 * (spec 10.3, 11.3).
 *
 * Advisory only: it creates a pending recommendation record. It never alters an
 * active path; a human triages it and, if accepted, it drafts a change-set
 * proposal. Recorded with source "ai".
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_path_recommendation extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid' => new external_value(PARAM_INT, 'Target path id'),
            'identifier' => new external_value(PARAM_ALPHANUMEXT,
                'Article id or slug (empty for a path-level recommendation)', VALUE_DEFAULT, ''),
            'rectype' => new external_value(PARAM_ALPHANUMEXT,
                'add, remove, reorder, replace, split_path, merge_paths, update_required_status',
                VALUE_DEFAULT, 'add'),
            'confidence' => new external_value(PARAM_ALPHANUMEXT, 'low, medium or high',
                VALUE_DEFAULT, 'medium'),
            'rationale' => new external_value(PARAM_RAW, 'Why this is recommended', VALUE_DEFAULT, ''),
            'suggestedsection' => new external_value(PARAM_TEXT, 'Suggested section', VALUE_DEFAULT, ''),
            'suggestedrequired' => new external_value(PARAM_BOOL, 'Suggested required flag',
                VALUE_DEFAULT, true),
            'suggestedafterpageid' => new external_value(PARAM_INT,
                'Place after this page (0 = end)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Record the recommendation.
     *
     * @param int $pathid Path id.
     * @param string $identifier Article id or slug.
     * @param string $rectype Recommendation type.
     * @param string $confidence Confidence.
     * @param string $rationale Rationale.
     * @param string $suggestedsection Suggested section.
     * @param bool $suggestedrequired Suggested required flag.
     * @param int $suggestedafterpageid Ordering hint.
     * @return array
     */
    public static function execute(int $pathid, string $identifier = '', string $rectype = 'add',
            string $confidence = 'medium', string $rationale = '', string $suggestedsection = '',
            bool $suggestedrequired = true, int $suggestedafterpageid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'pathid' => $pathid,
            'identifier' => $identifier,
            'rectype' => $rectype,
            'confidence' => $confidence,
            'rationale' => $rationale,
            'suggestedsection' => $suggestedsection,
            'suggestedrequired' => $suggestedrequired,
            'suggestedafterpageid' => $suggestedafterpageid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $pageid = 0;
        $revisionid = 0;
        if ($params['identifier'] !== '') {
            $page = helper::get_page_by_identifier($params['identifier']);
            helper::assert_not_excluded($page);
            $pageid = (int)$page->id;
            $revisionid = (int)$page->publishedrevisionid;
        }

        $rec = recommendation_service::create((object)[
            'pathid' => $params['pathid'],
            'pageid' => $pageid,
            'revisionid' => $revisionid,
            'rectype' => $params['rectype'],
            'confidence' => $params['confidence'],
            'rationale' => $params['rationale'],
            'suggestedsection' => $params['suggestedsection'],
            'suggestedrequired' => $params['suggestedrequired'],
            'suggestedafterpageid' => $params['suggestedafterpageid'],
            'triggerkind' => 'ai',
            'source' => 'ai',
        ]);

        return helper::export_recommendation($rec);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::recommendation_structure();
    }
}
