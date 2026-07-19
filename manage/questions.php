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
 * Comprehension questions of a page — edited on the WORKING DRAFT, exactly
 * like content: importing creates or reuses the draft, the reviewer sees
 * the questions with the revision, and they go live on publication.
 * Readers keep answering the published revision's questions meanwhile.
 *
 * Human-only — the AI can author XML but has no import surface.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\local\service\page_service;
use local_handbook\local\service\quiz_service;

$pageid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:edit', $context);

$record = $DB->get_record('local_handbook_page', ['id' => $pageid], '*', MUST_EXIST);

$url = new moodle_url('/local/handbook/manage/questions.php', ['id' => $pageid]);
local_handbook_apply_page_setup($url, $context, 'home',
    get_string('managequestions', 'local_handbook'));

$publishedid = (int)$record->publishedrevisionid;
$working = page_service::get_working_revision($pageid);
$workinglocked = $working !== null
    && !in_array($working->status, page_service::EDITABLE_STATUSES, true);

// ---- Actions: always against the working draft ----------------------------.

$report = null;
if (($action === 'import' || $action === 'deleteall') && confirm_sesskey()) {
    if ($workinglocked) {
        redirect($url, get_string('qdraftlocked', 'local_handbook', (object)[
            'version' => (int)$working->versionnumber,
            'status' => get_string('status_' . $working->status, 'local_handbook'),
        ]), null, \core\output\notification::NOTIFY_WARNING);
    }

    if ($action === 'import') {
        $xml = optional_param('xml', '', PARAM_RAW);
        $report = quiz_service::parse_xml($xml);
        if (!$report->errors && $report->questions) {
            if (!$working) {
                $working = page_service::create_revision_draft($record);
            }
            quiz_service::import((int)$working->id, $report->questions, (int)$USER->id);
            $report->imported = count($report->questions);
        }
    } else {
        if (!$working) {
            $working = page_service::create_revision_draft($record);
        }
        quiz_service::delete_all((int)$working->id);
        redirect($url, get_string('qdeleted', 'local_handbook'));
    }
}

$publishedquestions = quiz_service::get_questions($publishedid);
$draftquestions = $working ? quiz_service::get_questions((int)$working->id) : null;
$questionschanged = $working
    && quiz_service::fingerprint((int)$working->id) !== quiz_service::fingerprint($publishedid);

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);
echo local_handbook_render_page_heading(get_string('managequestions', 'local_handbook')
    . ': ' . format_string($record->title));

echo html_writer::tag('p', s(get_string('questionsintro', 'local_handbook')),
    ['class' => 'text-muted']);

if ($report !== null) {
    foreach ($report->errors as $error) {
        echo html_writer::div(s($error), 'alert alert-danger');
    }
    foreach ($report->warnings as $warning) {
        echo html_writer::div(s($warning), 'alert alert-warning');
    }
    if (!empty($report->imported)) {
        echo html_writer::div(s(get_string('qimporteddraft', 'local_handbook', (object)[
            'count' => (int)$report->imported,
            'version' => (int)$working->versionnumber,
        ])), 'alert alert-success');
    }
}

// Shared read-only list renderer.
$renderlist = static function (array $questions) use ($context): string {
    $rows = '';
    $number = 0;
    foreach ($questions as $question) {
        $number++;
        $meta = [];
        $meta[] = $question->qtype === quiz_service::TYPE_ORDERING
            ? get_string('qtypeordering', 'local_handbook')
            : get_string('qtypemultichoice', 'local_handbook');
        if (trim((string)$question->bloomlabel) !== '') {
            $meta[] = $question->bloomlabel;
        }
        $meta[] = get_string('qoptioncount', 'local_handbook', count($question->options));

        $optionlist = '';
        foreach (quiz_service::sorted_options($question) as $index => $option) {
            $marker = $question->qtype === quiz_service::TYPE_ORDERING
                ? ($index + 1) . '.'
                : ((int)$option->iscorrect ? '✓' : '·');
            $optionlist .= html_writer::tag('li',
                html_writer::span(s($marker), 'mr-1 '
                    . ((int)$option->iscorrect || $question->qtype === quiz_service::TYPE_ORDERING
                        ? 'text-success font-weight-bold' : 'text-muted'))
                . ' ' . format_text($option->optiontext, FORMAT_HTML, ['context' => $context]));
        }

        $rows .= html_writer::div(
            html_writer::tag('h4', $number . '. ' . s($question->title !== ''
                ? $question->title : get_string('question', 'core')), ['class' => 'h6 mb-1'])
            . html_writer::div(s(implode(' · ', $meta)), 'small text-muted mb-2')
            . ($question->questiontext !== ''
                ? html_writer::div(format_text($question->questiontext, FORMAT_HTML,
                    ['context' => $context]), 'small mb-2') : '')
            . html_writer::tag('ul', $optionlist, ['class' => 'small mb-0']),
            'py-3 border-bottom');
    }
    return $rows;
};

// ---- Published set (what readers answer today) ----------------------------.

$publishedversion = $publishedid
    ? (int)$DB->get_field('local_handbook_revision', 'versionnumber', ['id' => $publishedid]) : 0;
echo html_writer::tag('h3',
    s(get_string('qpublishedheading', 'local_handbook', $publishedversion)),
    ['class' => 'h5 mt-4 mb-2']);
if ($publishedquestions) {
    echo html_writer::div(html_writer::div($renderlist($publishedquestions), 'card-body'),
        'card mb-4');
} else {
    echo html_writer::div(s(get_string('qnonepublished', 'local_handbook')), 'alert alert-info');
}

// ---- Working draft (where editing happens) ---------------------------------.

if ($working) {
    $heading = get_string('qdraftheading', 'local_handbook', (object)[
        'version' => (int)$working->versionnumber,
        'status' => get_string('status_' . $working->status, 'local_handbook'),
    ]);
    echo html_writer::tag('h3', s($heading)
        . ($questionschanged
            ? ' ' . html_writer::span(s(get_string('qchangedbadge', 'local_handbook')),
                'badge badge-warning ml-2')
            : ''),
        ['class' => 'h5 mt-4 mb-2']);
    if ($draftquestions) {
        echo html_writer::div(html_writer::div($renderlist($draftquestions), 'card-body'),
            'card mb-3');
    } else {
        echo html_writer::div(s(get_string('qnonedraft', 'local_handbook')), 'alert alert-info');
    }
} else {
    echo html_writer::tag('h3', s(get_string('qnodraftheading', 'local_handbook')),
        ['class' => 'h5 mt-4 mb-2']);
    echo html_writer::div(s(get_string('qnodraftnotice', 'local_handbook')), 'alert alert-info');
}

if ($workinglocked) {
    echo html_writer::div(s(get_string('qdraftlocked', 'local_handbook', (object)[
        'version' => (int)$working->versionnumber,
        'status' => get_string('status_' . $working->status, 'local_handbook'),
    ])), 'alert alert-warning');
} else {
    if ($working && $draftquestions) {
        echo html_writer::div(
            html_writer::link(new moodle_url($url, ['action' => 'deleteall', 'sesskey' => sesskey()]),
                s(get_string('qdeleteall', 'local_handbook')),
                [
                    'class' => 'btn btn-outline-secondary btn-sm',
                    'data-confirmation' => 'modal',
                    'data-confirmation-type' => 'delete',
                    'data-confirmation-content' => get_string('confirmdeletequestions', 'local_handbook',
                        format_string($record->title)),
                    'data-confirmation-yes-button' => get_string('delete', 'core'),
                ]),
            'mb-4');
    }

    // Import form (replaces the draft's current set).
    $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'import']);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::tag('label', s(get_string('qimportlabel', 'local_handbook')),
        ['for' => 'hbq-xml', 'class' => 'font-weight-bold']);
    $form .= html_writer::tag('p', s(get_string('qimporthelp', 'local_handbook')),
        ['class' => 'small text-muted']);
    $form .= html_writer::tag('textarea', '', ['name' => 'xml', 'id' => 'hbq-xml', 'rows' => 12,
        'class' => 'form-control', 'style' => 'font-family: monospace; font-size: 0.8rem;',
        'placeholder' => '<?xml version="1.0" ?>' . "\n" . '<quiz> … </quiz>']);
    $form .= html_writer::tag('button', s(get_string('qimportbtn', 'local_handbook')),
        ['type' => 'submit', 'class' => 'btn btn-primary mt-3']);
    $form .= html_writer::tag('p', s(get_string('qeffectonpublish', 'local_handbook')),
        ['class' => 'small text-muted mt-2 mb-0']);
    $form .= html_writer::end_tag('form');
    echo html_writer::div(html_writer::div($form, 'card-body'), 'card');
}

echo html_writer::tag('p',
    html_writer::link(local_handbook_page_url($record), s(get_string('backtopage', 'local_handbook'))),
    ['class' => 'mt-3']);

echo $OUTPUT->footer();
