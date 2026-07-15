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
 * Category create/edit form.
 *
 * Custom data:
 * - parents: array of categoryid => name (0 = top level).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_form extends \moodleform {

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('categoryname', 'local_handbook'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'slug', get_string('categoryslug', 'local_handbook'), ['size' => 60]);
        $mform->setType('slug', PARAM_TEXT);

        $mform->addElement('select', 'parentid', get_string('categoryparent', 'local_handbook'),
            $this->_customdata['parents']);

        $mform->addElement('textarea', 'description', get_string('categorydescription', 'local_handbook'),
            ['rows' => 3, 'cols' => 70]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'visible', get_string('categoryvisible', 'local_handbook'));
        $mform->setDefault('visible', 1);

        $mform->addElement('text', 'icon', get_string('categoryicon', 'local_handbook'),
            ['size' => 30, 'placeholder' => 'fa-folder-open']);
        $mform->setType('icon', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('icon', 'categoryicon', 'local_handbook');

        $mform->addElement('text', 'sortorder', get_string('order', 'core'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons();
    }
}
