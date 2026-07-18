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
 * Shared helpers for local_handbook pages.
 *
 * Page shell mirrors local_grades (its AGENTS.md and shared plugin
 * instructions): one page-setup helper, one heading helper, one area-actions
 * row, all pages share the pagetype id that scopes styles.css.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// file_rewrite_pluginfile_urls() lives here; reader pages do not load
// formslib, so filelib must be required explicitly.
require_once($CFG->libdir . '/filelib.php');

use local_handbook\local\service\ack_service;
use local_handbook\local\service\completion_service;
use local_handbook\local\service\page_service;
use local_handbook\local\service\path_service;

/** @var string Pagetype for all handbook pages; scopes all plugin CSS. */
const LOCAL_HANDBOOK_PAGE_TYPE = 'local-handbook-area';

/**
 * Require login plus the base view capability.
 *
 * @param context_system $context System context.
 * @return void
 */
function local_handbook_require_view(context_system $context): void {
    require_login(null, false);
    require_capability('local/handbook:view', $context);
}

/**
 * Whether the user has any editorial capability.
 *
 * @param context_system $context System context.
 * @return bool
 */
function local_handbook_user_is_editorial(context_system $context): bool {
    return has_any_capability([
        'local/handbook:edit',
        'local/handbook:review',
        'local/handbook:approve',
        'local/handbook:publish',
    ], $context);
}

/**
 * Apply the shared handbook page setup so all pages share one layout.
 *
 * @param moodle_url $url Current page URL.
 * @param context_system $context System context.
 * @param string $subpage Subpage key (matches area-actions keys).
 * @param string $title Browser/page title.
 * @param string $breadcrumbtitle Breadcrumb label when it differs from title.
 * @return void
 */
function local_handbook_apply_page_setup(
    moodle_url $url,
    context_system $context,
    string $subpage,
    string $title,
    string $breadcrumbtitle = ''
): void {
    global $PAGE;

    $PAGE->set_url($url);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_pagetype(LOCAL_HANDBOOK_PAGE_TYPE);
    $PAGE->set_subpage($subpage);
    $PAGE->set_title($title);
    $PAGE->set_heading(local_handbook_get_plugin_heading($context));
    $PAGE->requires->css(new moodle_url('/local/handbook/styles.css'));

    local_handbook_apply_breadcrumbs($url, $breadcrumbtitle !== '' ? $breadcrumbtitle : $title);
}

/**
 * Apply the shared breadcrumb trail for the handbook area.
 *
 * @param moodle_url $currenturl Current page URL.
 * @param string $currenttitle Current page breadcrumb label.
 * @return void
 */
function local_handbook_apply_breadcrumbs(moodle_url $currenturl, string $currenttitle): void {
    global $PAGE;

    $homeurl = new moodle_url('/local/handbook/index.php');
    $sameashome = $currenturl->out(false) === $homeurl->out(false);

    if ($sameashome) {
        $PAGE->navbar->add(get_string('pluginname', 'local_handbook'));
        return;
    }

    $PAGE->navbar->add(get_string('pluginname', 'local_handbook'), $homeurl);
    $PAGE->navbar->add($currenttitle);
}

/**
 * Theme heading for the handbook area; shows the release to managers.
 *
 * @param context_system $context System context.
 * @return string
 */
function local_handbook_get_plugin_heading(context_system $context): string {
    $headingcontent = s(get_string('pluginname', 'local_handbook'));

    if (has_capability('local/handbook:manage', $context)) {
        $plugininfo = core_plugin_manager::instance()->get_plugin_info('local_handbook');
        $release = $plugininfo !== null ? trim((string)$plugininfo->release) : '';

        if ($release !== '') {
            $headingcontent .= ' ' . html_writer::span(
                s('v' . $release),
                'small text-muted local-handbook-heading-version'
            );
        }
    }

    return $headingcontent;
}

/**
 * Render the shared content heading row (title left, actions right).
 *
 * @param string $title Content title.
 * @param string $actions Optional action buttons HTML.
 * @return string
 */
function local_handbook_render_page_heading(string $title, string $actions = ''): string {
    $heading = html_writer::tag('h2', s($title), ['class' => 'mb-0']);
    $actionshtml = $actions !== ''
        ? html_writer::div($actions, 'd-flex flex-wrap gap-2 local-handbook-content-actions')
        : '';

    return html_writer::div(
        $heading . $actionshtml,
        'd-flex flex-wrap align-items-center justify-content-between gap-2 local-handbook-content-header'
    ) . html_writer::empty_tag('hr', ['class' => 'local-handbook-content-divider']);
}

/**
 * Build a single-action POST button for a change-set item (spec 36.4).
 *
 * @param moodle_url $url Form target (the change-set detail page).
 * @param string $action Action key.
 * @param int $revisionid Revision the action applies to.
 * @param string $label Button label.
 * @param string $btnclass Bootstrap button variant class.
 * @return string
 */
function local_handbook_changeset_action_button(moodle_url $url, string $action, int $revisionid,
        string $label, string $btnclass): string {
    $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
        'class' => 'd-inline']);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rid', 'value' => $revisionid]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::tag('button', s($label),
        ['type' => 'submit', 'class' => 'btn btn-sm ' . $btnclass]);
    $form .= html_writer::end_tag('form');
    return $form;
}

/**
 * Render a change-set item workflow button that posts an item id (used by
 * non-revision proposal items, e.g. metadata patches).
 *
 * @param moodle_url $url Change-set detail URL.
 * @param string $action Action name.
 * @param int $itemid Change-item id.
 * @param string $label Button label.
 * @param string $btnclass Bootstrap button class suffix.
 * @return string
 */
function local_handbook_changeset_item_button(moodle_url $url, string $action, int $itemid,
        string $label, string $btnclass): string {
    $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
        'class' => 'd-inline']);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'itemid', 'value' => $itemid]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::tag('button', s($label),
        ['type' => 'submit', 'class' => 'btn btn-sm ' . $btnclass]);
    $form .= html_writer::end_tag('form');
    return $form;
}

/**
 * Format a single page metadata value for display in a review diff.
 *
 * @param string $field Field name.
 * @param mixed $value Field value (published or proposed).
 * @return string HTML-safe display string.
 */
function local_handbook_format_metadata_value(string $field, $value): string {
    if ($value === null || $value === '') {
        return html_writer::span('—', 'text-muted');
    }
    switch ($field) {
        case 'contenttype':
            return s(get_string('contenttype_' . $value, 'local_handbook'));
        case 'criticality':
            return s(get_string('criticality_' . $value, 'local_handbook'));
        case 'requiredreading':
            return s(get_string((int)$value ? 'yes' : 'no'));
        case 'reviewdate':
            return (int)$value
                ? s(userdate((int)$value, get_string('strftimedate', 'langconfig')))
                : html_writer::span('—', 'text-muted');
        default:
            return s((string)$value);
    }
}

/**
 * Render a before/after table for a proposed page metadata (fiche) patch.
 *
 * @param stdClass $page The page (published values).
 * @param array $patch Field => proposed value map.
 * @return string HTML.
 */
function local_handbook_render_metadata_diff(stdClass $page, array $patch): string {
    $head = html_writer::tag('tr',
        html_writer::tag('th', s(get_string('metadatafield', 'local_handbook')), ['scope' => 'col'])
        . html_writer::tag('th', s(get_string('metadatacurrentvalue', 'local_handbook')), ['scope' => 'col'])
        . html_writer::tag('th', s(get_string('metadataproposedvalue', 'local_handbook')), ['scope' => 'col']));

    $rows = '';
    foreach ($patch as $field => $proposed) {
        $label = get_string('metafield_' . $field, 'local_handbook');
        $current = $page->$field ?? null;
        $rows .= html_writer::tag('tr',
            html_writer::tag('th', s($label), ['scope' => 'row', 'class' => 'text-nowrap'])
            . html_writer::tag('td', local_handbook_format_metadata_value($field, $current),
                ['class' => 'text-muted'])
            . html_writer::tag('td', local_handbook_format_metadata_value($field, $proposed)));
    }

    return html_writer::tag('table',
        html_writer::tag('thead', $head) . html_writer::tag('tbody', $rows),
        ['class' => 'table table-sm table-bordered small mb-2']);
}

/**
 * A set-level change-set action button (posts an action + sesskey), with an
 * optional confirmation prompt.
 *
 * @param moodle_url $url Change-set detail URL.
 * @param string $action Action name.
 * @param string $label Button label.
 * @param string $btnclass Bootstrap button class suffix.
 * @param string $confirmmsg Optional confirmation message.
 * @return string
 */
function local_handbook_changeset_set_button(moodle_url $url, string $action, string $label,
        string $btnclass, string $confirmmsg = ''): string {
    $attrs = ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline'];
    if ($confirmmsg !== '') {
        $attrs['onsubmit'] = 'return confirm(' . json_encode($confirmmsg) . ');';
    }
    $form = html_writer::start_tag('form', $attrs);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::tag('button', s($label),
        ['type' => 'submit', 'class' => 'btn btn-sm ' . $btnclass]);
    $form .= html_writer::end_tag('form');
    return $form;
}

/**
 * A remove-item form for a non-revision change item.
 *
 * @param moodle_url $url Change-set detail URL.
 * @param int $itemid Change-item id.
 * @return string
 */
function local_handbook_changeset_item_remove_form(moodle_url $url, int $itemid): string {
    $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
        'class' => 'd-inline',
        'onsubmit' => 'return confirm(' . json_encode(get_string('confirmremoveitem', 'local_handbook')) . ');']);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'removeitem']);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'itemid', 'value' => $itemid]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::tag('button', s(get_string('removeitem', 'local_handbook')),
        ['type' => 'submit', 'class' => 'btn btn-link btn-sm text-danger']);
    $form .= html_writer::end_tag('form');
    return $form;
}

/**
 * Workflow action row for a non-revision change item (metadata, new page,
 * relations): approve / apply / reject / remove, gated by the reviewer's caps.
 *
 * @param moodle_url $url Change-set detail URL.
 * @param stdClass $item Change-item record.
 * @param bool $canapprove Reviewer holds approve.
 * @param bool $canpublish Reviewer holds publish.
 * @param bool $canreview Reviewer holds review.
 * @return string HTML (empty when no action is available).
 */
function local_handbook_changeset_nonrevision_actions(moodle_url $url, stdClass $item,
        bool $canapprove, bool $canpublish, bool $canreview): string {
    $service = \local_handbook\local\service\changeset_service::class;
    $actions = [];
    if ($item->itemstatus === $service::ITEM_IN_REVIEW && $canapprove) {
        $actions[] = local_handbook_changeset_item_button($url, 'approveitem', (int)$item->id,
            get_string('approve', 'local_handbook'), 'btn-primary');
    }
    if ($item->itemstatus === $service::ITEM_APPROVED && $canpublish) {
        $actions[] = local_handbook_changeset_item_button($url, 'applyitem', (int)$item->id,
            get_string('applychange', 'local_handbook'), 'btn-primary');
    }
    if ($item->itemstatus === $service::ITEM_IN_REVIEW && $canreview) {
        $actions[] = local_handbook_changeset_item_button($url, 'rejectitem', (int)$item->id,
            get_string('reject', 'local_handbook'), 'btn-outline-danger');
    }
    if (!in_array($item->itemstatus, [$service::ITEM_IN_REVIEW, $service::ITEM_APPROVED,
            $service::ITEM_PUBLISHED], true)) {
        $actions[] = local_handbook_changeset_item_remove_form($url, (int)$item->id);
    }
    return $actions
        ? html_writer::div(implode(' ', $actions), 'd-flex flex-wrap gap-2 align-items-center')
        : '';
}

/**
 * Render a preview of a proposed new page (fiche summary + content preview).
 *
 * @param array $data New-page payload.
 * @return string HTML.
 */
function local_handbook_render_new_page_preview(array $data): string {
    global $DB;

    $rows = '';
    $catid = (int)($data['categoryid'] ?? 0);
    $catname = $catid
        ? (string)$DB->get_field('local_handbook_category', 'name', ['id' => $catid])
        : '';
    $fields = [
        'metafield_title' => (string)($data['title'] ?? ''),
        'category' => $catname,
        'metafield_contenttype' => isset($data['contenttype'])
            ? get_string('contenttype_' . $data['contenttype'], 'local_handbook') : '',
        'metafield_responsiblearea' => (string)($data['responsiblearea'] ?? ''),
    ];
    foreach ($fields as $labelkey => $value) {
        if (trim((string)$value) === '') {
            continue;
        }
        $rows .= html_writer::tag('tr',
            html_writer::tag('th', s(get_string($labelkey, 'local_handbook')),
                ['scope' => 'row', 'class' => 'text-nowrap'])
            . html_writer::tag('td', s($value)));
    }
    $out = html_writer::tag('table', html_writer::tag('tbody', $rows),
        ['class' => 'table table-sm table-bordered small mb-2']);

    $summary = trim((string)($data['summary'] ?? ''));
    if ($summary !== '') {
        $out .= html_writer::div(s($summary), 'small text-muted mb-2');
    }
    return $out;
}

/**
 * Render a list of proposed relation operations for review.
 *
 * @param array $ops Operation list (op, relationtype, targetpageid, targettempkey).
 * @return string HTML.
 */
function local_handbook_render_relation_ops(array $ops): string {
    global $DB;

    $items = '';
    foreach ($ops as $op) {
        $verb = ($op['op'] ?? '') === 'remove'
            ? get_string('relationopremove', 'local_handbook')
            : get_string('relationopcreate', 'local_handbook');
        $type = local_handbook_relation_label((string)($op['relationtype'] ?? ''));
        $targetid = (int)($op['targetpageid'] ?? 0);
        if ($targetid) {
            $target = (string)$DB->get_field('local_handbook_page', 'title', ['id' => $targetid]);
        } else {
            $target = (string)($op['targettempkey'] ?? '');
        }
        $items .= html_writer::tag('li',
            html_writer::span(s($verb), 'font-weight-bold') . ' '
            . s($type) . ' → ' . s($target));
    }
    return html_writer::tag('ul', $items, ['class' => 'small mb-2']);
}

/**
 * One before/after style row for a lifecycle proposal table.
 *
 * @param string $labelkey Lang key for the row label.
 * @param string $value Display value.
 * @return string
 */
function local_handbook_lifecycle_row(string $labelkey, string $value): string {
    return html_writer::tag('tr',
        html_writer::tag('th', s(get_string($labelkey, 'local_handbook')),
            ['scope' => 'row', 'class' => 'text-nowrap'])
        . html_writer::tag('td', s($value)));
}

/**
 * Render an archive or restore proposal item for review (spec 21, 25, 26).
 *
 * @param stdClass $page The affected page.
 * @param stdClass $item Change-item record (page_archive or page_restore).
 * @return string HTML.
 */
function local_handbook_render_lifecycle_item(stdClass $page, stdClass $item): string {
    global $DB;

    $service = \local_handbook\local\service\changeset_service::class;
    $payload = json_decode((string)$item->payloadjson, true) ?: [];

    if ($item->kind === $service::KIND_PAGE_RESTORE) {
        $out = html_writer::div(html_writer::tag('strong',
            s(get_string('restoreproposal', 'local_handbook'))), 'small mb-2');
        if (!empty($payload['note'])) {
            $out .= html_writer::div(s($payload['note']), 'small text-muted mb-2');
        }
        return $out;
    }

    // Archive proposal.
    $out = html_writer::div(html_writer::tag('strong',
        s(get_string('archiveproposal', 'local_handbook'))), 'small mb-1');

    $rows = '';
    $reason = (string)($payload['reason'] ?? '');
    if ($reason !== '') {
        $rows .= local_handbook_lifecycle_row('archivereasonlabel',
            get_string('archivereason_' . $reason, 'local_handbook'));
    }
    $repid = (int)($payload['replacementpageid'] ?? 0);
    if ($repid) {
        $rep = $DB->get_record('local_handbook_page', ['id' => $repid], 'id, title');
        $rows .= local_handbook_lifecycle_row('replacementpage',
            $rep ? format_string($rep->title) : (string)$repid);
    }
    $mode = (string)($payload['redirectmode'] ?? '');
    if ($mode !== '') {
        $rows .= local_handbook_lifecycle_row('redirectmodelabel',
            get_string('redirectmode_' . $mode, 'local_handbook'));
    }
    $out .= html_writer::tag('table', html_writer::tag('tbody', $rows),
        ['class' => 'table table-sm table-bordered small mb-2']);
    if (!empty($payload['note'])) {
        $out .= html_writer::div(s($payload['note']), 'small text-muted mb-2');
    }

    $impact = $service::archive_impact((int)$page->id);
    $out .= html_writer::div(
        s(get_string('archiveimpact', 'local_handbook', (object)[
            'relations' => $impact['inboundrelations'],
            'paths' => $impact['activepaths'],
        ])),
        'alert alert-info py-2 px-3 small mb-2');
    return $out;
}

/**
 * Render a category proposal item for review (spec 11).
 *
 * @param stdClass $item Change-item record (category_change).
 * @return string HTML.
 */
function local_handbook_render_category_item(stdClass $item): string {
    global $DB;

    $op = json_decode((string)$item->payloadjson, true) ?: [];
    $action = (string)($op['op'] ?? '');
    $name = static function (int $id) use ($DB): string {
        return $id ? (string)$DB->get_field('local_handbook_category', 'name', ['id' => $id]) : '';
    };

    $rows = local_handbook_lifecycle_row('categoryoplabel',
        get_string('categoryop_' . ($action !== '' ? $action : 'update'), 'local_handbook'));

    if ($action === 'create') {
        $rows .= local_handbook_lifecycle_row('categoryname', (string)($op['name'] ?? ''));
        if (!empty($op['parentid'])) {
            $rows .= local_handbook_lifecycle_row('categoryparent', $name((int)$op['parentid']));
        }
    } else if ($action === 'update') {
        $rows .= local_handbook_lifecycle_row('category', $name((int)($op['categoryid'] ?? 0)));
        if (isset($op['name'])) {
            $rows .= local_handbook_lifecycle_row('categoryname', (string)$op['name']);
        }
    } else if ($action === 'move') {
        $rows .= local_handbook_lifecycle_row('category', $name((int)($op['categoryid'] ?? 0)));
        $rows .= local_handbook_lifecycle_row('categoryparent',
            (int)($op['newparentid'] ?? 0) ? $name((int)$op['newparentid'])
                : get_string('topcategory', 'local_handbook'));
    } else if ($action === 'merge') {
        $rows .= local_handbook_lifecycle_row('categorymergesource', $name((int)($op['sourceid'] ?? 0)));
        $rows .= local_handbook_lifecycle_row('categorymergetarget', $name((int)($op['targetid'] ?? 0)));
    } else if ($action === 'delete_empty') {
        $rows .= local_handbook_lifecycle_row('category', $name((int)($op['categoryid'] ?? 0)));
    }

    return html_writer::tag('table', html_writer::tag('tbody', $rows),
        ['class' => 'table table-sm table-bordered small mb-2']);
}

/**
 * Render a reading-path proposal item for review (spec 7).
 *
 * The item carries a complete snapshot; this shows the header fields and the
 * ordered sections and pages that the path would match once applied.
 *
 * @param stdClass $item Change-item record (reading_path).
 * @return string HTML.
 */
function local_handbook_render_reading_path_item(stdClass $item): string {
    global $DB;

    $data = json_decode((string)$item->payloadjson, true) ?: [];
    $service = \local_handbook\local\service\changeset_service::class;
    $diff = $service::reading_path_diff($data);
    $changed = $diff['fields'];
    $pathid = (int)($data['pathid'] ?? 0);

    // Annotate a header value with its previous value when the field changed.
    $was = static function (string $key) use ($changed): string {
        return isset($changed[$key])
            ? ' ' . get_string('pathwas', 'local_handbook', (string)$changed[$key]['old']) : '';
    };

    $rows = local_handbook_lifecycle_row('pathnamelabel',
        (string)($data['name'] ?? '') . $was('name'));
    $rows .= local_handbook_lifecycle_row('pathoperation',
        get_string($pathid ? 'pathupdate' : 'pathcreate', 'local_handbook'));
    if (!empty($data['pathtype'])) {
        $rows .= local_handbook_lifecycle_row('pathtypelabel',
            get_string('pathtype_' . $data['pathtype'], 'local_handbook'));
    }
    if (!empty($data['schoolyear'])) {
        $rows .= local_handbook_lifecycle_row('pathschoolyear',
            (string)$data['schoolyear'] . $was('schoolyear'));
    }
    $activeval = get_string(!empty($data['active']) ? 'yes' : 'no');
    if (isset($changed['active'])) {
        $activeval .= ' ' . get_string('pathwas', 'local_handbook',
            get_string($changed['active']['old'] ? 'yes' : 'no'));
    }
    $rows .= local_handbook_lifecycle_row('pathactive', $activeval);
    if (!empty($data['estimatedminutes'])) {
        $rows .= local_handbook_lifecycle_row('pathestimatedminutes',
            (string)(int)$data['estimatedminutes'] . $was('estimatedminutes'));
    }
    $out = html_writer::tag('table', html_writer::tag('tbody', $rows),
        ['class' => 'table table-sm table-bordered small mb-2']);

    if (!empty($data['description'])) {
        $out .= html_writer::div(s((string)$data['description']), 'small text-muted mb-2');
    }

    // Proposed items, in order, grouped by section and annotated against the
    // current path (New / Now required|optional / Moved from ...).
    $pagetitle = static function (int $pid, string $tempkey) use ($DB): string {
        if ($pid) {
            $title = (string)$DB->get_field('local_handbook_page', 'title', ['id' => $pid]);
            return $title !== '' ? format_string($title) : ('#' . $pid);
        }
        return get_string('pathnewpageitem', 'local_handbook', $tempkey);
    };

    $blocks = [];
    $lastsection = null;
    foreach ($diff['items'] as $it) {
        $section = (string)$it['section'];
        if ($lastsection === null || $section !== $lastsection) {
            $blocks[] = ['name' => $section, 'lis' => ''];
            $lastsection = $section;
        }
        $idx = count($blocks) - 1;

        $label = s($pagetitle((int)$it['pageid'], (string)$it['pagetempkey']));
        if ($it['status'] === 'new' && !$diff['iscreate']) {
            $label .= ' ' . html_writer::span(s(get_string('pathitemnew', 'local_handbook')),
                'badge badge-success');
        }
        if (!empty($it['requiredchanged'])) {
            $label .= ' ' . html_writer::span(
                s(get_string($it['required'] ? 'pathitemnowrequired' : 'pathitemnowoptional',
                    'local_handbook')), 'badge badge-warning');
        } else if (empty($it['required'])) {
            $label .= ' ' . html_writer::span(
                s(get_string('pathoptionalsuffix', 'local_handbook')), 'text-muted');
        }
        if (!empty($it['sectionchanged'])) {
            $from = (string)$it['oldsection'] !== '' ? (string)$it['oldsection']
                : get_string('pathnosection', 'local_handbook');
            $label .= ' ' . html_writer::span(
                s(get_string('pathitemmovedsection', 'local_handbook', $from)), 'badge badge-info');
        }
        $blocks[$idx]['lis'] .= html_writer::tag('li', $label);
    }
    foreach ($blocks as $block) {
        if ($block['name'] !== '') {
            $out .= html_writer::tag('div', s($block['name']), ['class' => 'font-weight-bold small mt-2']);
        }
        if ($block['lis'] !== '') {
            $out .= html_writer::tag('ol', $block['lis'], ['class' => 'small mb-2']);
        }
    }

    // Pages the update drops from the path.
    if (!empty($diff['removed'])) {
        $rl = '';
        foreach ($diff['removed'] as $pid) {
            $rl .= html_writer::tag('li', s($pagetitle((int)$pid, '')));
        }
        $out .= html_writer::div(s(get_string('pathremovedheading', 'local_handbook')),
            'font-weight-bold small mt-2 text-danger');
        $out .= html_writer::tag('ul', $rl, ['class' => 'small mb-2 text-danger']);
    }

    return $out;
}

/**
 * Render the shared area navigation row (tab strip).
 *
 * @param string $currentpage Key of the current page.
 * @param context_system $context System context.
 * @return string
 */
function local_handbook_render_area_actions(string $currentpage, context_system $context): string {
    global $DB, $USER;

    $tabitems = [
        'home' => [
            'label' => get_string('pluginname', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/index.php'),
            'iconclass' => 'fa-book-open',
            'visible' => true,
        ],
        'search' => [
            'label' => get_string('searchhandbook', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/search.php'),
            'iconclass' => 'fa-magnifying-glass',
            'visible' => true,
        ],
        'path' => [
            'label' => get_string('myreadingpath', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/path.php'),
            'iconclass' => 'fa-route',
            'visible' => !empty(path_service::visible_paths((int)$USER->id,
                has_capability('local/handbook:managepaths', $context))),
            'badge' => has_capability('local/handbook:acknowledge', $context)
                ? ack_service::count_pending_for_user((int)$USER->id) : 0,
        ],
    ];

    $managementitems = [
        'reviewqueue' => [
            'label' => get_string('reviewqueue', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/review.php'),
            'visible' => has_any_capability(
                ['local/handbook:review', 'local/handbook:approve', 'local/handbook:publish'], $context),
        ],
        'categories' => [
            'label' => get_string('managecategories', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/categories.php'),
            'visible' => has_capability('local/handbook:managecategories', $context),
        ],
        'areas' => [
            'label' => get_string('manageareas', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/areas.php'),
            'visible' => has_capability('local/handbook:managecategories', $context),
        ],
        'paths' => [
            'label' => get_string('managepaths', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/paths.php'),
            'visible' => has_capability('local/handbook:managepaths', $context),
        ],
        'recommendations' => [
            'label' => get_string('recommendations', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/recommendations.php'),
            'visible' => has_capability('local/handbook:managepaths', $context),
        ],
        'findings' => [
            'label' => get_string('managefindings', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/findings.php'),
            'visible' => has_capability('local/handbook:managefindings', $context),
        ],
        'styleguide' => [
            'label' => get_string('styleguide', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/styleguide.php'),
            'visible' => has_capability('local/handbook:edit', $context),
        ],
        'changesets' => [
            'label' => get_string('changesets', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/changesets.php'),
            'visible' => has_capability('local/handbook:managechangesets', $context),
        ],
        'reports' => [
            'label' => get_string('reports', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/reports.php'),
            'visible' => has_capability('local/handbook:viewreports', $context),
        ],
        'readers' => [
            'label' => get_string('readingdashboard', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/readers.php'),
            'visible' => has_capability('local/handbook:viewreports', $context),
        ],
        'import' => [
            'label' => get_string('importseed', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/import.php'),
            'visible' => has_capability('local/handbook:manage', $context),
        ],
        'images' => [
            'label' => get_string('manageimages', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/images.php'),
            'visible' => has_capability('local/handbook:manage', $context),
        ],
    ];

    $tabs = '';
    foreach ($tabitems as $key => $item) {
        if (!$item['visible']) {
            continue;
        }

        $classes = 'nav-link d-flex align-items-center';
        $classes .= $key === $currentpage ? ' active' : '';
        $badge = !empty($item['badge'])
            ? ' ' . html_writer::span((string)$item['badge'], 'badge badge-primary ml-2')
            : '';
        $tabs .= html_writer::tag('li',
            html_writer::link($item['url'], html_writer::tag('i', '', [
                'class' => 'fa-solid ' . $item['iconclass'] . ' me-2',
                'aria-hidden' => 'true',
            ]) . s($item['label']) . $badge, ['class' => $classes]),
            ['class' => 'nav-item']
        );
    }

    $dropdownitems = '';
    foreach ($managementitems as $key => $item) {
        if (!$item['visible']) {
            continue;
        }

        $itemclasses = 'dropdown-item';
        $itemclasses .= $key === $currentpage ? ' active' : '';
        $dropdownitems .= html_writer::link($item['url'], s($item['label']), ['class' => $itemclasses]);
    }

    if ($dropdownitems !== '') {
        $isgroupactive = in_array($currentpage,
            ['reviewqueue', 'categories', 'paths', 'findings', 'changesets', 'reports', 'import'], true);
        $toggleclasses = 'nav-link d-flex align-items-center';
        $toggleclasses .= $isgroupactive ? ' active' : '';

        $tabs .= html_writer::tag('li',
            html_writer::link('#', html_writer::tag('i', '', [
                'class' => 'fa-solid fa-gear me-2',
                'aria-hidden' => 'true',
            ]) . s(get_string('managetools', 'local_handbook')) . html_writer::tag('i', '', [
                'class' => 'fa-solid fa-chevron-down ms-2',
                'aria-hidden' => 'true',
            ]), [
                'class' => $toggleclasses,
                'data-toggle' => 'dropdown',
                'data-bs-toggle' => 'dropdown',
                'aria-expanded' => 'false',
            ]) . html_writer::div($dropdownitems, 'dropdown-menu'),
            ['class' => 'nav-item dropdown']
        );
    }

    if ($tabs === '') {
        return '';
    }

    return html_writer::tag(
        'nav',
        html_writer::tag('ul', $tabs, ['class' => 'nav nav-tabs gap-2 mb-0']),
        [
            'class' => 'mb-4 local-handbook-area-actions',
            'aria-label' => get_string('navigation'),
        ]
    );
}

/**
 * Format a timestamp as a short date, or a muted dash when unset.
 *
 * @param int $timestamp Unix timestamp (0 = unset).
 * @return string
 */
function local_handbook_format_date(int $timestamp): string {
    if ($timestamp <= 0) {
        return html_writer::span('—', 'text-muted');
    }
    return userdate($timestamp, get_string('strftimedate', 'langconfig'));
}

/**
 * URL of a page's reader view (slug preferred, id fallback).
 *
 * @param stdClass $page Page record.
 * @return moodle_url
 */
function local_handbook_page_url(stdClass $page): moodle_url {
    return new moodle_url('/local/handbook/view.php', ['page' => $page->slug]);
}

/**
 * Render the badge row for a page (type, criticality, required reading).
 *
 * @param stdClass $page Page record.
 * @return string
 */
function local_handbook_render_page_badges(stdClass $page): string {
    $badges = html_writer::span(
        s(get_string('contenttype_' . $page->contenttype, 'local_handbook')),
        'badge badge-secondary'
    );

    if ($page->criticality === 'safetycritical') {
        $badges .= ' ' . html_writer::span(
            s(get_string('criticality_safetycritical', 'local_handbook')),
            'badge badge-warning'
        );
    }

    if ((int)$page->requiredreading === 1) {
        $badges .= ' ' . html_writer::span(
            s(get_string('requiredreading', 'local_handbook')),
            'badge badge-primary'
        );
    }

    if ((int)$page->authoritylevel === 1) {
        $badges .= ' ' . html_writer::span(
            s(get_string('authority_1', 'local_handbook')),
            'badge badge-dark local-handbook-badge-authority'
        );
    }

    return html_writer::div($badges, 'local-handbook-page-badges');
}

/**
 * Demote heading levels in stored content by one step (h2->h3, h3->h4, h4->h5).
 *
 * Stored content starts at h2 (specification 10.2); the reader renders it
 * beneath the h2 content title, so headings shift one level down. Mirrors
 * the reader-view mockup convention.
 *
 * @param string $html Rendered page content.
 * @return string
 */
function local_handbook_demote_headings(string $html): string {
    // Deepest first so already-demoted tags are not demoted twice.
    $html = preg_replace('/<(\/?)h4(\s[^>]*)?>/i', '<$1h5$2>', $html);
    $html = preg_replace('/<(\/?)h3(\s[^>]*)?>/i', '<$1h4$2>', $html);
    $html = preg_replace('/<(\/?)h2(\s[^>]*)?>/i', '<$1h3$2>', $html);
    return $html;
}

/**
 * Render a published revision's content through Moodle's format/file APIs.
 *
 * @param stdClass $revision Revision record.
 * @param context_system $context System context.
 * @return string
 */
function local_handbook_render_revision_content(stdClass $revision, context_system $context): string {
    $content = file_rewrite_pluginfile_urls(
        (string)$revision->content,
        'pluginfile.php',
        $context->id,
        'local_handbook',
        'revision',
        $revision->id
    );

    $content = format_text($content, $revision->contentformat, [
        'context' => $context,
        'noclean' => false,
    ]);

    $content = local_handbook_demote_headings($content);

    // Wikipedia-style cross-links: the first mention of another published
    // page's title becomes a link to it (render time only; stored content
    // is never modified).
    $content = \local_handbook\local\service\autolink_service::apply(
        $content, (int)$revision->pageid);

    return html_writer::div($content, 'local-handbook-page-body');
}

/**
 * Fetch visible categories ordered for display.
 *
 * @param int $parentid Parent category id (0 = top level).
 * @param bool $includehidden Include hidden categories (for managers).
 * @return stdClass[]
 */
function local_handbook_get_categories(int $parentid = 0, bool $includehidden = false): array {
    global $DB;

    $conditions = ['parentid' => $parentid];
    if (!$includehidden) {
        $conditions['visible'] = 1;
    }

    return $DB->get_records('local_handbook_category', $conditions, 'sortorder ASC, name ASC');
}

/**
 * Count published, non-archived pages per category.
 *
 * @return array Map of categoryid => count.
 */
function local_handbook_count_published_pages_by_category(): array {
    global $DB;

    $sql = "SELECT categoryid, COUNT(*) AS pagecount
              FROM {local_handbook_page}
             WHERE publishedrevisionid > 0 AND archived = 0
          GROUP BY categoryid";

    $counts = [];
    foreach ($DB->get_records_sql($sql) as $row) {
        $counts[(int)$row->categoryid] = (int)$row->pagecount;
    }
    return $counts;
}

/**
 * Fetch published, non-archived pages of a category, ordered for display.
 *
 * @param int $categoryid Category id.
 * @return stdClass[]
 */
function local_handbook_get_published_pages(int $categoryid): array {
    global $DB;

    return $DB->get_records_select('local_handbook_page',
        'categoryid = :categoryid AND publishedrevisionid > 0 AND archived = 0',
        ['categoryid' => $categoryid], 'sortorder ASC, title ASC');
}

/**
 * Fetch the most recently published pages across the handbook.
 *
 * @param int $limit Maximum number of pages.
 * @return stdClass[] Page records with ->timepublished and ->versionnumber.
 */
function local_handbook_get_recently_published(int $limit = 5): array {
    global $DB;

    $sql = "SELECT p.*, r.timepublished, r.versionnumber
              FROM {local_handbook_page} p
              JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
             WHERE p.archived = 0
          ORDER BY r.timepublished DESC";

    return $DB->get_records_sql($sql, [], 0, $limit);
}

/**
 * Validated Font Awesome icon class for a category (default: folder).
 *
 * @param stdClass $category Category record.
 * @return string A safe fa-* class name.
 */
function local_handbook_category_icon(stdClass $category): string {
    $icon = trim((string)($category->icon ?? ''));
    if (preg_match('/^fa-[a-z0-9-]+$/', $icon)) {
        return $icon;
    }
    return 'fa-folder-open';
}

/**
 * URL of a page's banner image, or null when none is set.
 *
 * The banner lives in file area "bannerimage" (itemid = page id) and is used
 * twice: cropped to 16:9 on category cards and to 3:1 at the top of the
 * article — both crops are CSS (object-fit: cover), one upload serves both.
 *
 * @param int $pageid Page id.
 * @return moodle_url|null
 */
function local_handbook_banner_url(int $pageid): ?moodle_url {
    $fs = get_file_storage();
    $files = $fs->get_area_files(context_system::instance()->id, 'local_handbook',
        'bannerimage', $pageid, 'itemid, filepath, filename', false);
    foreach ($files as $file) {
        if ($file->is_valid_image()) {
            return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                $file->get_filearea(), $file->get_itemid(), $file->get_filepath(),
                $file->get_filename());
        }
    }
    return null;
}

/**
 * Font Awesome icon for a content type (used by the no-image card fallback).
 *
 * @param string $contenttype Content type key.
 * @return string A safe fa-* class name.
 */
function local_handbook_contenttype_icon(string $contenttype): string {
    $map = [
        'policy' => 'fa-scale-balanced',
        'procedure' => 'fa-list-check',
        'standard' => 'fa-clipboard-check',
        'guideline' => 'fa-compass',
        'quickguide' => 'fa-bolt',
        'template' => 'fa-clone',
        'example' => 'fa-lightbulb',
        'roledescription' => 'fa-user-tie',
    ];
    return $map[$contenttype] ?? 'fa-book-open';
}

/**
 * Reading-path context for an article view (spec 8.6, 15).
 *
 * Chooses the path: the ?path=N the reader arrived with when the page belongs
 * to it, else the first visible active path containing the page. Returns the
 * path, its ordered items with the reader's completion state, the current
 * item's position, and the sequential next item — or null when the page is in
 * no (visible) active path.
 *
 * @param stdClass $page Page record.
 * @param int $requestedpathid Path id from the URL (0 = none).
 * @param int $userid Reader.
 * @param bool $manager Managers see every path.
 * @return stdClass|null {path, items[], currentindex, next, confirmed, total}
 */
function local_handbook_path_context(stdClass $page, int $requestedpathid, int $userid,
        bool $manager): ?stdClass {
    global $DB;

    $memberships = $DB->get_records_sql(
        "SELECT p.*
           FROM {local_handbook_path} p
           JOIN {local_handbook_pathitem} i ON i.pathid = p.id
          WHERE i.pageid = :pageid AND p.active = 1
       ORDER BY p.schoolyear DESC, p.name ASC", ['pageid' => (int)$page->id]);
    if (!$memberships) {
        return null;
    }

    $chosen = null;
    if ($requestedpathid && isset($memberships[$requestedpathid])
            && ($manager || path_service::is_visible($memberships[$requestedpathid], $userid))) {
        $chosen = $memberships[$requestedpathid];
    }
    if (!$chosen) {
        foreach ($memberships as $candidate) {
            if ($manager || path_service::is_visible($candidate, $userid)) {
                $chosen = $candidate;
                break;
            }
        }
    }
    if (!$chosen) {
        return null;
    }

    $rows = $DB->get_records_sql(
        "SELECT i.id, i.pageid, i.sectionname, i.required, i.sortorder,
                p.slug, p.title, p.publishedrevisionid,
                COALESCE(r.wordcount, 0) AS wordcount
           FROM {local_handbook_pathitem} i
           JOIN {local_handbook_page} p ON p.id = i.pageid
      LEFT JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
          WHERE i.pathid = :pathid AND p.archived = 0
       ORDER BY i.sortorder ASC, i.id ASC", ['pathid' => (int)$chosen->id]);

    $items = [];
    $currentindex = -1;
    $confirmed = 0;
    $total = 0;
    $index = 0;
    foreach ($rows as $row) {
        $status = completion_service::completion_status($userid, (object)[
            'id' => (int)$row->pageid,
            'publishedrevisionid' => (int)$row->publishedrevisionid,
        ]);
        $done = $status->status === completion_service::STATUS_COMPLETED;
        if ((int)$row->required) {
            $total++;
            if ($done) {
                $confirmed++;
            }
        }
        if ((int)$row->pageid === (int)$page->id) {
            $currentindex = $index;
        }
        $items[] = (object)[
            'pageid' => (int)$row->pageid,
            'slug' => (string)$row->slug,
            'title' => (string)$row->title,
            'sectionname' => (string)$row->sectionname,
            'required' => (int)$row->required,
            'done' => $done,
            'iscurrent' => (int)$row->pageid === (int)$page->id,
            'wordcount' => (int)$row->wordcount,
        ];
        $index++;
    }

    // Reading-time estimate: what is still unconfirmed, in minutes.
    $remainingwords = 0;
    foreach ($items as $item) {
        if (!$item->done) {
            $remainingwords += $item->wordcount;
        }
    }

    return (object)[
        'path' => $chosen,
        'items' => $items,
        'currentindex' => $currentindex,
        'next' => ($currentindex >= 0 && isset($items[$currentindex + 1]))
            ? $items[$currentindex + 1] : null,
        'confirmed' => $confirmed,
        'total' => $total,
        'remainingminutes' => local_handbook_reading_minutes($remainingwords),
    ];
}

/**
 * Render the "you are on a path" rail panel: ordered items with completion
 * ticks, the current article highlighted, and a link to the full path.
 *
 * @param stdClass $ctx Context from local_handbook_path_context().
 * @return string HTML.
 */
function local_handbook_render_path_panel(stdClass $ctx): string {
    $percent = $ctx->total > 0 ? (int)round($ctx->confirmed / $ctx->total * 100) : 0;

    $body = html_writer::tag('h3',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-route me-2 text-primary', 'aria-hidden' => 'true'])
        . s(get_string('myreadingpath', 'local_handbook')),
        ['class' => 'h6 text-uppercase text-muted mb-1']);
    $optionalbadge = !empty($ctx->path->optionalpath)
        ? ' ' . html_writer::span(s(get_string('optionalitem', 'local_handbook')),
            'pathpanel-optional')
        : '';
    $body .= html_writer::tag('p', html_writer::link(
        new moodle_url('/local/handbook/path.php', ['id' => $ctx->path->id]),
        html_writer::tag('strong', s(format_string($ctx->path->name)))) . $optionalbadge,
        ['class' => 'mb-2']);
    $body .= html_writer::div(
        html_writer::div('', 'progress-bar', [
            'role' => 'progressbar',
            'style' => 'width: ' . $percent . '%',
            'aria-valuenow' => $ctx->confirmed,
            'aria-valuemin' => 0,
            'aria-valuemax' => max(1, $ctx->total),
        ]),
        'progress mb-1', ['style' => 'height: 0.4rem;']);
    $body .= html_writer::div(s(get_string('pathprogress', 'local_handbook', (object)[
        'confirmed' => $ctx->confirmed, 'total' => $ctx->total,
    ]))
        . (!empty($ctx->remainingminutes)
            ? ' · ' . s(get_string('readingtimeleft', 'local_handbook', $ctx->remainingminutes))
            : ''), 'small text-muted mb-2');

    $rows = '';
    foreach ($ctx->items as $item) {
        $classes = 'pathpanel-item';
        $classes .= $item->done ? ' is-done' : ' is-pending';
        if ($item->iscurrent) {
            $classes .= ' is-current';
        }
        $label = s($item->title)
            . (!$item->required
                ? ' ' . html_writer::span(s(get_string('optionalitem', 'local_handbook')),
                    'pathpanel-optional')
                : '');
        $rows .= html_writer::tag('li',
            $item->iscurrent
                ? html_writer::span($label)
                : html_writer::link(new moodle_url('/local/handbook/view.php',
                    ['page' => $item->slug, 'path' => $ctx->path->id]), $label),
            ['class' => $classes]);
    }
    $body .= html_writer::tag('ol', $rows, ['class' => 'local-handbook-pathpanel-list']);

    return html_writer::div(html_writer::div($body, 'card-body'),
        'card mb-3 local-handbook-pathpanel');
}

/**
 * Reading minutes for a word count: ~200 words per minute (institutional
 * Spanish prose reads slower than casual text), minimum one minute.
 *
 * @param int $words Word count (image weight already baked in at save).
 * @return int Estimated minutes, >= 1 when there are any words.
 */
function local_handbook_reading_minutes(int $words): int {
    if ($words <= 0) {
        return 0;
    }
    return max(1, (int)ceil($words / 200));
}

/**
 * Attached source documents of a page (file area "attachments").
 *
 * @param int $pageid Page id.
 * @return stored_file[] Ordered by filename.
 */
function local_handbook_page_attachments(int $pageid): array {
    $fs = get_file_storage();
    return $fs->get_area_files(context_system::instance()->id, 'local_handbook',
        'attachments', $pageid, 'filename', false);
}

/**
 * Attachment count for a page, from a request-level cache of all counts
 * (one query serves every card in a grid).
 *
 * @param int $pageid Page id.
 * @return int
 */
function local_handbook_attachment_count(int $pageid): int {
    global $DB;

    static $counts = null;
    if ($counts === null) {
        $counts = [];
        $sql = "SELECT itemid, COUNT(1) AS filecount
                  FROM {files}
                 WHERE contextid = :contextid AND component = 'local_handbook'
                       AND filearea = 'attachments' AND filename <> '.'
              GROUP BY itemid";
        $records = $DB->get_records_sql($sql, ['contextid' => context_system::instance()->id]);
        foreach ($records as $record) {
            $counts[(int)$record->itemid] = (int)$record->filecount;
        }
    }
    return $counts[$pageid] ?? 0;
}

/**
 * Type tile (css modifier + short label) for an attachment filename.
 *
 * @param string $filename File name.
 * @return string[] [$modifierclass, $label].
 */
function local_handbook_attachment_tile(string $filename): array {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'pdf' => ['is-pdf', 'PDF'],
        'doc' => ['is-doc', 'DOC'], 'docx' => ['is-doc', 'DOC'],
        'odt' => ['is-doc', 'DOC'], 'rtf' => ['is-doc', 'DOC'],
        'xls' => ['is-xls', 'XLS'], 'xlsx' => ['is-xls', 'XLS'],
        'ods' => ['is-xls', 'XLS'], 'csv' => ['is-xls', 'CSV'],
        'ppt' => ['is-ppt', 'PPT'], 'pptx' => ['is-ppt', 'PPT'], 'odp' => ['is-ppt', 'PPT'],
        'png' => ['is-img', 'IMG'], 'jpg' => ['is-img', 'IMG'], 'jpeg' => ['is-img', 'IMG'],
        'gif' => ['is-img', 'IMG'], 'webp' => ['is-img', 'IMG'], 'svg' => ['is-img', 'IMG'],
        'zip' => ['is-zip', 'ZIP'], 'rar' => ['is-zip', 'RAR'], '7z' => ['is-zip', '7Z'],
    ];
    if (isset($map[$extension])) {
        return $map[$extension];
    }
    $label = $extension !== '' ? core_text::strtoupper(core_text::substr($extension, 0, 4)) : 'FILE';
    return ['is-file', $label];
}

/**
 * Render the "Documentos" rail card. Empty string when the page has no
 * attachments, so the card simply does not appear.
 *
 * @param int $pageid Page id.
 * @return string
 */
function local_handbook_render_attachments_card(int $pageid): string {
    $files = local_handbook_page_attachments($pageid);
    if (!$files) {
        return '';
    }

    $context = context_system::instance();
    $rows = '';
    foreach ($files as $file) {
        $filename = $file->get_filename();
        [$tileclass, $tilelabel] = local_handbook_attachment_tile($filename);
        $url = moodle_url::make_pluginfile_url($context->id, 'local_handbook', 'attachments',
            $pageid, $file->get_filepath(), $filename, true);
        $meta = display_size((int)$file->get_filesize()) . ' · '
            . userdate((int)$file->get_timemodified(), get_string('strftimedatefullshort', 'langconfig'));
        $rows .= html_writer::link($url,
            html_writer::span(s($tilelabel), 'att-ic ' . $tileclass)
            . html_writer::span(
                html_writer::span(s($filename), 'att-name')
                . html_writer::span(s($meta), 'att-meta'),
                'att-info')
            . html_writer::span('&#8595;', 'att-dl', ['aria-hidden' => 'true']),
            ['class' => 'local-handbook-att']);
    }

    return html_writer::div(
        html_writer::div(
            html_writer::tag('h3', s(get_string('attachments', 'local_handbook')),
                ['class' => 'h6 text-uppercase text-muted mb-0']),
            'card-body pb-2')
        . html_writer::div($rows, 'local-handbook-attachments'),
        'card mb-3');
}

/**
 * Render one page as a banner card (category view, home accordion, live
 * search results all share this markup). Whole card is clickable.
 *
 * @param stdClass $page Page record.
 * @param int $version Published version number (0 = omit).
 * @return string HTML.
 */
function local_handbook_render_page_card(stdClass $page, int $version = 0): string {
    $bannerurl = local_handbook_banner_url((int)$page->id);
    if ($bannerurl) {
        $media = html_writer::div(
            html_writer::empty_tag('img', [
                'src' => $bannerurl->out(false), 'alt' => '', 'loading' => 'lazy',
            ]),
            'local-handbook-card-media');
    } else {
        $media = html_writer::div(
            html_writer::tag('i', '', [
                'class' => 'fa-solid ' . local_handbook_contenttype_icon((string)$page->contenttype),
                'aria-hidden' => 'true',
            ]),
            'local-handbook-card-media is-fallback');
    }

    $pills = html_writer::span(
        s(get_string('contenttype_' . $page->contenttype, 'local_handbook')),
        'local-handbook-card-pill');
    if ((int)$page->requiredreading) {
        $pills .= html_writer::span(s(get_string('requiredreading', 'local_handbook')),
            'local-handbook-card-pill is-required');
    }

    $body = html_writer::div($pills, 'local-handbook-card-pills')
        . html_writer::tag('h4', s($page->title), ['class' => 'local-handbook-card-title'])
        . (trim((string)$page->summary) !== ''
            ? html_writer::tag('p', s($page->summary), ['class' => 'local-handbook-card-summary'])
            : '');

    $foot = html_writer::span(
            s(get_string('lastupdated', 'local_handbook') . ': '
                . local_handbook_format_date((int)$page->timemodified)))
        . ($version ? html_writer::span(s(get_string('versionnumber', 'local_handbook', $version))) : '');
    $attachmentcount = local_handbook_attachment_count((int)$page->id);
    if ($attachmentcount > 0) {
        $foot .= html_writer::span((string)$attachmentcount, 'local-handbook-card-clip', [
            'title' => get_string('attachmentcount', 'local_handbook', $attachmentcount),
        ]);
    }

    // The card IS the link (no overlay tricks a theme can break); it contains
    // no other interactive elements, so the whole surface navigates.
    return html_writer::link(local_handbook_page_url($page),
        $media
        . html_writer::div($body, 'local-handbook-card-body')
        . html_writer::div($foot, 'local-handbook-card-foot'),
        ['class' => 'local-handbook-card']);
}

/**
 * Published version numbers for a set of pages, in one query.
 *
 * @param stdClass[] $pages Page records (need publishedrevisionid).
 * @return int[] versionnumber keyed by revision id.
 */
function local_handbook_published_versions(array $pages): array {
    global $DB;

    $versions = [];
    $revisionids = array_filter(array_map(
        static fn(stdClass $p): int => (int)$p->publishedrevisionid, $pages));
    if ($revisionids) {
        foreach ($DB->get_records_list('local_handbook_revision', 'id', $revisionids,
                '', 'id, versionnumber') as $rev) {
            $versions[(int)$rev->id] = (int)$rev->versionnumber;
        }
    }
    return $versions;
}

/**
 * The handbook content-pattern catalogue (the "hb-*" house style).
 *
 * Single source of truth shared by the editor style guide (manage/styleguide.php)
 * and the API (external\get_style_guide, which feeds the Handbook AI). Each entry
 * carries a stable key, a localized title and "when to use" line, and an
 * example of KSES-safe HTML the author copies into the editor. The example is
 * both rendered (as a live preview) and shown as source.
 *
 * @return stdClass[] Each: {key, title, whenuse, html}.
 */
function local_handbook_style_patterns(): array {
    $patterns = [];
    $add = static function (string $key, string $html) use (&$patterns): void {
        $patterns[] = (object)[
            'key' => $key,
            'title' => get_string('sgtitle_' . $key, 'local_handbook'),
            'whenuse' => get_string('sguse_' . $key, 'local_handbook'),
            'html' => trim($html),
        ];
    };

    $add('steps', <<<'HTML'
<ol class="hb-steps">
  <li>
    <p class="hb-step-title">Planificar la salida <span class="hb-role">Docente responsable</span></p>
    <p>Definir el objetivo pedagógico, la fecha y el costo por estudiante.</p>
    <ol class="hb-substeps">
      <li>Completar el formulario de salida pedagógica.</li>
      <li>Adjuntar el presupuesto de transporte.</li>
    </ol>
  </li>
  <li>
    <p class="hb-step-title">Obtener la autorización <span class="hb-role">Coordinación</span></p>
    <div class="hb-note"><p><strong>Nota:</strong> salidas fuera del municipio requieren visto bueno de Rectorado.</p></div>
  </li>
</ol>
<div class="hb-result"><p><strong>Resultado esperado:</strong> salida documentada de inicio a fin.</p></div>
HTML);

    $add('callouts', <<<'HTML'
<div class="hb-note"><p><strong>Nota:</strong> información contextual que ayuda pero no cambia el procedimiento.</p></div>
<div class="hb-tip"><p><strong>Consejo:</strong> una práctica recomendada, opcional.</p></div>
<div class="hb-warning"><p><strong>Advertencia:</strong> riesgo de error o retrabajo si se omite.</p></div>
<div class="hb-important"><p><strong>Importante:</strong> obligación normativa o de seguridad; nunca omitir.</p></div>
HTML);

    $add('branches', <<<'HTML'
<div class="hb-branches">
  <div class="hb-branch">
    <p class="hb-branch-if">Si el estudiante es menor de edad</p>
    <ul><li>Contactar primero al responsable legal.</li><li>Registrar la comunicación.</li></ul>
  </div>
  <div class="hb-branch">
    <p class="hb-branch-if">Si el estudiante es mayor de edad</p>
    <ul><li>Informar al estudiante directamente.</li></ul>
  </div>
</div>
HTML);

    $add('compact', <<<'HTML'
<ol class="hb-steps hb-compact">
  <li><p class="hb-step-title">Asegurar al estudiante</p><p>Atención inmediata; no dejarlo solo.</p></li>
  <li><p class="hb-step-title">Avisar a enfermería</p><p>Extensión 114.</p></li>
  <li><p class="hb-step-title">Registrar el incidente</p><p>Formulario el mismo día.</p></li>
</ol>
HTML);

    $add('org', <<<'HTML'
<div class="hb-org">
  <ul>
    <li>
      <div class="hb-org-node"><span class="unit">Rectorado</span><span class="holder">Dirección general</span></div>
      <ul>
        <li>
          <div class="hb-org-team">
            <span class="team-label">Equipo de Liderazgo Educativo</span>
            <div class="team-members">
              <div class="hb-org-member"><span class="name">Dirección</span></div>
              <div class="hb-org-member"><span class="name">Coordinación</span></div>
              <div class="hb-org-member"><span class="name">Convivencia</span></div>
              <div class="hb-org-member"><span class="name">Consejería</span></div>
            </div>
          </div>
          <ul>
            <li><div class="hb-org-node"><span class="unit">Docentes</span></div></li>
          </ul>
        </li>
        <li><div class="hb-org-node"><span class="unit">Administración</span></div></li>
      </ul>
    </li>
  </ul>
</div>
HTML);

    $add('roles', <<<'HTML'
<div class="hb-roles">
  <div class="hb-role-card">
    <p class="role-name">Docente responsable</p>
    <p class="who">Área académica</p>
    <ul><li>Planifica y ejecuta la actividad.</li><li>Custodia las autorizaciones.</li></ul>
  </div>
  <div class="hb-role-card">
    <p class="role-name">Coordinación Académica</p>
    <p class="who">Coordinación</p>
    <ul><li>Autoriza salidas y proyectos.</li><li>Escala a Rectorado cuando corresponde.</li></ul>
  </div>
</div>
HTML);

    $add('escalation', <<<'HTML'
<ol class="hb-escalation">
  <li><p class="lvl-title">Con la persona</p><p>Plantear el desacuerdo directa y respetuosamente con la persona involucrada.</p></li>
  <li><p class="lvl-title">Jefatura de área</p><p>Si no se resuelve, llevarlo a la jefatura correspondiente.</p></li>
  <li><p class="lvl-title">Coordinación</p><p>La coordinación media y deja constancia en la bitácora.</p></li>
  <li><p class="lvl-title">Rectorado</p><p>Última instancia interna.</p></li>
</ol>
HTML);

    $add('dodont', <<<'HTML'
<div class="hb-dodont">
  <div class="hb-do">
    <p class="col-title">Lo que esperamos</p>
    <ul><li>Puntualidad en clases y turnos.</li><li>Comunicación por los canales internos.</li></ul>
  </div>
  <div class="hb-dont">
    <p class="col-title">Lo que no aceptamos</p>
    <ul><li>Contactar familias por mensajería personal.</li><li>Exponer desacuerdos frente a estudiantes.</li></ul>
  </div>
</div>
HTML);

    $add('timeline', <<<'HTML'
<ol class="hb-timeline">
  <li class="is-done"><span class="when">Julio</span><p class="what">Planificación institucional</p><p>Definición de prioridades y calendario.</p></li>
  <li class="is-done"><span class="when">Agosto</span><p class="what">Inducción del personal</p></li>
  <li><span class="when">Septiembre</span><p class="what">Inicio de clases</p></li>
</ol>
HTML);

    $add('contact', <<<'HTML'
<div class="hb-contacts">
  <div class="hb-contact is-emergency">
    <div class="ic">+</div>
    <div>
      <p class="name">Enfermería</p>
      <p class="role">Atención de salud</p>
      <dl><dt>Extensión</dt><dd>114</dd><dt>Ubicación</dt><dd>Planta baja, ala norte</dd></dl>
      <p class="when">Llamar de inmediato ante cualquier incidente físico.</p>
    </div>
  </div>
  <div class="hb-contact">
    <div class="ic">&#9742;</div>
    <div>
      <p class="name">Secretaría académica</p>
      <p class="role">Administración</p>
      <dl>
        <dt>Extensión</dt><dd>101</dd>
        <dt>Horario</dt><dd>7:00&ndash;15:00</dd>
        <dt>Última verificación</dt><dd><span class="hb-fill">[incorporar fecha]</span></dd>
      </dl>
      <p class="when">Autorizaciones, circulares y archivo de documentos.</p>
    </div>
  </div>
</div>
HTML);

    $add('define', <<<'HTML'
<div class="hb-define">
  <span class="eyebrow">Definición</span>
  <p class="term">Salida pedagógica <span class="abbr">(salida)</span></p>
  <p>Actividad educativa fuera del campus, planificada por un docente y autorizada por Coordinación, con autorización firmada de las familias.</p>
</div>
<p>Toda <span class="hb-term" title="Actividad educativa fuera del campus autorizada por Coordinación.">salida pedagógica</span> requiere autorización con 15 días de anticipación.</p>
HTML);

    $add('matrix', <<<'HTML'
<div class="hb-matrix-wrap">
  <table class="hb-matrix">
    <caption>Responsabilidades por etapa</caption>
    <thead><tr><th scope="col">Tarea</th><th scope="col">Docente</th><th scope="col">Coordinación</th><th scope="col">Secretaría</th></tr></thead>
    <tbody>
      <tr><th scope="row">Planificar</th><td><span class="raci raci-r">R</span></td><td><span class="raci raci-c">C</span></td><td></td></tr>
      <tr><th scope="row">Autorizar</th><td></td><td><span class="raci raci-a">A</span></td><td></td></tr>
      <tr><th scope="row">Comunicar familias</th><td><span class="raci raci-c">C</span></td><td><span class="raci raci-i">I</span></td><td><span class="raci raci-r">R</span></td></tr>
    </tbody>
  </table>
</div>
<div class="hb-matrix-legend"><span><span class="raci raci-r">R</span> Responsable</span><span><span class="raci raci-a">A</span> Aprueba</span><span><span class="raci raci-c">C</span> Consultado</span><span><span class="raci raci-i">I</span> Informado</span></div>
HTML);

    $add('figure', <<<'HTML'
<figure class="hb-figure">
  <img src="URL_DE_LA_IMAGEN" alt="Descripción de la imagen para lectores de pantalla">
  <figcaption><strong>Figura 1.</strong> Zonas de supervisión durante el recreo, por turno.</figcaption>
</figure>
HTML);

    $add('keyvalue', <<<'HTML'
<div class="hb-keyvalue">
  <div class="kv-title">Ficha &mdash; Comité de Protección del Menor</div>
  <dl>
    <dt>Coordina</dt><dd>Consejería</dd>
    <dt>Integrantes</dt><dd>Consejería, Convivencia, Dirección, Enfermería</dd>
    <dt>Reuniones</dt><dd>Mensual &middot; primer martes</dd>
    <dt>Reporta a</dt><dd>Rectorado</dd>
  </dl>
</div>
HTML);

    $add('checklist', <<<'HTML'
<ul class="hb-checklist">
  <li>Formulario de salida completo<span class="sub">Con presupuesto adjunto.</span></li>
  <li>Autorizaciones firmadas recibidas<span class="sub">Una por cada estudiante menor de edad.</span></li>
  <li>Nómina impresa y en el teléfono</li>
  <li>Botiquín y contactos de emergencia</li>
</ul>
HTML);

    $add('email', <<<'HTML'
<div class="hb-email is-good">
  <div class="e-chrome"><i></i><i></i><i></i><span class="e-badge">Así sí</span></div>
  <div class="e-head">
    <div class="e-row"><span class="e-label">De</span>
      <span class="e-value">Secretaría Académica &lt;secretaria@europaschule.eu&gt;</span></div>
    <div class="e-row"><span class="e-label">Para</span>
      <span class="e-value"><span class="pill">Familias 4.º B</span></span></div>
    <div class="e-row"><span class="e-label">Asunto</span>
      <span class="e-value e-subject">Salida pedagógica — 24 de julio (autorización adjunta)</span></div>
  </div>
  <div class="e-body">
    <p>Estimadas familias de 4.º B:</p>
    <p>El <strong>jueves 24 de julio</strong> el grado realizará una salida pedagógica al
    Museo para la Identidad Nacional.</p>
    <ul>
      <li><strong>Salida:</strong> 8:00, desde el campus.</li>
      <li><strong>Regreso:</strong> 12:30, almuerzo normal en el comedor.</li>
    </ul>
    <p>Adjuntamos el talón de autorización; devuélvanlo firmado <strong>a más tardar el
    lunes 21 de julio</strong>.</p>
  </div>
  <div class="e-attach"><span>Autorizacion_salida_4B.pdf</span></div>
  <div class="e-sign">
    <span class="name">Ana Martínez</span><br>
    Secretaría Académica &middot; EuropaSchule San Pedro Sula<br>
    Ext. 101 &middot; secretaria@europaschule.eu
  </div>
</div>

<div class="hb-email is-bad">
  <div class="e-chrome"><i></i><i></i><i></i><span class="e-badge">Así no</span></div>
  <div class="e-head">
    <div class="e-row"><span class="e-label">Asunto</span>
      <span class="e-value e-subject">IMPORTANTE!!!</span></div>
  </div>
  <div class="e-body">
    <p>se les recuerda que hay salida el jueves mandar el dinero y el papel firmado. gracias</p>
  </div>
</div>
HTML);

    $add('chat', <<<'HTML'
<div class="hb-chat">
  <div class="chat-title">Familias 4.º B — EuropaSchule
    <span class="sub">54 participantes</span></div>
  <div class="chat-day">Lunes 21 de julio</div>
  <div class="hb-msg is-out">
    <span class="who">Coordinación Académica</span>
    <p>Buenos días, familias. Hoy es el último día para entregar la autorización firmada
    de la salida del jueves.</p>
    <span class="when">9:02</span>
  </div>
  <div class="hb-msg is-in">
    <span class="who">Madre de Sofía R.</span>
    <p>Buenos días, ¿sirve mandarla escaneada por correo?</p>
    <span class="when">9:14</span>
  </div>
  <div class="hb-msg is-out">
    <span class="who">Coordinación Académica</span>
    <p>Sí, con gusto: secretaria@europaschule.eu. El original lo puede traer el estudiante
    el mismo jueves.</p>
    <span class="when">9:16</span>
  </div>
</div>

<div class="hb-chat">
  <div class="chat-day">Ejemplos</div>
  <span class="chat-verdict is-bad">Así no</span>
  <div class="hb-msg is-out is-bad">
    <p>señora la nota de mateo estuvo pesima hay que hablar URGENTE</p>
    <span class="when">21:47</span>
  </div>
  <span class="chat-verdict is-good">Así sí</span>
  <div class="hb-msg is-out is-good">
    <p>Buenas tardes. Quisiera coordinar una breve reunión para conversar sobre el avance
    de Mateo. ¿Le queda bien el miércoles a las 14:30, presencial o por llamada?</p>
    <span class="when">14:10</span>
  </div>
</div>
HTML);

    $add('dialogue', <<<'HTML'
<div class="hb-dialogue">
  <div class="dlg-context">Llamada: madre molesta por una calificación &middot; Recepción &rarr; Coordinación</div>
  <div class="dlg-turn is-staff">
    <span class="dlg-who">Recepción</span>
    <span class="dlg-text">EuropaSchule, buenos días. Le atiende Carmen, ¿en qué puedo servirle?</span>
  </div>
  <div class="dlg-turn">
    <span class="dlg-who">Madre</span>
    <span class="dlg-text">¡Es la tercera vez que llamo! La nota de mi hija está mal y nadie me resuelve.</span>
  </div>
  <p class="dlg-note">— pausa; dejar que termine, no interrumpir —</p>
  <div class="dlg-turn is-staff is-good">
    <span class="dlg-who">Recepción</span>
    <span class="dlg-text">Entiendo su molestia, señora, y lamento que haya tenido que llamar
    varias veces.<span class="dlg-verdict is-good">Así sí</span> La comunico con la Coordinación
    Académica, que es quien revisa calificaciones. ¿Me permite un momento en línea?</span>
  </div>
  <div class="dlg-turn is-staff is-bad">
    <span class="dlg-who">(Evitar)</span>
    <span class="dlg-text">Eso no es conmigo, llame mañana.<span class="dlg-verdict is-bad">Así no</span></span>
  </div>
</div>

<!-- Variante de llamada (is-call): icono de teléfono en el encabezado;
     los turnos alternan lo que dice la familia y lo que dice el personal. -->
<div class="hb-dialogue is-call">
  <div class="dlg-context">Llamada a la familia &middot; incidente en revisión</div>
  <div class="dlg-turn is-staff">
    <span class="dlg-who">Personal</span>
    <span class="dlg-text">Buenas tardes. Le llamo de EuropaSchule para informarle que hoy se
    presentó una situación relacionada con [nombre del estudiante]. Los hechos que podemos
    confirmar son: [hechos observables].</span>
  </div>
  <div class="dlg-turn">
    <span class="dlg-who">Familia</span>
    <span class="dlg-text">¿Mi hijo está bien? ¿Qué fue exactamente lo que pasó?</span>
  </div>
  <p class="dlg-note">— responder solo con hechos confirmados; no especular —</p>
  <div class="dlg-turn is-staff">
    <span class="dlg-who">Personal</span>
    <span class="dlg-text">Se encuentra [estado observable]. De inmediato realizamos [medidas
    adoptadas]. Le contactaremos a más tardar el [fecha y hora] con los próximos pasos.</span>
  </div>
</div>
HTML);

    $add('acta', <<<'HTML'
<div class="hb-agenda">
  <div class="ag-title">Agenda <span class="meta">ELP &middot; miércoles 23 de julio &middot; Sala de juntas</span></div>
  <ol>
    <li><span class="ag-time">14:00–14:10</span><span class="ag-topic">Seguimiento de acuerdos anteriores</span><span class="ag-who">Dirección</span></li>
    <li><span class="ag-time">14:10–14:35</span><span class="ag-topic">Resultados del diagnóstico de lectura 3.º–6.º</span><span class="ag-who">Coordinación</span></li>
    <li><span class="ag-time">14:35–14:55</span><span class="ag-topic">Plan de acompañamiento del segundo semestre</span><span class="ag-who">Consejería</span></li>
    <li><span class="ag-time">14:55–15:00</span><span class="ag-topic">Acuerdos y cierre</span><span class="ag-who">Dirección</span></li>
  </ol>
</div>

<div class="hb-acta">
  <div class="ac-title">Acta 14-2026 <span class="meta">ELP &middot; 23 de julio de 2026</span></div>
  <dl class="ac-head">
    <dt>Participantes</dt><dd>Dirección, Coordinación, Convivencia, Consejería</dd>
    <dt>Preside</dt><dd>Dirección</dd>
    <dt>Ausencias</dt><dd>Ninguna</dd>
  </dl>
  <div class="ac-section">Acuerdos</div>
  <table>
    <thead><tr><th></th><th>Acuerdo</th><th>Responsable</th><th>Fecha límite</th></tr></thead>
    <tbody>
      <tr><td class="ac-num">14.1</td>
          <td>Aplicar el plan de refuerzo de lectura en 3.º y 4.º, dos sesiones semanales.</td>
          <td class="ac-who">Coordinación</td><td class="ac-when">4 ago</td></tr>
      <tr><td class="ac-num">14.2</td>
          <td>Presentar propuesta de horario de acompañamiento individual.</td>
          <td class="ac-who">Consejería</td><td class="ac-when">30 jul</td></tr>
      <tr><td class="ac-num">13.4</td>
          <td>Circular a familias sobre el nuevo protocolo de retiro. <span class="ac-done">Cumplido</span></td>
          <td class="ac-who">Secretaría</td><td class="ac-when">18 jul</td></tr>
    </tbody>
  </table>
</div>
HTML);

    $add('letter', <<<'HTML'
<div class="hb-letter">
  <div class="lt-head">
    <div class="lt-school">EuropaSchule San Pedro Sula</div>
    <div class="lt-sub">Educación Helvética S.A. &middot; San Pedro Sula, Honduras</div>
  </div>
  <p class="lt-place">San Pedro Sula, 21 de julio de 2026</p>
  <p class="lt-ref">Ref.: Circular 08-2026 — Salida pedagógica 4.º B</p>
  <p>Estimadas familias:</p>
  <p>Por este medio les informamos que el jueves 24 de julio el grado 4.º B realizará una
  salida pedagógica al Museo para la Identidad Nacional, en el marco de la unidad de
  Estudios Sociales.</p>
  <p>La salida se realizará de 8:00 a 12:30. Se requiere la autorización firmada por el
  responsable legal, a más tardar el lunes 21 de julio.</p>
  <p>Agradecemos su apoyo para el buen desarrollo de esta actividad.</p>
  <p>Atentamente,</p>
  <div class="lt-sign">
    <span class="line"></span>
    <span class="name">Nombre Apellido</span><br>
    <span class="role">Rectorado &middot; EuropaSchule San Pedro Sula</span>
  </div>
</div>
HTML);

    $add('feedback', <<<'HTML'
<div class="hb-feedback is-bad">
  <div class="fb-head">
    <span class="fb-type">Tarea</span>
    <span class="fb-what">Ensayo — Los recursos naturales</span>
    <span class="fb-meta">Docente &rarr; Estudiante</span>
    <span class="fb-badge">Así no</span>
  </div>
  <div class="fb-field"><p>Mal hecho. Repetir para el viernes.</p></div>
  <span class="fb-grade">4/10</span>
</div>

<div class="hb-feedback is-good">
  <div class="fb-head">
    <span class="fb-type">Tarea</span>
    <span class="fb-what">Ensayo — Los recursos naturales</span>
    <span class="fb-meta">Docente &rarr; Estudiante</span>
    <span class="fb-badge">Así sí</span>
  </div>
  <div class="fb-field">
    <p>Tu introducción plantea bien el problema y usas dos fuentes correctamente citadas —
    buen avance frente al ensayo anterior.</p>
    <p>Para la versión del viernes: (1) cada párrafo debe defender UNA idea — el segundo
    mezcla tres; (2) la conclusión repite la introducción, intenta cerrarla con tu propia
    postura. Si quieres revisarlo juntos antes de entregar, búscame el jueves en el recreo.</p>
  </div>
  <span class="fb-grade">6/10 &middot; puede reentregar</span>
</div>

<div class="hb-feedback">
  <div class="fb-head">
    <span class="fb-type">Evaluación docente</span>
    <span class="fb-what">Observación de clase — Matemáticas 5.º</span>
    <span class="fb-meta">Coordinación &rarr; Docente</span>
  </div>
  <div class="fb-field">
    <p><strong>Fortalezas:</strong> arranque puntual con objetivo visible en pizarra; buen
    uso de mini-pizarras para verificar comprensión de todos.</p>
    <p><strong>Área de mejora:</strong> el cierre quedó en 2 minutos y sin verificación del
    objetivo. Sugerencia concreta: reservar 7 minutos y usar la misma rutina de
    mini-pizarras como ticket de salida.</p>
  </div>
</div>
HTML);

    $add('course', <<<'HTML'
<div class="hb-course">
  <div class="crs-sec is-collapsed">
    <div class="sec-title">Recursos generales</div>
  </div>

  <div class="crs-sec">
    <div class="sec-title">Parcial I — El conocimiento y el conocedor</div>

    <div class="crs-week">
      <div class="week-title">Semana del 18 al 22 de agosto</div>

      <div class="crs-act is-page">
        <span class="act-ic"></span>
        <span class="act-name">Planificación semanal — Semana 1</span>
      </div>

      <div class="crs-act is-pdf">
        <span class="act-ic"></span>
        <span class="act-name">Ppt: Conocimiento personal y compartido</span>
        <span class="act-chip">PDF</span>
      </div>

      <div class="crs-meta is-dates">
        <span><b>Apertura:</b> lunes 18 de agosto, 7:00</span>
        <span><b>Cierre:</b> jueves 28 de agosto, 23:59</span>
      </div>
      <div class="crs-act is-assign">
        <span class="act-ic"></span>
        <span class="act-name">Tarea: Reflexión sobre el mapa del conocimiento</span>
      </div>
      <div class="crs-meta is-lock">No disponible hasta que: la actividad
        <b>Ppt: Conocimiento personal y compartido</b> se marque como completada</div>

      <div class="crs-act is-url">
        <span class="act-ic"></span>
        <span class="act-name">La isla de California (enlace externo)</span>
      </div>

      <div class="crs-act is-pptx is-hidden">
        <span class="act-ic"></span>
        <span class="act-name">Conocimiento personal y compartido (editable)</span>
        <span class="act-chip">PPTX</span>
      </div>
      <div class="crs-badge">Oculto para estudiantes</div>
    </div>

    <div class="crs-week is-collapsed">
      <div class="week-title">Semana del 25 al 29 de agosto</div>
    </div>

    <div class="crs-week">
      <div class="week-title">Exámenes: 20–24 de octubre</div>
      <div class="crs-meta is-dates"><span><b>Apertura:</b> viernes 24 de octubre, 7:00</span></div>
      <div class="crs-act is-quiz">
        <span class="act-ic"></span>
        <span class="act-name">Examen Parcial I — Teoría del Conocimiento</span>
      </div>
      <div class="crs-desc">
        <p class="desc-title">Examen Parcial I — Instrucciones</p>
        <p><strong>Materiales permitidos:</strong> solo el cuaderno de la clase.
        <strong>Tiempo:</strong> 90 minutos.</p>
      </div>
    </div>
  </div>
</div>

<!-- Estado "curso nuevo": secciones vacías en color apagado (is-empty),
     con matiz opcional (is-green / is-red / is-blue). -->
<div class="hb-course">
  <div class="crs-sec is-collapsed">
    <div class="sec-title">Recursos generales</div>
  </div>
  <div class="crs-sec is-collapsed is-green is-empty">
    <div class="sec-title">Evaluación diagnóstica</div>
  </div>
  <div class="crs-sec is-collapsed is-empty">
    <div class="sec-title">Parcial I</div>
  </div>
  <div class="crs-sec is-collapsed is-red is-empty">
    <div class="sec-title">Recuperaciones anuales</div>
  </div>
</div>

<!-- Filas anotadas para lecciones de estructura ("así sí / así no"). -->
<div class="hb-course">
  <div class="crs-sec">
    <div class="sec-title">Ejemplo — nombres de actividades</div>
    <div class="crs-week">
      <div class="week-title">Semana del 18 al 22 de agosto</div>
      <div class="crs-act is-assign is-good">
        <span class="act-ic"></span>
        <span class="act-name">Tarea: Reflexión sobre el mapa del conocimiento</span>
      </div>
      <div class="crs-note"><b>Así sí:</b> tipo + tema; el estudiante sabe qué es antes de abrirla.</div>
      <div class="crs-act is-assign is-bad">
        <span class="act-ic"></span>
        <span class="act-name">tarea1_FINAL(2)</span>
      </div>
      <div class="crs-note"><b>Así no:</b> sin tipo, sin tema, con numeración interna.</div>
    </div>
  </div>
</div>
HTML);

    $add('acc', <<<'HTML'
<div class="hb-acc-group">

  <div class="hb-acc">
    <p class="acc-title">Recordatorio de evaluaciones y temarios
      <span class="acc-chip">WhatsApp</span></p>
    <div class="acc-body">
      <div class="hb-keyvalue">
        <div class="kv-title">Ficha de uso</div>
        <dl>
          <dt>Uso</dt><dd>Recordar fechas de evaluación y disponibilidad de temarios.</dd>
          <dt>Canal</dt><dd>WhatsApp institucional o grupo informativo.</dd>
          <dt>Área</dt><dd>Coordinación Académica o docente responsable.</dd>
        </dl>
      </div>
      <div class="hb-chat">
        <div class="chat-title">Familias de [grado]<span class="sub">Mensaje colectivo</span></div>
        <div class="hb-msg is-out">
          <span class="who">Coordinación Académica</span>
          <p>Estimadas familias: les recordamos que las evaluaciones del parcial se realizarán
          del [fecha inicial] al [fecha final]. Los temarios ya están publicados en la
          plataforma.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="hb-acc">
    <p class="acc-title">Cambio de horario o modificación del calendario
      <span class="acc-chip">Correo</span></p>
    <div class="acc-body">
      <div class="hb-keyvalue">
        <div class="kv-title">Ficha de uso</div>
        <dl>
          <dt>Uso</dt><dd>Informar un cambio confirmado que afecta la jornada.</dd>
          <dt>Área</dt><dd>Dirección Oficial o Gerencia Académica.</dd>
        </dl>
      </div>
      <div class="hb-email">
        <div class="e-chrome"><i></i><i></i><i></i><span class="e-badge">Modelo</span></div>
        <div class="e-head">
          <div class="e-row"><span class="e-label">Asunto</span>
            <span class="e-value e-subject">Cambio de horario del [fecha]</span></div>
        </div>
        <div class="e-body">
          <p>Estimadas familias: les informamos que el [fecha] la jornada escolar se
          desarrollará de [hora inicial] a [hora final] debido a [motivo confirmado].</p>
        </div>
      </div>
    </div>
  </div>

</div>
HTML);

    $add('refs', <<<'HTML'
<p>Durante actos oficiales se aplica el uniforme institucional
<a class="hb-ref" href="/local/handbook/view.php?page=reglamento-interno-titulo-quinto#art-112"><span>Art. 112 b)</span> <span class="doc">&middot; Regl. Interno</span></a>,
y las faltas de presentación se tratan como faltas leves.</p>

<div class="hb-seealso">
  <span class="lbl">Ver normativa</span>
  <a href="/local/handbook/view.php?page=reglamento-interno-titulo-quinto#art-112">Art. 112 b) &middot; Reglamento Interno</a>
  <span class="sep">&middot;</span>
  <a href="/local/handbook/view.php?page=reglamento-personal-capitulo-11#art-84">Art. 84 4) &middot; Reglamento del Personal</a>
</div>

<div class="hb-refbox">
  <div class="rb-title">Normativa relacionada</div>
  <ul>
    <li><span class="hb-doc is-ri">Regl. Interno</span>
        <a href="/local/handbook/view.php?page=reglamento-interno-titulo-quinto#art-112">Artículo 112 b)</a>
        <span class="what">&mdash; uso correcto y oportuno de la vestimenta.</span></li>
    <li><span class="hb-doc is-rp">Regl. Personal</span>
        <a href="/local/handbook/view.php?page=reglamento-personal-capitulo-11#art-84">Artículo 84 4)</a>
        <span class="what">&mdash; presentación personal e higiene como falta leve.</span></li>
  </ul>
</div>

<div class="hb-refs">
  <p class="refs-title">Normativa relacionada</p>
  <div class="refs-group">
    <p class="refs-doc"><span class="hb-doc is-ri">Regl. Interno</span> Reglamento Interno &mdash; Título Quinto</p>
    <ul>
      <li><a href="/local/handbook/view.php?page=reglamento-interno-titulo-quinto#art-112">Artículo 112 b)</a>
          <span class="what">&mdash; uso correcto y oportuno de la vestimenta.</span></li>
    </ul>
  </div>
  <div class="refs-group">
    <p class="refs-doc"><span class="hb-doc is-ed">Estatuto Docente</span> Reglamento del Estatuto del Docente</p>
    <ul>
      <li><a href="/local/handbook/view.php?page=estatuto-docente#art-134">Artículo 134</a>
          <span class="what">&mdash; clasificación de faltas del personal docente.</span></li>
    </ul>
  </div>
</div>
HTML);

    $add('next', <<<'HTML'
<a class="hb-next" href="/local/handbook/view.php?page=reglamento-personal-capitulo-12">
  <span class="eyebrow">Siguiente capítulo</span>
  <span class="title">Capítulo 12 — Terminación de la relación laboral</span>
</a>

<div class="hb-next-group">
  <a class="hb-next" href="/local/handbook/view.php?page=evaluacion-docente">
    <span class="eyebrow">Si eres docente</span>
    <span class="title">Evaluación docente anual</span>
  </a>
  <a class="hb-next" href="/local/handbook/view.php?page=evaluacion-administrativa">
    <span class="eyebrow">Si eres personal administrativo</span>
    <span class="title">Evaluación administrativa</span>
  </a>
</div>

<a class="hb-next is-prev" href="/local/handbook/view.php?page=reglamento-personal-capitulo-10">
  <span class="eyebrow">Anterior</span>
  <span class="title">Capítulo 10 — Jornada de trabajo</span>
</a>
HTML);

    $add('legal', <<<'HTML'
<div class="hb-legal">

  <h2 class="hb-titulo"><span class="no">5</span>
    <span class="name">Título Quinto</span></h2>

  <h3 class="hb-seccion"><span class="no">5.1</span>
    <span>Disposiciones de Orden y Disciplina</span></h3>

  <h4 class="hb-subseccion"><span class="no">5.1.1</span>
    <span>Consideraciones Preliminares</span></h4>

  <p class="hb-art" id="art-106"><span class="hb-art-no">Artículo 106.</span>
  En EuropaSchule se entiende como disciplina un conjunto de normas formativas
  que deben acatarse para promover la buena convivencia escolar.</p>

  <p class="hb-art" id="art-110"><span class="hb-art-no">Artículo 110.</span>
  La aplicación de cualquier medida debe considerar:</p>
  <ol class="hb-literals">
    <li>Cuidado y protección a la integridad física y a la dignidad personal del alumno(a).</li>
    <li>Las medidas deberán propender a la toma de conciencia y reflexión.</li>
    <li>Las correcciones serán proporcionales a la falta.</li>
  </ol>

  <p class="hb-art" id="art-82"><span class="hb-art-no">Artículo 82.</span>
  Las medidas disciplinarias serán de 4 tipos:</p>
  <ol>
    <li value="1">Amonestación privada, verbal.</li>
    <li value="2">Amonestación por escrito.</li>
    <li value="3">Suspensión del trabajo sin goce de sueldo de uno a ocho días laborables.</li>
    <li value="4">Despido como último recurso.</li>
  </ol>

  <!-- Cuerpo que comienza directamente con la enumeración (sin frase
       introductoria): agregue is-solo al párrafo para que la lista arranque
       en la misma línea que el número, sin línea en blanco. -->
  <p class="hb-art is-solo" id="art-85"><span class="hb-art-no">Artículo 85.</span></p>
  <ol>
    <li value="1">Son faltas menos graves las reincidencias de faltas leves.</li>
    <li value="2">El abandono del puesto de trabajo sin autorización.</li>
  </ol>

  <div class="hb-note"><p><strong>Nota de vigencia:</strong> literales b) y c)
  modificados por acuerdo del ELP, acta 12-2026.</p></div>

  <p class="hb-art is-derogado" id="art-108"><span class="hb-art-no">Artículo 108.</span>
  <span class="der">(Derogado — acuerdo ELP, acta 09-2026.)</span></p>

</div>
HTML);

    return $patterns;
}

/**
 * Localized label for a typed relation.
 *
 * @param string $type Relation type key (spec 9.2).
 * @param bool $reverse Whether the relation points AT the current page.
 * @return string
 */
function local_handbook_relation_label(string $type, bool $reverse = false): string {
    $stringkey = 'relation' . ($reverse ? 'rev' : '') . '_' . $type;
    if (get_string_manager()->string_exists($stringkey, 'local_handbook')) {
        return get_string($stringkey, 'local_handbook');
    }
    return $type;
}

/**
 * Render a compact list of pages as a card body list.
 *
 * @param stdClass[] $pages Page records (need slug + title).
 * @param callable|null $metacallback Optional meta line per page.
 * @return string
 */
function local_handbook_render_pagelist(array $pages, ?callable $metacallback = null): string {
    $items = '';
    foreach ($pages as $page) {
        $meta = $metacallback !== null
            ? html_writer::span(s($metacallback($page)), 'page-meta')
            : '';
        $items .= html_writer::tag('li',
            html_writer::link(local_handbook_page_url($page), s($page->title)) . $meta);
    }
    return html_writer::tag('ul', $items, ['class' => 'local-handbook-pagelist']);
}

/**
 * Render the trail of parent categories above a page or category title.
 *
 * @param int $categoryid Category id to start from.
 * @return string
 */
function local_handbook_render_category_trail(int $categoryid): string {
    global $DB;

    $parts = [];
    $guard = 0;
    while ($categoryid && $guard++ < 10) {
        $category = $DB->get_record('local_handbook_category', ['id' => $categoryid]);
        if (!$category) {
            break;
        }
        $url = new moodle_url('/local/handbook/category.php', ['id' => $category->id]);
        array_unshift($parts, html_writer::link($url, s($category->name)));
        $categoryid = (int)$category->parentid;
    }

    if (!$parts) {
        return '';
    }

    return html_writer::div(
        implode(html_writer::span('›', 'sep'), $parts),
        'local-handbook-category-trail'
    );
}
