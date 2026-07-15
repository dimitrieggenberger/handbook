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
 * Change-set list and creation (specification 36.4, 36.5).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\local\service\changeset_service;

$action = optional_param('action', '', PARAM_ALPHA);
$statusfilter = optional_param('status', 'all', PARAM_ALPHAEXT);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:managechangesets', $context);

$url = new moodle_url('/local/handbook/manage/changesets.php', ['status' => $statusfilter]);
local_handbook_apply_page_setup($url, $context, 'changesets',
    get_string('changesets', 'local_handbook'));

// ---- Create ---------------------------------------------------------------.

if ($action === 'create') {
    require_sesskey();
    $data = new stdClass();
    $data->title = required_param('title', PARAM_TEXT);
    $data->instructionsummary = optional_param('instructionsummary', '', PARAM_TEXT);
    $data->source = 'human';
    $changeset = changeset_service::create($data);
    redirect(new moodle_url('/local/handbook/manage/changeset.php', ['id' => $changeset->id]),
        get_string('changesetcreated', 'local_handbook'));
}

echo $OUTPUT->header();
echo local_handbook_render_area_actions('changesets', $context);
echo local_handbook_render_page_heading(get_string('changesets', 'local_handbook'));

// ---- Create form ----------------------------------------------------------.

$createform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
    'class' => 'card mb-4']);
$createform .= html_writer::start_div('card-body');
$createform .= html_writer::tag('h3', s(get_string('newchangeset', 'local_handbook')),
    ['class' => 'h6 mb-3']);
$createform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'create']);
$createform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$createform .= html_writer::div(
    html_writer::tag('label', s(get_string('pagetitle', 'local_handbook')),
        ['for' => 'cs-title', 'class' => 'form-label'])
    . html_writer::empty_tag('input', ['type' => 'text', 'id' => 'cs-title', 'name' => 'title',
        'class' => 'form-control', 'required' => 'required', 'maxlength' => 255]),
    'mb-2');
$createform .= html_writer::div(
    html_writer::tag('label', s(get_string('changesetinstructions', 'local_handbook')),
        ['for' => 'cs-instr', 'class' => 'form-label'])
    . html_writer::tag('textarea', '', ['id' => 'cs-instr', 'name' => 'instructionsummary',
        'class' => 'form-control', 'rows' => 2]),
    'mb-3');
$createform .= html_writer::tag('button', s(get_string('createchangeset', 'local_handbook')),
    ['type' => 'submit', 'class' => 'btn btn-primary']);
$createform .= html_writer::end_div();
$createform .= html_writer::end_tag('form');
echo $createform;

// ---- Filter tabs ----------------------------------------------------------.

$filters = array_merge(['all'], [
    changeset_service::STATUS_DRAFT,
    changeset_service::STATUS_IN_REVIEW,
    changeset_service::STATUS_PARTIALLY_COMPLETED,
    changeset_service::STATUS_COMPLETED,
    changeset_service::STATUS_CANCELLED,
]);
$filterlinks = '';
foreach ($filters as $filter) {
    $label = $filter === 'all'
        ? get_string('all', 'core')
        : get_string('changesetstatus_' . $filter, 'local_handbook');
    $classes = 'badge ' . ($filter === $statusfilter ? 'badge-primary' : 'badge-light border');
    $filterlinks .= html_writer::link(
        new moodle_url('/local/handbook/manage/changesets.php', ['status' => $filter]),
        s($label), ['class' => $classes]) . ' ';
}
echo html_writer::div($filterlinks, 'mb-3 d-flex flex-wrap gap-1');

// ---- Listing --------------------------------------------------------------.

$conditions = $statusfilter === 'all' ? [] : ['status' => $statusfilter];
$changesets = changeset_service::list_changesets($conditions, 0, 200);

if (!$changesets) {
    echo html_writer::div(s(get_string('nochangesets', 'local_handbook')), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$statusbadges = [
    changeset_service::STATUS_DRAFT => 'badge badge-secondary',
    changeset_service::STATUS_IN_REVIEW => 'badge badge-info',
    changeset_service::STATUS_PARTIALLY_COMPLETED => 'badge badge-warning',
    changeset_service::STATUS_COMPLETED => 'badge badge-success',
    changeset_service::STATUS_CANCELLED => 'badge badge-light border',
];

foreach ($changesets as $changeset) {
    $itemcount = $DB->count_records('local_handbook_changeitem', ['changesetid' => $changeset->id]);
    $detailurl = new moodle_url('/local/handbook/manage/changeset.php', ['id' => $changeset->id]);

    $head = html_writer::link($detailurl, s($changeset->title)) . ' '
        . html_writer::span(s(get_string('changesetstatus_' . $changeset->status, 'local_handbook')),
            $statusbadges[$changeset->status] ?? 'badge badge-secondary');
    if ($changeset->source === 'ai') {
        $head .= ' ' . html_writer::span(s(get_string('source_ai', 'local_handbook')),
            'badge badge-light border');
    }

    $meta = get_string('pagecount', 'local_handbook', $itemcount)
        . ' · ' . get_string('changesetcreatedon', 'local_handbook')
        . ' ' . userdate((int)$changeset->timecreated, get_string('strftimedate', 'langconfig'));

    $body = html_writer::tag('h3', $head, ['class' => 'h6 mb-1']);
    $body .= html_writer::div(s($meta), 'small text-muted mb-1');
    if (trim((string)$changeset->instructionsummary) !== '') {
        $body .= html_writer::div(s(shorten_text($changeset->instructionsummary, 300)), 'small');
    }

    echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-2');
}

echo $OUTPUT->footer();
