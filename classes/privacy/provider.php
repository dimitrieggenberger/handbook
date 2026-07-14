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

namespace local_handbook\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider for local_handbook.
 *
 * The plugin stores editorial attribution (who created, modified, reviewed,
 * approved or published institutional content). This attribution is part of
 * the institution's editorial audit record. Acknowledgement data arrives in
 * a later phase and will extend this provider with export/delete handling.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {

    /**
     * Describe the personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection to extend.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_handbook_category', [
            'createdby' => 'privacy:metadata:local_handbook_category',
            'modifiedby' => 'privacy:metadata:local_handbook_category',
        ], 'privacy:metadata:local_handbook_category');

        $collection->add_database_table('local_handbook_page', [
            'owneruserid' => 'privacy:metadata:local_handbook_page:owneruserid',
            'approveruserid' => 'privacy:metadata:local_handbook_page',
            'createdby' => 'privacy:metadata:local_handbook_page',
            'modifiedby' => 'privacy:metadata:local_handbook_page',
        ], 'privacy:metadata:local_handbook_page');

        $collection->add_database_table('local_handbook_revision', [
            'createdby' => 'privacy:metadata:local_handbook_revision:createdby',
            'modifiedby' => 'privacy:metadata:local_handbook_revision:modifiedby',
            'reviewedby' => 'privacy:metadata:local_handbook_revision',
            'approvedby' => 'privacy:metadata:local_handbook_revision',
            'publishedby' => 'privacy:metadata:local_handbook_revision:publishedby',
        ], 'privacy:metadata:local_handbook_revision');

        return $collection;
    }
}
