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
 * External function: get one change set with its items (specification 36.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_changeset extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
        ]);
    }

    /**
     * Get the change set.
     *
     * @param int $changesetid Change-set id.
     * @return array
     */
    public static function execute(int $changesetid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            ['changesetid' => $changesetid]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $changeset = changeset_service::get($params['changesetid']);

        $items = [];
        foreach ($changeset->items as $item) {
            $page = $DB->get_record('local_handbook_page', ['id' => $item->pageid], 'slug, title');
            $items[] = helper::export_changeitem($item,
                $page->slug ?? '', $page->title ?? '');
        }

        $export = helper::export_changeset($changeset, count($changeset->items));
        $export['items'] = $items;
        return $export;
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $fields = helper::changeset_structure()->keys;
        $fields['items'] = new external_multiple_structure(helper::changeitem_structure());
        return new external_single_structure($fields);
    }
}
