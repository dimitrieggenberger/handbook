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
 * Print-friendly view of a published page (specification 12.2).
 *
 * Uses the embedded layout (no navbars/blocks) with print-specific CSS;
 * the print button simply calls window.print().
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$pageparam = required_param('page', PARAM_ALPHANUMEXT);

$context = context_system::instance();
local_handbook_require_view($context);

if (ctype_digit($pageparam)) {
    $page = $DB->get_record('local_handbook_page', ['id' => (int)$pageparam]);
} else {
    $page = $DB->get_record('local_handbook_page', ['slug' => $pageparam]);
}
if (!$page || !$page->publishedrevisionid
        || ((int)$page->archived === 1 && !local_handbook_user_is_editorial($context))) {
    throw new moodle_exception('errorpagenotfound', 'local_handbook');
}

$revision = $DB->get_record('local_handbook_revision',
    ['id' => $page->publishedrevisionid], '*', MUST_EXIST);

$url = new moodle_url('/local/handbook/print.php', ['page' => $page->slug]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_pagetype(LOCAL_HANDBOOK_PAGE_TYPE);
$PAGE->set_title(format_string($page->title));
$PAGE->set_heading(format_string($page->title));
$PAGE->requires->css(new moodle_url('/local/handbook/styles.css'));

echo $OUTPUT->header();

echo html_writer::start_div('local-handbook-print');

// Screen-only toolbar.
echo html_writer::div(
    html_writer::tag('button',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-print me-2', 'aria-hidden' => 'true'])
        . s(get_string('printpage', 'local_handbook')),
        ['type' => 'button', 'class' => 'btn btn-primary btn-sm', 'onclick' => 'window.print(); return false;'])
    . ' ' . html_writer::link(local_handbook_page_url($page),
        s(get_string('backtopage', 'local_handbook')), ['class' => 'btn btn-outline-secondary btn-sm']),
    'local-handbook-print-toolbar d-flex gap-2 mb-4'
);

// Document header.
echo html_writer::tag('h1', s(format_string($page->title)), ['class' => 'h2 mb-1']);
$metaparts = [
    get_string('contenttype_' . $page->contenttype, 'local_handbook'),
    get_string('versionnumber', 'local_handbook', (int)$revision->versionnumber),
    get_string('effectivedate', 'local_handbook') . ': '
        . ((int)$page->effectivedate > 0
            ? userdate((int)$page->effectivedate, get_string('strftimedate', 'langconfig')) : '—'),
    get_string('lastupdated', 'local_handbook') . ': '
        . userdate((int)$revision->timepublished, get_string('strftimedate', 'langconfig')),
];
if (trim((string)$page->responsiblearea) !== '') {
    $metaparts[] = get_string('responsiblearea', 'local_handbook') . ': ' . $page->responsiblearea;
}
echo html_writer::div(s(implode(' · ', $metaparts)), 'text-muted small mb-3');

if (trim((string)$page->summary) !== '') {
    echo html_writer::tag('p', s($page->summary), ['class' => 'lead']);
}

echo local_handbook_render_revision_content($revision, $context);

// Sources on record: the printed page lists which documents accompany the
// article, even though the files themselves cannot follow the paper.
$attachments = local_handbook_page_attachments((int)$page->id);
if ($attachments) {
    $items = '';
    foreach ($attachments as $file) {
        $items .= html_writer::tag('li',
            s($file->get_filename()) . ' '
            . html_writer::span('(' . s(display_size((int)$file->get_filesize())) . ')', 'text-muted'));
    }
    echo html_writer::div(
        html_writer::tag('h3', s(get_string('attachments', 'local_handbook')),
            ['class' => 'h6 text-uppercase text-muted mb-2'])
        . html_writer::tag('ul', $items, ['class' => 'small mb-0']),
        'local-handbook-print-attachments mt-4 pt-2 border-top');
}

// Provenance footer: printed copies age; the handbook is authoritative.
echo html_writer::div(
    s(get_string('printfooter', 'local_handbook', (object)[
        'date' => userdate(time(), get_string('strftimedate', 'langconfig')),
        'url' => local_handbook_page_url($page)->out(false),
    ])),
    'local-handbook-print-footer text-muted small mt-4 pt-2 border-top'
);

echo html_writer::end_div();

echo $OUTPUT->footer();
