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

/**
 * External function: propose a page metadata (fiche) patch inside a change set.
 *
 * Draft authority only (spec 5, 17.3): the patch is staged as a change-set
 * item and is applied to the page row solely by the human-gated publish path.
 * Only the fields provided are proposed; omitted fields keep their published
 * value. There is no approve/publish/apply function.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upsert_changeset_metadata extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Page id or slug'),
            'metadata' => new external_single_structure([
                'title' => new external_value(PARAM_TEXT, 'Proposed title', VALUE_OPTIONAL),
                'summary' => new external_value(PARAM_RAW, 'Proposed summary', VALUE_OPTIONAL),
                'contenttype' => new external_value(PARAM_ALPHANUMEXT, 'Proposed content type', VALUE_OPTIONAL),
                'authoritylevel' => new external_value(PARAM_INT, 'Proposed authority level (1-5)', VALUE_OPTIONAL),
                'criticality' => new external_value(PARAM_ALPHANUMEXT, 'Proposed criticality', VALUE_OPTIONAL),
                'responsiblearea' => new external_value(PARAM_TEXT, 'Proposed responsible area', VALUE_OPTIONAL),
                'reviewdate' => new external_value(PARAM_INT, 'Proposed review date (unix time)', VALUE_OPTIONAL),
                'requiredreading' => new external_value(PARAM_BOOL, 'Proposed required-reading flag', VALUE_OPTIONAL),
            ], 'Partial fiche patch; include only the fields to change'),
            'expectedtimemodified' => new external_value(PARAM_INT,
                'Page timemodified the patch was based on (0 = skip the concurrency check)',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Stage the metadata proposal.
     *
     * @param int $changesetid Change-set id.
     * @param string $identifier Page id or slug.
     * @param array $metadata Partial fiche patch.
     * @param int $expectedtimemodified Concurrency token.
     * @return array
     */
    public static function execute(int $changesetid, string $identifier, array $metadata,
            int $expectedtimemodified = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'changesetid' => $changesetid,
            'identifier' => $identifier,
            'metadata' => $metadata,
            'expectedtimemodified' => $expectedtimemodified,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_propose_metadata($context);

        $page = helper::get_page_by_identifier($params['identifier']);
        helper::assert_writable($page);

        // Keep only the supported fields the caller actually supplied; the
        // service validates and normalises each value.
        $patch = [];
        foreach (changeset_service::metadata_fields() as $field) {
            if (array_key_exists($field, $params['metadata'])) {
                $patch[$field] = $params['metadata'][$field];
            }
        }

        return changeset_service::upsert_metadata($params['changesetid'], (int)$page->id,
            $patch, $params['expectedtimemodified']);
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
