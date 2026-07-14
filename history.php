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
 * Revision history of a page (specification 11; history-dialog mockup).
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

$url = new moodle_url('/local/handbook/history.php', ['page' => $page->slug]);
local_handbook_apply_page_setup($url, $context, 'home',
    get_string('revisionhistory', 'local_handbook'),
    get_string('revisionhistory', 'local_handbook'));

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);

echo local_handbook_render_category_trail((int)$page->categoryid);
echo local_handbook_render_page_heading(
    get_string('revisionhistory', 'local_handbook') . ': ' . format_string($page->title));

$statusbadges = [
    page_service::STATUS_DRAFT => 'badge badge-secondary',
    page_service::STATUS_IN_REVIEW => 'badge badge-info',
    page_service::STATUS_CHANGES_REQUESTED => 'badge badge-warning',
    page_service::STATUS_APPROVED => 'badge badge-success',
    page_service::STATUS_PUBLISHED => 'badge badge-success',
    page_service::STATUS_SUPERSEDED => 'badge badge-secondary',
    page_service::STATUS_REJECTED => 'badge badge-secondary',
];

$revisions = $DB->get_records('local_handbook_revision', ['pageid' => $page->id],
    'versionnumber DESC');

foreach ($revisions as $revision) {
    $creator = core_user::get_user((int)$revision->createdby, '*', IGNORE_MISSING);

    $head = html_writer::tag('strong',
        s(get_string('versionnumber', 'local_handbook', (int)$revision->versionnumber)));
    $head .= ' ' . html_writer::span(
        s(get_string('status_' . $revision->status, 'local_handbook')),
        $statusbadges[$revision->status] ?? 'badge badge-secondary');

    $metaparts = [];
    if ((int)$revision->timepublished > 0) {
        $metaparts[] = get_string('lastupdated', 'local_handbook') . ': '
            . userdate((int)$revision->timepublished, get_string('strftimedate', 'langconfig'));
    } else {
        $metaparts[] = userdate((int)$revision->timemodified, get_string('strftimedate', 'langconfig'));
    }
    if ($creator) {
        $metaparts[] = get_string('createdby', 'local_handbook') . ': ' . fullname($creator);
    }
    if ((int)$revision->baserevisionid) {
        $baseversion = (int)$DB->get_field('local_handbook_revision', 'versionnumber',
            ['id' => $revision->baserevisionid]);
        if ($baseversion) {
            $metaparts[] = get_string('basedon', 'local_handbook', $baseversion);
        }
    }

    $body = html_writer::div($head, 'mb-1');
    $body .= html_writer::div(s(implode(' · ', $metaparts)), 'small text-muted mb-1');
    if (trim((string)$revision->changesummary) !== '') {
        $body .= html_writer::div(s($revision->changesummary), 'small mb-2');
    }
    if ($revision->status === page_service::STATUS_CHANGES_REQUESTED
            && trim((string)$revision->reviewnote) !== '') {
        $body .= html_writer::div(
            html_writer::tag('strong', s(get_string('reviewnote', 'local_handbook')) . ': ')
            . s($revision->reviewnote), 'small mb-2');
    }

    // Compare actions: everything compares against the published revision;
    // when this IS the published revision, compare against its base.
    $links = [];
    if ((int)$page->publishedrevisionid && (int)$revision->id !== (int)$page->publishedrevisionid) {
        $links[] = html_writer::link(new moodle_url('/local/handbook/compare.php', [
            'page' => $page->slug,
            'from' => ($revision->status === page_service::STATUS_SUPERSEDED
                || (int)$revision->timepublished > 0) ? $revision->id : $page->publishedrevisionid,
            'to' => ($revision->status === page_service::STATUS_SUPERSEDED
                || (int)$revision->timepublished > 0) ? $page->publishedrevisionid : $revision->id,
        ]), s(get_string('comparewithpublished', 'local_handbook')));
    } else if ((int)$revision->baserevisionid) {
        $links[] = html_writer::link(new moodle_url('/local/handbook/compare.php', [
            'page' => $page->slug,
            'from' => (int)$revision->baserevisionid,
            'to' => $revision->id,
        ]), s(get_string('comparewithprevious', 'local_handbook')));
    }
    if ($links) {
        $body .= html_writer::div(implode(' · ', $links), 'small');
    }

    echo html_writer::div(html_writer::div($body, 'card-body py-3'), 'card mb-2');
}

echo html_writer::tag('p',
    html_writer::link(local_handbook_page_url($page),
        s(get_string('backtopage', 'local_handbook'))),
    ['class' => 'small mt-3']);

echo $OUTPUT->footer();
