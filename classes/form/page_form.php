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

use local_handbook\local\service\page_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Page metadata + draft content form (specification 12.3, first milestone).
 *
 * Custom data:
 * - categories: array of categoryid => name.
 * - page: existing page record or null.
 * - revision: editable draft revision or null.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_form extends \moodleform {

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $categories = $this->_customdata['categories'];
        $page = $this->_customdata['page'];

        $mform->addElement('hidden', 'id', $page->id ?? 0);
        $mform->setType('id', PARAM_INT);

        // Metadata.
        $mform->addElement('text', 'title', get_string('pagetitle', 'local_handbook'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('text', 'slug', get_string('pageslug', 'local_handbook'), ['size' => 60]);
        $mform->setType('slug', PARAM_TEXT);
        $mform->addHelpButton('slug', 'pageslug', 'local_handbook');

        $mform->addElement('select', 'categoryid', get_string('category', 'local_handbook'), $categories);
        $mform->addRule('categoryid', null, 'required', null, 'client');

        $contenttypes = [];
        foreach (page_service::content_types() as $type) {
            $contenttypes[$type] = get_string('contenttype_' . $type, 'local_handbook');
        }
        $mform->addElement('select', 'contenttype', get_string('contenttype', 'local_handbook'), $contenttypes);
        $mform->setDefault('contenttype', 'procedure');

        $authoritylevels = [];
        for ($level = 1; $level <= 6; $level++) {
            $authoritylevels[$level] = get_string('authority_' . $level, 'local_handbook');
        }
        $mform->addElement('select', 'authoritylevel', get_string('authoritylevel', 'local_handbook'),
            $authoritylevels);
        $mform->setDefault('authoritylevel', 4);

        $criticalities = [];
        foreach (page_service::criticalities() as $criticality) {
            $criticalities[$criticality] = get_string('criticality_' . $criticality, 'local_handbook');
        }
        $mform->addElement('select', 'criticality', get_string('criticality', 'local_handbook'), $criticalities);
        $mform->setDefault('criticality', 'operational');

        $mform->addElement('text', 'responsiblearea', get_string('responsiblearea', 'local_handbook'),
            ['size' => 60]);
        $mform->setType('responsiblearea', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'requiredreading', get_string('requiredreading', 'local_handbook'));

        $aiaccesslevels = [];
        foreach (page_service::ai_access_levels() as $level) {
            $aiaccesslevels[$level] = get_string('aiaccess_' . $level, 'local_handbook');
        }
        $mform->addElement('select', 'aiaccess', get_string('aiaccess', 'local_handbook'), $aiaccesslevels);
        $mform->setDefault('aiaccess', 'full');

        $mform->addElement('date_selector', 'reviewdate', get_string('reviewdate', 'local_handbook'),
            ['optional' => true]);

        $mform->addElement('textarea', 'summary', get_string('summary', 'local_handbook'),
            ['rows' => 3, 'cols' => 70]);
        $mform->setType('summary', PARAM_TEXT);
        $mform->addRule('summary', null, 'required', null, 'client');

        // Draft content.
        $mform->addElement('editor', 'content_editor', get_string('pagecontent', 'local_handbook'), null,
            $this->_customdata['editoroptions']);

        $mform->addElement('textarea', 'changesummary', get_string('changesummary', 'local_handbook'),
            ['rows' => 2, 'cols' => 70]);
        $mform->setType('changesummary', PARAM_TEXT);
        $mform->addHelpButton('changesummary', 'changesummary', 'local_handbook');

        // Concurrency token (specification 11.3).
        $mform->addElement('hidden', 'revisiontimemodified', 0);
        $mform->setType('revisiontimemodified', PARAM_INT);

        $buttons = [
            $mform->createElement('submit', 'savedraft', get_string('savedraft', 'local_handbook')),
            $mform->createElement('submit', 'submitreview', get_string('submitforreview', 'local_handbook')),
        ];
        // Bootstrap mode only (spec 4.10): direct publish for publishers.
        if (!empty($this->_customdata['candirectpublish'])) {
            $buttons[] = $mform->createElement('submit', 'saveandpublish',
                get_string('saveandpublish', 'local_handbook'));
        }
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
    }

    /**
     * Server-side validation.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim($data['title'] ?? '') === '') {
            $errors['title'] = get_string('required');
        }
        if (trim($data['summary'] ?? '') === '') {
            $errors['summary'] = get_string('required');
        }
        if (!empty($data['submitreview']) && trim($data['changesummary'] ?? '') === '') {
            $errors['changesummary'] = get_string('changesummary_help', 'local_handbook');
        }

        return $errors;
    }
}
