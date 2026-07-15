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

// file_rewrite_pluginfile_urls() and EDITOR_UNLIMITED_FILES live in these
// libs; plugin pages use them before formslib would load them indirectly,
// so they must be required explicitly.
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/editorlib.php');

use local_handbook\local\service\ack_service;
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
        'paths' => [
            'label' => get_string('managepaths', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/paths.php'),
            'visible' => has_capability('local/handbook:managepaths', $context),
        ],
        'findings' => [
            'label' => get_string('managefindings', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/findings.php'),
            'visible' => has_capability('local/handbook:managefindings', $context),
        ],
        'reports' => [
            'label' => get_string('reports', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/reports.php'),
            'visible' => has_capability('local/handbook:viewreports', $context),
        ],
        'import' => [
            'label' => get_string('importseed', 'local_handbook'),
            'url' => new moodle_url('/local/handbook/manage/import.php'),
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
            ['reviewqueue', 'categories', 'paths', 'findings', 'reports', 'import'], true);
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

    return html_writer::div(local_handbook_demote_headings($content), 'local-handbook-page-body');
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
