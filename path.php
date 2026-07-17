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
 * Reading path view: sections, per-item acknowledgement status, progress
 * (specification 15; reading-path mockup).
 *
 * Audience assignment is a deferred decision (spec 32.1): for now every
 * active path is visible to every staff member with the view capability.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_handbook\local\service\completion_service;
use local_handbook\local\service\path_service;

$pathid = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
local_handbook_require_view($context);

$ismanager = has_capability('local/handbook:managepaths', $context);
$visiblepaths = path_service::visible_paths((int)$USER->id, $ismanager);

if ($pathid) {
    $path = $DB->get_record('local_handbook_path', ['id' => $pathid], '*', MUST_EXIST);
    if (!$ismanager && (!(int)$path->active || !path_service::is_visible($path, (int)$USER->id))) {
        throw new moodle_exception('errorpathnotvisible', 'local_handbook');
    }
} else {
    $path = $visiblepaths ? reset($visiblepaths) : null;
}

$url = new moodle_url('/local/handbook/path.php', $path ? ['id' => $path->id] : []);
local_handbook_apply_page_setup($url, $context, 'path',
    get_string('myreadingpath', 'local_handbook'),
    $path ? format_string($path->name) : get_string('myreadingpath', 'local_handbook'));

echo $OUTPUT->header();
echo local_handbook_render_area_actions('path', $context);

if (!$path) {
    echo local_handbook_render_page_heading(get_string('myreadingpath', 'local_handbook'));
    echo html_writer::div(s(get_string('nopathsyet', 'local_handbook')), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$actions = '';
if (has_capability('local/handbook:managepaths', $context)) {
    $actions .= html_writer::link(
        new moodle_url('/local/handbook/manage/paths.php', ['action' => 'edit', 'id' => $path->id]),
        html_writer::tag('i', '', ['class' => 'fa-solid fa-pen me-2', 'aria-hidden' => 'true'])
            . s(get_string('editpath', 'local_handbook')),
        ['class' => 'btn btn-outline-secondary btn-sm']
    );
}

echo html_writer::div(
    s(get_string('myreadingpath', 'local_handbook'))
    . ($path->schoolyear !== '' ? ' · ' . s($path->schoolyear) : ''),
    'local-handbook-category-trail'
);
echo local_handbook_render_page_heading(format_string($path->name), $actions);

// Switcher when several paths are visible to this user.
if (count($visiblepaths) > 1) {
    $links = '';
    foreach ($visiblepaths as $candidate) {
        $classes = 'badge ' . ((int)$candidate->id === (int)$path->id
            ? 'badge-primary' : 'badge-light border');
        $links .= html_writer::link(new moodle_url('/local/handbook/path.php', ['id' => $candidate->id]),
            s($candidate->name), ['class' => $classes]) . ' ';
    }
    echo html_writer::div($links, 'mb-3 d-flex flex-wrap gap-1');
}

if (trim((string)$path->description) !== '') {
    echo html_writer::div(
        format_text($path->description, $path->descriptionformat, ['context' => $context]),
        'mb-3'
    );
}

// ---- Items grouped into sections, with per-user acknowledgement status --.

// Archived pages drop out of active reading paths (spec 23), consistent with
// the progress calculation in path_service.
$sql = "SELECT i.*, p.slug, p.title, p.requiredreading, p.publishedrevisionid, p.archived,
               p.categoryid, p.contenttype
          FROM {local_handbook_pathitem} i
          JOIN {local_handbook_page} p ON p.id = i.pageid
         WHERE i.pathid = :pathid AND p.archived = 0
      ORDER BY i.sortorder ASC, i.id ASC";
$items = $DB->get_records_sql($sql, ['pathid' => $path->id]);

$sections = [];
foreach ($items as $item) {
    $sections[$item->sectionname][] = $item;
}

// Completion counts every required item regardless of the page's global
// required-reading flag (spec 8.3); optional items never block completion.
$requiredtotal = 0;
$confirmedtotal = 0;
$statusbyitem = [];
foreach ($items as $item) {
    $status = completion_service::completion_status((int)$USER->id, (object)[
        'id' => $item->pageid,
        'publishedrevisionid' => $item->publishedrevisionid,
    ]);
    $statusbyitem[$item->id] = $status;

    if ((int)$item->required) {
        $requiredtotal++;
        if ($status->status === completion_service::STATUS_COMPLETED) {
            $confirmedtotal++;
        }
    }
}

// The next required item still pending or needing a renewed read drives the
// "Continuar" card and the current-section highlight.
$nextitem = null;
foreach ($items as $item) {
    if (!(int)$item->required) {
        continue;
    }
    if ($statusbyitem[$item->id]->status !== completion_service::STATUS_COMPLETED) {
        $nextitem = $item;
        break;
    }
}

// Progress summary card.
$percent = $requiredtotal > 0 ? (int)round($confirmedtotal / $requiredtotal * 100) : 0;
$summary = html_writer::div(
    html_writer::tag('strong', s(get_string('pathprogress', 'local_handbook', (object)[
        'confirmed' => $confirmedtotal,
        'total' => $requiredtotal,
    ]))),
    'mb-2'
);
$summary .= html_writer::div(
    html_writer::div('', 'progress-bar', [
        'role' => 'progressbar',
        'style' => 'width: ' . $percent . '%',
        'aria-valuenow' => $confirmedtotal,
        'aria-valuemin' => 0,
        'aria-valuemax' => max(1, $requiredtotal),
    ]),
    'progress mb-2'
);
echo html_writer::div(html_writer::div($summary, 'card-body'),
    'card mb-3 local-handbook-path-summary');

// "Continuar" card: jump to the next pending required item.
if ($nextitem !== null) {
    $continuestate = $statusbyitem[$nextitem->id]->status === completion_service::STATUS_RECONFIRM
        ? get_string('reconfirmitem', 'local_handbook')
        : get_string('pendingitem', 'local_handbook');
    echo html_writer::div(
        html_writer::div(
            html_writer::tag('h3',
                html_writer::tag('i', '', ['class' => 'fa-solid fa-forward me-2 text-primary',
                    'aria-hidden' => 'true'])
                . s(get_string('continuepath', 'local_handbook')),
                ['class' => 'h6 text-uppercase text-muted mb-2'])
            . html_writer::tag('p',
                html_writer::link(new moodle_url('/local/handbook/view.php',
                        ['page' => $nextitem->slug, 'path' => $path->id]),
                    html_writer::tag('strong', s($nextitem->title)))
                . html_writer::span(' · ' . s($nextitem->sectionname) . ' · ' . s($continuestate),
                    'small text-muted'),
                ['class' => 'mb-2'])
            . html_writer::link(
                new moodle_url('/local/handbook/view.php',
                    ['page' => $nextitem->slug, 'path' => $path->id]),
                html_writer::tag('i', '', ['class' => 'fa-solid fa-arrow-right me-2', 'aria-hidden' => 'true'])
                    . s(get_string('continuereading', 'local_handbook')),
                ['class' => 'btn btn-primary btn-sm']),
            'card-body'),
        'card mb-3');
}

if (!$sections) {
    echo html_writer::div(s(get_string('emptypath', 'local_handbook')), 'alert alert-info');
}

$sectionnumber = 0;
foreach ($sections as $sectionname => $sectionitems) {
    $sectionnumber++;

    $sectionconfirmed = 0;
    $sectionrequired = 0;
    foreach ($sectionitems as $item) {
        if ((int)$item->required) {
            $sectionrequired++;
            if ($statusbyitem[$item->id]->status === completion_service::STATUS_COMPLETED) {
                $sectionconfirmed++;
            }
        }
    }

    $iscurrent = $nextitem !== null && $nextitem->sectionname === $sectionname;

    $header = html_writer::tag('h3',
        html_writer::span($sectionnumber . '.', 'section-number') . ' ' . s($sectionname)
        . ($iscurrent
            ? ' ' . html_writer::span(s(get_string('currentsection', 'local_handbook')),
                'badge badge-primary ml-2')
            : ''),
        ['class' => 'h6 mb-0']);
    $progress = html_writer::span(
        s(get_string('sectionprogress', 'local_handbook', (object)[
            'confirmed' => $sectionconfirmed,
            'total' => $sectionrequired,
        ])),
        'section-progress'
    );

    $rows = '';
    foreach ($sectionitems as $item) {
        $status = $statusbyitem[$item->id];
        $pageurl = new moodle_url('/local/handbook/view.php',
        ['page' => $item->slug, 'path' => $path->id]);

        $completed = $status->status === completion_service::STATUS_COMPLETED;
        if (!(int)$item->required) {
            // Optional items never block completion, but show a tick once read.
            $rowclass = $completed ? 'is-confirmed' : 'is-pending';
            $icon = $completed ? 'fa-circle-check' : 'fa-bolt';
            $state = get_string('optionalitem', 'local_handbook');
        } else if ($completed) {
            $rowclass = 'is-confirmed';
            $icon = 'fa-circle-check';
            $state = $status->record
                ? get_string('ackconfirmedshort', 'local_handbook',
                    userdate((int)$status->record->timecompleted, get_string('strftimedate', 'langconfig')))
                : get_string('status_published', 'local_handbook');
        } else if ($status->status === completion_service::STATUS_RECONFIRM) {
            $rowclass = 'is-reconfirm';
            $icon = 'fa-rotate';
            $state = get_string('reconfirmitem', 'local_handbook');
        } else {
            $rowclass = 'is-pending';
            $icon = 'fa-book-open';
            $state = get_string('readitem', 'local_handbook');
        }

        $titlehtml = html_writer::link($pageurl, s($item->title));
        if ($item->quizcmid) {
            $titlehtml .= ' ' . html_writer::link(
                new moodle_url('/mod/quiz/view.php', ['id' => $item->quizcmid]),
                html_writer::tag('i', '', ['class' => 'fa-solid fa-clipboard-check me-1',
                    'aria-hidden' => 'true'])
                . s(get_string('connectedquiz', 'local_handbook')),
                ['class' => 'badge badge-light border ml-2']);
        }

        $rows .= html_writer::tag('li',
            html_writer::span(html_writer::tag('i', '',
                ['class' => 'fa-solid ' . $icon, 'aria-hidden' => 'true']), 'item-status-icon')
            . html_writer::span($titlehtml, 'item-title')
            . html_writer::span(s($state), 'item-state'),
            ['class' => $rowclass]);
    }

    echo html_writer::div(
        html_writer::div(
            html_writer::div($header . $progress, 'section-header')
            . html_writer::tag('ul', $rows, ['class' => 'local-handbook-path-items']),
            'card-body py-3'
        ),
        'card mb-2 local-handbook-path-section' . ($iscurrent ? ' is-current' : '')
    );
}

echo $OUTPUT->footer();
