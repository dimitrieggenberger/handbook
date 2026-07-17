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
 * Live-search endpoint (specification 13.2): returns matching published pages
 * rendered as banner cards, as JSON {count, html}. Read-only; same query rules
 * as search.php (title, summary and published plain text; never drafts,
 * never archived pages).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$query = trim(optional_param('q', '', PARAM_RAW));

$context = context_system::instance();
require_login(null, false);
require_capability('local/handbook:view', $context);

$limit = 12;
$response = ['count' => 0, 'html' => ''];

if (\core_text::strlen($query) >= 2) {
    $like = '%' . $DB->sql_like_escape($query) . '%';
    $where = 'p.archived = 0 AND p.publishedrevisionid > 0 AND ('
        . $DB->sql_like('p.title', ':q1', false) . ' OR '
        . $DB->sql_like('p.summary', ':q2', false) . ' OR '
        . $DB->sql_like('r.plaintext', ':q3', false) . ')';
    $sqlparams = ['q1' => $like, 'q2' => $like, 'q3' => $like];

    $total = (int)$DB->count_records_sql(
        "SELECT COUNT(1)
           FROM {local_handbook_page} p
           JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
          WHERE $where", $sqlparams);

    // Title matches first, then the rest alphabetically.
    $titlelike = $DB->sql_like('p.title', ':qorder', false);
    $sql = "SELECT p.*, r.versionnumber
              FROM {local_handbook_page} p
              JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
             WHERE $where
          ORDER BY CASE WHEN $titlelike THEN 0 ELSE 1 END, p.title ASC";
    $results = $DB->get_records_sql($sql, $sqlparams + ['qorder' => $like], 0, $limit);

    $cards = '';
    foreach ($results as $page) {
        $cards .= local_handbook_render_page_card($page, (int)$page->versionnumber);
    }

    $html = html_writer::tag('p', s(get_string('searchresultcount', 'local_handbook', $total)),
        ['class' => 'text-muted small mb-2']);
    if ($cards !== '') {
        $html .= html_writer::div($cards, 'local-handbook-cards');
        if ($total > $limit) {
            $html .= html_writer::div(html_writer::link(
                new moodle_url('/local/handbook/search.php', ['q' => $query]),
                s(get_string('viewallresults', 'local_handbook', $total)) . ' ›'),
                'mt-2 small');
        }
    } else {
        $html .= html_writer::div(s(get_string('noresults', 'local_handbook')), 'alert alert-info mb-0');
    }

    $response = ['count' => $total, 'html' => $html];
}

echo json_encode($response);
