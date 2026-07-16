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
 * Reading-path recommendations & audit (specification 10). Advisory review:
 * accepting a recommendation drafts a reading-path revision into a change set;
 * it never edits the active path.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

use local_handbook\local\service\changeset_service;
use local_handbook\local\service\recommendation_service;

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:managepaths', $context);

$url = new moodle_url('/local/handbook/manage/recommendations.php');
local_handbook_apply_page_setup($url, $context, 'recommendations',
    get_string('recommendations', 'local_handbook'));

// ---- Actions ------------------------------------------------------------.

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    $recid = required_param('recid', PARAM_INT);

    if ($action === 'accept') {
        $rec = $DB->get_record('local_handbook_pathrec', ['id' => $recid], '*', MUST_EXIST);
        $pathname = (string)$DB->get_field('local_handbook_path', 'name', ['id' => $rec->pathid]);
        $changeset = changeset_service::create((object)[
            'title' => get_string('recchangesettitle', 'local_handbook', $pathname),
            'source' => 'human',
        ]);
        recommendation_service::accept_into_changeset($recid, (int)$changeset->id);
        redirect(new moodle_url('/local/handbook/manage/changeset.php', ['id' => $changeset->id]),
            get_string('recaccepted', 'local_handbook'));
    }

    // Status triage (dismiss / already_covered / intentional_omission / defer / reopen).
    $status = required_param('status', PARAM_ALPHANUMEXT);
    recommendation_service::set_status($recid, $status);
    redirect($url, get_string('recupdated', 'local_handbook'));
}

echo $OUTPUT->header();
echo local_handbook_render_area_actions('recommendations', $context);
echo local_handbook_render_page_heading(get_string('recommendations', 'local_handbook'));

// ---- Coverage summary ---------------------------------------------------.

$coverage = recommendation_service::coverage();
$stat = static function (string $labelkey, int $value): string {
    return html_writer::div(
        html_writer::div($value, 'h4 mb-0') . html_writer::div(
            s(get_string($labelkey, 'local_handbook')), 'small text-muted'),
        'mr-4 mb-2');
};
echo html_writer::div(
    html_writer::div(
        html_writer::tag('h2', s(get_string('coverage', 'local_handbook')),
            ['class' => 'h6 text-uppercase text-muted mb-3'])
        . html_writer::div(
            $stat('coverage_covered', $coverage->pagescovered)
            . $stat('coverage_orphans', $coverage->orphans)
            . $stat('coverage_required', $coverage->requiredcovered)
            . $stat('coverage_overlap', $coverage->overlap)
            . $stat('coverage_paths', $coverage->activepaths),
            'd-flex flex-wrap'),
        'card-body'),
    'card mb-4');

// ---- Audit findings -----------------------------------------------------.

$audit = recommendation_service::audit();
if ($audit) {
    $rows = '';
    foreach ($audit as $finding) {
        $target = $finding->pageid
            ? s($finding->pagetitle)
            : ($finding->pathid ? s($finding->pathname) : '');
        $rows .= html_writer::tag('li',
            html_writer::span(s($finding->message), 'mr-2')
            . ($target !== '' ? html_writer::span($target, 'text-muted small') : ''),
            ['class' => 'mb-1']);
    }
    echo html_writer::div(
        html_writer::div(
            html_writer::tag('h2', s(get_string('audit', 'local_handbook')),
                ['class' => 'h6 text-uppercase text-muted mb-2'])
            . html_writer::tag('ul', $rows, ['class' => 'mb-0']),
            'card-body'),
        'card mb-4');
}

// ---- Open recommendations ----------------------------------------------.

echo html_writer::tag('h2', s(get_string('openrecommendations', 'local_handbook')),
    ['class' => 'h5 mb-3']);

$recs = recommendation_service::list_recommendations(['status' => recommendation_service::STATUS_OPEN],
    0, 200);
if (!$recs) {
    echo html_writer::div(s(get_string('norecommendations', 'local_handbook')), 'alert alert-info');
} else {
    $confbadge = ['high' => 'badge-danger', 'medium' => 'badge-warning', 'low' => 'badge-secondary'];
    foreach ($recs as $rec) {
        $pathname = (int)$rec->pathid
            ? (string)$DB->get_field('local_handbook_path', 'name', ['id' => $rec->pathid]) : '';
        $pagetitle = (int)$rec->pageid
            ? (string)$DB->get_field('local_handbook_page', 'title', ['id' => $rec->pageid]) : '';

        $head = html_writer::span(
            s(get_string('rectype_' . $rec->rectype, 'local_handbook')), 'font-weight-bold mr-2')
            . html_writer::span(s(get_string('scale_' . $rec->confidence, 'local_handbook')),
                'badge ' . ($confbadge[$rec->confidence] ?? 'badge-secondary') . ' mr-2')
            . ($rec->source === 'ai'
                ? html_writer::span(s(get_string('source_ai', 'local_handbook')), 'badge badge-light border')
                : '');

        $body = html_writer::tag('div', $head, ['class' => 'mb-1']);
        $line = [];
        if ($pagetitle !== '') {
            $line[] = html_writer::tag('strong', s($pagetitle));
        }
        if ($pathname !== '') {
            $line[] = get_string('rectopath', 'local_handbook', s($pathname));
        }
        if ($line) {
            $body .= html_writer::div(implode(' · ', $line), 'mb-1');
        }
        if (trim((string)$rec->rationale) !== '') {
            $body .= html_writer::div(s($rec->rationale), 'small text-muted mb-2');
        }

        // Action buttons.
        $btn = static function (string $act, string $label, string $class, array $extra = [])
                use ($url, $rec): string {
            $form = html_writer::start_tag('form',
                ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']);
            $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $act]);
            $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'recid', 'value' => (int)$rec->id]);
            $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            foreach ($extra as $k => $v) {
                $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $k, 'value' => $v]);
            }
            $form .= html_writer::tag('button', s($label), ['type' => 'submit', 'class' => 'btn btn-sm ' . $class]);
            $form .= html_writer::end_tag('form');
            return $form;
        };

        $actions = '';
        if ((int)$rec->pathid) {
            $actions .= $btn('accept', get_string('recaccept', 'local_handbook'), 'btn-primary mr-1');
        }
        $actions .= $btn('setstatus', get_string('recstatus_already_covered', 'local_handbook'),
            'btn-outline-secondary mr-1', ['status' => recommendation_service::STATUS_ALREADY_COVERED]);
        $actions .= $btn('setstatus', get_string('recstatus_intentional_omission', 'local_handbook'),
            'btn-outline-secondary mr-1', ['status' => recommendation_service::STATUS_INTENTIONAL]);
        $actions .= $btn('setstatus', get_string('recdismiss', 'local_handbook'),
            'btn-outline-danger', ['status' => recommendation_service::STATUS_DISMISSED]);

        $body .= html_writer::div($actions, 'd-flex flex-wrap gap-1');

        echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-2');
    }
}

echo $OUTPUT->footer();
