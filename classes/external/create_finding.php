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
use local_handbook\local\service\finding_service;

/**
 * External function: create a quality finding (specification 17.3, 19).
 *
 * AI findings are advisory; they never change page content or status
 * (19.3). Agents should cite the pages and anchors supporting each finding
 * and distinguish confirmed from possible contradictions (18.3) via the
 * confidence field.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_finding extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'findingtype' => new external_value(PARAM_ALPHAEXT, 'Finding type key'),
            'summary' => new external_value(PARAM_RAW, 'One-line summary'),
            'explanation' => new external_value(PARAM_RAW, 'Full explanation', VALUE_DEFAULT, ''),
            'recommendation' => new external_value(PARAM_RAW, 'Recommended action', VALUE_DEFAULT, ''),
            'severity' => new external_value(PARAM_ALPHA, 'low | medium | high', VALUE_DEFAULT, 'medium'),
            'confidence' => new external_value(PARAM_ALPHA, 'low | medium | high', VALUE_DEFAULT, 'medium'),
            'source' => new external_value(PARAM_ALPHANUMEXT,
                'Reporting tool, e.g. claude, chatgpt, codex, audit', VALUE_DEFAULT, 'api'),
            'pages' => new external_multiple_structure(new external_single_structure([
                'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Page id or slug'),
                'anchor' => new external_value(PARAM_TEXT, 'Heading anchor or section',
                    VALUE_DEFAULT, ''),
                'excerpt' => new external_value(PARAM_RAW, 'Relevant excerpt', VALUE_DEFAULT, ''),
            ]), 'Affected pages (at least one)'),
        ]);
    }

    /**
     * Create the finding.
     *
     * @param string $findingtype Finding type key.
     * @param string $summary One-line summary.
     * @param string $explanation Full explanation.
     * @param string $recommendation Recommended action.
     * @param string $severity Severity key.
     * @param string $confidence Confidence key.
     * @param string $source Reporting tool.
     * @param array $pages Affected pages.
     * @return array
     */
    public static function execute(string $findingtype, string $summary, string $explanation = '',
            string $recommendation = '', string $severity = 'medium', string $confidence = 'medium',
            string $source = 'api', array $pages = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'findingtype' => $findingtype,
            'summary' => $summary,
            'explanation' => $explanation,
            'recommendation' => $recommendation,
            'severity' => $severity,
            'confidence' => $confidence,
            'source' => $source,
            'pages' => $pages,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $findingpages = [];
        foreach ($params['pages'] as $pageref) {
            $page = helper::get_page_by_identifier($pageref['identifier']);
            helper::assert_not_excluded($page);
            $findingpages[] = [
                'pageid' => (int)$page->id,
                'revisionid' => (int)$page->publishedrevisionid,
                'anchor' => $pageref['anchor'],
                'excerpt' => $pageref['excerpt'],
            ];
        }

        $finding = finding_service::create((object)[
            'findingtype' => $params['findingtype'],
            'summary' => $params['summary'],
            'explanation' => $params['explanation'],
            'recommendation' => $params['recommendation'],
            'severity' => $params['severity'],
            'confidence' => $params['confidence'],
            'source' => $params['source'],
        ], $findingpages);

        return [
            'id' => (int)$finding->id,
            'status' => $finding->status,
            'timecreated' => (int)$finding->timecreated,
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Finding id'),
            'status' => new external_value(PARAM_ALPHAEXT, 'Initial status (open)'),
            'timecreated' => new external_value(PARAM_INT, 'Creation time'),
        ]);
    }
}
