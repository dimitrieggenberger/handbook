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

$categoryid = required_param('id', PARAM_INT);

$context = context_system::instance();
local_handbook_require_view($context);

$category = $DB->get_record('local_handbook_category', ['id' => $categoryid]);
if (!$category || (!(int)$category->visible
        && !has_capability('local/handbook:managecategories', $context))) {
    throw new moodle_exception('errorcategorynotfound', 'local_handbook');
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

echo html_writer::tag('h3', s(get_string('pagesincategory', 'local_handbook')), ['class' => 'h5 mb-3']);

if (!$pages) {
    echo html_writer::div(s(get_string('emptycategory', 'local_handbook')), 'alert alert-info');
} else {
    // Published version numbers in one query for the card footers.
    $versions = [];
    $revisionids = array_filter(array_map(
        static fn(stdClass $p): int => (int)$p->publishedrevisionid, $pages));
    if ($revisionids) {
        foreach ($DB->get_records_list('local_handbook_revision', 'id', $revisionids,
                '', 'id, versionnumber') as $rev) {
            $versions[(int)$rev->id] = (int)$rev->versionnumber;
        }
    }

    $cards = '';
    foreach ($pages as $page) {
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
            . html_writer::tag('h4',
                html_writer::link(local_handbook_page_url($page), s($page->title),
                    ['class' => 'stretched-link']),
                ['class' => 'local-handbook-card-title'])
            . (trim((string)$page->summary) !== ''
                ? html_writer::tag('p', s($page->summary), ['class' => 'local-handbook-card-summary'])
                : '');

        $version = $versions[(int)$page->publishedrevisionid] ?? 0;
        $foot = html_writer::span(
                s(get_string('lastupdated', 'local_handbook') . ': '
                    . local_handbook_format_date((int)$page->timemodified)))
            . ($version ? html_writer::span(s(get_string('versionnumber', 'local_handbook', $version)))
                : '');

        $cards .= html_writer::tag('article',
            $media
            . html_writer::div($body, 'local-handbook-card-body')
            . html_writer::div($foot, 'local-handbook-card-foot'),
            ['class' => 'local-handbook-card position-relative']);
    }
    echo html_writer::div($cards, 'local-handbook-cards mb-3');
}

echo $OUTPUT->footer();
