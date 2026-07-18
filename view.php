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

use local_handbook\local\service\ack_service;
use local_handbook\local\service\completion_service;
use local_handbook\local\service\page_service;
use local_handbook\local\service\path_service;
use local_handbook\local\service\toc_service;

$pageparam = required_param('page', PARAM_ALPHANUMEXT);
$action = optional_param('action', '', PARAM_ALPHA);

$context = context_system::instance();
local_handbook_require_view($context);

if (ctype_digit($pageparam)) {
    $page = $DB->get_record('local_handbook_page', ['id' => (int)$pageparam]);
} else {
    $page = $DB->get_record('local_handbook_page', ['slug' => $pageparam]);
    // A retired slug still resolves: redirect to the page's current address
    // so old links and bookmarks keep working (spec 7.3).
    if (!$page) {
        $alias = $DB->get_record('local_handbook_pagealias', ['oldslug' => $pageparam]);
        if ($alias) {
            $target = $DB->get_record('local_handbook_page', ['id' => $alias->pageid]);
            if ($target) {
                redirect(new moodle_url('/local/handbook/view.php', ['page' => $target->slug]));
            }
        }
    }
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

// Unpublished pages: readers get a 404 (editors may preview).
if (!$revision && !$iseditorial) {
    throw new moodle_exception('errorpagenotfound', 'local_handbook');
}

// Archived pages: readers are redirected to the replacement or shown a notice
// per the page's redirect mode; otherwise a 404 (editors always preview).
if ((int)$page->archived === 1 && !$iseditorial) {
    $replacement = (int)$page->replacementpageid
        ? $DB->get_record('local_handbook_page', ['id' => $page->replacementpageid]) : null;
    $mode = (string)$page->redirectmode;
    if ($replacement && $mode === 'automatic_redirect') {
        redirect(local_handbook_page_url($replacement));
    }
    if ($replacement && $mode === 'redirect_with_notice') {
        redirect(local_handbook_page_url($replacement),
            get_string('archivedredirectnotice', 'local_handbook', format_string($page->title)));
    }
    if ($mode !== 'notice_only') {
        throw new moodle_exception('errorpagenotfound', 'local_handbook');
    }
    // notice_only: fall through and render the page with an archived banner.
}

// Archive / unarchive (publishers only; history is never deleted, 11.3).
if ($action === 'archive' || $action === 'unarchive') {
    require_sesskey();
    require_capability('local/handbook:publish', $context);
    page_service::set_archived($page, $action === 'archive');
    redirect(local_handbook_page_url($page),
        get_string($action === 'archive' ? 'pagearchived' : 'pageunarchived', 'local_handbook'));
}

// Record reading completion (spec 8, 16). A globally required page keeps its
// formal compliance acknowledgement; a path-required page (not globally
// required) records an article-level read receipt. Either satisfies every path
// containing the article.
if ($action === 'acknowledge') {
    require_sesskey();
    require_capability('local/handbook:acknowledge', $context);
    $pathid = optional_param('pathid', 0, PARAM_INT);
    if ((int)$page->requiredreading) {
        ack_service::acknowledge((int)$USER->id, $page, $pathid);
    } else if (path_service::is_required_in_active_path((int)$page->id)) {
        completion_service::record_receipt((int)$USER->id, $page,
            $pathid ? 'reading_path' : 'manual');
    } else {
        throw new moodle_exception('errornotrequiredreading', 'local_handbook');
    }
    // Stay in path context so the "next in path" button appears on reload.
    redirect(new moodle_url(local_handbook_page_url($page),
        $pathid ? ['path' => $pathid] : [], 'confirmar'),
        get_string('ackrecorded', 'local_handbook'));
}

// Compliance status drives the required-reading card; completion status drives
// the lighter "mark as read" card for path-required (non-global) articles.
$ackstatus = null;
$completionstatus = null;
if ($revision && has_capability('local/handbook:acknowledge', $context)) {
    if ((int)$page->requiredreading) {
        $ackstatus = ack_service::get_status((int)$USER->id, $page);
    } else if (path_service::is_required_in_active_path((int)$page->id)) {
        $completionstatus = completion_service::completion_status((int)$USER->id, $page);
    }
}

// Reading-path context (spec 8.6): the rail panel, per-item ticks and the
// "next in path" card. Chosen from ?path=N or the first visible active path
// containing this page.
$pathctx = null;
if ($revision) {
    $pathctx = local_handbook_path_context($page, optional_param('path', 0, PARAM_INT),
        (int)$USER->id, has_capability('local/handbook:managepaths', $context));
}

// Rendered content with heading anchors + on-page TOC (spec 10.2, 12.2).
$contenthtml = '';
$toc = [];
if ($revision) {
    $anchored = toc_service::add_anchors(
        local_handbook_render_revision_content($revision, $context));
    $contenthtml = $anchored->html;
    $toc = $anchored->toc;
}

// Content accordions (hb-acc): behaviour + expand-all labels. Print never
// loads this, so drawers render open there.
$PAGE->requires->js(new moodle_url('/local/handbook/js/contentacc.js'));
$contenthtml = html_writer::div('', 'd-none', [
    'data-region' => 'local-handbook-accstrings',
    'data-expand' => get_string('accexpandall', 'local_handbook'),
    'data-collapse' => get_string('acccollapseall', 'local_handbook'),
]) . $contenthtml;

// Typed relations in both directions (spec 9.2).
$outgoing = $DB->get_records_sql(
    "SELECT rel.id, rel.relationtype, p.slug, p.title, p.publishedrevisionid, p.archived
       FROM {local_handbook_relation} rel
       JOIN {local_handbook_page} p ON p.id = rel.targetpageid
      WHERE rel.sourcepageid = :pageid
   ORDER BY rel.sortorder ASC, rel.id ASC", ['pageid' => $page->id]);
$incoming = $DB->get_records_sql(
    "SELECT rel.id, rel.relationtype, p.slug, p.title, p.publishedrevisionid, p.archived
       FROM {local_handbook_relation} rel
       JOIN {local_handbook_page} p ON p.id = rel.sourcepageid
      WHERE rel.targetpageid = :pageid
   ORDER BY rel.sortorder ASC, rel.id ASC", ['pageid' => $page->id]);

// Reading paths that include this page (for the acknowledgement card).
$memberpaths = $DB->get_records_sql(
    "SELECT pa.id, pa.name, i.sectionname
       FROM {local_handbook_pathitem} i
       JOIN {local_handbook_path} pa ON pa.id = i.pathid
      WHERE i.pageid = :pageid AND pa.active = 1
   ORDER BY pa.schoolyear DESC", ['pageid' => $page->id]);

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);

echo local_handbook_render_category_trail((int)$page->categoryid);

$actions = '';
if ($revision) {
    $printprimary = $page->contenttype === 'quickguide';
    $actions .= html_writer::link(
        new moodle_url('/local/handbook/print.php', ['page' => $page->slug]),
        html_writer::tag('i', '', ['class' => 'fa-solid fa-print me-2', 'aria-hidden' => 'true'])
            . s(get_string('printpage', 'local_handbook')),
        ['class' => 'btn btn-sm ' . ($printprimary ? 'btn-primary' : 'btn-outline-secondary'),
            'target' => '_blank']
    );
}
if (has_capability('local/handbook:edit', $context)) {
    $actions .= html_writer::link(
        new moodle_url('/local/handbook/edit.php', ['id' => $page->id]),
        html_writer::tag('i', '', ['class' => 'fa-solid fa-pen me-2', 'aria-hidden' => 'true'])
            . s(get_string('editpage', 'local_handbook')),
        ['class' => 'btn btn-outline-secondary btn-sm']
    );
}
$actions .= html_writer::link(
    new moodle_url('/local/handbook/report.php', ['page' => $page->slug]),
    html_writer::tag('i', '', ['class' => 'fa-solid fa-triangle-exclamation me-2', 'aria-hidden' => 'true'])
        . s(get_string('reportproblem', 'local_handbook')),
    ['class' => 'btn btn-outline-secondary btn-sm']
);
if (has_capability('local/handbook:publish', $context)) {
    $archiveaction = (int)$page->archived === 1 ? 'unarchive' : 'archive';
    $actions .= html_writer::link(
        new moodle_url('/local/handbook/view.php', ['page' => $page->slug,
            'action' => $archiveaction, 'sesskey' => sesskey()]),
        html_writer::tag('i', '', ['class' => 'fa-solid fa-box-archive me-2', 'aria-hidden' => 'true'])
            . s(get_string($archiveaction . 'page', 'local_handbook')),
        [
            'class' => 'btn btn-outline-secondary btn-sm',
            'data-confirmation' => 'modal',
            'data-confirmation-content' => get_string('confirm' . $archiveaction, 'local_handbook',
                format_string($page->title)),
            'data-confirmation-yes-button' => get_string($archiveaction . 'page', 'local_handbook'),
        ]
    );
}
echo local_handbook_render_page_heading(format_string($page->title), $actions);

if ((int)$page->archived === 1) {
    $archivedmsg = s(get_string('archivedpage', 'local_handbook'));
    if ((int)$page->replacementpageid) {
        $replacementpage = $DB->get_record('local_handbook_page', ['id' => $page->replacementpageid]);
        if ($replacementpage) {
            $archivedmsg .= ' ' . get_string('archivedseereplacement', 'local_handbook',
                html_writer::link(local_handbook_page_url($replacementpage),
                    s(format_string($replacementpage->title))));
        }
    }
    echo html_writer::div($archivedmsg, 'alert alert-warning');
}

// Required-reading status notice (spec 16; mirrors the reader mockups).
if ($ackstatus !== null) {
    if ($ackstatus->status === ack_service::STATUS_CONFIRMED) {
        echo html_writer::div(
            html_writer::tag('i', '', ['class' => 'fa-solid fa-circle-check me-2', 'aria-hidden' => 'true'])
            . s(get_string('ackconfirmednotice', 'local_handbook', (object)[
                'version' => (int)$revision->versionnumber,
                'date' => userdate((int)$ackstatus->ack->timeacknowledged,
                    get_string('strftimedate', 'langconfig')),
            ])),
            'alert alert-success', ['role' => 'status']);
    } else {
        $stringkey = $ackstatus->status === ack_service::STATUS_RECONFIRM
            ? 'ackreconfirmnotice' : 'ackpendingnotice';
        echo html_writer::div(
            html_writer::tag('i', '', ['class' => 'fa-solid fa-circle-info me-2', 'aria-hidden' => 'true'])
            . s(get_string($stringkey, 'local_handbook', (int)$revision->versionnumber)) . ' '
            . html_writer::link('#confirmar', s(get_string('gotoconfirmation', 'local_handbook')),
                ['class' => 'alert-link']),
            'alert alert-info', ['role' => 'status']);
    }
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

// Banner image: fixed 16:9 (same crop as the category cards), masked with
// object-fit cover, centered both axes. Without a banner, the same quiet
// 16:4 content-type tint strip the cards use.
$bannerurl = local_handbook_banner_url((int)$page->id);
if ($bannerurl) {
    echo html_writer::div(
        html_writer::empty_tag('img', ['src' => $bannerurl->out(false), 'alt' => '']),
        'local-handbook-page-banner');
} else {
    echo html_writer::div(
        html_writer::tag('i', '', [
            'class' => 'fa-solid ' . local_handbook_contenttype_icon((string)$page->contenttype),
            'aria-hidden' => 'true',
        ]),
        'local-handbook-page-banner is-fallback');
}

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

    // Quick-guide authority note (spec 10.3): the source procedure prevails.
    if ($page->contenttype === 'quickguide') {
        foreach ($outgoing as $relation) {
            if ($relation->relationtype === 'quickguidefor') {
                $targetlink = html_writer::link(
                    new moodle_url('/local/handbook/view.php', ['page' => $relation->slug]),
                    s($relation->title));
                echo html_writer::div(
                    html_writer::tag('i', '', ['class' => 'fa-solid fa-scale-balanced me-2 text-muted',
                        'aria-hidden' => 'true'])
                    . get_string('authoritynote', 'local_handbook', $targetlink),
                    'local-handbook-authority-note');
                break;
            }
        }
    }

    echo $contenthtml;
} else {
    echo html_writer::div(s(get_string('notpublished', 'local_handbook')), 'alert alert-info');
}

// Confirmation card (spec 16; mirrors the reader mockups).
if ($ackstatus !== null) {
    $cardbody = html_writer::tag('h3', s(get_string('readingconfirmation', 'local_handbook')),
        ['class' => 'h5 mb-2']);

    if ($ackstatus->status === ack_service::STATUS_CONFIRMED) {
        $cardbody .= html_writer::tag('p',
            html_writer::tag('i', '', ['class' => 'fa-solid fa-circle-check text-success me-2',
                'aria-hidden' => 'true'])
            . s(get_string('ackconfirmedrecord', 'local_handbook', (object)[
                'date' => userdate((int)$ackstatus->ack->timeacknowledged,
                    get_string('strftimedate', 'langconfig')),
                'version' => (int)$DB->get_field('local_handbook_revision', 'versionnumber',
                    ['id' => $ackstatus->ack->revisionid]),
            ])), ['class' => 'mb-1']);
        $cardbody .= html_writer::tag('p', s(get_string('ackrecordinfo', 'local_handbook')),
            ['class' => 'text-muted small mb-0']);
    } else {
        $cardbody .= html_writer::tag('p', s(get_string('ackrecordinfo', 'local_handbook')),
            ['class' => 'text-muted small']);
        $cardbody .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => local_handbook_page_url($page)->out(false),
        ]);
        $cardbody .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',
            'value' => 'acknowledge']);
        if ($pathctx) {
            $cardbody .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'pathid',
                'value' => (int)$pathctx->path->id]);
        }
        $cardbody .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',
            'value' => sesskey()]);
        $cardbody .= html_writer::div(
            html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'ack-check',
                'class' => 'form-check-input', 'required' => 'required'])
            . html_writer::tag('label',
                s(get_string('ackcheckboxlabel', 'local_handbook', format_string($page->title))),
                ['for' => 'ack-check', 'class' => 'form-check-label']),
            'form-check mb-3'
        );
        $cardbody .= html_writer::tag('button',
            html_writer::tag('i', '', ['class' => 'fa-solid fa-check me-2', 'aria-hidden' => 'true'])
            . s(get_string('confirmreading', 'local_handbook')),
            ['type' => 'submit', 'class' => 'btn btn-primary']);
        $cardbody .= html_writer::end_tag('form');
    }

    foreach ($memberpaths as $memberpath) {
        $pathlabel = html_writer::link(
            new moodle_url('/local/handbook/path.php', ['id' => $memberpath->id]),
            s(format_string($memberpath->name)))
            . ($memberpath->sectionname !== '' ? ' · ' . s($memberpath->sectionname) : '');
        $cardbody .= html_writer::tag('p',
            get_string('partofpath', 'local_handbook', $pathlabel),
            ['class' => 'text-muted small mb-0 mt-3']);
    }

    echo html_writer::div(html_writer::div($cardbody, 'card-body'),
        'card mt-4 local-handbook-ack', ['id' => 'confirmar']);
}

// "Mark as read" card for a path-required article that is not globally required
// reading (spec 8.3). Records an article-level read receipt, shared across paths.
if ($completionstatus !== null) {
    $cardbody = html_writer::tag('h3', s(get_string('readingcompletion', 'local_handbook')),
        ['class' => 'h5 mb-2']);

    if ($completionstatus->status === completion_service::STATUS_COMPLETED) {
        $done = $completionstatus->record;
        $cardbody .= html_writer::tag('p',
            html_writer::tag('i', '', ['class' => 'fa-solid fa-circle-check text-success me-2',
                'aria-hidden' => 'true'])
            . s(get_string('completedrecord', 'local_handbook', (object)[
                'date' => userdate((int)$done->timecompleted, get_string('strftimedate', 'langconfig')),
                'version' => (int)$done->versionnumber,
            ])), ['class' => 'mb-1']);
        $cardbody .= html_writer::tag('p', s(get_string('completioninfo', 'local_handbook')),
            ['class' => 'text-muted small mb-0']);
    } else {
        if ($completionstatus->status === completion_service::STATUS_RECONFIRM) {
            $cardbody .= html_writer::tag('p',
                s(get_string('completionreread', 'local_handbook', (int)$revision->versionnumber)),
                ['class' => 'mb-2']);
        }
        $cardbody .= html_writer::tag('p', s(get_string('completioninfo', 'local_handbook')),
            ['class' => 'text-muted small']);
        $cardbody .= html_writer::start_tag('form', [
            'method' => 'post', 'action' => local_handbook_page_url($page)->out(false)]);
        $cardbody .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',
            'value' => 'acknowledge']);
        if ($pathctx) {
            $cardbody .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'pathid',
                'value' => (int)$pathctx->path->id]);
        }
        $cardbody .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',
            'value' => sesskey()]);
        $cardbody .= html_writer::div(
            html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'read-check',
                'class' => 'form-check-input', 'required' => 'required'])
            . html_writer::tag('label',
                s(get_string('completioncheckboxlabel', 'local_handbook', format_string($page->title))),
                ['for' => 'read-check', 'class' => 'form-check-label']),
            'form-check mb-3');
        $cardbody .= html_writer::tag('button',
            html_writer::tag('i', '', ['class' => 'fa-solid fa-check me-2', 'aria-hidden' => 'true'])
            . s(get_string('markasread', 'local_handbook')),
            ['type' => 'submit', 'class' => 'btn btn-primary']);
        $cardbody .= html_writer::end_tag('form');
    }

    foreach ($memberpaths as $memberpath) {
        $pathlabel = html_writer::link(
            new moodle_url('/local/handbook/path.php', ['id' => $memberpath->id]),
            s(format_string($memberpath->name)))
            . ($memberpath->sectionname !== '' ? ' · ' . s($memberpath->sectionname) : '');
        $cardbody .= html_writer::tag('p',
            get_string('partofpath', 'local_handbook', $pathlabel),
            ['class' => 'text-muted small mb-0 mt-3']);
    }

    echo html_writer::div(html_writer::div($cardbody, 'card-body'),
        'card mt-4 local-handbook-ack', ['id' => 'confirmar']);
}

// "Next in the path" card (spec 8.6): primary once this page needs no
// (further) confirmation; otherwise it points at the confirmation first.
if ($pathctx) {
    $needsconfirm = ($ackstatus !== null && $ackstatus->status !== ack_service::STATUS_CONFIRMED)
        || ($completionstatus !== null
            && $completionstatus->status !== completion_service::STATUS_COMPLETED);

    $inner = html_writer::span(s(get_string('myreadingpath', 'local_handbook')) . ' · '
        . format_string($pathctx->path->name), 'eyebrow');

    if ($pathctx->next) {
        $nexturl = new moodle_url('/local/handbook/view.php',
            ['page' => $pathctx->next->slug, 'path' => $pathctx->path->id]);
        if ($needsconfirm) {
            // The confirmation card sits directly above — no second button.
            $inner .= html_writer::span(s(get_string('pathnextconfirm', 'local_handbook')), 'title');
            $inner .= html_writer::div(
                html_writer::link($nexturl,
                    s(get_string('pathnextup', 'local_handbook', format_string($pathctx->next->title))) . ' ›',
                    ['class' => 'btn btn-outline-secondary btn-sm']),
                'mt-2');
        } else {
            $inner .= html_writer::span(s(format_string($pathctx->next->title)), 'title');
            $inner .= html_writer::div(
                html_writer::link($nexturl,
                    s(get_string('pathnext', 'local_handbook'))
                    . html_writer::tag('i', '', ['class' => 'fa-solid fa-arrow-right ms-2 ml-2',
                        'aria-hidden' => 'true']),
                    ['class' => 'btn btn-primary btn-sm'])
                . html_writer::link(new moodle_url('/local/handbook/path.php',
                        ['id' => $pathctx->path->id]),
                    s(get_string('viewfullpath', 'local_handbook')),
                    ['class' => 'btn btn-link btn-sm']),
                'mt-2');
        }
    } else {
        $inner .= html_writer::span(s(get_string('pathend', 'local_handbook')), 'title');
        $inner .= html_writer::div(
            html_writer::link(new moodle_url('/local/handbook/path.php',
                    ['id' => $pathctx->path->id]),
                s(get_string('viewfullpath', 'local_handbook'))
                . html_writer::tag('i', '', ['class' => 'fa-solid fa-arrow-right ms-2 ml-2',
                    'aria-hidden' => 'true']),
                ['class' => 'btn btn-primary btn-sm']),
            'mt-2');
    }

    echo html_writer::div(html_writer::div($inner, 'card-body'),
        'card mt-4 local-handbook-pathnext');
}

echo html_writer::end_div(); // .col-lg-8.

// Rail: on-page TOC, metadata card and typed relations.
echo html_writer::start_div('col-lg-4');
echo html_writer::start_div('local-handbook-rail');

if ($pathctx) {
    echo local_handbook_render_path_panel($pathctx);
}

if (count($toc) >= 2) {
    $tocitems = '';
    foreach ($toc as $entry) {
        $tocitems .= html_writer::tag('li',
            html_writer::link('#' . $entry->id, s($entry->text)));
    }
    if ($ackstatus !== null || $completionstatus !== null) {
        $tocitems .= html_writer::tag('li',
            html_writer::link('#confirmar', s($ackstatus !== null
                ? get_string('readingconfirmation', 'local_handbook')
                : get_string('readingcompletion', 'local_handbook'))));
    }
    echo html_writer::div(
        html_writer::div(
            html_writer::tag('h3', s(get_string('onthispage', 'local_handbook')),
                ['class' => 'h6 text-uppercase text-muted mb-3'])
            . html_writer::tag('ul', $tocitems, ['class' => 'local-handbook-toc']),
            'card-body'),
        'card mb-3');
}

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

// Staff-facing published author and approver (spec 36.5). Sourced from the
// revision's authoruserid/approvedby, never from createdby, so an
// AI-prepared page never shows Handbook AI as its author.
if ($revision && (int)$revision->authoruserid > 0) {
    $authoruser = core_user::get_user((int)$revision->authoruserid, '*', IGNORE_MISSING);
    if ($authoruser) {
        $rows .= html_writer::tag('dt', s(get_string('author', 'local_handbook')), ['class' => 'col-5'])
            . html_writer::tag('dd', s(fullname($authoruser)), ['class' => 'col-7']);
    }
}
if ($revision && (int)$revision->approvedby > 0
        && (int)$revision->approvedby !== (int)($revision->authoruserid ?? 0)) {
    $approveruser = core_user::get_user((int)$revision->approvedby, '*', IGNORE_MISSING);
    if ($approveruser) {
        $rows .= html_writer::tag('dt', s(get_string('approver', 'local_handbook')), ['class' => 'col-5'])
            . html_writer::tag('dd', s(fullname($approveruser)), ['class' => 'col-7']);
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

// Typed relations, both directions, published targets only for readers.
$relitems = '';
foreach ([[$outgoing, false], [$incoming, true]] as [$relations, $reverse]) {
    foreach ($relations as $relation) {
        if (((int)$relation->publishedrevisionid === 0 || (int)$relation->archived === 1)
                && !$iseditorial) {
            continue;
        }
        $target = new stdClass();
        $target->slug = $relation->slug;
        $relitems .= html_writer::tag('li',
            html_writer::span(s(local_handbook_relation_label($relation->relationtype, $reverse)),
                'relation-type')
            . html_writer::link(local_handbook_page_url($target), s($relation->title))
        );
    }
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
        . html_writer::link(new moodle_url('/local/handbook/history.php', ['page' => $page->slug]),
            s(get_string('revisionhistory', 'local_handbook')))
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
