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
 * Content style guide: the handbook's reusable "hb-*" patterns, each shown
 * rendered with its copy-paste HTML. Editors write these in the page editor's
 * HTML source view; the Handbook AI receives the same catalogue via the API.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:edit', $context);

$url = new moodle_url('/local/handbook/manage/styleguide.php');
local_handbook_apply_page_setup($url, $context, 'styleguide',
    get_string('styleguide', 'local_handbook'));

echo $OUTPUT->header();
echo local_handbook_render_area_actions('styleguide', $context);
echo local_handbook_render_page_heading(get_string('styleguide', 'local_handbook'));

echo html_writer::div(s(get_string('styleguideintro', 'local_handbook')),
    'alert alert-info', ['role' => 'note']);

// On-page index.
$patterns = local_handbook_style_patterns();
$toc = '';
foreach ($patterns as $pattern) {
    $toc .= html_writer::tag('li',
        html_writer::link('#sg-' . $pattern->key, s($pattern->title)));
}
echo html_writer::div(
    html_writer::div(
        html_writer::tag('h2', s(get_string('styleguidepatterns', 'local_handbook')),
            ['class' => 'h6 text-uppercase text-muted mb-2'])
        . html_writer::tag('ul', $toc, ['class' => 'local-handbook-toc mb-0']),
        'card-body'),
    'card mb-4');

foreach ($patterns as $pattern) {
    // Heading + when-to-use.
    $head = html_writer::tag('h2', s($pattern->title), ['class' => 'h5 mb-1', 'id' => 'sg-' . $pattern->key])
        . html_writer::tag('p', s($pattern->whenuse), ['class' => 'text-muted small mb-3']);

    // Live preview (wrapped like real page content so the hb-* tokens apply).
    $preview = html_writer::div(
        html_writer::div($pattern->html, 'local-handbook-page-body'),
        'border rounded p-3 mb-3 local-handbook-sg-preview');

    // Copy-paste source.
    $source = html_writer::tag('p',
        s(get_string('styleguidecopy', 'local_handbook')), ['class' => 'small text-muted mb-1'])
        . html_writer::tag('pre', s($pattern->html),
            ['class' => 'local-handbook-sg-code']);

    echo html_writer::div(
        html_writer::div($head . $preview . $source, 'card-body'),
        'card mb-4');
}

echo $OUTPUT->footer();
