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
 * Audience vocabulary management (multi-audience handbook, phase 1).
 *
 * Audiences are labels on articles, never copies of the manual. Each one
 * carries a membership matcher: staff by handbook role, everyone else by
 * a user profile field value (e.g. city = a value agreed by leadership).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\form\audience_form;
use local_handbook\local\service\audience_service;

$action = optional_param('action', '', PARAM_ALPHA);
$audienceid = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:managecategories', $context);

$url = new moodle_url('/local/handbook/manage/audiences.php');
local_handbook_apply_page_setup($url, $context, 'audiences',
    get_string('manageaudiences', 'local_handbook'));

// ---- State-changing actions -------------------------------------------------.

if ($action === 'delete' && $audienceid) {
    require_sesskey();
    if (audience_service::delete($audienceid)) {
        redirect($url, get_string('audiencedeleted', 'local_handbook'));
    }
    redirect($url, get_string('audienceinuse', 'local_handbook',
        audience_service::page_count($audienceid)), null,
        \core\output\notification::NOTIFY_WARNING);
}

// ---- Create / edit form -----------------------------------------------------.

if ($action === 'edit') {
    $audience = $audienceid ? audience_service::get($audienceid) : null;

    $formurl = new moodle_url($url, ['action' => 'edit'] + ($audienceid ? ['id' => $audienceid] : []));
    $form = new audience_form($formurl->out(false));

    if ($form->is_cancelled()) {
        redirect($url);
    }

    if ($data = $form->get_data()) {
        audience_service::save($data, (int)$USER->id);
        redirect($url, get_string('audiencesaved', 'local_handbook'));
    }

    if ($audience) {
        $form->set_data($audience);
    }

    echo $OUTPUT->header();
    echo local_handbook_render_area_actions('audiences', $context);
    echo local_handbook_render_page_heading($audience
        ? get_string('editaudience', 'local_handbook')
        : get_string('newaudience', 'local_handbook'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// ---- Listing ---------------------------------------------------------------.

echo $OUTPUT->header();
echo local_handbook_render_area_actions('audiences', $context);

$newbutton = html_writer::link(
    new moodle_url($url, ['action' => 'edit']),
    html_writer::tag('i', '', ['class' => 'fa-solid fa-plus me-2', 'aria-hidden' => 'true'])
        . s(get_string('newaudience', 'local_handbook')),
    ['class' => 'btn btn-outline-secondary btn-sm']
);
echo local_handbook_render_page_heading(get_string('manageaudiences', 'local_handbook'), $newbutton);
echo html_writer::div(s(get_string('manageaudiences_help', 'local_handbook')), 'text-muted small mb-3');

$audiences = audience_service::get_all();

if (!$audiences) {
    echo html_writer::div(s(get_string('noaudiences', 'local_handbook')), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$rows = '';
foreach ($audiences as $audience) {
    $chip = local_handbook_audience_chip($audience);
    $name = $chip . ' ' . html_writer::tag('code', s($audience->audiencekey), ['class' => 'small text-muted']);
    if (!(int)$audience->active) {
        $name .= ' ' . html_writer::span(s(get_string('areainactive', 'local_handbook')),
            'badge badge-secondary');
    }

    if ($audience->matchtype === 'staff') {
        $matcher = s(get_string('audiencematch_staff', 'local_handbook'));
    } else if (trim((string)$audience->profilevalue) !== '') {
        $matcher = html_writer::tag('code',
            s($audience->profilefield . ' = "' . $audience->profilevalue . '"'), ['class' => 'small']);
    } else {
        $matcher = html_writer::span(s(get_string('audiencenomatcher', 'local_handbook')),
            'small text-warning font-weight-bold');
    }

    $count = audience_service::page_count((int)$audience->id);
    $countlabel = html_writer::span(
        s(get_string('audiencepagecount', 'local_handbook', $count)), 'small text-muted');

    $actions = html_writer::link(
        new moodle_url($url, ['action' => 'edit', 'id' => $audience->id]),
        s(get_string('edit', 'core')), ['class' => 'btn btn-outline-secondary btn-sm']);
    $actions .= ' ' . html_writer::link(
        new moodle_url($url, ['action' => 'delete', 'id' => $audience->id, 'sesskey' => sesskey()]),
        s(get_string('delete', 'core')),
        [
            'class' => 'btn btn-outline-secondary btn-sm',
            'data-confirmation' => 'modal',
            'data-confirmation-type' => 'delete',
            'data-confirmation-content' => get_string('confirmdeleteaudience', 'local_handbook',
                format_string($audience->name)),
            'data-confirmation-yes-button' => get_string('delete', 'core'),
        ]);

    $rows .= html_writer::div(
        html_writer::div($name, 'mr-auto')
        . html_writer::div($matcher, 'mx-3')
        . html_writer::div($countlabel, 'mx-3')
        . html_writer::div($actions, 'd-flex gap-2'),
        'd-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom'
    );
}

echo html_writer::div(html_writer::div($rows, 'card-body'), 'card');
echo html_writer::tag('p', s(get_string('audiencesfootnote', 'local_handbook')),
    ['class' => 'small text-muted mt-3']);
echo $OUTPUT->footer();
