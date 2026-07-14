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
 * Category management: list, create, edit, delete (specification 12.5).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\form\category_form;
use local_handbook\local\service\page_service;

$action = optional_param('action', '', PARAM_ALPHA);
$categoryid = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:managecategories', $context);

$url = new moodle_url('/local/handbook/manage/categories.php');
local_handbook_apply_page_setup($url, $context, 'categories',
    get_string('managecategories', 'local_handbook'));

// ---- Delete ------------------------------------------------------------.

if ($action === 'delete' && $categoryid) {
    require_sesskey();

    $category = $DB->get_record('local_handbook_category', ['id' => $categoryid], '*', MUST_EXIST);

    $haspages = $DB->record_exists('local_handbook_page', ['categoryid' => $category->id]);
    $haschildren = $DB->record_exists('local_handbook_category', ['parentid' => $category->id]);
    if ($haspages || $haschildren) {
        redirect($url, get_string('categorynotempty', 'local_handbook'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $DB->delete_records('local_handbook_category', ['id' => $category->id]);
    redirect($url, get_string('categorydeleted', 'local_handbook'));
}

// ---- Create / edit form ------------------------------------------------.

if ($action === 'edit') {
    $category = null;
    if ($categoryid) {
        $category = $DB->get_record('local_handbook_category', ['id' => $categoryid], '*', MUST_EXIST);
    }

    // Parent options: top level plus all top-level categories (two levels
    // of nesting are enough for the first milestone UI).
    $parents = [0 => get_string('topcategory', 'local_handbook')];
    foreach (local_handbook_get_categories(0, true) as $top) {
        if ($category && (int)$top->id === (int)$category->id) {
            continue;
        }
        $parents[(int)$top->id] = format_string($top->name);
    }

    $formurl = new moodle_url($url, ['action' => 'edit'] + ($categoryid ? ['id' => $categoryid] : []));
    $form = new category_form($formurl->out(false), ['parents' => $parents]);

    if ($form->is_cancelled()) {
        redirect($url);
    }

    if ($data = $form->get_data()) {
        $now = time();
        $record = new stdClass();
        $record->name = trim($data->name);
        $record->parentid = (int)$data->parentid;
        $record->description = $data->description;
        $record->descriptionformat = FORMAT_HTML;
        $record->sortorder = (int)$data->sortorder;
        $record->visible = (int)$data->visible;
        $record->audiencekey = '';
        $record->timemodified = $now;
        $record->modifiedby = (int)$USER->id;

        if ($category) {
            $record->id = $category->id;
            $record->slug = !empty($data->slug)
                ? page_service::unique_slug('local_handbook_category',
                    page_service::slugify($data->slug), (int)$category->id)
                : $category->slug;
            $DB->update_record('local_handbook_category', $record);
        } else {
            $record->slug = page_service::unique_slug('local_handbook_category',
                page_service::slugify(!empty($data->slug) ? $data->slug : $record->name));
            $record->timecreated = $now;
            $record->createdby = (int)$USER->id;
            $DB->insert_record('local_handbook_category', $record);
        }

        redirect($url, get_string('categorysaved', 'local_handbook'));
    }

    if ($category) {
        $form->set_data($category);
    }

    echo $OUTPUT->header();
    echo local_handbook_render_area_actions('categories', $context);
    echo local_handbook_render_page_heading($category
        ? get_string('editcategory', 'local_handbook')
        : get_string('newcategory', 'local_handbook'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// ---- Listing -----------------------------------------------------------.

echo $OUTPUT->header();
echo local_handbook_render_area_actions('categories', $context);

$newbutton = html_writer::link(
    new moodle_url($url, ['action' => 'edit']),
    html_writer::tag('i', '', ['class' => 'fa-solid fa-plus me-2', 'aria-hidden' => 'true'])
        . s(get_string('newcategory', 'local_handbook')),
    ['class' => 'btn btn-outline-secondary btn-sm']
);
echo local_handbook_render_page_heading(get_string('managecategories', 'local_handbook'), $newbutton);

$counts = local_handbook_count_published_pages_by_category();

/**
 * Render one category row with actions, then recurse into children.
 *
 * @param stdClass $category Category record.
 * @param int $depth Nesting depth.
 * @param array $counts Published page counts by category id.
 * @param moodle_url $url Base management URL.
 * @return string
 */
function local_handbook_manage_category_row(stdClass $category, int $depth, array $counts,
        moodle_url $url): string {
    $pagecount = $counts[(int)$category->id] ?? 0;
    $countlabel = $pagecount === 1
        ? get_string('pagecountone', 'local_handbook')
        : get_string('pagecount', 'local_handbook', $pagecount);

    $name = html_writer::link(
        new moodle_url('/local/handbook/category.php', ['id' => $category->id]),
        s($category->name)
    );
    if (!(int)$category->visible) {
        $name .= ' ' . html_writer::span(s(get_string('hidden', 'core')), 'badge badge-secondary');
    }

    $actions = html_writer::link(
        new moodle_url($url, ['action' => 'edit', 'id' => $category->id]),
        s(get_string('edit', 'core')),
        ['class' => 'btn btn-outline-secondary btn-sm']
    );
    $actions .= ' ' . html_writer::link(
        new moodle_url($url, ['action' => 'delete', 'id' => $category->id, 'sesskey' => sesskey()]),
        s(get_string('delete', 'core')),
        [
            'class' => 'btn btn-outline-secondary btn-sm',
            'data-confirmation' => 'modal',
            'data-confirmation-content' => get_string('confirmdeletecategory', 'local_handbook',
                format_string($category->name)),
            'data-confirmation-yes-button-str' => get_string('delete', 'core'),
        ]
    );

    $row = html_writer::div(
        html_writer::div($name . ' ' . html_writer::span(s($countlabel), 'text-muted small ml-2'),
            'mr-auto', ['style' => 'padding-left: ' . ($depth * 1.5) . 'rem;'])
        . html_writer::div($actions, 'd-flex gap-2'),
        'd-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom'
    );

    foreach (local_handbook_get_categories((int)$category->id, true) as $child) {
        $row .= local_handbook_manage_category_row($child, $depth + 1, $counts, $url);
    }
    return $row;
}

$rows = '';
foreach (local_handbook_get_categories(0, true) as $category) {
    $rows .= local_handbook_manage_category_row($category, 0, $counts, $url);
}

if ($rows === '') {
    echo html_writer::div(s(get_string('nocategoriesyet', 'local_handbook')), 'alert alert-info');
} else {
    echo html_writer::div(html_writer::div($rows, 'card-body'), 'card');
}

echo $OUTPUT->footer();
