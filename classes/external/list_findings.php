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
 * External function: list quality findings (specification 17.2, 19).
 *
 * Findings whose affected pages are all AI-excluded are omitted.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_findings extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'status' => new external_value(PARAM_ALPHAEXT,
                'Filter by status; empty = open and under_review', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Page size (max 200)', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * List findings.
     *
     * @param string $status Status filter.
     * @param int $page Zero-based page number.
     * @param int $perpage Page size.
     * @return array
     */
    public static function execute(string $status = '', int $page = 0, int $perpage = 50): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'status' => $status,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        [$limitfrom, $limitnum] = helper::paginate($params['page'], $params['perpage']);

        if ($params['status'] !== '' && in_array($params['status'], finding_service::statuses(), true)) {
            $where = 'status = :status';
            $sqlparams = ['status' => $params['status']];
        } else {
            $where = 'status IN (:s1, :s2)';
            $sqlparams = ['s1' => finding_service::STATUS_OPEN,
                's2' => finding_service::STATUS_UNDER_REVIEW];
        }

        $findings = $DB->get_records_select('local_handbook_finding', $where, $sqlparams,
            'timemodified DESC', '*', $limitfrom, $limitnum);

        $result = [];
        foreach ($findings as $finding) {
            $pages = [];
            $allexcluded = null;
            foreach (finding_service::get_pages((int)$finding->id) as $findpage) {
                $pagerecord = $DB->get_record('local_handbook_page', ['id' => $findpage->pageid],
                    'id, aiaccess');
                $isexcluded = $pagerecord && $pagerecord->aiaccess === 'excluded';
                $allexcluded = ($allexcluded ?? true) && $isexcluded;
                if ($isexcluded) {
                    continue;
                }
                $pages[] = [
                    'pageid' => (int)$findpage->pageid,
                    'slug' => $findpage->slug,
                    'title' => $findpage->title,
                    'revisionid' => (int)$findpage->revisionid,
                    'anchor' => $findpage->anchor,
                    'excerpt' => (string)$findpage->excerpt,
                ];
            }
            if ($allexcluded === true) {
                continue;
            }

            $result[] = [
                'id' => (int)$finding->id,
                'findingtype' => $finding->findingtype,
                'severity' => $finding->severity,
                'confidence' => $finding->confidence,
                'status' => $finding->status,
                'summary' => $finding->summary,
                'explanation' => (string)$finding->explanation,
                'recommendation' => (string)$finding->recommendation,
                'source' => $finding->source,
                'resolutionnote' => (string)$finding->resolutionnote,
                'timecreated' => (int)$finding->timecreated,
                'timemodified' => (int)$finding->timemodified,
                'pages' => $pages,
            ];
        }
        return $result;
    }

    /**
     * Return definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Finding id'),
            'findingtype' => new external_value(PARAM_ALPHAEXT, 'Finding type key'),
            'severity' => new external_value(PARAM_ALPHA, 'Severity'),
            'confidence' => new external_value(PARAM_ALPHA, 'Confidence'),
            'status' => new external_value(PARAM_ALPHAEXT, 'Status'),
            'summary' => new external_value(PARAM_RAW, 'Summary'),
            'explanation' => new external_value(PARAM_RAW, 'Explanation'),
            'recommendation' => new external_value(PARAM_RAW, 'Recommended action'),
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Source'),
            'resolutionnote' => new external_value(PARAM_RAW, 'Resolution note'),
            'timecreated' => new external_value(PARAM_INT, 'Creation time'),
            'timemodified' => new external_value(PARAM_INT, 'Last modification time'),
            'pages' => new external_multiple_structure(new external_single_structure([
                'pageid' => new external_value(PARAM_INT, 'Page id'),
                'slug' => new external_value(PARAM_ALPHANUMEXT, 'Page slug'),
                'title' => new external_value(PARAM_TEXT, 'Page title'),
                'revisionid' => new external_value(PARAM_INT, 'Revision the finding refers to'),
                'anchor' => new external_value(PARAM_TEXT, 'Heading anchor or section'),
                'excerpt' => new external_value(PARAM_RAW, 'Relevant excerpt'),
            ])),
        ]));
    }
}
