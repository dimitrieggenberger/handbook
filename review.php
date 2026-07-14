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
 * Review queue: drafts awaiting review, approval and publication (12.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_handbook\local\service\page_service;

$action = optional_param('action', '', PARAM_ALPHA);
$revisionid = optional_param('rid', 0, PARAM_INT);

$context = context_system::instance();
require_login(null, false);
if (!has_any_capability(
        ['local/handbook:review', 'local/handbook:approve', 'local/handbook:publish'], $context)) {
    require_capability('local/handbook:review', $context);
}

$url = new moodle_url('/local/handbook/review.php');
local_handbook_apply_page_setup($url, $context, 'reviewqueue',
    get_string('reviewqueue', 'local_handbook'));

// ---- Actions -----------------------------------------------------------.

if ($action !== '' && $revisionid) {
    require_sesskey();

    $revision = $DB->get_record('local_handbook_revision', ['id' => $revisionid], '*', MUST_EXIST);

    if ($action === 'approve') {
        require_capability('local/handbook:approve', $context);
        page_service::approve($revision);
        redirect($url, get_string('revisionapproved', 'local_handbook'));
    }

    if ($action === 'publish') {
        require_capability('local/handbook:publish', $context);
        page_service::publish($revision);
        redirect($url, get_string('revisionpublished', 'local_handbook'));
    }

    if ($action === 'requestchanges') {
        require_capability('local/handbook:review', $context);
        $note = required_param('note', PARAM_TEXT);
        page_service::request_changes($revision, $note);
        redirect($url, get_string('changesrequested', 'local_handbook'));
    }
}

// ---- Queue listing -----------------------------------------------------.

echo $OUTPUT->header();
echo local_handbook_render_area_actions('reviewqueue', $context);
echo local_handbook_render_page_heading(get_string('reviewqueue', 'local_handbook'));

$sql = "SELECT r.*, p.title, p.slug
          FROM {local_handbook_revision} r
          JOIN {local_handbook_page} p ON p.id = r.pageid
         WHERE r.status IN (:s1, :s2)
      ORDER BY r.timemodified ASC";
$queue = $DB->get_records_sql($sql, [
    's1' => page_service::STATUS_IN_REVIEW,
    's2' => page_service::STATUS_APPROVED,
]);

if (!$queue) {
    echo html_writer::div(s(get_string('nodraftsinreview', 'local_handbook')), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$canapprove = has_capability('local/handbook:approve', $context);
$canpublish = has_capability('local/handbook:publish', $context);
$canreview = has_capability('local/handbook:review', $context);

foreach ($queue as $revision) {
    $pagerecord = new stdClass();
    $pagerecord->slug = $revision->slug;

    $submitter = core_user::get_user((int)$revision->modifiedby, '*', IGNORE_MISSING);
    $submittedinfo = get_string('submittedby', 'local_handbook', (object)[
        'name' => $submitter ? fullname($submitter) : '-',
        'date' => userdate((int)$revision->timemodified, get_string('strftimedate', 'langconfig')),
    ]);

    $statusbadgeclass = $revision->status === page_service::STATUS_APPROVED
        ? 'badge badge-success' : 'badge badge-info';

    $head = html_writer::link(local_handbook_page_url($pagerecord), s($revision->title))
        . ' ' . html_writer::span(
            s(get_string('versionnumber', 'local_handbook', (int)$revision->versionnumber)), 'text-muted')
        . ' ' . html_writer::span(
            s(get_string('status_' . $revision->status, 'local_handbook')), $statusbadgeclass);

    $body = html_writer::tag('h3', $head, ['class' => 'h6 mb-1']);
    $body .= html_writer::div(s($submittedinfo), 'small text-muted mb-2');
    if (trim((string)$revision->changesummary) !== '') {
        $body .= html_writer::div(
            html_writer::tag('strong', s(get_string('changesummary', 'local_handbook')) . ': ')
            . s($revision->changesummary),
            'small mb-2'
        );
    }
    if ((int)$revision->baserevisionid) {
        $body .= html_writer::div(
            html_writer::link(new moodle_url('/local/handbook/compare.php', [
                'page' => $revision->slug,
                'from' => (int)$revision->baserevisionid,
                'to' => $revision->id,
            ]), s(get_string('viewchanges', 'local_handbook'))),
            'small mb-2'
        );
    }

    // Action row.
    $actions = '';
    if ($revision->status === page_service::STATUS_IN_REVIEW && $canapprove) {
        $actions .= $OUTPUT->single_button(
            new moodle_url($url, ['action' => 'approve', 'rid' => $revision->id, 'sesskey' => sesskey()]),
            get_string('approve', 'local_handbook'), 'post', ['type' => 'primary']);
    }
    if ($revision->status === page_service::STATUS_APPROVED && $canpublish) {
        $actions .= $OUTPUT->single_button(
            new moodle_url($url, ['action' => 'publish', 'rid' => $revision->id, 'sesskey' => sesskey()]),
            get_string('publish', 'local_handbook'), 'post', ['type' => 'primary']);
    }
    if ($revision->status === page_service::STATUS_IN_REVIEW && $canreview) {
        // Inline request-changes form: note + submit.
        $actions .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $url->out(false),
            'class' => 'form-inline d-flex flex-wrap gap-2 align-items-center',
        ]);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',
            'value' => 'requestchanges']);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rid',
            'value' => $revision->id]);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',
            'value' => sesskey()]);
        $actions .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'note',
            'class' => 'form-control form-control-sm', 'required' => 'required',
            'placeholder' => get_string('reviewnote', 'local_handbook')]);
        $actions .= html_writer::tag('button', s(get_string('requestchanges', 'local_handbook')),
            ['type' => 'submit', 'class' => 'btn btn-outline-secondary btn-sm']);
        $actions .= html_writer::end_tag('form');
    }

    if ($actions !== '') {
        $body .= html_writer::div($actions, 'd-flex flex-wrap gap-2 align-items-center');
    }

    echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-3');
}

echo $OUTPUT->footer();
