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
 * Scheduled tasks for local_handbook (specification 21.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    // Weekly reminder to page owners whose review date is near or overdue.
    [
        'classname' => 'local_handbook\task\review_reminder',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '6',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '1',
    ],

    // Daily link checker: dead internal links and quiz cmids become findings.
    [
        'classname' => 'local_handbook\task\link_checker',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '5',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
