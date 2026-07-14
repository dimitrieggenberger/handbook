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
 * Published-page reader view (specification 12.2).
 *
 * Accepts ?page=<slug> or ?page=<id> (stable fallback, specification 8.1).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_handbook\local\service\page_service;

$pageparam = required_param('page', PARAM_ALPHANUMEXT);

$context = context_system::instance();
local_handbook_require_view($context);

if (ctype_digit($pageparam)) {
    $page = $DB->get_record('local_handbook_page', ['id' => (int)$pageparam]);
} else {
    $page = $DB->get_record('local_handbook_page', ['slug' => $pageparam]);
}
if (!$page) {
    throw new moodle_exception('errorpagenotfound', 'local_handbook');
}

$iseditorial = local_handbook_user_is_editorial($context);

$url = new moodle_url('/local/handbook/view.php', ['page' => $page->slug]);
local_handbook_apply_page_setup($url, $context, 'home',
    format_string($page->title), format_string($page->title));

$revision = null;
if ($page->publishedrevisionid) {
    $revision = $DB->get_record('local_handbook_revision', ['id' => $page->publishedrevisionid]);
}

// Readers only see published, non-archived pages; editors may preview.
if ((!$revision || (int)$page->archived === 1) && !$iseditorial) {
    throw new moodle_exception('errorpagenotfound', 'local_handbook');
}

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);

echo local_handbook_render_category_trail((int)$page->categoryid);

$actions = '';
if (has_capability('local/handbook:edit', $context)) {
    $actions .= html_writer::link(
        new moodle_url('/local/handbook/edit.php', ['id' => $page->id]),
        html_writer::tag('i', '', ['class' => 'fa-solid fa-pen me-2', 'aria-hidden' => 'true'])
            . s(get_string('editpage', 'local_handbook')),
        ['class' => 'btn btn-outline-secondary btn-sm']
    );
}
echo local_handbook_render_page_heading(format_string($page->title), $actions);

if ((int)$page->archived === 1) {
    echo html_writer::div(s(get_string('archivedpage', 'local_handbook')), 'alert alert-warning');
}

// Notice for editors when a newer working revision exists.
if ($iseditorial) {
    $working = page_service::get_working_revision((int)$page->id);
    if ($working && (!$revision || (int)$working->id !== (int)$revision->id)) {
        echo html_writer::div(
            s(get_string('draftnotice', 'local_handbook', (object)[
                'version' => (int)$working->versionnumber,
                'status' => get_string('status_' . $working->status, 'local_handbook'),
            ])),
            'alert alert-info'
        );
    }
}

echo html_writer::start_div('row');
echo html_writer::start_div('col-lg-8');

echo local_handbook_render_page_badges($page);

if (trim((string)$page->summary) !== '') {
    echo html_writer::tag('p', s($page->summary), ['class' => 'lead']);
}

if ($revision) {
    echo html_writer::div(
        html_writer::span(html_writer::tag('strong', s(get_string('effectivedate', 'local_handbook')) . ':') . ' '
            . local_handbook_format_date((int)$page->effectivedate))
        . html_writer::span(html_writer::tag('strong', s(get_string('lastupdated', 'local_handbook')) . ':') . ' '
            . local_handbook_format_date((int)$revision->timepublished))
        . html_writer::span(html_writer::tag('strong', s(get_string('publishedversion', 'local_handbook')) . ':') . ' '
            . s(get_string('versionnumber', 'local_handbook', (int)$revision->versionnumber))),
        'local-handbook-page-dates'
    );

    echo local_handbook_render_revision_content($revision, $context);
} else {
    echo html_writer::div(s(get_string('notpublished', 'local_handbook')), 'alert alert-info');
}

echo html_writer::end_div(); // .col-lg-8.

// Rail: metadata card and typed relations.
echo html_writer::start_div('col-lg-4');
echo html_writer::start_div('local-handbook-rail');

$rows = '';
$rows .= html_writer::tag('dt', s(get_string('contenttype', 'local_handbook')), ['class' => 'col-5'])
    . html_writer::tag('dd', s(get_string('contenttype_' . $page->contenttype, 'local_handbook')), ['class' => 'col-7']);
$rows .= html_writer::tag('dt', s(get_string('authoritylevel', 'local_handbook')), ['class' => 'col-5'])
    . html_writer::tag('dd',
        s(get_string('authority_' . min(6, max(1, (int)$page->authoritylevel)), 'local_handbook')),
        ['class' => 'col-7']);

if (trim((string)$page->responsiblearea) !== '') {
    $rows .= html_writer::tag('dt', s(get_string('responsiblearea', 'local_handbook')), ['class' => 'col-5'])
        . html_writer::tag('dd', s($page->responsiblearea), ['class' => 'col-7']);
}

if ((int)$page->owneruserid > 0) {
    $owneruser = core_user::get_user((int)$page->owneruserid, '*', IGNORE_MISSING);
    if ($owneruser) {
        $rows .= html_writer::tag('dt', s(get_string('owner', 'local_handbook')), ['class' => 'col-5'])
            . html_writer::tag('dd', s(fullname($owneruser)), ['class' => 'col-7']);
    }
}

$rows .= html_writer::tag('dt', s(get_string('effectivedate', 'local_handbook')), ['class' => 'col-5'])
    . html_writer::tag('dd', local_handbook_format_date((int)$page->effectivedate), ['class' => 'col-7']);
$rows .= html_writer::tag('dt', s(get_string('reviewdate', 'local_handbook')), ['class' => 'col-5'])
    . html_writer::tag('dd', local_handbook_format_date((int)$page->reviewdate), ['class' => 'col-7']);
$rows .= html_writer::tag('dt', s(get_string('languagelabel', 'local_handbook')), ['class' => 'col-5'])
    . html_writer::tag('dd', s($page->language), ['class' => 'col-7 mb-0']);

echo html_writer::div(
    html_writer::div(
        html_writer::tag('h3', s(get_string('pagedetails', 'local_handbook')),
            ['class' => 'h6 text-uppercase text-muted mb-3'])
        . html_writer::tag('dl', $rows, ['class' => 'row mb-0 small local-handbook-meta']),
        'card-body'
    ),
    'card mb-3'
);

// Typed relations (both directions), published targets only for readers.
$relations = $DB->get_records_sql(
    "SELECT rel.id, rel.relationtype, p.id AS pageid, p.slug, p.title, p.publishedrevisionid, p.archived
       FROM {local_handbook_relation} rel
       JOIN {local_handbook_page} p ON p.id = rel.targetpageid
      WHERE rel.sourcepageid = :pageid
   ORDER BY rel.sortorder ASC, rel.id ASC", ['pageid' => $page->id]);

$relitems = '';
foreach ($relations as $relation) {
    if ((int)$relation->publishedrevisionid === 0 || (int)$relation->archived === 1) {
        if (!$iseditorial) {
            continue;
        }
    }
    $target = new stdClass();
    $target->slug = $relation->slug;
    $relitems .= html_writer::tag('li',
        html_writer::span(s($relation->relationtype), 'relation-type')
        . html_writer::link(local_handbook_page_url($target), s($relation->title))
    );
}

if ($relitems !== '') {
    echo html_writer::div(
        html_writer::div(
            html_writer::tag('h3', s(get_string('relatedpages', 'local_handbook')),
                ['class' => 'h6 text-uppercase text-muted mb-3'])
            . html_writer::tag('ul', $relitems, ['class' => 'local-handbook-relations']),
            'card-body'
        ),
        'card mb-3'
    );
}

if ($iseditorial) {
    echo html_writer::tag('p',
        s(get_string('foreditors', 'local_handbook')) . ': '
        . html_writer::link(new moodle_url('/local/handbook/edit.php', ['id' => $page->id]),
            s(get_string('editpage', 'local_handbook')))
        . ' · '
        . html_writer::link(new moodle_url('/local/handbook/review.php'),
            s(get_string('reviewqueue', 'local_handbook'))),
        ['class' => 'small text-muted']
    );
}

echo html_writer::end_div(); // .local-handbook-rail.
echo html_writer::end_div(); // .col-lg-4.
echo html_writer::end_div(); // .row.

echo $OUTPUT->footer();
