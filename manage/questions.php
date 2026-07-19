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
 * Comprehension questions of a page: list, import from Moodle XML
 * (the institutional pauta), delete. Human-only — the AI can author
 * XML but has no import surface.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

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

$report = null;
if ($action === 'import' && confirm_sesskey()) {
    $xml = optional_param('xml', '', PARAM_RAW);
    $report = quiz_service::parse_xml($xml);
    if (!$report->errors && $report->questions) {
        quiz_service::import($pageid, $report->questions, (int)$USER->id);
        $report->imported = count($report->questions);
    }
}
if ($action === 'deleteall' && confirm_sesskey()) {
    quiz_service::delete_all($pageid);
    redirect($url, get_string('qdeleted', 'local_handbook'));
}

$questions = quiz_service::get_questions($pageid);

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
        echo html_writer::div(s(get_string('qimported', 'local_handbook', $report->imported)),
            'alert alert-success');
    }
}

// Current questions.
if ($questions) {
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
            html_writer::tag('h3', $number . '. ' . s($question->title !== ''
                ? $question->title : get_string('question', 'core')), ['class' => 'h6 mb-1'])
            . html_writer::div(s(implode(' · ', $meta)), 'small text-muted mb-2')
            . ($question->questiontext !== ''
                ? html_writer::div(format_text($question->questiontext, FORMAT_HTML,
                    ['context' => $context]), 'small mb-2') : '')
            . html_writer::tag('ul', $optionlist, ['class' => 'small mb-0']),
            'py-3 border-bottom');
    }
    echo html_writer::div(html_writer::div($rows, 'card-body'), 'card mb-3');

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
} else {
    echo html_writer::div(s(get_string('qnoneyet', 'local_handbook')), 'alert alert-info');
}

// Import form (replaces the current set).
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
$form .= html_writer::end_tag('form');
echo html_writer::div(html_writer::div($form, 'card-body'), 'card');

echo html_writer::tag('p',
    html_writer::link(local_handbook_page_url($record), s(get_string('backtopage', 'local_handbook'))),
    ['class' => 'mt-3']);

echo $OUTPUT->footer();
