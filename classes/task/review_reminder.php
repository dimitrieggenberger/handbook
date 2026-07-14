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

namespace local_handbook\task;

use local_handbook\local\service\notification_service;

/**
 * Weekly reminder to page owners: review date within 30 days or overdue.
 *
 * Runs weekly, so owners are reminded at most once a week without needing
 * per-page notification bookkeeping.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_reminder extends \core\task\scheduled_task {

    /**
     * Task name for the admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_reviewreminder', 'local_handbook');
    }

    /**
     * Send the reminders.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $horizon = time() + 30 * DAYSECS;

        $pages = $DB->get_records_select('local_handbook_page',
            'archived = 0 AND publishedrevisionid > 0 AND owneruserid > 0
             AND reviewdate > 0 AND reviewdate < :horizon',
            ['horizon' => $horizon], 'reviewdate ASC');

        foreach ($pages as $page) {
            notification_service::review_due($page);
        }

        mtrace('local_handbook: review reminders sent for ' . count($pages) . ' page(s).');
    }
}
