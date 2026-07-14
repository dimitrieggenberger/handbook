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
 * Dedicated handbook search with filters (specification 13.2, 13.3).
 *
 * Searches titles, summaries and the normalized plain text of published
 * revisions. Only published, non-archived pages appear.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_handbook\local\service\page_service;

$query = trim(optional_param('q', '', PARAM_RAW));
$contenttype = optional_param('contenttype', '', PARAM_ALPHANUMEXT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$pagenumber = optional_param('page', 0, PARAM_INT);

$context = context_system::instance();
local_handbook_require_view($context);

$urlparams = array_filter([
    'q' => $query,
    'contenttype' => $contenttype,
    'categoryid' => $categoryid,
]);
$url = new moodle_url('/local/handbook/search.php', $urlparams);
local_handbook_apply_page_setup($url, $context, 'search',
    get_string('searchhandbook', 'local_handbook'));

$perpage = 20;

echo $OUTPUT->header();
echo local_handbook_render_area_actions('search', $context);
echo local_handbook_render_page_heading(get_string('searchhandbook', 'local_handbook'));

// ---- Filter form (GET) --------------------------------------------------.

$typeoptions = html_writer::tag('option', s(get_string('alltypes', 'local_handbook')),
    ['value' => '']);
foreach (page_service::content_types() as $type) {
    $attributes = ['value' => $type];
    if ($type === $contenttype) {
        $attributes['selected'] = 'selected';
    }
    $typeoptions .= html_writer::tag('option',
        s(get_string('contenttype_' . $type, 'local_handbook')), $attributes);
}

$categoryoptions = html_writer::tag('option', s(get_string('allcategories', 'local_handbook')),
    ['value' => 0]);
foreach (local_handbook_get_categories(0) as $top) {
    $attributes = ['value' => $top->id];
    if ((int)$top->id === $categoryid) {
        $attributes['selected'] = 'selected';
    }
    $categoryoptions .= html_writer::tag('option', s(format_string($top->name)), $attributes);
    foreach (local_handbook_get_categories((int)$top->id) as $child) {
        $attributes = ['value' => $child->id];
        if ((int)$child->id === $categoryid) {
            $attributes['selected'] = 'selected';
        }
        $categoryoptions .= html_writer::tag('option',
            s(format_string($top->name) . ' › ' . format_string($child->name)), $attributes);
    }
}

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/handbook/search.php'))->out(false),
    'class' => 'local-handbook-search mb-4',
    'role' => 'search',
]);
echo html_writer::start_div('input-group input-group-lg');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'q',
    'value' => $query,
    'class' => 'form-control form-control-lg',
    'placeholder' => get_string('searchplaceholder', 'local_handbook'),
    'aria-label' => get_string('searchhandbook', 'local_handbook'),
]);
echo html_writer::div(
    html_writer::tag('button',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-magnifying-glass me-2', 'aria-hidden' => 'true'])
        . s(get_string('search', 'core')),
        ['type' => 'submit', 'class' => 'btn btn-primary']),
    'input-group-append'
);
echo html_writer::end_div();
echo html_writer::start_div('d-flex flex-wrap gap-2 mt-3 align-items-center');
echo html_writer::tag('label', s(get_string('contenttype', 'local_handbook')),
    ['for' => 'search-contenttype', 'class' => 'mb-0 small text-muted']);
echo html_writer::tag('select', $typeoptions,
    ['name' => 'contenttype', 'id' => 'search-contenttype', 'class' => 'custom-select custom-select-sm w-auto']);
echo html_writer::tag('label', s(get_string('category', 'local_handbook')),
    ['for' => 'search-category', 'class' => 'mb-0 small text-muted ml-2']);
echo html_writer::tag('select', $categoryoptions,
    ['name' => 'categoryid', 'id' => 'search-category', 'class' => 'custom-select custom-select-sm w-auto']);
echo html_writer::end_div();
echo html_writer::end_tag('form');

// ---- Results ------------------------------------------------------------.

if (\core_text::strlen($query) >= 2 || $contenttype !== '' || $categoryid) {
    $like = '%' . $DB->sql_like_escape($query) . '%';
    $where = 'p.archived = 0 AND p.publishedrevisionid > 0';
    $sqlparams = [];

    if (\core_text::strlen($query) >= 2) {
        $where .= ' AND ('
            . $DB->sql_like('p.title', ':q1', false) . ' OR '
            . $DB->sql_like('p.summary', ':q2', false) . ' OR '
            . $DB->sql_like('r.plaintext', ':q3', false) . ')';
        $sqlparams += ['q1' => $like, 'q2' => $like, 'q3' => $like];
    }
    if ($contenttype !== '') {
        $where .= ' AND p.contenttype = :contenttype';
        $sqlparams['contenttype'] = $contenttype;
    }
    if ($categoryid) {
        // Include direct children of the chosen category.
        $childids = array_keys($DB->get_records('local_handbook_category',
            ['parentid' => $categoryid], '', 'id'));
        $catids = array_merge([$categoryid], array_map('intval', $childids));
        [$catsql, $catparams] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cat');
        $where .= " AND p.categoryid $catsql";
        $sqlparams += $catparams;
    }

    $countsql = "SELECT COUNT(1)
                   FROM {local_handbook_page} p
                   JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
                  WHERE $where";
    $total = (int)$DB->count_records_sql($countsql, $sqlparams);

    $sql = "SELECT p.*, r.versionnumber, r.timepublished
              FROM {local_handbook_page} p
              JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
             WHERE $where
          ORDER BY p.title ASC";
    $results = $DB->get_records_sql($sql, $sqlparams, $pagenumber * $perpage, $perpage);

    echo html_writer::tag('p',
        s(get_string('searchresultcount', 'local_handbook', $total)),
        ['class' => 'text-muted small']);

    if (!$results) {
        echo html_writer::div(s(get_string('noresults', 'local_handbook')), 'alert alert-info');
    } else {
        $items = '';
        foreach ($results as $result) {
            $meta = get_string('contenttype_' . $result->contenttype, 'local_handbook')
                . ' · ' . get_string('versionnumber', 'local_handbook', (int)$result->versionnumber)
                . ' · ' . local_handbook_format_date((int)$result->timepublished);
            $summary = trim((string)$result->summary) !== ''
                ? html_writer::div(s(shorten_text($result->summary, 220)), 'small')
                : '';
            $items .= html_writer::tag('li',
                html_writer::link(local_handbook_page_url($result), s($result->title))
                . $summary
                . html_writer::span($meta, 'page-meta')
            );
        }
        echo html_writer::div(
            html_writer::div(html_writer::tag('ul', $items, ['class' => 'local-handbook-pagelist']),
                'card-body'),
            'card mb-3'
        );

        echo $OUTPUT->paging_bar($total, $pagenumber, $perpage, $url);
    }
}

echo $OUTPUT->footer();
