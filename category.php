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
    // Reorder mode: the same pages as a numbered list with up/down arrows.
    // The order set here is the order everywhere: this category page and
    // the home accordion alike.
    echo html_writer::tag('p', s(get_string('reorderintro', 'local_handbook')),
        ['class' => 'text-muted small']);
    $items = '';
    $last = count($pages) - 1;
    foreach (array_values($pages) as $index => $page) {
        $arrows = '';
        if ($index > 0) {
            $arrows .= html_writer::link(new moodle_url($url,
                    ['reorder' => 1, 'move' => $page->id, 'dir' => 'up', 'sesskey' => sesskey()]),
                '↑', ['class' => 'btn btn-outline-secondary btn-sm',
                    'title' => get_string('moveup'), 'aria-label' => get_string('moveup')]);
        }
        if ($index < $last) {
            $arrows .= html_writer::link(new moodle_url($url,
                    ['reorder' => 1, 'move' => $page->id, 'dir' => 'down', 'sesskey' => sesskey()]),
                '↓', ['class' => 'btn btn-outline-secondary btn-sm ml-1',
                    'title' => get_string('movedown'), 'aria-label' => get_string('movedown')]);
        }
        $items .= html_writer::div(
            html_writer::span((string)($index + 1), 'badge badge-secondary mr-2')
            . html_writer::span(
                html_writer::link(local_handbook_page_url($page), s(format_string($page->title)))
                . (trim((string)$page->summary) !== ''
                    ? html_writer::div(s(shorten_text($page->summary, 90)), 'small text-muted')
                    : ''),
                'flex-grow-1 min-width-0 mr-2')
            . html_writer::span($arrows, 'text-nowrap'),
            'list-group-item d-flex align-items-center');
    }
    echo html_writer::div($items, 'list-group mb-3');
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
