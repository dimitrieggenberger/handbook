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
 * Reports: editorial health, path completion, page acknowledgements
 * (specification 12.5, 15.3).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\local\service\report_service;

$report = optional_param('report', 'health', PARAM_ALPHA);
$pathid = optional_param('pathid', 0, PARAM_INT);
$pageid = optional_param('pageid', 0, PARAM_INT);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:viewreports', $context);

$url = new moodle_url('/local/handbook/manage/reports.php', ['report' => $report]);
local_handbook_apply_page_setup($url, $context, 'reports',
    get_string('reports', 'local_handbook'));

echo $OUTPUT->header();
echo local_handbook_render_area_actions('reports', $context);
echo local_handbook_render_page_heading(get_string('reports', 'local_handbook'));

// Report switcher.
$tabs = [
    'health' => get_string('reporthealth', 'local_handbook'),
    'paths' => get_string('reportpaths', 'local_handbook'),
    'page' => get_string('reportpageacks', 'local_handbook'),
];
$tablinks = '';
foreach ($tabs as $key => $label) {
    $classes = 'badge ' . ($key === $report ? 'badge-primary' : 'badge-light border');
    $tablinks .= html_writer::link(
        new moodle_url('/local/handbook/manage/reports.php', ['report' => $key]),
        s($label), ['class' => $classes]) . ' ';
}
echo html_writer::div($tablinks, 'mb-3 d-flex flex-wrap gap-1');

/**
 * Render a compact list of pages as a card.
 *
 * @param stdClass[] $pages Page records.
 * @param callable $metacallback Returns the meta line for a page.
 * @return string
 */
function local_handbook_report_pagelist(array $pages, callable $metacallback): string {
    if (!$pages) {
        return html_writer::div('—', 'text-muted small');
    }
    $items = '';
    foreach ($pages as $page) {
        $items .= html_writer::tag('li',
            html_writer::link(new moodle_url('/local/handbook/view.php', ['page' => $page->slug]),
                s($page->title))
            . html_writer::span(s($metacallback($page)), 'page-meta'));
    }
    return html_writer::tag('ul', $items, ['class' => 'local-handbook-pagelist']);
}

if ($report === 'health') {
    $health = report_service::editorial_health();

    echo html_writer::div(
        s(get_string('openfindingscount', 'local_handbook', $health->openfindings)) . ' · '
        . html_writer::link(new moodle_url('/local/handbook/manage/findings.php'),
            s(get_string('managefindings', 'local_handbook'))),
        'alert alert-secondary'
    );

    $sections = [
        'reportoverdue' => [$health->overduereview,
            static fn($page) => get_string('reviewdate', 'local_handbook') . ': '
                . userdate((int)$page->reviewdate, get_string('strftimedate', 'langconfig'))],
        'reportmissingowner' => [$health->missingowner,
            static fn($page) => get_string('contenttype_' . $page->contenttype, 'local_handbook')],
        'reportneverpublished' => [$health->neverpublished,
            static fn($page) => get_string('lastupdated', 'local_handbook') . ': '
                . userdate((int)$page->timemodified, get_string('strftimedate', 'langconfig'))],
    ];
    echo html_writer::start_div('row');
    foreach ($sections as $stringkey => [$pages, $meta]) {
        echo html_writer::div(
            html_writer::div(
                html_writer::div(
                    html_writer::tag('h3', s(get_string($stringkey, 'local_handbook'))
                        . ' ' . html_writer::span((string)count($pages), 'badge badge-secondary'),
                        ['class' => 'h6 text-uppercase text-muted mb-3'])
                    . local_handbook_report_pagelist($pages, $meta),
                    'card-body'),
                'card mb-3 flex-fill'),
            'col-lg-4 d-flex');
    }
    echo html_writer::end_div();

    // Aging drafts.
    echo html_writer::tag('h3', s(get_string('reportagingdrafts', 'local_handbook')),
        ['class' => 'h5 mb-3 mt-2']);
    if (!$health->agingdrafts) {
        echo html_writer::div(s(get_string('nodraftsinreview', 'local_handbook')), 'alert alert-info');
    } else {
        $items = '';
        foreach ($health->agingdrafts as $draft) {
            $items .= html_writer::tag('li',
                html_writer::link(new moodle_url('/local/handbook/view.php', ['page' => $draft->slug]),
                    s($draft->title))
                . html_writer::span(
                    s(get_string('versionnumber', 'local_handbook', (int)$draft->versionnumber)
                    . ' · ' . get_string('status_' . $draft->status, 'local_handbook')
                    . ' · ' . userdate((int)$draft->timemodified, get_string('strftimedate', 'langconfig'))),
                    'page-meta'));
        }
        echo html_writer::div(
            html_writer::div(html_writer::tag('ul', $items, ['class' => 'local-handbook-pagelist']),
                'card-body'),
            'card mb-3');
    }
}

if ($report === 'paths') {
    $paths = $DB->get_records('local_handbook_path', [], 'schoolyear DESC, name ASC');
    if (!$paths) {
        echo html_writer::div(s(get_string('nopathsyet', 'local_handbook')), 'alert alert-info');
    } else {
        if (!$pathid || !isset($paths[$pathid])) {
            $pathid = (int)array_key_first($paths);
        }

        // Path selector.
        $links = '';
        foreach ($paths as $path) {
            $classes = 'badge ' . ((int)$path->id === $pathid ? 'badge-primary' : 'badge-light border');
            $links .= html_writer::link(new moodle_url('/local/handbook/manage/reports.php',
                ['report' => 'paths', 'pathid' => $path->id]), s($path->name), ['class' => $classes]) . ' ';
        }
        echo html_writer::div($links, 'mb-3 d-flex flex-wrap gap-1');

        $completion = report_service::path_completion($paths[$pathid]);

        echo html_writer::tag('p',
            s(get_string('reportpathintro', 'local_handbook', $completion->totalrequired)),
            ['class' => 'text-muted small']);

        $rows = '';
        foreach ($completion->users as $row) {
            $barclass = $row->percent >= 100 ? 'bg-success' : '';
            $rows .= html_writer::tag('tr',
                html_writer::tag('td', s(fullname($row->user)))
                . html_writer::tag('td', $row->confirmed . ' / ' . $completion->totalrequired,
                    ['class' => 'text-nowrap'])
                . html_writer::tag('td',
                    html_writer::div(
                        html_writer::div('', 'progress-bar ' . $barclass, [
                            'role' => 'progressbar',
                            'style' => 'width: ' . $row->percent . '%',
                            'aria-valuenow' => $row->percent,
                            'aria-valuemin' => 0,
                            'aria-valuemax' => 100,
                        ]),
                        'progress', ['style' => 'height: 0.5rem; min-width: 8rem;'])
                    . html_writer::span($row->percent . '%', 'small text-muted ml-2'))
            );
        }
        echo html_writer::start_div('table-responsive');
        echo html_writer::tag('table',
            html_writer::tag('thead', html_writer::tag('tr',
                html_writer::tag('th', s(get_string('user', 'core')))
                . html_writer::tag('th', s(get_string('pathprogressshort', 'local_handbook')))
                . html_writer::tag('th', '')))
            . html_writer::tag('tbody', $rows),
            ['class' => 'table table-sm']);
        echo html_writer::end_div();
    }
}

if ($report === 'page') {
    $pages = $DB->get_records_select('local_handbook_page',
        'requiredreading = 1 AND publishedrevisionid > 0 AND archived = 0', [], 'title ASC');
    if (!$pages) {
        echo html_writer::div(s(get_string('norequiredpages', 'local_handbook')), 'alert alert-info');
    } else {
        if (!$pageid || !isset($pages[$pageid])) {
            $pageid = (int)array_key_first($pages);
        }

        $links = '';
        foreach ($pages as $page) {
            $classes = 'badge ' . ((int)$page->id === $pageid ? 'badge-primary' : 'badge-light border');
            $links .= html_writer::link(new moodle_url('/local/handbook/manage/reports.php',
                ['report' => 'page', 'pageid' => $page->id]),
                s(shorten_text($page->title, 60)), ['class' => $classes]) . ' ';
        }
        echo html_writer::div($links, 'mb-3 d-flex flex-wrap gap-1');

        $acks = report_service::page_acknowledgements($pages[$pageid]);

        echo html_writer::start_div('row');

        $confirmeditems = '';
        foreach ($acks->confirmed as $row) {
            $confirmeditems .= html_writer::tag('li', s(fullname($row->user))
                . html_writer::span(
                    s(get_string('versionnumber', 'local_handbook', $row->version) . ' · '
                    . userdate($row->time, get_string('strftimedate', 'langconfig'))), 'page-meta'));
        }
        echo html_writer::div(
            html_writer::div(
                html_writer::div(
                    html_writer::tag('h3',
                        s(get_string('reportconfirmed', 'local_handbook'))
                        . ' ' . html_writer::span((string)count($acks->confirmed), 'badge badge-success'),
                        ['class' => 'h6 text-uppercase text-muted mb-3'])
                    . ($confirmeditems !== ''
                        ? html_writer::tag('ul', $confirmeditems, ['class' => 'local-handbook-pagelist'])
                        : html_writer::div('—', 'text-muted small')),
                    'card-body'),
                'card mb-3 flex-fill'),
            'col-lg-6 d-flex');

        $pendingitems = '';
        foreach ($acks->pending as $user) {
            $pendingitems .= html_writer::tag('li', s(fullname($user)));
        }
        echo html_writer::div(
            html_writer::div(
                html_writer::div(
                    html_writer::tag('h3',
                        s(get_string('reportpending', 'local_handbook'))
                        . ' ' . html_writer::span((string)count($acks->pending), 'badge badge-warning'),
                        ['class' => 'h6 text-uppercase text-muted mb-3'])
                    . ($pendingitems !== ''
                        ? html_writer::tag('ul', $pendingitems, ['class' => 'local-handbook-pagelist'])
                        : html_writer::div('—', 'text-muted small')),
                    'card-body'),
                'card mb-3 flex-fill'),
            'col-lg-6 d-flex');

        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
