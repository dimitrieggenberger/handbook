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
 * Seed content import (specification 25.1).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\form\import_form;
use local_handbook\local\service\import_service;
use local_handbook\local\service\page_service;

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:manage', $context);

$url = new moodle_url('/local/handbook/manage/import.php');
local_handbook_apply_page_setup($url, $context, 'import',
    get_string('importseed', 'local_handbook'));

$form = new import_form($url->out(false), [
    'canpublish' => page_service::bootstrap_mode_enabled()
        && has_capability('local/handbook:publish', $context),
]);

$report = null;
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/handbook/index.php'));
} else if ($data = $form->get_data()) {
    $json = $form->get_file_content('seedfile');
    $seed = json_decode((string)$json);
    if (!is_object($seed)) {
        $report = (object)['errors' => [get_string('errorinvalidjson', 'local_handbook')],
            'categoriescreated' => 0, 'categoriesupdated' => 0, 'pagescreated' => 0,
            'pagesupdated' => 0, 'pagespublished' => 0, 'relationscreated' => 0,
            'pathscreated' => 0, 'pathsupdated' => 0];
    } else {
        $report = import_service::import($seed, !empty($data->publishonimport));
    }
}

echo $OUTPUT->header();
echo local_handbook_render_area_actions('import', $context);
echo local_handbook_render_page_heading(get_string('importseed', 'local_handbook'));

if (!page_service::bootstrap_mode_enabled()) {
    echo html_writer::div(s(get_string('bootstrapoffnotice', 'local_handbook')), 'alert alert-info');
}

if ($report !== null) {
    $lines = '';
    $counters = [
        'importcategoriescreated' => $report->categoriescreated,
        'importcategoriesupdated' => $report->categoriesupdated,
        'importpagescreated' => $report->pagescreated,
        'importpagesupdated' => $report->pagesupdated,
        'importpagespublished' => $report->pagespublished,
        'importrelationscreated' => $report->relationscreated,
        'importpathscreated' => $report->pathscreated,
        'importpathsupdated' => $report->pathsupdated,
    ];
    foreach ($counters as $stringkey => $count) {
        $lines .= html_writer::tag('li', s(get_string($stringkey, 'local_handbook', $count)));
    }
    echo html_writer::div(
        html_writer::tag('ul', $lines, ['class' => 'mb-0']),
        'alert alert-success'
    );

    if ($report->errors) {
        $errorlines = '';
        foreach ($report->errors as $error) {
            $errorlines .= html_writer::tag('li', s($error));
        }
        echo html_writer::div(
            html_writer::tag('strong', s(get_string('importerrors', 'local_handbook')))
            . html_writer::tag('ul', $errorlines, ['class' => 'mb-0']),
            'alert alert-warning'
        );
    }
}

$form->display();

echo $OUTPUT->footer();
