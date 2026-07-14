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
 * Revision comparison: metadata changes and a word-level text diff (11.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_handbook\local\service\diff_service;
use local_handbook\local\service\page_service;

$pageparam = required_param('page', PARAM_ALPHANUMEXT);
$fromid = required_param('from', PARAM_INT);
$toid = required_param('to', PARAM_INT);

$context = context_system::instance();
local_handbook_require_view($context);
if (!has_capability('local/handbook:viewhistory', $context)
        && !local_handbook_user_is_editorial($context)) {
    require_capability('local/handbook:viewhistory', $context);
}

if (ctype_digit($pageparam)) {
    $page = $DB->get_record('local_handbook_page', ['id' => (int)$pageparam]);
} else {
    $page = $DB->get_record('local_handbook_page', ['slug' => $pageparam]);
}
if (!$page) {
    throw new moodle_exception('errorpagenotfound', 'local_handbook');
}

$from = $DB->get_record('local_handbook_revision',
    ['id' => $fromid, 'pageid' => $page->id], '*', MUST_EXIST);
$to = $DB->get_record('local_handbook_revision',
    ['id' => $toid, 'pageid' => $page->id], '*', MUST_EXIST);

$url = new moodle_url('/local/handbook/compare.php',
    ['page' => $page->slug, 'from' => $from->id, 'to' => $to->id]);
local_handbook_apply_page_setup($url, $context, 'home',
    get_string('comparerevisions', 'local_handbook'),
    get_string('comparerevisions', 'local_handbook'));

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);

echo local_handbook_render_category_trail((int)$page->categoryid);
echo local_handbook_render_page_heading(
    get_string('comparerevisions', 'local_handbook') . ': ' . format_string($page->title));

echo html_writer::div(
    s(get_string('comparingversions', 'local_handbook', (object)[
        'from' => (int)$from->versionnumber,
        'to' => (int)$to->versionnumber,
    ])) . ' — ' . s(get_string('difflegend', 'local_handbook')),
    'alert alert-secondary'
);

// ---- Metadata comparison (11.4) -----------------------------------------.

$rows = '';
$metafields = [
    'status' => static function(stdClass $revision): string {
        return get_string('status_' . $revision->status, 'local_handbook');
    },
    'changesummary' => static function(stdClass $revision): string {
        return trim((string)$revision->changesummary);
    },
    'effectivefrom' => static function(stdClass $revision): string {
        return (int)$revision->effectivefrom > 0
            ? userdate((int)$revision->effectivefrom, get_string('strftimedate', 'langconfig'))
            : '—';
    },
    'timemodified' => static function(stdClass $revision): string {
        return userdate((int)$revision->timemodified, get_string('strftimedate', 'langconfig'));
    },
];
$metalabels = [
    'status' => get_string('status', 'core'),
    'changesummary' => get_string('changesummary', 'local_handbook'),
    'effectivefrom' => get_string('effectivedate', 'local_handbook'),
    'timemodified' => get_string('lastupdated', 'local_handbook'),
];

foreach ($metafields as $field => $formatter) {
    $fromvalue = $formatter($from);
    $tovalue = $formatter($to);
    $changed = $fromvalue !== $tovalue;
    $rows .= html_writer::tag('tr',
        html_writer::tag('th', s($metalabels[$field]), ['scope' => 'row'])
        . html_writer::tag('td', s($fromvalue))
        . html_writer::tag('td', $changed
            ? html_writer::tag('strong', s($tovalue))
            : s($tovalue))
    );
}

echo html_writer::start_div('table-responsive mb-4');
echo html_writer::tag('table',
    html_writer::tag('thead', html_writer::tag('tr',
        html_writer::tag('th', '')
        . html_writer::tag('th', s(get_string('versionnumber', 'local_handbook', (int)$from->versionnumber)))
        . html_writer::tag('th', s(get_string('versionnumber', 'local_handbook', (int)$to->versionnumber)))
    ))
    . html_writer::tag('tbody', $rows),
    ['class' => 'table table-sm']
);
echo html_writer::end_div();

// ---- Text diff -----------------------------------------------------------.

$segments = diff_service::diff_words((string)$from->plaintext, (string)$to->plaintext);

if (!diff_service::has_changes($segments)) {
    echo html_writer::div(s(get_string('nocontentdiff', 'local_handbook')), 'alert alert-info');
} else {
    echo html_writer::div(
        html_writer::div(diff_service::render_html($segments), 'card-body'),
        'card mb-3'
    );
}

echo html_writer::tag('p',
    html_writer::link(new moodle_url('/local/handbook/history.php', ['page' => $page->slug]),
        s(get_string('revisionhistory', 'local_handbook')))
    . ' · '
    . html_writer::link(local_handbook_page_url($page), s(get_string('backtopage', 'local_handbook'))),
    ['class' => 'small mt-3']);

echo $OUTPUT->footer();
