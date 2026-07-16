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
 * External function: handbook-wide reading-path audit (spec 10.4).
 *
 * Read-only. Returns deterministic advisory findings (orphaned required pages,
 * paths past review, paths with no required item, oversized paths). Persists
 * nothing and edits no path.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_reading_paths extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return audit findings.
     *
     * @return array
     */
    public static function execute(): array {
        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $out = [];
        foreach (recommendation_service::audit() as $finding) {
            $out[] = [
                'kind' => (string)$finding->kind,
                'severity' => (string)$finding->severity,
                'pathid' => (int)$finding->pathid,
                'pathname' => (string)$finding->pathname,
                'pageid' => (int)$finding->pageid,
                'pagetitle' => (string)$finding->pagetitle,
                'message' => (string)$finding->message,
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
            'kind' => new external_value(PARAM_ALPHANUMEXT,
                'orphan_required, path_review_due, path_no_required, path_oversized'),
            'severity' => new external_value(PARAM_ALPHANUMEXT, 'low, medium or high'),
            'pathid' => new external_value(PARAM_INT, 'Path id (0 = none)'),
            'pathname' => new external_value(PARAM_TEXT, 'Path name'),
            'pageid' => new external_value(PARAM_INT, 'Page id (0 = none)'),
            'pagetitle' => new external_value(PARAM_TEXT, 'Page title'),
            'message' => new external_value(PARAM_TEXT, 'Human-readable finding'),
        ]));
    }
}
