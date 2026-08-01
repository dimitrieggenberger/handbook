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

namespace local_handbook\local\service;

/**
 * Soft reading signal: article opens ("the gray zone").
 *
 * One row per (user, page) with first visit, last visit and open count,
 * written whenever a logged-in user opens the PUBLISHED article. Views
 * never grant reading credit — the dashboard shows them as "opened
 * without confirming", context for a conversation, not compliance. No
 * AI/MCP surface reads this data.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pageview_service {

    /**
     * Count one open of a page by a user (upsert).
     *
     * @param int $userid Viewer.
     * @param int $pageid Page opened.
     * @return void
     */
    public static function record(int $userid, int $pageid): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record('local_handbook_pageview',
            ['userid' => $userid, 'pageid' => $pageid]);
        if ($existing) {
            $DB->update_record('local_handbook_pageview', (object)[
                'id' => (int)$existing->id,
                'viewcount' => (int)$existing->viewcount + 1,
                'lastviewed' => $now,
            ]);
            return;
        }
        try {
            $DB->insert_record('local_handbook_pageview', (object)[
                'userid' => $userid,
                'pageid' => $pageid,
                'viewcount' => 1,
                'firstviewed' => $now,
                'lastviewed' => $now,
            ]);
        } catch (\dml_exception $e) {
            // Concurrent first view of the same page (unique index): count it
            // on the row the other request just created.
            $existing = $DB->get_record('local_handbook_pageview',
                ['userid' => $userid, 'pageid' => $pageid]);
            if ($existing) {
                $DB->update_record('local_handbook_pageview', (object)[
                    'id' => (int)$existing->id,
                    'viewcount' => (int)$existing->viewcount + 1,
                    'lastviewed' => $now,
                ]);
            }
        }
    }

    /**
     * A user's view rows over a page set, keyed by page id.
     *
     * @param int $userid User.
     * @param int[] $pageids Page ids.
     * @return \stdClass[] pageid => {viewcount, firstviewed, lastviewed}.
     */
    public static function user_views(int $userid, array $pageids): array {
        global $DB;

        if (!$pageids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED, 'pv');
        $params['userid'] = $userid;
        $views = [];
        $records = $DB->get_records_select('local_handbook_pageview',
            "userid = :userid AND pageid $insql", $params);
        foreach ($records as $record) {
            $views[(int)$record->pageid] = $record;
        }
        return $views;
    }
}
