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
 * Handbook category listing: subcategories and published pages.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_handbook\local\service\page_service;

$categoryid = required_param('id', PARAM_INT);
$reorder = optional_param('reorder', 0, PARAM_BOOL);
$move = optional_param('move', 0, PARAM_INT);
$dir = optional_param('dir', '', PARAM_ALPHA);

$context = context_system::instance();
local_handbook_require_view($context);

$category = $DB->get_record('local_handbook_category', ['id' => $categoryid]);
if (!$category || (!(int)$category->visible
        && !has_capability('local/handbook:managecategories', $context))) {
    throw new moodle_exception('errorcategorynotfound', 'local_handbook');
}

$canreorder = has_capability('local/handbook:managecategories', $context);
$reorder = $reorder && $canreorder;

// Move a page up/down in this category's display order, then return to
// the reorder view.
if ($move && ($dir === 'up' || $dir === 'down')) {
    require_sesskey();
    require_capability('local/handbook:managecategories', $context);
    $movepage = $DB->get_record('local_handbook_page', ['id' => $move], '*', MUST_EXIST);
    if ((int)$movepage->categoryid === (int)$category->id) {
        page_service::move_in_category($move, $dir);
    }
    redirect(new moodle_url('/local/handbook/category.php',
        ['id' => $category->id, 'reorder' => 1]));
}

// Pin / unpin the category's featured page (at most one per category).
$featureaction = optional_param('feature', 0, PARAM_INT);
$featureset = optional_param('set', 0, PARAM_BOOL);
if ($featureaction) {
    require_sesskey();
    require_capability('local/handbook:managecategories', $context);
    $featurepage = $DB->get_record('local_handbook_page', ['id' => $featureaction], '*', MUST_EXIST);
    if ((int)$featurepage->categoryid === (int)$category->id) {
        page_service::set_featured($featureaction, (bool)$featureset);
    }
    redirect(new moodle_url('/local/handbook/category.php',
        ['id' => $category->id, 'reorder' => 1]));
}

// Persist a full drag-and-drop order (posted by js/reorder.js; the up/down
// arrows remain the no-JS fallback).
if (optional_param('action', '', PARAM_ALPHA) === 'saveorder') {
    require_sesskey();
    require_capability('local/handbook:managecategories', $context);
    $order = array_filter(array_map('intval',
        explode(',', optional_param('order', '', PARAM_SEQUENCE))));
    page_service::set_category_order((int)$category->id, $order);
    if (optional_param('ajax', 0, PARAM_BOOL)) {
        @header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }
    redirect(new moodle_url('/local/handbook/category.php',
        ['id' => $category->id, 'reorder' => 1]));
}

$url = new moodle_url('/local/handbook/category.php', ['id' => $category->id]);
local_handbook_apply_page_setup($url, $context, 'home',
    format_string($category->name), format_string($category->name));

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);

echo local_handbook_render_category_trail((int)$category->parentid);
echo local_handbook_render_page_heading(format_string($category->name));

if (trim((string)$category->description) !== '') {
    echo html_writer::div(
        format_text($category->description, $category->descriptionformat, ['context' => $context]),
        'mb-3'
    );
}

// Subcategories.
$children = local_handbook_get_categories((int)$category->id,
    has_capability('local/handbook:managecategories', $context));
$counts = local_handbook_count_published_pages_by_category();

if ($children) {
    echo html_writer::tag('h3', s(get_string('subcategories', 'local_handbook')), ['class' => 'h5 mb-3']);
    $items = '';
    foreach ($children as $child) {
        $pagecount = $counts[(int)$child->id] ?? 0;
        $countlabel = $pagecount === 1
            ? get_string('pagecountone', 'local_handbook')
            : get_string('pagecount', 'local_handbook', $pagecount);
        $items .= html_writer::tag('li',
            html_writer::link(new moodle_url('/local/handbook/category.php', ['id' => $child->id]),
                s($child->name))
            . html_writer::span(s($countlabel), 'page-meta')
        );
    }
    echo html_writer::div(
        html_writer::div(html_writer::tag('ul', $items, ['class' => 'local-handbook-pagelist']), 'card-body'),
        'card mb-4'
    );
}

// Published pages of this category, as an image-led card grid. One banner
// upload per page serves both the card (16:9) and the article top (3:1);
// pages without an image get a content-type tint fallback.
$pages = local_handbook_get_published_pages((int)$category->id);

$heading = html_writer::tag('h3', s(get_string('pagesincategory', 'local_handbook')),
    ['class' => 'h5 mb-0']);
if ($canreorder && count($pages) > 1) {
    $heading .= html_writer::link(
        new moodle_url($url, $reorder ? [] : ['reorder' => 1]),
        s(get_string($reorder ? 'reorderdone' : 'reorderpages', 'local_handbook')),
        ['class' => 'btn btn-outline-secondary btn-sm']);
}
echo html_writer::div($heading, 'd-flex align-items-center justify-content-between mb-3');

if (!$pages) {
    echo html_writer::div(s(get_string('emptycategory', 'local_handbook')), 'alert alert-info');
} else if ($reorder) {
    // Reorder mode: the same pages as a numbered list. Rows can be dragged
    // (js/reorder.js) or moved with the arrows (no-JS fallback); the pinned
    // featured page stays first and is reordered by unpinning it. The order
    // set here is the order everywhere: this page and the home accordion.
    echo html_writer::tag('p', s(get_string('reorderintro', 'local_handbook')),
        ['class' => 'text-muted small']);
    $items = '';
    $plain = array_values(array_filter($pages, static fn(stdClass $p): bool => empty($p->featured)));
    $plainlast = count($plain) - 1;
    $plainindex = -1;
    foreach (array_values($pages) as $index => $page) {
        $isfeatured = !empty($page->featured);
        if (!$isfeatured) {
            $plainindex++;
        }

        $actions = '';
        if ($isfeatured) {
            $actions .= html_writer::link(new moodle_url($url,
                    ['reorder' => 1, 'feature' => $page->id, 'set' => 0, 'sesskey' => sesskey()]),
                s(get_string('featureunpin', 'local_handbook')),
                ['class' => 'btn btn-outline-secondary btn-sm']);
        } else {
            $actions .= html_writer::link(new moodle_url($url,
                    ['reorder' => 1, 'feature' => $page->id, 'set' => 1, 'sesskey' => sesskey()]),
                '★', ['class' => 'btn btn-outline-secondary btn-sm mr-1',
                    'title' => get_string('featurepin', 'local_handbook'),
                    'aria-label' => get_string('featurepin', 'local_handbook')]);
            if ($plainindex > 0) {
                $actions .= html_writer::link(new moodle_url($url,
                        ['reorder' => 1, 'move' => $page->id, 'dir' => 'up', 'sesskey' => sesskey()]),
                    '↑', ['class' => 'btn btn-outline-secondary btn-sm',
                        'title' => get_string('moveup'), 'aria-label' => get_string('moveup')]);
            }
            if ($plainindex < $plainlast) {
                $actions .= html_writer::link(new moodle_url($url,
                        ['reorder' => 1, 'move' => $page->id, 'dir' => 'down', 'sesskey' => sesskey()]),
                    '↓', ['class' => 'btn btn-outline-secondary btn-sm ml-1',
                        'title' => get_string('movedown'), 'aria-label' => get_string('movedown')]);
            }
        }

        $badge = $isfeatured
            ? html_writer::span('★', 'badge badge-primary mr-2',
                ['title' => get_string('featuredbadge', 'local_handbook')])
            : html_writer::span((string)($index + 1), 'badge badge-secondary mr-2 reorder-num');

        $items .= html_writer::div(
            (!$isfeatured
                ? html_writer::span('⠿', 'reorder-grip text-muted mr-2',
                    ['title' => get_string('reorderdrag', 'local_handbook'), 'aria-hidden' => 'true'])
                : '')
            . $badge
            . html_writer::span(
                html_writer::link(local_handbook_page_url($page), s(format_string($page->title)))
                . (trim((string)$page->summary) !== ''
                    ? html_writer::div(s(shorten_text($page->summary, 90)), 'small text-muted')
                    : ''),
                'flex-grow-1 min-width-0 mr-2')
            . html_writer::span($actions, 'text-nowrap'),
            'list-group-item d-flex align-items-center'
            . ($isfeatured ? ' is-featured-row' : ' reorder-row'),
            ['data-pageid' => $page->id]);
    }
    echo html_writer::div($items, 'list-group mb-3 local-handbook-reorder', [
        'data-region' => 'handbook-reorder',
        'data-action-url' => $url->out(false),
        'data-sesskey' => sesskey(),
    ]);
    $PAGE->requires->js(new moodle_url('/local/handbook/js/reorder.js'));
} else {
    $versions = local_handbook_published_versions($pages);
    $cards = '';
    foreach ($pages as $page) {
        $cards .= local_handbook_render_page_card($page,
            $versions[(int)$page->publishedrevisionid] ?? 0);
    }
    echo html_writer::div($cards, 'local-handbook-cards mb-3');
}

echo $OUTPUT->footer();
