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
 * Quality findings dashboard (specification 12.5, 19).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\local\service\finding_service;

$action = optional_param('action', '', PARAM_ALPHA);
$findingid = optional_param('id', 0, PARAM_INT);
$statusfilter = optional_param('status', 'openish', PARAM_ALPHAEXT);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:managefindings', $context);

$url = new moodle_url('/local/handbook/manage/findings.php', ['status' => $statusfilter]);
local_handbook_apply_page_setup($url, $context, 'findings',
    get_string('managefindings', 'local_handbook'));

// ---- Status transition ----------------------------------------------------.

if ($action === 'setstatus' && $findingid) {
    require_sesskey();
    $finding = $DB->get_record('local_handbook_finding', ['id' => $findingid], '*', MUST_EXIST);
    $newstatus = required_param('newstatus', PARAM_ALPHAEXT);
    $note = optional_param('note', '', PARAM_TEXT);
    finding_service::set_status($finding, $newstatus, $note);
    redirect($url, get_string('findingupdated', 'local_handbook'));
}

// ---- Listing ----------------------------------------------------------------.

echo $OUTPUT->header();
echo local_handbook_render_area_actions('findings', $context);
echo local_handbook_render_page_heading(get_string('managefindings', 'local_handbook'));

// Filter tabs: open-ish (default), each status, all.
$filters = array_merge(['openish'], finding_service::statuses(), ['all']);
$filterlinks = '';
foreach ($filters as $filter) {
    $label = $filter === 'openish'
        ? get_string('filteropenish', 'local_handbook')
        : ($filter === 'all' ? get_string('all', 'core')
            : get_string('findingstatus_' . $filter, 'local_handbook'));
    $classes = 'badge ' . ($filter === $statusfilter ? 'badge-primary' : 'badge-light border');
    $filterlinks .= html_writer::link(
        new moodle_url('/local/handbook/manage/findings.php', ['status' => $filter]),
        s($label), ['class' => $classes]) . ' ';
}
echo html_writer::div($filterlinks, 'mb-3 d-flex flex-wrap gap-1');

if ($statusfilter === 'openish') {
    $where = 'status IN (:s1, :s2)';
    $params = ['s1' => finding_service::STATUS_OPEN, 's2' => finding_service::STATUS_UNDER_REVIEW];
} else if ($statusfilter === 'all') {
    $where = '1 = 1';
    $params = [];
} else {
    $where = 'status = :status';
    $params = ['status' => $statusfilter];
}

$findings = $DB->get_records_select('local_handbook_finding', $where, $params,
    'timemodified DESC', '*', 0, 100);

if (!$findings) {
    echo html_writer::div(s(get_string('nofindings', 'local_handbook')), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$statusbadges = [
    finding_service::STATUS_OPEN => 'badge badge-warning',
    finding_service::STATUS_UNDER_REVIEW => 'badge badge-info',
    finding_service::STATUS_ACCEPTED => 'badge badge-primary',
    finding_service::STATUS_DISMISSED => 'badge badge-secondary',
    finding_service::STATUS_RESOLVED => 'badge badge-success',
    finding_service::STATUS_INTENTIONAL => 'badge badge-secondary',
];

foreach ($findings as $finding) {
    $reporter = core_user::get_user((int)$finding->createdby, '*', IGNORE_MISSING);

    $head = html_writer::tag('strong', '#F-' . $finding->id) . ' '
        . html_writer::span(s(get_string('findingtype_' . $finding->findingtype, 'local_handbook')),
            'badge badge-light border') . ' '
        . html_writer::span(s(get_string('findingstatus_' . $finding->status, 'local_handbook')),
            $statusbadges[$finding->status] ?? 'badge badge-secondary');

    $meta = get_string('scale_' . $finding->severity, 'local_handbook')
        . ' · ' . s($finding->source)
        . ' · ' . userdate((int)$finding->timecreated, get_string('strftimedate', 'langconfig'))
        . ($reporter ? ' · ' . fullname($reporter) : '');

    $body = html_writer::tag('h3', $head, ['class' => 'h6 mb-1']);
    $body .= html_writer::div(s($meta), 'small text-muted mb-1');
    $body .= html_writer::div(s($finding->summary), 'mb-1');
    if (trim((string)$finding->explanation) !== ''
            && trim((string)$finding->explanation) !== trim((string)$finding->summary)) {
        $body .= html_writer::div(s(shorten_text($finding->explanation, 400)), 'small mb-1');
    }

    // Affected pages.
    $pagelinks = [];
    foreach (finding_service::get_pages((int)$finding->id) as $findpage) {
        $link = html_writer::link(
            new moodle_url('/local/handbook/view.php', ['page' => $findpage->slug]),
            s($findpage->title));
        if ($findpage->anchor !== '') {
            $link .= ' ' . html_writer::span('(' . s($findpage->anchor) . ')', 'text-muted');
        }
        $pagelinks[] = $link;
    }
    if ($pagelinks) {
        $body .= html_writer::div(implode(' · ', $pagelinks), 'small mb-2');
    }

    if (trim((string)$finding->resolutionnote) !== '') {
        $body .= html_writer::div(
            html_writer::tag('strong', s(get_string('resolutionnote', 'local_handbook')) . ': ')
            . s($finding->resolutionnote), 'small mb-2');
    }

    // Status transition form.
    $options = '';
    foreach (finding_service::statuses() as $status) {
        if ($status === $finding->status) {
            continue;
        }
        $options .= html_writer::tag('option',
            s(get_string('findingstatus_' . $status, 'local_handbook')), ['value' => $status]);
    }
    $statusform = html_writer::start_tag('form', ['method' => 'post',
        'action' => $url->out(false),
        'class' => 'd-flex flex-wrap gap-2 align-items-center']);
    $statusform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',
        'value' => 'setstatus']);
    $statusform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',
        'value' => $finding->id]);
    $statusform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'status',
        'value' => $statusfilter]);
    $statusform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',
        'value' => sesskey()]);
    $statusform .= html_writer::tag('select', $options,
        ['name' => 'newstatus', 'class' => 'custom-select custom-select-sm w-auto',
            'aria-label' => get_string('status', 'core')]);
    $statusform .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'note',
        'class' => 'form-control form-control-sm', 'style' => 'max-width: 24rem;',
        'placeholder' => get_string('resolutionnote', 'local_handbook')]);
    $statusform .= html_writer::tag('button', s(get_string('update', 'core')),
        ['type' => 'submit', 'class' => 'btn btn-outline-secondary btn-sm']);
    $statusform .= html_writer::end_tag('form');

    $body .= $statusform;

    echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-3');
}

echo $OUTPUT->footer();
