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
 * Add/edit one responsible-person assignment of a page.
 *
 * The account picker is a client-side searchable select filled with the
 * site's active users, so it works for every handbook editor regardless
 * of user-search capabilities. Contact fields are deliberately explicit:
 * only what is typed here is shown on the page — the account's own email
 * is never displayed.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class holder_form extends \moodleform {

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'pageid', 0);
        $mform->setType('pageid', PARAM_INT);

        $namefields = implode(', ', \core_user\fields::get_name_fields());
        $users = $DB->get_records_select('user',
            'deleted = 0 AND suspended = 0 AND confirmed = 1 AND id > :guest',
            ['guest' => 1], 'lastname ASC, firstname ASC', 'id, ' . $namefields);
        $options = [];
        foreach ($users as $user) {
            $options[(int)$user->id] = fullname($user);
        }
        $mform->addElement('autocomplete', 'userid',
            get_string('holderuser', 'local_handbook'), $options,
            ['noselectionstring' => get_string('holderuserplaceholder', 'local_handbook')]);
        $mform->addRule('userid', null, 'required', null, 'client');

        $mform->addElement('text', 'rolelabel',
            get_string('holderrolelabel', 'local_handbook'), ['size' => 48]);
        $mform->setType('rolelabel', PARAM_TEXT);
        $mform->addRule('rolelabel', null, 'required', null, 'client');

        $mform->addElement('text', 'contactemail',
            get_string('holdercontactemail', 'local_handbook'), ['size' => 48]);
        $mform->setType('contactemail', PARAM_NOTAGS);

        $mform->addElement('text', 'contactphone',
            get_string('holdercontactphone', 'local_handbook'), ['size' => 48]);
        $mform->setType('contactphone', PARAM_TEXT);

        $mform->addElement('text', 'whatsapp',
            get_string('holderwhatsapp', 'local_handbook'), ['size' => 24]);
        $mform->setType('whatsapp', PARAM_TEXT);

        $mform->addElement('date_selector', 'sincedate',
            get_string('holdersincedate', 'local_handbook'), ['optional' => true]);

        $mform->addElement('text', 'sortorder',
            get_string('holdersortorder', 'local_handbook'), ['size' => 4]);
        $mform->setType('sortorder', PARAM_INT);

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

        $userid = (int)($data['userid'] ?? 0);
        if ($userid <= 1 || !$DB->record_exists_select('user',
                'id = :id AND deleted = 0', ['id' => $userid])) {
            $errors['userid'] = get_string('holderinvaliduser', 'local_handbook');
        }

        if (trim($data['contactemail'] ?? '') !== ''
                && !validate_email(trim($data['contactemail']))) {
            $errors['contactemail'] = get_string('invalidemail');
        }

        $whatsapp = trim($data['whatsapp'] ?? '');
        if ($whatsapp !== '' && strlen(preg_replace('/\D+/', '', $whatsapp)) < 8) {
            $errors['whatsapp'] = get_string('holderinvalidwhatsapp', 'local_handbook');
        }

        return $errors;
    }
}
