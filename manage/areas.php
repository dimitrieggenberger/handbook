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
 * Responsible-area vocabulary management (specification 9).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\form\area_form;
use local_handbook\local\service\area_service;

$action = optional_param('action', '', PARAM_ALPHA);
$areaid = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:managecategories', $context);

$url = new moodle_url('/local/handbook/manage/areas.php');
local_handbook_apply_page_setup($url, $context, 'areas',
    get_string('manageareas', 'local_handbook'));

// ---- State-changing actions -------------------------------------------------.

if ($action === 'delete' && $areaid) {
    require_sesskey();
    area_service::delete($areaid);
    redirect($url, get_string('areadeleted', 'local_handbook'));
}

if (($action === 'activate' || $action === 'deactivate') && $areaid) {
    require_sesskey();
    area_service::set_active($areaid, $action === 'activate', (int)$USER->id);
    redirect($url, get_string('areasaved', 'local_handbook'));
}

// ---- Create / edit form -----------------------------------------------------.

if ($action === 'edit') {
    $area = $areaid ? area_service::get($areaid) : null;

    $formurl = new moodle_url($url, ['action' => 'edit'] + ($areaid ? ['id' => $areaid] : []));
    $form = new area_form($formurl->out(false));

    if ($form->is_cancelled()) {
        redirect($url);
    }

    if ($data = $form->get_data()) {
        area_service::save($data, (int)$USER->id);
        redirect($url, get_string('areasaved', 'local_handbook'));
    }

    if ($area) {
        $form->set_data($area);
    }

    echo $OUTPUT->header();
    echo local_handbook_render_area_actions('areas', $context);
    echo local_handbook_render_page_heading($area
        ? get_string('editarea', 'local_handbook')
        : get_string('newarea', 'local_handbook'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// ---- Listing ---------------------------------------------------------------.

echo $OUTPUT->header();
echo local_handbook_render_area_actions('areas', $context);

$newbutton = html_writer::link(
    new moodle_url($url, ['action' => 'edit']),
    html_writer::tag('i', '', ['class' => 'fa-solid fa-plus me-2', 'aria-hidden' => 'true'])
        . s(get_string('newarea', 'local_handbook')),
    ['class' => 'btn btn-outline-secondary btn-sm']
);
echo local_handbook_render_page_heading(get_string('manageareas', 'local_handbook'), $newbutton);
echo html_writer::div(s(get_string('manageareas_help', 'local_handbook')), 'text-muted small mb-3');

$areas = area_service::all();

if (!$areas) {
    echo html_writer::div(s(get_string('noareas', 'local_handbook')), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$rows = '';
foreach ($areas as $area) {
    $name = s($area->name);
    if (!(int)$area->active) {
        $name .= ' ' . html_writer::span(s(get_string('areainactive', 'local_handbook')),
            'badge badge-secondary');
    }
    $key = html_writer::tag('code', s($area->areakey), ['class' => 'small text-muted']);

    $actions = html_writer::link(
        new moodle_url($url, ['action' => 'edit', 'id' => $area->id]),
        s(get_string('edit', 'core')), ['class' => 'btn btn-outline-secondary btn-sm']);
    $toggle = (int)$area->active ? 'deactivate' : 'activate';
    $actions .= ' ' . html_writer::link(
        new moodle_url($url, ['action' => $toggle, 'id' => $area->id, 'sesskey' => sesskey()]),
        s(get_string('area' . $toggle, 'local_handbook')),
        ['class' => 'btn btn-outline-secondary btn-sm']);
    $actions .= ' ' . html_writer::link(
        new moodle_url($url, ['action' => 'delete', 'id' => $area->id, 'sesskey' => sesskey()]),
        s(get_string('delete', 'core')),
        [
            'class' => 'btn btn-outline-secondary btn-sm',
            'data-confirmation' => 'modal',
            'data-confirmation-type' => 'delete',
            'data-confirmation-content' => get_string('confirmdeletearea', 'local_handbook',
                format_string($area->name)),
            'data-confirmation-yes-button' => get_string('delete', 'core'),
        ]);

    $rows .= html_writer::div(
        html_writer::div($name . ' ' . $key, 'mr-auto')
        . html_writer::div($actions, 'd-flex gap-2'),
        'd-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom'
    );
}

echo html_writer::div(html_writer::div($rows, 'card-body'), 'card');
echo $OUTPUT->footer();
