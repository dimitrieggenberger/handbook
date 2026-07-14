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

use stdClass;

/**
 * JSON seed importer (specification 25.1, pulled forward for initial
 * population).
 *
 * Seed format (all matching is by slug, so re-importing updates instead of
 * duplicating):
 *
 * {
 *   "categories": [
 *     {"slug": "...", "name": "...", "parent": "", "description": "",
 *      "sortorder": 10, "visible": 1}
 *   ],
 *   "pages": [
 *     {"slug": "...", "title": "...", "category": "<category slug>",
 *      "contenttype": "procedure", "authoritylevel": 2,
 *      "criticality": "operational", "requiredreading": 0,
 *      "aiaccess": "full", "language": "es", "responsiblearea": "",
 *      "summary": "...", "content": "<h2>...</h2>..."}
 *   ],
 *   "relations": [
 *     {"source": "<page slug>", "type": "implements", "target": "<page slug>"}
 *   ]
 * }
 *
 * Pages are created/updated as working drafts through page_service, so
 * every import is part of the normal revision history. With $publish (and
 * bootstrap mode enabled) each touched draft is published directly.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_service {

    /**
     * Import a decoded seed structure.
     *
     * @param stdClass $seed Decoded JSON seed.
     * @param bool $publish Publish touched drafts (requires bootstrap mode).
     * @param int $userid Acting user (0 = current user).
     * @return stdClass Report: counters + errors[].
     */
    public static function import(stdClass $seed, bool $publish = false, int $userid = 0): stdClass {
        global $USER;

        $userid = $userid ?: (int)$USER->id;

        $report = (object)[
            'categoriescreated' => 0,
            'categoriesupdated' => 0,
            'pagescreated' => 0,
            'pagesupdated' => 0,
            'pagespublished' => 0,
            'relationscreated' => 0,
            'pathscreated' => 0,
            'pathsupdated' => 0,
            'errors' => [],
        ];

        foreach ((array)($seed->categories ?? []) as $index => $category) {
            self::import_category((object)$category, $userid, $report, $index);
        }

        foreach ((array)($seed->pages ?? []) as $index => $page) {
            self::import_page((object)$page, $publish, $userid, $report, $index);
        }

        foreach ((array)($seed->relations ?? []) as $index => $relation) {
            self::import_relation((object)$relation, $userid, $report, $index);
        }

        foreach ((array)($seed->paths ?? []) as $index => $path) {
            self::import_path((object)$path, $userid, $report, $index);
        }

        return $report;
    }

    /**
     * Create or update a reading path and (declaratively) its items.
     *
     * Items are replaced from the seed on every import, so the seed file is
     * the single description of the path's structure.
     *
     * @param stdClass $data Seed entry.
     * @param int $userid Acting user.
     * @param stdClass $report Report to update.
     * @param int $index Entry position for error messages.
     * @return void
     */
    private static function import_path(stdClass $data, int $userid, stdClass $report, int $index): void {
        global $DB;

        $slug = page_service::slugify((string)($data->slug ?? ($data->name ?? '')));
        $name = trim((string)($data->name ?? ''));
        if ($slug === '' || $name === '') {
            $report->errors[] = "paths[$index]: missing slug or name";
            return;
        }

        $now = time();
        $existing = $DB->get_record('local_handbook_path', ['slug' => $slug]);

        // Audience: cohort idnumbers and role shortnames, resolved to ids.
        $cohortids = [];
        foreach ((array)($data->cohorts ?? []) as $idnumber) {
            $cohortid = (int)$DB->get_field('cohort', 'id', ['idnumber' => (string)$idnumber]);
            if ($cohortid) {
                $cohortids[] = $cohortid;
            } else {
                $report->errors[] = "paths[$index] ($slug): unknown cohort idnumber '$idnumber'";
            }
        }
        $roleids = [];
        foreach ((array)($data->roles ?? []) as $shortname) {
            $roleid = (int)$DB->get_field('role', 'id', ['shortname' => (string)$shortname]);
            if ($roleid) {
                $roleids[] = $roleid;
            } else {
                $report->errors[] = "paths[$index] ($slug): unknown role shortname '$shortname'";
            }
        }

        $record = new stdClass();
        $record->name = $name;
        $record->description = (string)($data->description ?? '');
        $record->descriptionformat = FORMAT_HTML;
        $record->audiencejson = path_service::encode_audience($cohortids, $roleids);
        $record->schoolyear = (string)($data->schoolyear ?? '');
        $record->active = (int)($data->active ?? 1);
        $record->quizcmid = (int)($data->quizcmid ?? 0);
        $record->timemodified = $now;
        $record->modifiedby = $userid;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_handbook_path', $record);
            $pathid = (int)$existing->id;
            $report->pathsupdated++;
        } else {
            $record->slug = $slug;
            $record->timecreated = $now;
            $record->createdby = $userid;
            $pathid = (int)$DB->insert_record('local_handbook_path', $record);
            $report->pathscreated++;
        }

        // Declarative items: replace with the seed's list.
        $DB->delete_records('local_handbook_pathitem', ['pathid' => $pathid]);
        $sortorder = 10;
        foreach ((array)($data->items ?? []) as $itemindex => $item) {
            $item = (object)$item;
            $pageslug = trim((string)($item->page ?? ''));
            $pageid = $pageslug !== ''
                ? (int)$DB->get_field('local_handbook_page', 'id', ['slug' => $pageslug])
                : 0;
            if (!$pageid) {
                $report->errors[] = "paths[$index] ($slug) items[$itemindex]: unknown page '$pageslug'";
                continue;
            }
            $DB->insert_record('local_handbook_pathitem', (object)[
                'pathid' => $pathid,
                'pageid' => $pageid,
                'sectionname' => (string)($item->section ?? ''),
                'sortorder' => (int)($item->sortorder ?? $sortorder),
                'required' => (int)($item->required ?? 1),
                'quizcmid' => (int)($item->quizcmid ?? 0),
            ]);
            $sortorder += 10;
        }
    }

    /**
     * Create or update one category.
     *
     * @param stdClass $data Seed entry.
     * @param int $userid Acting user.
     * @param stdClass $report Report to update.
     * @param int $index Entry position for error messages.
     * @return void
     */
    private static function import_category(stdClass $data, int $userid, stdClass $report, int $index): void {
        global $DB;

        $slug = page_service::slugify((string)($data->slug ?? ($data->name ?? '')));
        $name = trim((string)($data->name ?? ''));
        if ($slug === '' || $name === '') {
            $report->errors[] = "categories[$index]: missing slug or name";
            return;
        }

        $parentid = 0;
        $parentslug = trim((string)($data->parent ?? ''));
        if ($parentslug !== '') {
            $parentid = (int)$DB->get_field('local_handbook_category', 'id', ['slug' => $parentslug]);
            if (!$parentid) {
                $report->errors[] = "categories[$index] ($slug): unknown parent '$parentslug'";
                return;
            }
        }

        $now = time();
        $existing = $DB->get_record('local_handbook_category', ['slug' => $slug]);

        $record = new stdClass();
        $record->parentid = $parentid;
        $record->name = $name;
        $record->description = (string)($data->description ?? '');
        $record->descriptionformat = FORMAT_HTML;
        $record->sortorder = (int)($data->sortorder ?? 0);
        $record->visible = (int)($data->visible ?? 1);
        $record->audiencekey = '';
        $record->timemodified = $now;
        $record->modifiedby = $userid;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_handbook_category', $record);
            $report->categoriesupdated++;
        } else {
            $record->slug = $slug;
            $record->timecreated = $now;
            $record->createdby = $userid;
            $DB->insert_record('local_handbook_category', $record);
            $report->categoriescreated++;
        }
    }

    /**
     * Create or update one page and its working draft.
     *
     * @param stdClass $data Seed entry.
     * @param bool $publish Publish the touched draft.
     * @param int $userid Acting user.
     * @param stdClass $report Report to update.
     * @param int $index Entry position for error messages.
     * @return void
     */
    private static function import_page(stdClass $data, bool $publish, int $userid,
            stdClass $report, int $index): void {
        global $DB;

        $slug = page_service::slugify((string)($data->slug ?? ($data->title ?? '')));
        $title = trim((string)($data->title ?? ''));
        $categoryslug = trim((string)($data->category ?? ''));
        if ($slug === '' || $title === '' || $categoryslug === '') {
            $report->errors[] = "pages[$index]: missing slug, title or category";
            return;
        }

        $categoryid = (int)$DB->get_field('local_handbook_category', 'id', ['slug' => $categoryslug]);
        if (!$categoryid) {
            $report->errors[] = "pages[$index] ($slug): unknown category '$categoryslug'";
            return;
        }

        $contenttype = (string)($data->contenttype ?? 'procedure');
        if (!in_array($contenttype, page_service::content_types(), true)) {
            $report->errors[] = "pages[$index] ($slug): unknown content type '$contenttype'";
            return;
        }
        $criticality = (string)($data->criticality ?? 'operational');
        if (!in_array($criticality, page_service::criticalities(), true)) {
            $report->errors[] = "pages[$index] ($slug): unknown criticality '$criticality'";
            return;
        }
        $aiaccess = (string)($data->aiaccess ?? 'full');
        if (!in_array($aiaccess, page_service::ai_access_levels(), true)) {
            $report->errors[] = "pages[$index] ($slug): unknown aiaccess '$aiaccess'";
            return;
        }

        $content = (string)($data->content ?? '');
        $now = time();
        $page = $DB->get_record('local_handbook_page', ['slug' => $slug]);

        if (!$page) {
            $create = new stdClass();
            $create->title = $title;
            $create->slug = $slug;
            $create->categoryid = $categoryid;
            $create->contenttype = $contenttype;
            $create->authoritylevel = (int)($data->authoritylevel ?? 4);
            $create->criticality = $criticality;
            $create->responsiblearea = (string)($data->responsiblearea ?? '');
            $create->requiredreading = (int)($data->requiredreading ?? 0);
            $create->aiaccess = $aiaccess;
            $create->language = (string)($data->language ?? 'es');
            $create->sortorder = (int)($data->sortorder ?? 0);
            $create->summary = (string)($data->summary ?? '');
            $create->content = $content;
            $create->contentformat = FORMAT_HTML;

            $page = page_service::create_page($create, $userid);
            $revision = $page->draftrevision;
            $report->pagescreated++;
        } else {
            $update = new stdClass();
            $update->id = $page->id;
            $update->title = $title;
            $update->categoryid = $categoryid;
            $update->contenttype = $contenttype;
            $update->authoritylevel = (int)($data->authoritylevel ?? $page->authoritylevel);
            $update->criticality = $criticality;
            $update->responsiblearea = (string)($data->responsiblearea ?? $page->responsiblearea);
            $update->requiredreading = (int)($data->requiredreading ?? $page->requiredreading);
            $update->aiaccess = $aiaccess;
            $update->language = (string)($data->language ?? $page->language);
            $update->sortorder = (int)($data->sortorder ?? $page->sortorder);
            $update->summary = (string)($data->summary ?? $page->summary);
            $update->timemodified = $now;
            $update->modifiedby = $userid;
            $DB->update_record('local_handbook_page', $update);
            $page = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);

            $revision = page_service::get_working_revision((int)$page->id);
            if ($revision && !in_array($revision->status, page_service::EDITABLE_STATUSES, true)) {
                $report->errors[] = "pages[$index] ($slug): working revision is in "
                    . "'{$revision->status}' and was not touched";
                $report->pagesupdated++;
                return;
            }

            // No version churn on repeated imports: when the seed content is
            // identical to the published content and no draft exists, only
            // the metadata update above applies.
            if (!$revision && $page->publishedrevisionid) {
                $publishedhash = (string)$DB->get_field('local_handbook_revision', 'contenthash',
                    ['id' => $page->publishedrevisionid]);
                if ($publishedhash === sha1($content)) {
                    $report->pagesupdated++;
                    return;
                }
            }

            if (!$revision) {
                $revision = page_service::create_revision_draft($page, $userid);
            }
            page_service::update_draft($revision, $content, FORMAT_HTML,
                'Seed import ' . userdate($now, '%Y-%m-%d'), $userid);
            $report->pagesupdated++;
        }

        if ($publish) {
            $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
            page_service::direct_publish($revision, $userid);
            $report->pagespublished++;
        }
    }

    /**
     * Create one typed relation (idempotent).
     *
     * @param stdClass $data Seed entry.
     * @param int $userid Acting user.
     * @param stdClass $report Report to update.
     * @param int $index Entry position for error messages.
     * @return void
     */
    private static function import_relation(stdClass $data, int $userid, stdClass $report, int $index): void {
        global $DB;

        $sourceslug = trim((string)($data->source ?? ''));
        $targetslug = trim((string)($data->target ?? ''));
        $type = trim((string)($data->type ?? ''));
        if ($sourceslug === '' || $targetslug === '' || $type === '') {
            $report->errors[] = "relations[$index]: missing source, type or target";
            return;
        }

        $sourceid = (int)$DB->get_field('local_handbook_page', 'id', ['slug' => $sourceslug]);
        $targetid = (int)$DB->get_field('local_handbook_page', 'id', ['slug' => $targetslug]);
        if (!$sourceid || !$targetid) {
            $report->errors[] = "relations[$index]: unknown page '"
                . (!$sourceid ? $sourceslug : $targetslug) . "'";
            return;
        }

        $exists = $DB->record_exists('local_handbook_relation', [
            'sourcepageid' => $sourceid,
            'targetpageid' => $targetid,
            'relationtype' => $type,
        ]);
        if ($exists) {
            return;
        }

        $DB->insert_record('local_handbook_relation', (object)[
            'sourcepageid' => $sourceid,
            'targetpageid' => $targetid,
            'relationtype' => $type,
            'sortorder' => (int)($data->sortorder ?? 0),
            'timecreated' => time(),
            'createdby' => $userid,
        ]);
        $report->relationscreated++;
    }
}
