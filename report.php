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
 * Report a possible error on a page (specification 12.2, 19).
 *
 * Creates a quality finding (source "human") linked to the page and its
 * published revision. Any reader with the view capability may report.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_handbook\form\report_form;
use local_handbook\local\service\finding_service;

$pageparam = required_param('page', PARAM_ALPHANUMEXT);

$context = context_system::instance();
local_handbook_require_view($context);

if (ctype_digit($pageparam)) {
    $page = $DB->get_record('local_handbook_page', ['id' => (int)$pageparam]);
} else {
    $page = $DB->get_record('local_handbook_page', ['slug' => $pageparam]);
}
if (!$page) {
    throw new moodle_exception('errorpagenotfound', 'local_handbook');
}

$url = new moodle_url('/local/handbook/report.php', ['page' => $page->slug]);
local_handbook_apply_page_setup($url, $context, 'home',
    get_string('reportproblem', 'local_handbook'),
    get_string('reportproblem', 'local_handbook'));

$form = new report_form($url->out(false), ['page' => $page]);

if ($form->is_cancelled()) {
    redirect(local_handbook_page_url($page));
}

if ($data = $form->get_data()) {
    $summary = shorten_text(trim($data->description), 200, true);

    $finding = finding_service::create((object)[
        'findingtype' => $data->findingtype,
        'summary' => $summary !== '' ? $summary : get_string('reportproblem', 'local_handbook'),
        'explanation' => trim($data->description),
        'source' => 'human',
        'severity' => 'medium',
        'confidence' => 'high',
    ], [[
        'pageid' => (int)$page->id,
        'revisionid' => (int)$page->publishedrevisionid,
        'anchor' => trim((string)$data->anchor),
    ]]);

    redirect(local_handbook_page_url($page),
        get_string('reportthanks', 'local_handbook', $finding->id));
}

echo $OUTPUT->header();
echo local_handbook_render_area_actions('home', $context);

echo local_handbook_render_category_trail((int)$page->categoryid);
echo local_handbook_render_page_heading(
    get_string('reportproblem', 'local_handbook') . ': ' . format_string($page->title));

echo html_writer::tag('p', s(get_string('reportintro', 'local_handbook')),
    ['class' => 'text-muted']);

$form->display();

echo $OUTPUT->footer();
