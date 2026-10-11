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
 * Seed the simulation Moodle with handbook content.
 *
 * Imports docs/seed/initial-handbook.json (published), adds one page that
 * carries every content pattern of the style guide (so the reader shows each
 * hb-* pattern inside a real article), a second revision of that page waiting
 * in the review queue, and an open finding on it. Idempotent: the import
 * matches by slug, and the draft and the finding are only added once.
 * Localhost only.
 *
 * Usage: php seed.php /opt/sim/moodle
 *
 * @package   local_handbook
 * @copyright Educacion Helvetica SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
$moodle = $argv[1] ?? '/opt/sim/moodle';
require($moodle . '/config.php');
require_once($CFG->dirroot . '/local/handbook/locallib.php');

use local_handbook\local\service\finding_service;
use local_handbook\local\service\import_service;
use local_handbook\local\service\page_service;

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?$~', $CFG->wwwroot)) {
    cli_error('Refusing to seed ' . $CFG->wwwroot . ': localhost only.');
}

\core\session\manager::set_user(get_admin());

$seed = json_decode(file_get_contents(__DIR__ . '/../../docs/seed/initial-handbook.json'));
if (!$seed) {
    cli_error('docs/seed/initial-handbook.json is not valid JSON.');
}

// The pattern showcase: every style-guide example in one published article.
$showcase = 'patrones-de-contenido';
$html = '';
foreach (local_handbook_style_patterns() as $pattern) {
    $html .= '<h2>' . s($pattern->title) . '</h2>' . $pattern->html;
}
$seed->pages[] = (object)[
    'slug' => $showcase,
    'title' => 'Patrones de contenido',
    'category' => $seed->pages[0]->category,
    'contenttype' => 'guideline',
    'authoritylevel' => 3,
    'criticality' => 'reference',
    'requiredreading' => 0,
    'responsiblearea' => 'Dirección',
    'summary' => 'Every content pattern of the style guide, rendered in an article (simulation only).',
    'content' => $html,
];

// Publishing on import needs bootstrap mode; the site's setting comes back after.
$bootstrap = get_config('local_handbook', 'bootstrapmode');
set_config('bootstrapmode', 1, 'local_handbook');
$report = import_service::import($seed, true);
set_config('bootstrapmode', $bootstrap, 'local_handbook');
mtrace("Imported: {$report->pagescreated} pages created, {$report->pagesupdated} updated, "
    . "{$report->pagespublished} published.");

$page = $DB->get_record('local_handbook_page', ['slug' => $showcase], '*', MUST_EXIST);

// A second revision in review: the review queue, history and compare pages.
if (!page_service::get_working_revision((int)$page->id)) {
    $draft = page_service::create_revision_draft($page);
    page_service::update_draft($draft, $html . '<p>Una línea nueva para comparar revisiones.</p>',
        FORMAT_HTML, 'Línea nueva al final');
    page_service::submit_for_review($DB->get_record('local_handbook_revision', ['id' => $draft->id]),
        'Línea nueva al final');
    mtrace('Draft submitted for review.');
}

// An open finding: the findings list and the page report.
if (!$DB->record_exists('local_handbook_finding', ['summary' => 'Simulación: enlace roto en la guía de patrones'])) {
    finding_service::create((object)[
        'findingtype' => finding_service::finding_types()[0],
        'severity' => 'medium',
        'summary' => 'Simulación: enlace roto en la guía de patrones',
        'explanation' => 'Un enlace del ejemplo de referencias no abre.',
        'recommendation' => 'Revisar el enlace.',
    ], [['pageid' => (int)$page->id, 'revisionid' => (int)$page->publishedrevisionid, 'anchor' => '', 'excerpt' => '']]);
    mtrace('Finding recorded.');
}
