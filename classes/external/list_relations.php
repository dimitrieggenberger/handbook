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
 * External function: typed relations of a page, both directions.
 *
 * Relations whose other end is AI-excluded are omitted (17.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_relations extends external_api {

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
     * List relations.
     *
     * @param string $identifier Page id or slug.
     * @return array
     */
    public static function execute(string $identifier): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['identifier' => $identifier]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $page = helper::get_page_by_identifier($params['identifier']);
        helper::assert_not_excluded($page);

        $sql = "SELECT rel.id, rel.relationtype, rel.sourcepageid, rel.targetpageid,
                       s.slug AS sourceslug, s.title AS sourcetitle, s.aiaccess AS sourceai,
                       t.slug AS targetslug, t.title AS targettitle, t.aiaccess AS targetai
                  FROM {local_handbook_relation} rel
                  JOIN {local_handbook_page} s ON s.id = rel.sourcepageid
                  JOIN {local_handbook_page} t ON t.id = rel.targetpageid
                 WHERE rel.sourcepageid = :pageid1 OR rel.targetpageid = :pageid2
              ORDER BY rel.sortorder ASC, rel.id ASC";

        $result = [];
        foreach ($DB->get_records_sql($sql, ['pageid1' => $page->id, 'pageid2' => $page->id]) as $rel) {
            if ($rel->sourceai === 'excluded' || $rel->targetai === 'excluded') {
                continue;
            }
            $result[] = [
                'id' => (int)$rel->id,
                'relationtype' => $rel->relationtype,
                'sourcepageid' => (int)$rel->sourcepageid,
                'sourceslug' => $rel->sourceslug,
                'sourcetitle' => $rel->sourcetitle,
                'targetpageid' => (int)$rel->targetpageid,
                'targetslug' => $rel->targetslug,
                'targettitle' => $rel->targettitle,
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
            'id' => new external_value(PARAM_INT, 'Relation id'),
            'relationtype' => new external_value(PARAM_ALPHANUMEXT, 'Relation type key'),
            'sourcepageid' => new external_value(PARAM_INT, 'Source page id'),
            'sourceslug' => new external_value(PARAM_ALPHANUMEXT, 'Source page slug'),
            'sourcetitle' => new external_value(PARAM_TEXT, 'Source page title'),
            'targetpageid' => new external_value(PARAM_INT, 'Target page id'),
            'targetslug' => new external_value(PARAM_ALPHANUMEXT, 'Target page slug'),
            'targettitle' => new external_value(PARAM_TEXT, 'Target page title'),
        ]));
    }
}
