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
 * Responsible people (position holders) of a page: list, add, edit and
 * remove assignments. Each assignment binds a real Moodle account with a
 * per-page role label and optional institutional contact fields; the page
 * card updates the moment the assignment changes.
 *
 * Human-only surface, outside the editorial workflow — like banners and
 * attachments, the Handbook AI can neither read nor propose changes here.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\form\holder_form;
use local_handbook\local\service\holder_service;

$pageid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$hid = optional_param('hid', 0, PARAM_INT);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:edit', $context);

$record = $DB->get_record('local_handbook_page', ['id' => $pageid], '*', MUST_EXIST);

$url = new moodle_url('/local/handbook/manage/holders.php', ['id' => $pageid]);
local_handbook_apply_page_setup($url, $context, 'home',
    get_string('manageholders', 'local_handbook'));

$editing = null;
if ($hid) {
    $editing = holder_service::get_holder($hid);
    if ((int)$editing->pageid !== $pageid) {
        throw new moodle_exception('invalidrecord', 'error');
    }
}

if ($action === 'delete' && $editing && confirm_sesskey()) {
    holder_service::delete((int)$editing->id);
    redirect($url, get_string('holderdeleted', 'local_handbook'));
}

$form = new holder_form($url->out(false));
if ($form->is_cancelled()) {
    redirect($url);
}
if ($data = $form->get_data()) {
    $data->pageid = $pageid;
    if ((int)$data->sortorder <= 0) {
        $data->sortorder = holder_service::next_sortorder($pageid);
    }
    holder_service::save($data, (int)$USER->id);
    redirect($url, get_string('holdersaved', 'local_handbook'));
}

if ($editing) {
    $form->set_data($editing);
} else {
    $form->set_data(['pageid' => $pageid,
        'sortorder' => holder_service::next_sortorder($pageid)]);
}

$holders = holder_service::get_holders($pageid);

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);
echo local_handbook_render_page_heading(get_string('manageholders', 'local_handbook')
    . ': ' . format_string($record->title));

echo html_writer::tag('p', s(get_string('holdersintro', 'local_handbook')),
    ['class' => 'text-muted']);

if ($holders) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        get_string('holderuser', 'local_handbook'),
        get_string('holderrolelabel', 'local_handbook'),
        get_string('holdercontactshort', 'local_handbook'),
        get_string('holdersortorder', 'local_handbook'),
        get_string('actions'),
    ];
    foreach ($holders as $holder) {
        $user = core_user::get_user((int)$holder->userid, '*', IGNORE_MISSING);
        if ($user && !$user->deleted && !$user->suspended) {
            $who = $OUTPUT->user_picture($user, ['size' => 35, 'link' => false])
                . ' ' . s(fullname($user));
        } else {
            $who = html_writer::span(s(get_string('holdertransition', 'local_handbook')),
                'text-muted font-italic');
        }

        $contactbits = [];
        if (trim((string)$holder->contactemail) !== '') {
            $contactbits[] = s($holder->contactemail);
        }
        if (trim((string)$holder->contactphone) !== '') {
            $contactbits[] = s($holder->contactphone);
        }
        if (trim((string)$holder->whatsapp) !== '') {
            $contactbits[] = 'WhatsApp ' . s($holder->whatsapp);
        }

        $actions = html_writer::link(new moodle_url($url, ['hid' => $holder->id]),
                s(get_string('edit')))
            . ' · '
            . html_writer::link(new moodle_url($url, ['hid' => $holder->id,
                    'action' => 'delete', 'sesskey' => sesskey()]),
                s(get_string('holderremove', 'local_handbook')));

        $table->data[] = [
            $who,
            s($holder->rolelabel),
            implode(' · ', $contactbits),
            (int)$holder->sortorder,
            $actions,
        ];
    }
    echo html_writer::table($table);
} else {
    echo html_writer::div(s(get_string('noholders', 'local_handbook')), 'alert alert-info');
}

echo html_writer::tag('h3',
    s($editing
        ? get_string('editholder', 'local_handbook')
        : get_string('addholder', 'local_handbook')),
    ['class' => 'h5 mt-4']);
$form->display();

echo html_writer::tag('p',
    html_writer::link(local_handbook_page_url($record),
        s(get_string('backtopage', 'local_handbook'))),
    ['class' => 'mt-3']);

echo $OUTPUT->footer();
