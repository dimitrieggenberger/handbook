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
 * One-off image optimisation over existing handbook files.
 *
 * New uploads are optimised automatically on save (when enabled in the
 * plugin settings); this page applies the same pipeline to images that
 * were uploaded before the optimiser existed, and reports the savings.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\local\service\image_service;

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:manage', $context);

$url = new moodle_url('/local/handbook/manage/images.php');
local_handbook_apply_page_setup($url, $context, 'images',
    get_string('manageimages', 'local_handbook'));

$action = optional_param('action', '', PARAM_ALPHA);

$report = null;
if ($action === 'optimize' && confirm_sesskey()) {
    $report = (object)['scanned' => 0, 'optimized' => 0, 'beforebytes' => 0, 'afterbytes' => 0];
    core_php_time_limit::raise(300);
    raise_memory_limit(MEMORY_EXTRA);
    foreach (image_service::FILE_AREAS as $area) {
        $areareport = image_service::optimize_area($context, $area);
        $report->scanned += $areareport->scanned;
        $report->optimized += $areareport->optimized;
        $report->beforebytes += $areareport->beforebytes;
        $report->afterbytes += $areareport->afterbytes;
    }
}

// Current stock of images per file area.
$fs = get_file_storage();
$stats = [];
foreach (image_service::FILE_AREAS as $area) {
    $count = 0;
    $bytes = 0;
    foreach ($fs->get_area_files($context->id, 'local_handbook', $area, false, 'id', false) as $file) {
        if (strpos((string)$file->get_mimetype(), 'image/') !== 0) {
            continue;
        }
        $count++;
        $bytes += (int)$file->get_filesize();
    }
    $stats[$area] = (object)['count' => $count, 'bytes' => $bytes];
}

echo $OUTPUT->header();
echo local_handbook_render_area_actions('images', $context);
echo local_handbook_render_page_heading(get_string('manageimages', 'local_handbook'));

echo html_writer::tag('p', s(get_string('imagesintro', 'local_handbook', (object)[
    'width' => image_service::max_width(),
    'quality' => image_service::jpeg_quality(),
])), ['class' => 'text-muted']);

if (!image_service::enabled()) {
    echo html_writer::div(s(get_string('imageoptimizeoff', 'local_handbook')), 'alert alert-info');
}

if ($report !== null) {
    $saved = max(0, $report->beforebytes - $report->afterbytes);
    $percent = $report->beforebytes > 0
        ? round($saved * 100 / $report->beforebytes) : 0;
    echo html_writer::div(s(get_string('imagesreport', 'local_handbook', (object)[
        'scanned' => $report->scanned,
        'optimized' => $report->optimized,
        'before' => display_size($report->beforebytes),
        'after' => display_size($report->afterbytes),
        'saved' => display_size($saved) . ' (' . $percent . '%)',
    ])), 'alert alert-success');
}

$rows = '';
$arealabels = [
    'bannerimage' => get_string('imagesareabanners', 'local_handbook'),
    'revision' => get_string('imagesareacontent', 'local_handbook'),
];
$totalcount = 0;
$totalbytes = 0;
foreach ($stats as $area => $stat) {
    $totalcount += $stat->count;
    $totalbytes += $stat->bytes;
    $rows .= html_writer::tag('tr',
        html_writer::tag('td', s($arealabels[$area] ?? $area))
        . html_writer::tag('td', (string)$stat->count, ['class' => 'text-right'])
        . html_writer::tag('td', s(display_size($stat->bytes)), ['class' => 'text-right']));
}
$rows .= html_writer::tag('tr',
    html_writer::tag('th', s(get_string('total')))
    . html_writer::tag('th', (string)$totalcount, ['class' => 'text-right'])
    . html_writer::tag('th', s(display_size($totalbytes)), ['class' => 'text-right']));

echo html_writer::tag('table',
    html_writer::tag('thead', html_writer::tag('tr',
        html_writer::tag('th', s(get_string('imagesarea', 'local_handbook')))
        . html_writer::tag('th', s(get_string('imagescount', 'local_handbook')), ['class' => 'text-right'])
        . html_writer::tag('th', s(get_string('imagessize', 'local_handbook')), ['class' => 'text-right'])))
    . html_writer::tag('tbody', $rows),
    ['class' => 'table table-sm w-auto']);

echo $OUTPUT->single_button(
    new moodle_url($url, ['action' => 'optimize', 'sesskey' => sesskey()]),
    get_string('imagesoptimizenow', 'local_handbook'), 'post', ['type' => 'primary']);

echo html_writer::tag('p', s(get_string('imagesnote', 'local_handbook')),
    ['class' => 'text-muted small mt-3']);

echo $OUTPUT->footer();
