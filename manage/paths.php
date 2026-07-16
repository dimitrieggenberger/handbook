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
 * Reading-path management: paths and their ordered items (spec 15.3).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\form\path_form;
use local_handbook\local\service\page_service;
use local_handbook\local\service\path_service;

$action = optional_param('action', '', PARAM_ALPHA);
$pathid = optional_param('id', 0, PARAM_INT);
$itemid = optional_param('item', 0, PARAM_INT);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:managepaths', $context);

$url = new moodle_url('/local/handbook/manage/paths.php');
local_handbook_apply_page_setup($url, $context, 'paths',
    get_string('managepaths', 'local_handbook'));

// ---- Item actions --------------------------------------------------------.

if ($action === 'additem' && $pathid) {
    require_sesskey();
    $path = $DB->get_record('local_handbook_path', ['id' => $pathid], '*', MUST_EXIST);

    $pageid = required_param('pageid', PARAM_INT);
    $DB->get_record('local_handbook_page', ['id' => $pageid], 'id', MUST_EXIST);

    if (!$DB->record_exists('local_handbook_pathitem', ['pathid' => $path->id, 'pageid' => $pageid])) {
        $maxsort = (int)$DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {local_handbook_pathitem} WHERE pathid = ?', [$path->id]);
        $DB->insert_record('local_handbook_pathitem', (object)[
            'pathid' => $path->id,
            'pageid' => $pageid,
            'sectionname' => trim(required_param('sectionname', PARAM_TEXT)),
            'sortorder' => $maxsort + 10,
            'required' => optional_param('required', 0, PARAM_BOOL) ? 1 : 0,
            'quizcmid' => optional_param('quizcmid', 0, PARAM_INT),
        ]);
    }
    redirect(new moodle_url($url, ['action' => 'edit', 'id' => $path->id]));
}

if ($action === 'deleteitem' && $itemid) {
    require_sesskey();
    $item = $DB->get_record('local_handbook_pathitem', ['id' => $itemid], '*', MUST_EXIST);
    $DB->delete_records('local_handbook_pathitem', ['id' => $item->id]);
    redirect(new moodle_url($url, ['action' => 'edit', 'id' => $item->pathid]));
}

if ($action === 'delete' && $pathid) {
    require_sesskey();
    $DB->delete_records('local_handbook_pathitem', ['pathid' => $pathid]);
    $DB->delete_records('local_handbook_path', ['id' => $pathid]);
    redirect($url, get_string('pathdeleted', 'local_handbook'));
}

// ---- Create / edit -------------------------------------------------------.

if ($action === 'edit') {
    $path = $pathid
        ? $DB->get_record('local_handbook_path', ['id' => $pathid], '*', MUST_EXIST)
        : null;

    $formurl = new moodle_url($url, ['action' => 'edit'] + ($pathid ? ['id' => $pathid] : []));
    $form = new path_form($formurl->out(false), [
        'cohorts' => $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name'),
        'roles' => role_fix_names(get_all_roles(), $context, ROLENAME_ORIGINAL),
    ]);

    if ($form->is_cancelled()) {
        redirect($url);
    }

    if ($data = $form->get_data()) {
        $now = time();
        $record = new stdClass();
        $record->name = trim($data->name);
        $record->description = $data->description;
        $record->descriptionformat = FORMAT_HTML;
        $record->schoolyear = trim((string)$data->schoolyear);
        $record->active = (int)$data->active;
        $record->audiencejson = path_service::encode_audience(
            (array)($data->audiencecohorts ?? []), (array)($data->audienceroles ?? []));
        $record->quizcmid = 0;
        $record->timemodified = $now;
        $record->modifiedby = (int)$USER->id;

        if ($path) {
            $record->id = $path->id;
            $DB->update_record('local_handbook_path', $record);
        } else {
            $record->slug = page_service::unique_slug('local_handbook_path',
                page_service::slugify($record->name . ' ' . $record->schoolyear));
            $record->timecreated = $now;
            $record->createdby = (int)$USER->id;
            $pathid = $DB->insert_record('local_handbook_path', $record);
        }
        redirect(new moodle_url($url, ['action' => 'edit', 'id' => $path->id ?? $pathid]),
            get_string('pathsaved', 'local_handbook'));
    }

    if ($path) {
        $audience = path_service::get_audience($path);
        $path->audiencecohorts = $audience->cohorts;
        $path->audienceroles = $audience->roles;
        $form->set_data($path);
    }

    echo $OUTPUT->header();
    echo local_handbook_render_area_actions('paths', $context);
    echo local_handbook_render_page_heading($path
        ? get_string('editpath', 'local_handbook') . ': ' . format_string($path->name)
        : get_string('newpath', 'local_handbook'));
    $form->display();

    // Items of an existing path.
    if ($path) {
        echo html_writer::tag('h3', s(get_string('pathitems', 'local_handbook')), ['class' => 'h5 mt-4 mb-3']);

        $sql = "SELECT i.*, p.title, p.slug
                  FROM {local_handbook_pathitem} i
                  JOIN {local_handbook_page} p ON p.id = i.pageid
                 WHERE i.pathid = :pathid
              ORDER BY i.sortorder ASC, i.id ASC";
        $items = $DB->get_records_sql($sql, ['pathid' => $path->id]);

        $rows = '';
        foreach ($items as $item) {
            $meta = [];
            $meta[] = $item->sectionname !== '' ? $item->sectionname : '—';
            $meta[] = (int)$item->required
                ? get_string('requiredreading', 'local_handbook')
                : get_string('optionalitem', 'local_handbook');
            if ($item->quizcmid) {
                $meta[] = get_string('connectedquiz', 'local_handbook') . ' #' . $item->quizcmid;
            }

            $rows .= html_writer::div(
                html_writer::div(
                    html_writer::link(new moodle_url('/local/handbook/view.php', ['page' => $item->slug]),
                        s($item->title))
                    . html_writer::div(s(implode(' · ', $meta)), 'small text-muted'),
                    'mr-auto')
                . html_writer::link(new moodle_url($url, ['action' => 'deleteitem', 'item' => $item->id,
                        'sesskey' => sesskey()]),
                    s(get_string('delete', 'core')),
                    ['class' => 'btn btn-outline-secondary btn-sm']),
                'd-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom'
            );
        }
        echo $rows !== ''
            ? html_writer::div(html_writer::div($rows, 'card-body'), 'card mb-3')
            : html_writer::div(s(get_string('emptypath', 'local_handbook')), 'alert alert-info');

        // Add-item form.
        $pageoptions = '';
        $pages = $DB->get_records_select('local_handbook_page', 'archived = 0', [], 'title ASC',
            'id, title');
        foreach ($pages as $page) {
            $pageoptions .= html_writer::tag('option', s($page->title), ['value' => $page->id]);
        }

        $additem = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
            'class' => 'd-flex flex-wrap gap-2 align-items-center']);
        $additem .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'additem']);
        $additem .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $path->id]);
        $additem .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $additem .= html_writer::tag('select', $pageoptions,
            ['name' => 'pageid', 'class' => 'custom-select custom-select-sm w-auto',
                'aria-label' => get_string('pagetitle', 'local_handbook')]);
        $additem .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'sectionname',
            'class' => 'form-control form-control-sm w-auto', 'required' => 'required',
            'placeholder' => get_string('sectionname', 'local_handbook')]);
        $additem .= html_writer::tag('label',
            html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'required', 'value' => 1,
                'checked' => 'checked', 'class' => 'mr-1'])
            . s(get_string('requiredreading', 'local_handbook')),
            ['class' => 'mb-0 small']);
        $additem .= html_writer::empty_tag('input', ['type' => 'number', 'name' => 'quizcmid',
            'class' => 'form-control form-control-sm w-auto', 'min' => 0, 'value' => 0,
            'title' => get_string('connectedquiz', 'local_handbook')]);
        $additem .= html_writer::tag('button', s(get_string('additem', 'local_handbook')),
            ['type' => 'submit', 'class' => 'btn btn-outline-secondary btn-sm']);
        $additem .= html_writer::end_tag('form');

        echo html_writer::div(html_writer::div($additem, 'card-body'), 'card');
    }

    echo $OUTPUT->footer();
    exit;
}

// ---- Listing --------------------------------------------------------------.

echo $OUTPUT->header();
echo local_handbook_render_area_actions('paths', $context);

$newbutton = html_writer::link(
    new moodle_url($url, ['action' => 'edit']),
    html_writer::tag('i', '', ['class' => 'fa-solid fa-plus me-2', 'aria-hidden' => 'true'])
        . s(get_string('newpath', 'local_handbook')),
    ['class' => 'btn btn-outline-secondary btn-sm']
);
echo local_handbook_render_page_heading(get_string('managepaths', 'local_handbook'), $newbutton);

$paths = $DB->get_records('local_handbook_path', [], 'schoolyear DESC, name ASC');

if (!$paths) {
    echo html_writer::div(s(get_string('nopathsyet', 'local_handbook')), 'alert alert-info');
} else {
    $rows = '';
    foreach ($paths as $path) {
        $itemcount = $DB->count_records('local_handbook_pathitem', ['pathid' => $path->id]);
        $meta = [];
        if ($path->schoolyear !== '') {
            $meta[] = $path->schoolyear;
        }
        $meta[] = get_string('pathitemcount', 'local_handbook', $itemcount);
        if (!(int)$path->active) {
            $meta[] = get_string('inactive', 'core');
        }

        $rows .= html_writer::div(
            html_writer::div(
                html_writer::link(new moodle_url('/local/handbook/path.php', ['id' => $path->id]),
                    s($path->name))
                . html_writer::div(s(implode(' · ', $meta)), 'small text-muted'),
                'mr-auto')
            . html_writer::div(
                html_writer::link(new moodle_url($url, ['action' => 'edit', 'id' => $path->id]),
                    s(get_string('edit', 'core')), ['class' => 'btn btn-outline-secondary btn-sm'])
                . ' ' . html_writer::link(new moodle_url($url, ['action' => 'delete', 'id' => $path->id,
                        'sesskey' => sesskey()]),
                    s(get_string('delete', 'core')),
                    [
                        'class' => 'btn btn-outline-secondary btn-sm',
                        'data-confirmation' => 'modal',
                        'data-confirmation-type' => 'delete',
                        'data-confirmation-content' => get_string('confirmdeletepath', 'local_handbook',
                            format_string($path->name)),
                        'data-confirmation-yes-button' => get_string('delete', 'core'),
                    ]),
                'd-flex gap-2'),
            'd-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom'
        );
    }
    echo html_writer::div(html_writer::div($rows, 'card-body'), 'card');
}

echo $OUTPUT->footer();
