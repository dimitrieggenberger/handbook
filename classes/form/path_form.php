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
 * Reading path create/edit form.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_form extends \moodleform {

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('pathname', 'local_handbook'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'schoolyear', get_string('schoolyear', 'local_handbook'),
            ['size' => 20]);
        $mform->setType('schoolyear', PARAM_TEXT);

        $mform->addElement('textarea', 'description',
            get_string('categorydescription', 'local_handbook'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'active', get_string('active'));
        $mform->setDefault('active', 1);

        // Path-level optionality: labels the path as recommended reading
        // everywhere it appears, instead of expected reading.
        $mform->addElement('advcheckbox', 'optionalpath',
            get_string('optionalpath', 'local_handbook'));
        $mform->addHelpButton('optionalpath', 'optionalpath', 'local_handbook');
        $mform->setDefault('optionalpath', 0);

        // Audience (spec 15.3): cohorts and/or system roles; empty = all staff.
        $cohortoptions = [];
        foreach ($this->_customdata['cohorts'] ?? [] as $cohort) {
            $cohortoptions[(int)$cohort->id] = format_string($cohort->name);
        }
        $cohortselect = $mform->addElement('select', 'audiencecohorts',
            get_string('pathcohorts', 'local_handbook'), $cohortoptions, ['size' => 6]);
        $cohortselect->setMultiple(true);
        $mform->addHelpButton('audiencecohorts', 'pathaudience', 'local_handbook');

        $roleoptions = [];
        foreach ($this->_customdata['roles'] ?? [] as $role) {
            $roleoptions[(int)$role->id] = $role->localname ?? $role->shortname;
        }
        $roleselect = $mform->addElement('select', 'audienceroles',
            get_string('pathroles', 'local_handbook'), $roleoptions, ['size' => 6]);
        $roleselect->setMultiple(true);

        $this->add_action_buttons();
    }
}
