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
 * Reading dashboard: who has confirmed how much reading, person by person.
 *
 * Sorted most-read first by default. Staff on leave can be hidden — a
 * reversible view filter that also removes them from the aggregates; no
 * reading data is ever modified. No AI/MCP surface reads this data.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\local\service\reading_dashboard;

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:viewreports', $context);

$audience = optional_param('audience', 'staff', PARAM_ALPHANUMEXT);
$scope = optional_param('scope', 'required', PARAM_ALPHANUMEXT);
$dir = optional_param('dir', 'desc', PARAM_ALPHA);
$showhidden = optional_param('showhidden', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHA);

$baseparams = ['audience' => $audience, 'scope' => $scope, 'dir' => $dir,
    'showhidden' => (int)$showhidden];
$url = new moodle_url('/local/handbook/manage/readers.php', $baseparams);
local_handbook_apply_page_setup($url, $context, 'readers',
    get_string('readingdashboard', 'local_handbook'));

// ---- Hide / show / note actions -------------------------------------------.

if ($action === 'hide' && confirm_sesskey()) {
    reading_dashboard::hide(required_param('user', PARAM_INT),
        trim(optional_param('note', '', PARAM_TEXT)), (int)$USER->id);
    redirect($url);
}
if ($action === 'show' && confirm_sesskey()) {
    reading_dashboard::unhide(required_param('user', PARAM_INT));
    redirect($url);
}
if ($action === 'note' && confirm_sesskey()) {
    reading_dashboard::hide(required_param('user', PARAM_INT),
        trim(optional_param('note', '', PARAM_TEXT)), (int)$USER->id);
    redirect(new moodle_url($url, ['showhidden' => 1]));
}

// ---- Data ------------------------------------------------------------------.

$audienceoptions = reading_dashboard::audience_options();
$scopeoptions = reading_dashboard::scope_options();
if (!isset($audienceoptions[$audience])) {
    $audience = 'staff';
}
if (!isset($scopeoptions[$scope])) {
    $scope = 'required';
}

$users = reading_dashboard::audience_users($audience);
$pageset = reading_dashboard::page_set($scope);
$rows = reading_dashboard::build_rows($users, $pageset);
$hiddenmap = reading_dashboard::hidden_map();

$visiblerows = [];
$hiddenrows = [];
foreach ($rows as $userid => $row) {
    if (isset($hiddenmap[$userid])) {
        $row->hideinfo = $hiddenmap[$userid];
        $hiddenrows[$userid] = $row;
    } else {
        $visiblerows[$userid] = $row;
    }
}

$sorter = static function (stdClass $a, stdClass $b) use ($dir): int {
    $cmp = $b->percent <=> $a->percent;
    if ($cmp === 0) {
        $cmp = $b->lastactivity <=> $a->lastactivity;
    }
    return $dir === 'asc' ? -$cmp : $cmp;
};
usort($visiblerows, $sorter);
usort($hiddenrows, $sorter);

// Aggregates over VISIBLE people only: hidden colleagues leave the numbers.
$count = count($visiblerows);
$sumpercent = 0;
$withstale = 0;
$neverread = 0;
foreach ($visiblerows as $row) {
    $sumpercent += $row->percent;
    if ($row->stale > 0) {
        $withstale++;
    }
    if ($row->confirmed === 0 && $row->stale === 0) {
        $neverread++;
    }
}
$avgpercent = $count > 0 ? (int)round($sumpercent / $count) : 0;

// ---- CSV export ------------------------------------------------------------.

if ($action === 'export' && confirm_sesskey()) {
    require_once($CFG->libdir . '/csvlib.class.php');
    $export = new csv_export_writer();
    $export->set_filename('handbook-reading-' . userdate(time(), '%Y%m%d'));
    $export->add_data([
        get_string('fullname'), get_string('email'),
        get_string('dashconfirmed', 'local_handbook'),
        get_string('dashstale', 'local_handbook'),
        get_string('dashpending', 'local_handbook'),
        get_string('dashtotal', 'local_handbook'), '%',
        get_string('dashlastactivity', 'local_handbook'),
        get_string('dashhidden', 'local_handbook'),
        get_string('dashhidenote', 'local_handbook'),
    ]);
    foreach (array_merge($visiblerows, $hiddenrows) as $row) {
        $export->add_data([
            fullname($row->user), $row->user->email,
            $row->confirmed, $row->stale, $row->pending, $row->total, $row->percent,
            $row->lastactivity ? userdate($row->lastactivity, get_string('strftimedatetimeshort', 'langconfig')) : '-',
            isset($row->hideinfo) ? 1 : 0,
            $row->hideinfo->note ?? '',
        ]);
    }
    $export->download_file();
    exit;
}

// ---- Output ----------------------------------------------------------------.

echo $OUTPUT->header();
echo local_handbook_render_area_actions('readers', $context);
echo local_handbook_render_page_heading(get_string('readingdashboard', 'local_handbook'));

// Filter controls (GET form; sesskey not needed for a read-only view).
$select = static function (string $name, array $options, string $current): string {
    $html = html_writer::start_tag('select', ['name' => $name,
        'class' => 'custom-select custom-select-sm w-auto', 'onchange' => 'this.form.submit()']);
    foreach ($options as $value => $label) {
        $attrs = ['value' => $value];
        if ((string)$value === $current) {
            $attrs['selected'] = 'selected';
        }
        $html .= html_writer::tag('option', s($label), $attrs);
    }
    return $html . html_writer::end_tag('select');
};

$controls = html_writer::start_tag('form', ['method' => 'get',
    'action' => (new moodle_url('/local/handbook/manage/readers.php'))->out(false),
    'class' => 'd-flex flex-wrap gap-2 align-items-center mb-3']);
$controls .= html_writer::tag('label', s(get_string('dashaudience', 'local_handbook')),
    ['class' => 'small text-muted mb-0 mr-1']);
$controls .= $select('audience', $audienceoptions, $audience);
$controls .= html_writer::tag('label', s(get_string('dashscope', 'local_handbook')),
    ['class' => 'small text-muted mb-0 ml-2 mr-1']);
$controls .= $select('scope', $scopeoptions, $scope);
$controls .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'dir', 'value' => $dir]);
$controls .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'showhidden', 'value' => (int)$showhidden]);
$controls .= html_writer::tag('noscript',
    html_writer::tag('button', s(get_string('go')), ['class' => 'btn btn-secondary btn-sm ml-2']));
$controls .= html_writer::end_tag('form');
echo $controls;

echo html_writer::start_div('d-flex flex-wrap gap-2 align-items-center mb-3');
echo html_writer::link(new moodle_url($url, ['showhidden' => (int)!$showhidden]),
    s($showhidden
        ? get_string('dashhidehidden', 'local_handbook')
        : get_string('dashshowhidden', 'local_handbook', count($hiddenrows))),
    ['class' => 'btn btn-outline-secondary btn-sm']);
echo html_writer::link(new moodle_url($url, ['action' => 'export', 'sesskey' => sesskey()]),
    s(get_string('dashexportcsv', 'local_handbook')),
    ['class' => 'btn btn-outline-secondary btn-sm']);
echo html_writer::end_div();

// Summary tiles (visible people only).
$tile = static function (string $value, string $label, string $modifier = ''): string {
    return html_writer::div(
        html_writer::div(s($value), 'v' . ($modifier ? ' text-' . $modifier : ''))
        . html_writer::div(s($label), 'k'),
        'local-handbook-dash-tile');
};
echo html_writer::div(
    $tile((string)$count, get_string('dashtilepeople', 'local_handbook'))
    . $tile($avgpercent . '%', get_string('dashtileaverage', 'local_handbook'), 'success')
    . $tile((string)$withstale, get_string('dashtilestale', 'local_handbook'), 'warning')
    . $tile((string)$neverread, get_string('dashtilenever', 'local_handbook'), 'danger'),
    'local-handbook-dash-tiles');

if (!$pageset) {
    echo html_writer::div(s(get_string('dashnopages', 'local_handbook')), 'alert alert-info');
}

// One person-row renderer for both sections.
$renderrow = static function (stdClass $row, bool $hidden) use ($url): string {
    $user = $row->user;
    $initials = core_text::strtoupper(core_text::substr($user->firstname, 0, 1)
        . core_text::substr($user->lastname, 0, 1));

    $okwidth = $row->total > 0 ? $row->confirmed * 100 / $row->total : 0;
    $stalewidth = $row->total > 0 ? $row->stale * 100 / $row->total : 0;
    $bar = html_writer::div(
        html_writer::div('', 'seg-ok', ['style' => 'width:' . round($okwidth, 1) . '%'])
        . html_writer::div('', 'seg-stale', ['style' => 'width:' . round($stalewidth, 1) . '%']),
        'local-handbook-dash-bar');

    $stalechip = $row->stale > 0
        ? html_writer::span(s(get_string('dashstalechip', 'local_handbook', $row->stale)),
            'local-handbook-dash-rechip')
        : '';
    $nums = html_writer::span(
        html_writer::tag('b', $row->percent . '%')
        . ' ' . html_writer::span($row->confirmed . '/' . $row->total, 'frac text-muted')
        . $stalechip, 'local-handbook-dash-nums');

    $last = $row->lastactivity
        ? html_writer::span(s(userdate($row->lastactivity,
            get_string('strftimedatefullshort', 'langconfig'))), 'small text-muted')
        : html_writer::span(s(get_string('dashnever', 'local_handbook')),
            'small font-weight-bold text-danger');

    $namebits = html_writer::span(s(fullname($user)), 'nm');
    if ($hidden && isset($row->hideinfo)) {
        if ($row->hideinfo->note !== '') {
            $namebits .= ' ' . html_writer::span(s($row->hideinfo->note), 'local-handbook-dash-notechip');
        }
        $hider = (object)['firstname' => $row->hideinfo->firstname,
            'lastname' => $row->hideinfo->lastname,
            'firstnamephonetic' => $row->hideinfo->firstnamephonetic ?? '',
            'lastnamephonetic' => $row->hideinfo->lastnamephonetic ?? '',
            'middlename' => $row->hideinfo->middlename ?? '',
            'alternatename' => $row->hideinfo->alternatename ?? ''];
        $namebits .= html_writer::div(s(get_string('dashhiddenby', 'local_handbook', (object)[
            'name' => fullname($hider),
            'date' => userdate((int)$row->hideinfo->timecreated,
                get_string('strftimedatefullshort', 'langconfig')),
        ])), 'small text-muted');
    }

    if ($hidden) {
        // Restore + editable note.
        $actions = html_writer::start_tag('form', ['method' => 'post',
            'action' => $url->out(false), 'class' => 'd-flex gap-1 align-items-center']);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'note']);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'user', 'value' => $user->id]);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        foreach (['audience', 'scope', 'dir'] as $keep) {
            $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $keep,
                'value' => $url->get_param($keep)]);
        }
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'showhidden', 'value' => 1]);
        $actions .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'note',
            'value' => $row->hideinfo->note ?? '', 'class' => 'form-control form-control-sm w-auto',
            'placeholder' => get_string('dashhidenote', 'local_handbook')]);
        $actions .= html_writer::tag('button', s(get_string('save', 'core')),
            ['type' => 'submit', 'class' => 'btn btn-outline-secondary btn-sm']);
        $actions .= html_writer::end_tag('form');
        $actions .= html_writer::link(new moodle_url($url, ['action' => 'show',
                'user' => $user->id, 'sesskey' => sesskey(), 'showhidden' => 1]),
            s(get_string('dashshow', 'local_handbook')),
            ['class' => 'btn btn-outline-primary btn-sm ml-1']);
    } else {
        $actions = html_writer::link(new moodle_url($url, ['action' => 'hide',
                'user' => $user->id, 'sesskey' => sesskey()]),
            s(get_string('dashhide', 'local_handbook')),
            ['class' => 'btn btn-outline-secondary btn-sm']);
    }

    return html_writer::div(
        html_writer::div(
            html_writer::span(s($initials), 'local-handbook-dash-av')
            . html_writer::div($namebits, 'min-width-0'),
            'local-handbook-dash-who')
        . $bar . $nums . $last
        . html_writer::div($actions, 'local-handbook-dash-actions'),
        'local-handbook-dash-row' . ($hidden ? ' is-hidden' : ''));
};

// Sort-direction header link.
echo html_writer::div(
    html_writer::link(new moodle_url($url, ['dir' => $dir === 'desc' ? 'asc' : 'desc']),
        s(get_string($dir === 'desc' ? 'dashsortdesc' : 'dashsortasc', 'local_handbook'))
        . ' ' . ($dir === 'desc' ? '↓' : '↑'),
        ['class' => 'small font-weight-bold text-uppercase text-muted']),
    'mb-1 px-2');

$list = '';
foreach ($visiblerows as $row) {
    $list .= $renderrow($row, false);
}
echo $list !== ''
    ? html_writer::div(html_writer::div($list, 'card-body py-2'), 'card mb-3')
    : html_writer::div(s(get_string('dashnousers', 'local_handbook')), 'alert alert-info');

if ($showhidden && $hiddenrows) {
    echo html_writer::tag('h3',
        s(get_string('dashhiddensection', 'local_handbook', count($hiddenrows))),
        ['class' => 'h6 text-uppercase text-muted mt-4 mb-2']);
    $list = '';
    foreach ($hiddenrows as $row) {
        $list .= $renderrow($row, true);
    }
    echo html_writer::div(html_writer::div($list, 'card-body py-2'), 'card mb-3');
}

echo html_writer::tag('p', s(get_string('dashfootnote', 'local_handbook')),
    ['class' => 'small text-muted mt-2']);

echo $OUTPUT->footer();
