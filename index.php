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
 * Handbook home (specification 12.1): search, personal cards, category
 * navigation, safety-critical pages, recent updates, quick guides, forms.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_handbook\local\service\ack_service;
use local_handbook\local\service\path_service;
use local_handbook\local\service\report_service;

$context = context_system::instance();
local_handbook_require_view($context);

$url = new moodle_url('/local/handbook/index.php');
local_handbook_apply_page_setup($url, $context, 'home', get_string('pluginname', 'local_handbook'));

$canedit = has_capability('local/handbook:edit', $context);
$iseditorial = local_handbook_user_is_editorial($context);

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

// ---- Prominent search (12.1) ---------------------------------------------.

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/handbook/search.php'))->out(false),
    'class' => 'local-handbook-search',
    'role' => 'search',
]);
echo html_writer::start_div('input-group input-group-lg');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'q',
    'class' => 'form-control form-control-lg',
    'placeholder' => get_string('searchplaceholder', 'local_handbook'),
    'aria-label' => get_string('searchhandbook', 'local_handbook'),
    'autocomplete' => 'off',
    'data-livesearch' => 1,
]);
echo html_writer::div(
    html_writer::tag('button',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-magnifying-glass me-2', 'aria-hidden' => 'true'])
        . s(get_string('search', 'core')),
        ['type' => 'submit', 'class' => 'btn btn-primary']),
    'input-group-append'
);
echo html_writer::end_div();
echo html_writer::end_tag('form');

// Live results land here as the user types (js/livesearch.js).
echo html_writer::div('', 'local-handbook-livesearch', [
    'data-region' => 'livesearch',
    'data-ajaxurl' => (new moodle_url('/local/handbook/ajax.php'))->out(false),
    'aria-live' => 'polite',
]);
$PAGE->requires->js(new moodle_url('/local/handbook/js/livesearch.js'));

// ---- Personal row: pending reading, path progress, editorial work --------.

$personalcards = [];

if (has_capability('local/handbook:acknowledge', $context)) {
    $pending = ack_service::get_pending_for_user((int)$USER->id, 5);
    $body = html_writer::tag('h3',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-circle-exclamation me-2 text-warning',
            'aria-hidden' => 'true'])
        . s(get_string('pendingreadingcard', 'local_handbook')),
        ['class' => 'h6 text-uppercase text-muted mb-3']);
    if (!$pending) {
        $body .= html_writer::div(s(get_string('noackpending', 'local_handbook')), 'small text-muted');
    } else {
        $body .= local_handbook_render_pagelist($pending, static function(stdClass $page): string {
            return $page->ackstatus === ack_service::STATUS_RECONFIRM
                ? get_string('reconfirmitem', 'local_handbook')
                : get_string('pendingitem', 'local_handbook');
        });
    }
    $personalcards[] = $body;
}

$visiblepaths = path_service::visible_paths((int)$USER->id,
    has_capability('local/handbook:managepaths', $context));
if ($visiblepaths) {
    $path = reset($visiblepaths);
    $progress = path_service::user_progress($path, (int)$USER->id);
    $percent = $progress->total > 0 ? (int)round($progress->confirmed / $progress->total * 100) : 0;

    $body = html_writer::tag('h3',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-route me-2 text-primary', 'aria-hidden' => 'true'])
        . s(get_string('myreadingpath', 'local_handbook')),
        ['class' => 'h6 text-uppercase text-muted mb-3']);
    $body .= html_writer::tag('p', html_writer::link(
        new moodle_url('/local/handbook/path.php', ['id' => $path->id]),
        html_writer::tag('strong', s(format_string($path->name)))), ['class' => 'mb-2']);
    $body .= html_writer::div(
        html_writer::div('', 'progress-bar', [
            'role' => 'progressbar',
            'style' => 'width: ' . $percent . '%',
            'aria-valuenow' => $progress->confirmed,
            'aria-valuemin' => 0,
            'aria-valuemax' => max(1, $progress->total),
        ]),
        'progress mb-2', ['style' => 'height: 0.5rem;']);
    $body .= html_writer::div(
        s(get_string('pathprogress', 'local_handbook', (object)[
            'confirmed' => $progress->confirmed,
            'total' => $progress->total,
        ])), 'small text-muted mb-2');
    $body .= html_writer::link(
        $progress->nextitem
            ? new moodle_url('/local/handbook/view.php', ['page' => $progress->nextitem->slug])
            : new moodle_url('/local/handbook/path.php', ['id' => $path->id]),
        html_writer::tag('i', '', ['class' => 'fa-solid fa-arrow-right me-2', 'aria-hidden' => 'true'])
            . s(get_string('continuepath', 'local_handbook')),
        ['class' => 'btn btn-outline-primary btn-sm']);
    $personalcards[] = $body;
}

if ($iseditorial) {
    $counts = report_service::editorial_counts();
    $lines = [
        html_writer::link(new moodle_url('/local/handbook/review.php'),
            s(get_string('draftsawaiting', 'local_handbook', $counts->inreview))),
        s(get_string('changesrequestedcount', 'local_handbook', $counts->changesrequested)),
        has_capability('local/handbook:viewreports', $context)
            ? html_writer::link(new moodle_url('/local/handbook/manage/reports.php'),
                s(get_string('overduereviewcount', 'local_handbook', $counts->overduereview)))
            : s(get_string('overduereviewcount', 'local_handbook', $counts->overduereview)),
        has_capability('local/handbook:managefindings', $context)
            ? html_writer::link(new moodle_url('/local/handbook/manage/findings.php'),
                s(get_string('openfindingscount', 'local_handbook', $counts->openfindings)))
            : s(get_string('openfindingscount', 'local_handbook', $counts->openfindings)),
    ];
    $items = '';
    foreach ($lines as $line) {
        $items .= html_writer::tag('li', $line);
    }
    $body = html_writer::tag('h3',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-list-check me-2 text-secondary',
            'aria-hidden' => 'true'])
        . s(get_string('editorialwork', 'local_handbook')),
        ['class' => 'h6 text-uppercase text-muted mb-3']);
    $body .= html_writer::tag('ul', $items, ['class' => 'local-handbook-pagelist']);
    $personalcards[] = $body;
}

if ($personalcards) {
    $columnclass = 'col-lg-' . (count($personalcards) === 3 ? '4' : (count($personalcards) === 2 ? '6' : '12'));
    echo html_writer::start_div('row');
    foreach ($personalcards as $card) {
        echo html_writer::div(
            html_writer::div(html_writer::div($card, 'card-body'), 'card mb-3 flex-fill'),
            $columnclass . ' d-flex');
    }
    echo html_writer::end_div();
}

// ---- Categories: full-width, two-column accordion -------------------------.
// Each category is a native <details> drawer; opening it reveals its pages as
// banner cards (same renderer as the category view) plus subcategory chips.

$categories = local_handbook_get_categories(0, has_capability('local/handbook:managecategories', $context));
$counts = local_handbook_count_published_pages_by_category();

echo html_writer::div(
    html_writer::tag('h3', s(get_string('categories', 'local_handbook')), ['class' => 'h5 mb-0'])
    . html_writer::tag('button', s(get_string('openall', 'local_handbook')), [
        'type' => 'button',
        'class' => 'btn btn-outline-secondary btn-sm',
        'data-action' => 'handbook-toggleall',
        'data-openlabel' => get_string('openall', 'local_handbook'),
        'data-closelabel' => get_string('closeall', 'local_handbook'),
    ]),
    'd-flex align-items-center justify-content-between mb-3');
$PAGE->requires->js(new moodle_url('/local/handbook/js/accordion.js'));

if (!$categories) {
    echo html_writer::div(s(get_string('nocategoriesyet', 'local_handbook')), 'alert alert-info');
} else {
    $items = '';
    foreach ($categories as $category) {
        $caturl = new moodle_url('/local/handbook/category.php', ['id' => $category->id]);
        $children = local_handbook_get_categories((int)$category->id);

        $pagecount = $counts[(int)$category->id] ?? 0;
        foreach ($children as $child) {
            $pagecount += $counts[(int)$child->id] ?? 0;
        }
        $countlabel = $pagecount === 1
            ? get_string('pagecountone', 'local_handbook')
            : get_string('pagecount', 'local_handbook', $pagecount);

        // Summary row: icon, name, count, chevron.
        $summary = html_writer::tag('summary',
            html_writer::span(
                html_writer::tag('i', '', ['class' => 'fa-solid '
                    . local_handbook_category_icon($category), 'aria-hidden' => 'true']),
                'category-icon')
            . html_writer::span(s($category->name)
                . (!(int)$category->visible
                    ? ' ' . html_writer::span(s(get_string('hidden', 'core')), 'badge badge-secondary')
                    : ''), 'cat-acc-name')
            . html_writer::span(s($countlabel), 'category-count')
            . html_writer::tag('i', '', ['class' => 'fa-solid fa-chevron-down cat-acc-chevron',
                'aria-hidden' => 'true']));

        // Drawer: subcategory chips + page cards + open-category link.
        $drawer = '';
        if ($children) {
            $chips = '';
            foreach ($children as $child) {
                $childcount = $counts[(int)$child->id] ?? 0;
                $chips .= html_writer::link(
                    new moodle_url('/local/handbook/category.php', ['id' => $child->id]),
                    s($child->name) . ($childcount
                        ? ' ' . html_writer::span($childcount, 'chip-count') : ''),
                    ['class' => 'cat-acc-chip']);
            }
            $chips .= html_writer::link($caturl,
                s(get_string('opencategorylink', 'local_handbook')) . ' ›',
                ['class' => 'cat-acc-chip is-open-link']);
            $drawer .= html_writer::div($chips, 'cat-acc-chips');
        }

        $pages = local_handbook_get_published_pages((int)$category->id);
        if ($pages) {
            $versions = local_handbook_published_versions($pages);
            $cards = '';
            foreach ($pages as $page) {
                $cards .= local_handbook_render_page_card($page,
                    $versions[(int)$page->publishedrevisionid] ?? 0);
            }
            $drawer .= html_writer::div($cards, 'local-handbook-cards cat-acc-cards');
        } else if (!$children) {
            $drawer .= html_writer::div(s(get_string('emptycategory', 'local_handbook')),
                'small text-muted');
        }
        if (!$children) {
            $drawer .= html_writer::div(html_writer::link($caturl,
                s(get_string('opencategorylink', 'local_handbook')) . ' ›',
                ['class' => 'cat-acc-openlink']), 'mt-2');
        }

        $items .= html_writer::tag('details',
            $summary . html_writer::div($drawer, 'cat-acc-body'),
            ['class' => 'local-handbook-cat-acc']);
    }
    echo html_writer::div($items, 'local-handbook-cat-grid mb-4');
}

// ---- Highlights row (formerly the rail) ------------------------------------.

$railcards = [];

$safetypages = $DB->get_records_select('local_handbook_page',
    "criticality = 'safetycritical' AND publishedrevisionid > 0 AND archived = 0",
    [], 'title ASC', '*', 0, 6);
if ($safetypages) {
    $railcards[] = html_writer::tag('h3',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-triangle-exclamation me-2 text-warning',
            'aria-hidden' => 'true'])
        . s(get_string('safetycriticalpages', 'local_handbook')),
        ['class' => 'h6 text-uppercase text-muted mb-3'])
        . local_handbook_render_pagelist($safetypages);
}

$recent = local_handbook_get_recently_published(5);
if ($recent) {
    $railcards[] = html_writer::tag('h3',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-clock-rotate-left me-2 text-muted',
            'aria-hidden' => 'true'])
        . s(get_string('recentlyupdated', 'local_handbook')),
        ['class' => 'h6 text-uppercase text-muted mb-3'])
        . local_handbook_render_pagelist($recent, static function(stdClass $page): string {
            return get_string('versionnumber', 'local_handbook', (int)$page->versionnumber)
                . ' · ' . userdate((int)$page->timepublished, get_string('strftimedate', 'langconfig'));
        });
}

$quickguides = $DB->get_records_select('local_handbook_page',
    "contenttype = 'quickguide' AND publishedrevisionid > 0 AND archived = 0",
    [], 'title ASC', '*', 0, 5);
if ($quickguides) {
    $railcards[] = html_writer::tag('h3',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-bolt me-2 text-muted', 'aria-hidden' => 'true'])
        . s(get_string('quickguides', 'local_handbook')),
        ['class' => 'h6 text-uppercase text-muted mb-3'])
        . local_handbook_render_pagelist($quickguides)
        . html_writer::tag('p', html_writer::link(
            new moodle_url('/local/handbook/search.php', ['contenttype' => 'quickguide']),
            s(get_string('viewall', 'local_handbook')) . ' ›'), ['class' => 'small mb-0 mt-2']);
}

$templates = $DB->get_records_select('local_handbook_page',
    "contenttype = 'template' AND publishedrevisionid > 0 AND archived = 0",
    [], 'title ASC', '*', 0, 5);
if ($templates) {
    $railcards[] = html_writer::tag('h3',
        html_writer::tag('i', '', ['class' => 'fa-solid fa-file-lines me-2 text-muted', 'aria-hidden' => 'true'])
        . s(get_string('formstemplates', 'local_handbook')),
        ['class' => 'h6 text-uppercase text-muted mb-3'])
        . local_handbook_render_pagelist($templates)
        . html_writer::tag('p', html_writer::link(
            new moodle_url('/local/handbook/search.php', ['contenttype' => 'template']),
            s(get_string('viewall', 'local_handbook')) . ' ›'), ['class' => 'small mb-0 mt-2']);
}

if (!$railcards && !$recent) {
    echo html_writer::div(s(get_string('nopagesyet', 'local_handbook')), 'alert alert-info');
}
if ($railcards) {
    $railcolumn = 'col-md-6 col-xl-' . (count($railcards) >= 4 ? '3' : (int)(12 / max(1, count($railcards))));
    echo html_writer::start_div('row');
    foreach ($railcards as $card) {
        echo html_writer::div(
            html_writer::div(html_writer::div($card, 'card-body'), 'card mb-3 flex-fill'),
            $railcolumn . ' d-flex');
    }
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
