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
 * Handbook home: category navigation and recent updates (specification 12.1).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$context = context_system::instance();
local_handbook_require_view($context);

$url = new moodle_url('/local/handbook/index.php');
local_handbook_apply_page_setup($url, $context, 'home', get_string('pluginname', 'local_handbook'));

$canedit = has_capability('local/handbook:edit', $context);

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);

$actions = '';
if ($canedit) {
    $actions .= html_writer::link(
        new moodle_url('/local/handbook/edit.php'),
        html_writer::tag('i', '', ['class' => 'fa-solid fa-plus me-2', 'aria-hidden' => 'true'])
            . s(get_string('newpage', 'local_handbook')),
        ['class' => 'btn btn-outline-secondary btn-sm']
    );
}
echo local_handbook_render_page_heading(get_string('pluginname', 'local_handbook'), $actions);

// Category navigation.
$categories = local_handbook_get_categories(0, has_capability('local/handbook:managecategories', $context));
$counts = local_handbook_count_published_pages_by_category();

echo html_writer::tag('h3', s(get_string('categories', 'local_handbook')), ['class' => 'h5 mb-3']);

if (!$categories) {
    echo html_writer::div(s(get_string('nocategoriesyet', 'local_handbook')), 'alert alert-info');
} else {
    $cards = '';
    foreach ($categories as $category) {
        $caturl = new moodle_url('/local/handbook/category.php', ['id' => $category->id]);

        // Aggregate the count of the category and its direct children.
        $pagecount = $counts[(int)$category->id] ?? 0;
        $children = local_handbook_get_categories((int)$category->id);
        $sublinks = [];
        foreach ($children as $child) {
            $pagecount += $counts[(int)$child->id] ?? 0;
            if (count($sublinks) < 4) {
                $sublinks[] = html_writer::tag('li', html_writer::link(
                    new moodle_url('/local/handbook/category.php', ['id' => $child->id]),
                    s($child->name)
                ));
            }
        }

        $countlabel = $pagecount === 1
            ? get_string('pagecountone', 'local_handbook')
            : get_string('pagecount', 'local_handbook', $pagecount);

        $header = html_writer::div(
            html_writer::div(
                html_writer::span(
                    html_writer::tag('i', '', ['class' => 'fa-solid fa-folder-open', 'aria-hidden' => 'true']),
                    'category-icon'
                )
                . html_writer::tag('h4', html_writer::link($caturl, s($category->name)), ['class' => 'h6 mb-0']),
                'd-flex align-items-center gap-2'
            )
            . html_writer::span(s($countlabel), 'category-count'),
            'd-flex align-items-center gap-2 justify-content-between'
        );

        $body = $header;
        if ($sublinks) {
            $body .= html_writer::tag('ul', implode('', $sublinks), ['class' => 'category-sub']);
        }
        if (!(int)$category->visible) {
            $body .= html_writer::div(s(get_string('hidden', 'core')), 'small text-muted mt-1');
        }

        $cards .= html_writer::div(
            html_writer::div(html_writer::div($body, 'card-body'), 'card mb-3 flex-fill local-handbook-category-card'),
            'col-md-6 col-xl-4 d-flex'
        );
    }
    echo html_writer::div($cards, 'row');
}

// Recently updated pages.
$recent = local_handbook_get_recently_published(5);

echo html_writer::tag('h3', s(get_string('recentlyupdated', 'local_handbook')), ['class' => 'h5 mb-3 mt-4']);

if (!$recent) {
    echo html_writer::div(s(get_string('nopagesyet', 'local_handbook')), 'alert alert-info');
} else {
    $items = '';
    foreach ($recent as $page) {
        $meta = get_string('versionnumber', 'local_handbook', (int)$page->versionnumber)
            . ' · ' . local_handbook_format_date((int)$page->timepublished);
        $items .= html_writer::tag('li',
            html_writer::link(local_handbook_page_url($page), s($page->title))
            . html_writer::span($meta, 'page-meta')
        );
    }
    echo html_writer::div(
        html_writer::div(
            html_writer::tag('ul', $items, ['class' => 'local-handbook-pagelist']),
            'card-body'
        ),
        'card mb-3'
    );
}

echo $OUTPUT->footer();
