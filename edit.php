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
 * Create a handbook page or edit its draft revision (specification 12.3).
 *
 * Editing never touches the published revision: it always works on the
 * page's single working draft, creating one from the published content
 * when none exists yet (specification 11).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
// EDITOR_UNLIMITED_FILES is defined in formslib (verified against Moodle
// 5.2 source: public/lib/formslib.php); this page uses it before the form
// class would load formslib.
require_once($CFG->libdir . '/formslib.php');

use local_handbook\form\page_form;
use local_handbook\local\service\image_service;
use local_handbook\local\service\page_service;

$pageid = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:edit', $context);

$page = null;
if ($pageid) {
    $page = $DB->get_record('local_handbook_page', ['id' => $pageid]);
    if (!$page) {
        throw new moodle_exception('errorpagenotfound', 'local_handbook');
    }
}

$url = new moodle_url('/local/handbook/edit.php', $pageid ? ['id' => $pageid] : []);
$title = $page ? get_string('editpage', 'local_handbook') : get_string('newpage', 'local_handbook');
local_handbook_apply_page_setup($url, $context, 'home', $title, $title);

// Category options (indented tree, two levels are enough for the form).
$categoryoptions = [];
foreach (local_handbook_get_categories(0, true) as $top) {
    $categoryoptions[(int)$top->id] = format_string($top->name);
    foreach (local_handbook_get_categories((int)$top->id, true) as $child) {
        $categoryoptions[(int)$child->id] = format_string($top->name) . ' › ' . format_string($child->name);
    }
}
if (!$categoryoptions) {
    // No categories yet: send managers to create one first.
    redirect(new moodle_url('/local/handbook/manage/categories.php'),
        get_string('nocategoriesyet', 'local_handbook'));
}

// Load or prepare the working draft for existing pages.
$revision = null;
if ($page) {
    $revision = page_service::get_working_revision((int)$page->id);
    if ($revision && !in_array($revision->status, page_service::EDITABLE_STATUSES, true)) {
        // A revision is in review or approved: no editing until it returns.
        redirect(local_handbook_page_url($page),
            get_string('draftnotice', 'local_handbook', (object)[
                'version' => (int)$revision->versionnumber,
                'status' => get_string('status_' . $revision->status, 'local_handbook'),
            ]));
    }
}

$editoroptions = [
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'maxbytes' => 0,
    'context' => $context,
    'subdirs' => 0,
];
$banneroptions = [
    'maxfiles' => 1,
    'maxbytes' => 0,
    'subdirs' => 0,
    'accepted_types' => ['web_image'],
];
$attachmentoptions = [
    'maxfiles' => 20,
    'maxbytes' => 0,
    'subdirs' => 0,
    'accepted_types' => ['document', 'archive', 'image'],
];

$candirectpublish = page_service::bootstrap_mode_enabled()
    && has_capability('local/handbook:publish', $context);

$form = new page_form($url->out(false), [
    'categories' => $categoryoptions,
    'page' => $page,
    'revision' => $revision,
    'editoroptions' => $editoroptions,
    'banneroptions' => $banneroptions,
    'attachmentoptions' => $attachmentoptions,
    'candirectpublish' => $candirectpublish,
]);

if ($form->is_cancelled()) {
    redirect($page ? local_handbook_page_url($page) : new moodle_url('/local/handbook/index.php'));
}

if ($data = $form->get_data()) {
    $submitforreview = !empty($data->submitreview);

    if (!$page) {
        // New page: create page + draft v1, then attach editor files.
        $create = new stdClass();
        $create->title = $data->title;
        $create->slug = $data->slug;
        $create->categoryid = (int)$data->categoryid;
        $create->contenttype = $data->contenttype;
        $create->authoritylevel = (int)$data->authoritylevel;
        $create->criticality = $data->criticality;
        $create->responsiblearea = $data->responsiblearea;
        $create->requiredreading = (int)$data->requiredreading;
        $create->aiaccess = $data->aiaccess;
        $create->reviewdate = (int)($data->reviewdate ?? 0);
        $create->summary = $data->summary;
        $create->content = '';
        $create->contentformat = FORMAT_HTML;

        $page = page_service::create_page($create);
        $revision = $page->draftrevision;
    } else {
        // Update metadata on the page record.
        $update = new stdClass();
        $update->id = $page->id;
        $update->title = trim($data->title);
        $update->categoryid = (int)$data->categoryid;
        $update->contenttype = $data->contenttype;
        $update->authoritylevel = (int)$data->authoritylevel;
        $update->criticality = $data->criticality;
        $update->responsiblearea = $data->responsiblearea;
        $update->requiredreading = (int)$data->requiredreading;
        $update->aiaccess = $data->aiaccess;
        $update->reviewdate = (int)($data->reviewdate ?? 0);
        $update->summary = $data->summary;
        $update->timemodified = time();
        $update->modifiedby = (int)$USER->id;
        $DB->update_record('local_handbook_page', $update);
        $page = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);

        if (!$revision) {
            $revision = page_service::create_revision_draft($page);
        } else if ((int)$data->revisiontimemodified !== (int)$revision->timemodified) {
            // Someone else saved while this form was open (11.3).
            throw new moodle_exception('errorrevisionconflict', 'local_handbook');
        }
    }

    // Store the banner image against the page (file area keyed by page id).
    $data = file_postupdate_standard_filemanager($data, 'bannerimage', $banneroptions, $context,
        'local_handbook', 'bannerimage', (int)$page->id);

    // Attached source documents, likewise page-scoped: they survive
    // revisions and never touch the draft/review workflow.
    $data = file_postupdate_standard_filemanager($data, 'attachments', $attachmentoptions, $context,
        'local_handbook', 'attachments', (int)$page->id);

    // Move editor draft files into the revision's file area, then save.
    $data = file_postupdate_standard_editor($data, 'content', $editoroptions, $context,
        'local_handbook', 'revision', (int)$revision->id);

    // Downscale/re-encode oversized images (camera photos, pasted
    // screenshots) now that they sit in their final areas. Filenames are
    // preserved, so the content HTML needs no rewriting.
    if (image_service::enabled()) {
        image_service::optimize_area($context, 'bannerimage', (int)$page->id);
        image_service::optimize_area($context, 'revision', (int)$revision->id);
    }

    page_service::update_draft($revision, $data->content, (int)$data->contentformat,
        trim((string)$data->changesummary), 0, (bool)($data->requiresreack ?? false));

    if ($submitforreview) {
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        page_service::submit_for_review($revision, trim((string)$data->changesummary));
        redirect(local_handbook_page_url($page), get_string('draftsubmitted', 'local_handbook'));
    }

    if (!empty($data->saveandpublish) && $candirectpublish) {
        require_capability('local/handbook:publish', $context);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        page_service::direct_publish($revision);
        redirect(local_handbook_page_url($page), get_string('revisionpublished', 'local_handbook'));
    }

    redirect(new moodle_url('/local/handbook/edit.php', ['id' => $page->id]),
        get_string('draftsaved', 'local_handbook'));
}

// Prepare current values for display.
$defaults = new stdClass();
$defaults->id = $page->id ?? 0;
if ($page) {
    $defaults->title = $page->title;
    $defaults->slug = $page->slug;
    $defaults->categoryid = (int)$page->categoryid;
    $defaults->contenttype = $page->contenttype;
    $defaults->authoritylevel = (int)$page->authoritylevel;
    $defaults->criticality = $page->criticality;
    $defaults->responsiblearea = $page->responsiblearea;
    $defaults->requiredreading = (int)$page->requiredreading;
    $defaults->aiaccess = $page->aiaccess;
    $defaults->reviewdate = (int)$page->reviewdate;
    $defaults->summary = $page->summary;
}

// Without a working draft, the editor starts from the published content
// (the draft itself is only created on save; spec 11.2).
$sourcerevision = $revision;
if (!$sourcerevision && $page && (int)$page->publishedrevisionid) {
    $sourcerevision = $DB->get_record('local_handbook_revision',
        ['id' => $page->publishedrevisionid]);
}

$defaults->content = $sourcerevision->content ?? '';
$defaults->contentformat = (int)($sourcerevision->contentformat ?? FORMAT_HTML);
$defaults->changesummary = $revision->changesummary ?? '';
$defaults->requiresreack = (int)($revision->requiresreacknowledgement ?? 0);
$defaults->revisiontimemodified = (int)($revision->timemodified ?? 0);

$defaults = file_prepare_standard_editor($defaults, 'content', $editoroptions, $context,
    'local_handbook', 'revision', $sourcerevision->id ?? null);
$defaults = file_prepare_standard_filemanager($defaults, 'bannerimage', $banneroptions, $context,
    'local_handbook', 'bannerimage', $page->id ?? null);
$defaults = file_prepare_standard_filemanager($defaults, 'attachments', $attachmentoptions, $context,
    'local_handbook', 'attachments', $page->id ?? null);

$form->set_data($defaults);

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);
echo local_handbook_render_page_heading($title);

if ($revision) {
    echo html_writer::div(
        s(get_string('version', 'local_handbook')) . ' '
        . s(get_string('versionnumber', 'local_handbook', (int)$revision->versionnumber))
        . ' · ' . s(get_string('status_' . $revision->status, 'local_handbook'))
        . ((int)$revision->baserevisionid ? ' · ' . s(get_string('basedon', 'local_handbook',
            (int)$DB->get_field('local_handbook_revision', 'versionnumber',
                ['id' => $revision->baserevisionid]))) : ''),
        'alert alert-secondary'
    );
    if ($revision->status === page_service::STATUS_CHANGES_REQUESTED
            && trim((string)$revision->reviewnote) !== '') {
        echo html_writer::div(
            html_writer::tag('strong', s(get_string('reviewnote', 'local_handbook')) . ': ')
            . s($revision->reviewnote),
            'alert alert-warning'
        );
    }
}

$form->display();

echo $OUTPUT->footer();
