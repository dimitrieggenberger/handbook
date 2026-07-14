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

/**
 * Message providers for local_handbook (specification 21.3).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // A draft was submitted for review (to reviewers).
    'draftsubmitted' => [
        'capability' => 'local/handbook:review',
    ],

    // Changes were requested on the user's draft (to the author).
    'changesrequested' => [],

    // A quality finding was created (to findings managers).
    'findingcreated' => [
        'capability' => 'local/handbook:managefindings',
    ],

    // A page's review date is due or overdue (to the page owner).
    'reviewdue' => [],
];
