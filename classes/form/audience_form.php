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
 * Audience create/edit form (multi-audience handbook, phase 1).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience_form extends \moodleform {

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('audiencename', 'local_handbook'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'audiencekey', get_string('audiencekey', 'local_handbook'), ['size' => 24]);
        $mform->setType('audiencekey', PARAM_ALPHANUMEXT);
        $mform->addRule('audiencekey', null, 'required', null, 'client');

        $mform->addElement('select', 'matchtype', get_string('audiencematchtype', 'local_handbook'), [
            'profile' => get_string('audiencematch_profile', 'local_handbook'),
            'staff' => get_string('audiencematch_staff', 'local_handbook'),
        ]);

        $mform->addElement('text', 'profilefield',
            get_string('audienceprofilefield', 'local_handbook'), ['size' => 20]);
        $mform->setType('profilefield', PARAM_ALPHANUMEXT);
        $mform->setDefault('profilefield', 'city');
        $mform->hideIf('profilefield', 'matchtype', 'eq', 'staff');

        $mform->addElement('text', 'profilevalue',
            get_string('audienceprofilevalue', 'local_handbook'), ['size' => 30]);
        $mform->setType('profilevalue', PARAM_TEXT);
        $mform->hideIf('profilevalue', 'matchtype', 'eq', 'staff');

        $mform->addElement('text', 'colorhex', get_string('audiencecolor', 'local_handbook'),
            ['size' => 8, 'placeholder' => '#0078c3']);
        $mform->setType('colorhex', PARAM_TEXT);

        $mform->addElement('text', 'sortorder', get_string('holdersortorder', 'local_handbook'), ['size' => 4]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $mform->addElement('advcheckbox', 'active', get_string('audienceactive', 'local_handbook'));
        $mform->setDefault('active', 1);

        $this->add_action_buttons();
    }

    /**
     * Server-side validation.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Field => error message.
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $key = trim($data['audiencekey'] ?? '');
        if ($key !== '') {
            $clash = $DB->get_record('local_handbook_audience', ['audiencekey' => $key]);
            if ($clash && (int)$clash->id !== (int)($data['id'] ?? 0)) {
                $errors['audiencekey'] = get_string('audiencekeytaken', 'local_handbook');
            }
        }

        $color = trim($data['colorhex'] ?? '');
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $errors['colorhex'] = get_string('audiencecolorinvalid', 'local_handbook');
        }

        return $errors;
    }
}
