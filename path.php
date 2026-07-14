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

use local_handbook\local\service\ack_service;

$pathid = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
local_handbook_require_view($context);

if ($pathid) {
    $path = $DB->get_record('local_handbook_path', ['id' => $pathid], '*', MUST_EXIST);
} else {
    $paths = $DB->get_records('local_handbook_path', ['active' => 1], 'schoolyear DESC, name ASC',
        '*', 0, 1);
    $path = $paths ? reset($paths) : null;
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

if (trim((string)$path->description) !== '') {
    echo html_writer::div(
        format_text($path->description, $path->descriptionformat, ['context' => $context]),
        'mb-3'
    );
}

// ---- Items grouped into sections, with per-user acknowledgement status --.

$sql = "SELECT i.*, p.slug, p.title, p.requiredreading, p.publishedrevisionid, p.archived,
               p.categoryid, p.contenttype
          FROM {local_handbook_pathitem} i
          JOIN {local_handbook_page} p ON p.id = i.pageid
         WHERE i.pathid = :pathid
      ORDER BY i.sortorder ASC, i.id ASC";
$items = $DB->get_records_sql($sql, ['pathid' => $path->id]);

$sections = [];
foreach ($items as $item) {
    $sections[$item->sectionname][] = $item;
}

$requiredtotal = 0;
$confirmedtotal = 0;
$statusbyitem = [];
foreach ($items as $item) {
    $pagerecord = (object)[
        'id' => $item->pageid,
        'requiredreading' => $item->requiredreading,
        'publishedrevisionid' => $item->publishedrevisionid,
    ];
    $status = ack_service::get_status((int)$USER->id, $pagerecord);
    $statusbyitem[$item->id] = $status;

    if ((int)$item->required && (int)$item->requiredreading) {
        $requiredtotal++;
        if ($status->status === ack_service::STATUS_CONFIRMED) {
            $confirmedtotal++;
        }
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

if (!$sections) {
    echo html_writer::div(s(get_string('emptypath', 'local_handbook')), 'alert alert-info');
}

$sectionnumber = 0;
foreach ($sections as $sectionname => $sectionitems) {
    $sectionnumber++;

    $sectionconfirmed = 0;
    $sectionrequired = 0;
    foreach ($sectionitems as $item) {
        if ((int)$item->required && (int)$item->requiredreading) {
            $sectionrequired++;
            if ($statusbyitem[$item->id]->status === ack_service::STATUS_CONFIRMED) {
                $sectionconfirmed++;
            }
        }
    }

    $header = html_writer::tag('h3',
        html_writer::span($sectionnumber . '.', 'section-number') . ' ' . s($sectionname),
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
        $pageurl = new moodle_url('/local/handbook/view.php', ['page' => $item->slug]);

        if (!(int)$item->required) {
            $rowclass = 'is-pending';
            $icon = 'fa-bolt';
            $state = get_string('optionalitem', 'local_handbook');
        } else if ($status->status === ack_service::STATUS_CONFIRMED) {
            $rowclass = 'is-confirmed';
            $icon = 'fa-circle-check';
            $state = $status->ack
                ? get_string('ackconfirmedshort', 'local_handbook',
                    userdate((int)$status->ack->timeacknowledged, get_string('strftimedate', 'langconfig')))
                : get_string('status_published', 'local_handbook');
        } else if ($status->status === ack_service::STATUS_RECONFIRM) {
            $rowclass = 'is-reconfirm';
            $icon = 'fa-rotate';
            $state = get_string('reconfirmitem', 'local_handbook');
        } else if ($status->status === ack_service::STATUS_NOT_REQUIRED) {
            $rowclass = 'is-pending';
            $icon = 'fa-book-open';
            $state = get_string('readitem', 'local_handbook');
        } else {
            $rowclass = 'is-pending';
            $icon = 'fa-circle';
            $state = get_string('pendingitem', 'local_handbook');
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
        'card mb-2 local-handbook-path-section'
    );
}

echo $OUTPUT->footer();
