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

namespace local_handbook\task;

use local_handbook\local\service\finding_service;

/**
 * Daily link checker (specification 15.4, 19.1).
 *
 * Scans published content for internal handbook links whose target page
 * does not exist (or is unpublished/archived), and path items whose quiz
 * course-module id no longer exists (cmids are breakable references,
 * 15.4). Each problem becomes an advisory "broken_link" finding, deduped
 * against findings that are still open or under review.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_checker extends \core\task\scheduled_task {

    /**
     * Task name for the admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_linkchecker', 'local_handbook');
    }

    /**
     * Run the check.
     *
     * @return void
     */
    public function execute() {
        $created = 0;
        $created += $this->check_internal_links();
        $created += $this->check_quiz_links();
        mtrace('local_handbook: link checker created ' . $created . ' finding(s).');
    }

    /**
     * Internal handbook links inside published content.
     *
     * @return int Findings created.
     */
    private function check_internal_links(): int {
        global $DB;

        $sql = "SELECT p.id AS pageid, p.slug, r.content
                  FROM {local_handbook_page} p
                  JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
                 WHERE p.archived = 0";
        $created = 0;

        foreach ($DB->get_records_sql($sql) as $record) {
            if (!preg_match_all('~/local/handbook/view\.php\?page=([a-zA-Z0-9_-]+)~',
                    (string)$record->content, $matches)) {
                continue;
            }
            foreach (array_unique($matches[1]) as $targetslug) {
                $target = ctype_digit($targetslug)
                    ? $DB->get_record('local_handbook_page', ['id' => (int)$targetslug])
                    : $DB->get_record('local_handbook_page', ['slug' => $targetslug]);

                $broken = !$target || (int)$target->archived === 1
                    || (int)$target->publishedrevisionid === 0;
                if (!$broken) {
                    continue;
                }

                $anchor = 'link:' . $targetslug;
                if ($this->open_finding_exists((int)$record->pageid, $anchor)) {
                    continue;
                }

                finding_service::create((object)[
                    'findingtype' => 'broken_link',
                    'summary' => get_string('brokenlinksummary', 'local_handbook', (object)[
                        'page' => $record->slug,
                        'target' => $targetslug,
                    ]),
                    'source' => 'audit',
                    'severity' => 'low',
                    'confidence' => 'high',
                ], [[
                    'pageid' => (int)$record->pageid,
                    'anchor' => $anchor,
                ]]);
                $created++;
            }
        }
        return $created;
    }

    /**
     * Quiz course-module ids on path items.
     *
     * @return int Findings created.
     */
    private function check_quiz_links(): int {
        global $DB;

        $sql = "SELECT i.id, i.quizcmid, i.pageid, p.slug
                  FROM {local_handbook_pathitem} i
                  JOIN {local_handbook_page} p ON p.id = i.pageid
                 WHERE i.quizcmid > 0";
        $created = 0;

        foreach ($DB->get_records_sql($sql) as $item) {
            if ($DB->record_exists('course_modules', ['id' => $item->quizcmid])) {
                continue;
            }

            $anchor = 'quizcmid:' . $item->quizcmid;
            if ($this->open_finding_exists((int)$item->pageid, $anchor)) {
                continue;
            }

            finding_service::create((object)[
                'findingtype' => 'broken_link',
                'summary' => get_string('brokenquizsummary', 'local_handbook', (object)[
                    'page' => $item->slug,
                    'cmid' => (int)$item->quizcmid,
                ]),
                'source' => 'audit',
                'severity' => 'medium',
                'confidence' => 'high',
            ], [[
                'pageid' => (int)$item->pageid,
                'anchor' => $anchor,
            ]]);
            $created++;
        }
        return $created;
    }

    /**
     * Whether an open/under-review broken_link finding already covers this
     * page + anchor.
     *
     * @param int $pageid Page id.
     * @param string $anchor Anchor key.
     * @return bool
     */
    private function open_finding_exists(int $pageid, string $anchor): bool {
        global $DB;

        $sql = "SELECT 1
                  FROM {local_handbook_finding} f
                  JOIN {local_handbook_findpage} fp ON fp.findingid = f.id
                 WHERE f.findingtype = 'broken_link'
                   AND f.status IN (:s1, :s2)
                   AND fp.pageid = :pageid
                   AND fp.anchor = :anchor";
        return $DB->record_exists_sql($sql, [
            's1' => finding_service::STATUS_OPEN,
            's2' => finding_service::STATUS_UNDER_REVIEW,
            'pageid' => $pageid,
            'anchor' => $anchor,
        ]);
    }
}
