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

namespace local_handbook\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Seed import upload form.
 *
 * Custom data:
 * - canpublish: whether the publish-on-import option is offered (bootstrap
 *   mode enabled and the user holds local/handbook:publish).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'seedfile', get_string('importfile', 'local_handbook'),
            null, ['accepted_types' => ['.json']]);
        $mform->addRule('seedfile', null, 'required');

        if (!empty($this->_customdata['canpublish'])) {
            $mform->addElement('advcheckbox', 'publishonimport',
                get_string('publishonimport', 'local_handbook'));
        }

        $this->add_action_buttons(true, get_string('importseed', 'local_handbook'));
    }
}
