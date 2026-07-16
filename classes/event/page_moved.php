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

namespace local_handbook\event;

/**
 * Event: a handbook page was moved to another category (spec 4.1).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_moved extends \core\event\base {

    /**
     * Initialise event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_handbook_page';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_page_moved', 'local_handbook');
    }

    /**
     * Event description for logs.
     *
     * @return string
     */
    public function get_description() {
        $from = (int)($this->other['fromcategoryid'] ?? 0);
        $to = (int)($this->other['tocategoryid'] ?? 0);
        return "The user with id '{$this->userid}' moved the handbook page with id "
            . "'{$this->objectid}' from category '{$from}' to category '{$to}'.";
    }

    /**
     * Relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/handbook/view.php', ['page' => $this->objectid]);
    }
}
