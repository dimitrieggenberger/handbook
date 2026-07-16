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
 * Change-set detail: affected pages, before/after diffs, review actions
 * (specification 36.4, 36.5).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\local\service\changeset_service;
use local_handbook\local\service\diff_service;
use local_handbook\local\service\page_service;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:managechangesets', $context);

$changeset = $DB->get_record('local_handbook_changeset', ['id' => $id], '*', MUST_EXIST);
$url = new moodle_url('/local/handbook/manage/changeset.php', ['id' => $id]);
local_handbook_apply_page_setup($url, $context, 'changesets',
    get_string('changeset', 'local_handbook') . ': ' . format_string($changeset->title));

$locked = in_array($changeset->status,
    [changeset_service::STATUS_COMPLETED, changeset_service::STATUS_CANCELLED], true);

// ---- Actions --------------------------------------------------------------.

if ($action !== '') {
    require_sesskey();

    if ($action === 'addpage' && !$locked) {
        $pageid = required_param('pageid', PARAM_INT);
        $page = $DB->get_record('local_handbook_page', ['id' => $pageid], '*', MUST_EXIST);
        $published = (int)$page->publishedrevisionid
            ? $DB->get_record('local_handbook_revision', ['id' => $page->publishedrevisionid])
            : null;
        // Seed the draft with the current published content so the diff starts
        // empty and the editor opens on the real text.
        changeset_service::upsert_draft($id, $pageid, (string)($published->content ?? ''),
            (int)($published->contentformat ?? FORMAT_HTML), '', (int)$page->publishedrevisionid);
        redirect($url, get_string('pageaddedtochangeset', 'local_handbook'));
    }

    if ($action === 'removeitem') {
        $itemid = required_param('itemid', PARAM_INT);
        changeset_service::remove_item($itemid);
        redirect($url, get_string('itemremoved', 'local_handbook'));
    }

    if ($action === 'submit' && !$locked) {
        changeset_service::submit($id);
        redirect($url, get_string('changesetsubmittednotice', 'local_handbook'));
    }

    if ($action === 'cancel' && !$locked) {
        changeset_service::cancel($id);
        redirect(new moodle_url('/local/handbook/manage/changesets.php'),
            get_string('changesetcancelled', 'local_handbook'));
    }

    // Per-item workflow actions operate on the item's revision through
    // page_service; the observer keeps the item status in sync.
    if (in_array($action, ['approve', 'publish', 'requestchanges', 'reject'], true)) {
        $rid = required_param('rid', PARAM_INT);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $rid], '*', MUST_EXIST);

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
            page_service::request_changes($revision, required_param('note', PARAM_TEXT));
            redirect($url, get_string('changesrequested', 'local_handbook'));
        }
        if ($action === 'reject') {
            require_capability('local/handbook:review', $context);
            page_service::reject($revision, optional_param('note', '', PARAM_TEXT));
            redirect($url, get_string('revisionrejected', 'local_handbook'));
        }
    }

    // Non-revision proposal items (e.g. metadata patches) are approved, applied
    // and rejected by item id. The apply (applyitem) is the only human-gated
    // write to published state; no external/MCP function reaches it.
    if (in_array($action, ['approveitem', 'applyitem', 'rejectitem'], true)) {
        $itemid = required_param('itemid', PARAM_INT);

        if ($action === 'approveitem') {
            require_capability('local/handbook:approve', $context);
            changeset_service::approve_item($itemid);
            redirect($url, get_string('changeitemapproved', 'local_handbook'));
        }
        if ($action === 'applyitem') {
            require_capability('local/handbook:publish', $context);
            changeset_service::publish_item($itemid);
            redirect($url, get_string('changeitemapplied', 'local_handbook'));
        }
        if ($action === 'rejectitem') {
            require_capability('local/handbook:review', $context);
            changeset_service::reject_item($itemid, optional_param('note', '', PARAM_TEXT));
            redirect($url, get_string('changeitemrejected', 'local_handbook'));
        }
    }
}

// Re-read after any redirect-less fallthrough.
$changeset = changeset_service::get($id);
$locked = in_array($changeset->status,
    [changeset_service::STATUS_COMPLETED, changeset_service::STATUS_CANCELLED], true);

$canapprove = has_capability('local/handbook:approve', $context);
$canpublish = has_capability('local/handbook:publish', $context);
$canreview = has_capability('local/handbook:review', $context);

echo $OUTPUT->header();
echo local_handbook_render_area_actions('changesets', $context);
echo local_handbook_render_page_heading(
    get_string('changeset', 'local_handbook') . ': ' . format_string($changeset->title));

echo html_writer::tag('p',
    html_writer::link(new moodle_url('/local/handbook/manage/changesets.php'),
        s(get_string('backtochangesets', 'local_handbook'))), ['class' => 'small mb-3']);

// ---- Metadata card --------------------------------------------------------.

$statusbadges = [
    changeset_service::STATUS_DRAFT => 'badge badge-secondary',
    changeset_service::STATUS_IN_REVIEW => 'badge badge-info',
    changeset_service::STATUS_PARTIALLY_COMPLETED => 'badge badge-warning',
    changeset_service::STATUS_COMPLETED => 'badge badge-success',
    changeset_service::STATUS_CANCELLED => 'badge badge-light border',
];

$creator = core_user::get_user((int)$changeset->createdby, '*', IGNORE_MISSING);
$meta = html_writer::span(s(get_string('changesetstatus_' . $changeset->status, 'local_handbook')),
    $statusbadges[$changeset->status] ?? 'badge badge-secondary');
$meta .= ' · ' . s(get_string('changesetsource', 'local_handbook')) . ': '
    . s(get_string('source_' . ($changeset->source === 'ai' ? 'ai' : 'human'), 'local_handbook'));
if ($creator) {
    $meta .= ' · ' . s(get_string('changesetpreparedby', 'local_handbook')) . ': ' . s(fullname($creator));
}
$meta .= ' · ' . s(get_string('changesetcreatedon', 'local_handbook')) . ' '
    . userdate((int)$changeset->timecreated, get_string('strftimedate', 'langconfig'));

$cardbody = html_writer::div($meta, 'small text-muted mb-2');
if (trim((string)$changeset->instructionsummary) !== '') {
    $cardbody .= html_writer::div(
        html_writer::tag('strong', s(get_string('changesetinstructions', 'local_handbook')) . ': ')
        . s($changeset->instructionsummary), 'mb-0');
}
if (trim((string)$changeset->externalreference) !== '') {
    $cardbody .= html_writer::div(
        html_writer::tag('strong', s(get_string('externalreference', 'local_handbook')) . ': ')
        . s($changeset->externalreference), 'small text-muted mt-1');
}
echo html_writer::div(html_writer::div($cardbody, 'card-body'), 'card mb-3');

// ---- Set-level actions ----------------------------------------------------.

if (!$locked) {
    $actions = '';

    // Submit for review.
    $submitform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
        'class' => 'd-inline']);
    $submitform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'submit']);
    $submitform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $submitattrs = ['type' => 'submit', 'class' => 'btn btn-primary btn-sm'];
    if (empty($changeset->items)) {
        $submitattrs['disabled'] = 'disabled';
    }
    $submitform .= html_writer::tag('button', s(get_string('submitchangeset', 'local_handbook')), $submitattrs);
    $submitform .= html_writer::end_tag('form');

    // Cancel.
    $cancelform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
        'class' => 'd-inline',
        'onsubmit' => 'return confirm(' . json_encode(get_string('confirmcancelchangeset', 'local_handbook')) . ');']);
    $cancelform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'cancel']);
    $cancelform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $cancelform .= html_writer::tag('button', s(get_string('cancelchangeset', 'local_handbook')),
        ['type' => 'submit', 'class' => 'btn btn-outline-secondary btn-sm']);
    $cancelform .= html_writer::end_tag('form');

    echo html_writer::div($submitform . ' ' . $cancelform, 'mb-3 d-flex flex-wrap gap-2');
}

// ---- Add a page -----------------------------------------------------------.

if (!$locked) {
    $existing = $DB->get_fieldset_select('local_handbook_changeitem', 'pageid', 'changesetid = ?', [$id]);
    $existing = array_map('intval', $existing);
    $candidates = $DB->get_records_select('local_handbook_page', 'archived = 0', [],
        'title ASC', 'id, title');
    $options = '';
    foreach ($candidates as $candidate) {
        if (in_array((int)$candidate->id, $existing, true)) {
            continue;
        }
        $options .= html_writer::tag('option', s(format_string($candidate->title)),
            ['value' => (int)$candidate->id]);
    }
    if ($options !== '') {
        $addform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
            'class' => 'd-flex flex-wrap gap-2 align-items-end mb-4']);
        $addform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addpage']);
        $addform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $addform .= html_writer::div(
            html_writer::tag('label', s(get_string('addpagetochangeset', 'local_handbook')),
                ['for' => 'cs-addpage', 'class' => 'form-label mb-1'])
            . html_writer::tag('select',
                html_writer::tag('option', s(get_string('selectpageadd', 'local_handbook')),
                    ['value' => '', 'disabled' => 'disabled', 'selected' => 'selected']) . $options,
                ['id' => 'cs-addpage', 'name' => 'pageid', 'required' => 'required',
                    'class' => 'custom-select custom-select-sm']));
        $addform .= html_writer::tag('button', s(get_string('addpagebutton', 'local_handbook')),
            ['type' => 'submit', 'class' => 'btn btn-outline-primary btn-sm']);
        $addform .= html_writer::end_tag('form');
        echo $addform;
    }
}

// ---- Items ----------------------------------------------------------------.

echo html_writer::tag('h3', s(get_string('changesetitems', 'local_handbook')), ['class' => 'h5 mb-3']);

if (empty($changeset->items)) {
    echo html_writer::div(s(get_string('nochangesetitems', 'local_handbook')), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$itembadges = [
    changeset_service::ITEM_DRAFT => 'badge badge-secondary',
    changeset_service::ITEM_CONFLICT => 'badge badge-danger',
    changeset_service::ITEM_IN_REVIEW => 'badge badge-info',
    changeset_service::ITEM_APPROVED => 'badge badge-primary',
    changeset_service::ITEM_PUBLISHED => 'badge badge-success',
    changeset_service::ITEM_REJECTED => 'badge badge-light border',
    changeset_service::ITEM_SKIPPED => 'badge badge-light border',
];

foreach ($changeset->items as $item) {
    // New-page proposal: rendered from its payload (no bound page until applied).
    if ($item->kind === changeset_service::KIND_PAGE_CREATE) {
        $data = json_decode((string)$item->payloadjson, true) ?: [];
        $title = (string)($data['title'] ?? get_string('changesetnewpage', 'local_handbook'));
        $applied = (int)$item->pageid
            ? $DB->get_record('local_handbook_page', ['id' => $item->pageid]) : null;
        $titlehtml = $applied
            ? html_writer::link(local_handbook_page_url($applied), s($title)) : s($title);
        $head = $titlehtml . ' '
            . html_writer::span(s(get_string('itemkindnewpage', 'local_handbook')), 'badge badge-light border')
            . ' ' . html_writer::span(s(get_string('itemstatus_' . $item->itemstatus, 'local_handbook')),
                $itembadges[$item->itemstatus] ?? 'badge badge-secondary');
        $body = html_writer::tag('h4', $head, ['class' => 'h6 mb-2']);
        if ($item->itemstatus === changeset_service::ITEM_CONFLICT
                && trim((string)$item->conflictnote) !== '') {
            $body .= html_writer::div(s($item->conflictnote), 'alert alert-warning py-2 px-3 small mb-2');
        }
        $body .= local_handbook_render_new_page_preview($data);
        $body .= local_handbook_changeset_nonrevision_actions($url, $item,
            $canapprove, $canpublish, $canreview);
        echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-3');
        continue;
    }

    $page = $DB->get_record('local_handbook_page', ['id' => $item->pageid]);
    if (!$page) {
        continue;
    }
    $pagelink = html_writer::link(local_handbook_page_url($page), s(format_string($page->title)));
    $head = $pagelink . ' '
        . html_writer::span(s(get_string('itemstatus_' . $item->itemstatus, 'local_handbook')),
            $itembadges[$item->itemstatus] ?? 'badge badge-secondary');

    $body = html_writer::tag('h4', $head, ['class' => 'h6 mb-2']);

    // Conflict note.
    if ($item->itemstatus === changeset_service::ITEM_CONFLICT
            && trim((string)$item->conflictnote) !== '') {
        $body .= html_writer::div(s($item->conflictnote), 'alert alert-warning py-2 px-3 small mb-2');
    }

    // Metadata (fiche) proposal: before/after field table + its own workflow.
    if ($item->kind === changeset_service::KIND_PAGE_METADATA) {
        $patch = json_decode((string)$item->payloadjson, true);
        if (is_array($patch) && $patch) {
            $body .= local_handbook_render_metadata_diff($page, $patch);
        } else {
            $body .= html_writer::div(s(get_string('metadatanochanges', 'local_handbook')),
                'small text-muted mb-2');
        }

        $body .= local_handbook_changeset_nonrevision_actions($url, $item,
            $canapprove, $canpublish, $canreview);
        echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-3');
        continue;
    }

    // Relation-edit proposal: list the operations + its workflow.
    if ($item->kind === changeset_service::KIND_RELATION_CHANGE) {
        $payload = json_decode((string)$item->payloadjson, true);
        $ops = is_array($payload) ? ($payload['ops'] ?? []) : [];
        if ($ops) {
            $body .= local_handbook_render_relation_ops($ops);
        } else {
            $body .= html_writer::div(s(get_string('metadatanochanges', 'local_handbook')),
                'small text-muted mb-2');
        }
        $body .= local_handbook_changeset_nonrevision_actions($url, $item,
            $canapprove, $canpublish, $canreview);
        echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-3');
        continue;
    }

    // Archive / restore lifecycle proposal.
    if (in_array($item->kind, [changeset_service::KIND_PAGE_ARCHIVE,
            changeset_service::KIND_PAGE_RESTORE], true)) {
        $body .= local_handbook_render_lifecycle_item($page, $item);
        $body .= local_handbook_changeset_nonrevision_actions($url, $item,
            $canapprove, $canpublish, $canreview);
        echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-3');
        continue;
    }

    // Before/after diff (published vs the item's draft).
    $draft = (int)$item->revisionid
        ? $DB->get_record('local_handbook_revision', ['id' => $item->revisionid]) : null;
    $published = (int)$page->publishedrevisionid
        ? $DB->get_record('local_handbook_revision', ['id' => $page->publishedrevisionid]) : null;

    if ($draft) {
        if (!$published) {
            $body .= html_writer::div(s(get_string('changesetnewpage', 'local_handbook')),
                'alert alert-secondary py-2 px-3 small mb-2');
        } else {
            $segments = diff_service::diff_words((string)$published->plaintext, (string)$draft->plaintext);
            if (!diff_service::has_changes($segments)) {
                $body .= html_writer::div(s(get_string('draftmatchespublished', 'local_handbook')),
                    'small text-muted mb-2');
            } else {
                $body .= html_writer::div(diff_service::render_html($segments),
                    'border rounded p-2 mb-2 small');
            }
        }
    }

    // Item actions.
    $itemactions = [];
    $editable = $draft && in_array($draft->status, page_service::EDITABLE_STATUSES, true);

    if ($editable) {
        $itemactions[] = html_writer::link(
            new moodle_url('/local/handbook/edit.php', ['id' => $page->id]),
            s(get_string('editdraft', 'local_handbook')), ['class' => 'btn btn-outline-secondary btn-sm']);
    }
    if ($draft && $published) {
        $itemactions[] = html_writer::link(
            new moodle_url('/local/handbook/compare.php', [
                'page' => $page->slug, 'from' => (int)$published->id, 'to' => (int)$draft->id,
            ]), s(get_string('viewchanges', 'local_handbook')), ['class' => 'btn btn-link btn-sm']);
    }

    // Workflow actions on the item's revision.
    if ($draft && $draft->status === page_service::STATUS_IN_REVIEW && $canapprove) {
        $itemactions[] = local_handbook_changeset_action_button($url, 'approve', (int)$draft->id,
            get_string('approve', 'local_handbook'), 'btn-primary');
    }
    if ($draft && $draft->status === page_service::STATUS_APPROVED && $canpublish) {
        $itemactions[] = local_handbook_changeset_action_button($url, 'publish', (int)$draft->id,
            get_string('publish', 'local_handbook'), 'btn-primary');
    }
    if ($draft && $draft->status === page_service::STATUS_IN_REVIEW && $canreview) {
        $itemactions[] = local_handbook_changeset_action_button($url, 'reject', (int)$draft->id,
            get_string('reject', 'local_handbook'), 'btn-outline-danger');
    }

    // Remove (only while the item is not in-flight or published).
    if (!in_array($item->itemstatus, [changeset_service::ITEM_IN_REVIEW,
            changeset_service::ITEM_APPROVED, changeset_service::ITEM_PUBLISHED], true)) {
        $removeform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
            'class' => 'd-inline',
            'onsubmit' => 'return confirm(' . json_encode(get_string('confirmremoveitem', 'local_handbook')) . ');']);
        $removeform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'removeitem']);
        $removeform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'itemid', 'value' => (int)$item->id]);
        $removeform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $removeform .= html_writer::tag('button', s(get_string('removeitem', 'local_handbook')),
            ['type' => 'submit', 'class' => 'btn btn-link btn-sm text-danger']);
        $removeform .= html_writer::end_tag('form');
        $itemactions[] = $removeform;
    }

    if ($itemactions) {
        $body .= html_writer::div(implode(' ', $itemactions), 'd-flex flex-wrap gap-2 align-items-center');
    }

    // Inline request-changes form (in review only).
    if ($draft && $draft->status === page_service::STATUS_IN_REVIEW && $canreview) {
        $rcform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
            'class' => 'form-inline d-flex flex-wrap gap-2 align-items-center mt-2']);
        $rcform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'requestchanges']);
        $rcform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rid', 'value' => (int)$draft->id]);
        $rcform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $rcform .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'note',
            'class' => 'form-control form-control-sm', 'required' => 'required',
            'style' => 'max-width: 24rem;',
            'placeholder' => get_string('reviewnote', 'local_handbook')]);
        $rcform .= html_writer::tag('button', s(get_string('requestchanges', 'local_handbook')),
            ['type' => 'submit', 'class' => 'btn btn-outline-secondary btn-sm']);
        $rcform .= html_writer::end_tag('form');
        $body .= $rcform;
    }

    echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-3');
}

echo $OUTPUT->footer();
