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

use local_handbook\local\service\finding_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Report-a-problem form (specification 12.2; report dialog mockup).
 *
 * Custom data:
 * - page: the page record being reported.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_form extends \moodleform {

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $types = [];
        foreach (finding_service::report_types() as $type) {
            $types[$type] = get_string('findingtype_' . $type, 'local_handbook');
        }
        $mform->addElement('select', 'findingtype', get_string('problemtype', 'local_handbook'),
            $types);

        $mform->addElement('text', 'anchor', get_string('affectedsection', 'local_handbook'),
            ['size' => 60]);
        $mform->setType('anchor', PARAM_TEXT);

        $mform->addElement('textarea', 'description', get_string('problemdescription', 'local_handbook'),
            ['rows' => 5, 'cols' => 70, 'placeholder' => get_string('reportplaceholder', 'local_handbook')]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addRule('description', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('sendreport', 'local_handbook'));
    }

    /**
     * Server-side validation.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim($data['description'] ?? '') === '') {
            $errors['description'] = get_string('required');
        }
        return $errors;
    }
}
