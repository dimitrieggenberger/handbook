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

namespace local_handbook\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: compact context index of AI-permitted pages (spec 36.6).
 *
 * Returns one lightweight record per page — metadata, category path, dates,
 * published hash, whether an editable working draft exists, and typed
 * relations in both directions — but never full content. The agent retrieves
 * full content only for the pages it decides are relevant. Excluded pages are
 * omitted (17.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_context_index extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'includearchived' => new external_value(PARAM_BOOL,
                'Include archived pages', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Build the context index.
     *
     * @param bool $includearchived Include archived pages.
     * @return array
     */
    public static function execute(bool $includearchived = false): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'includearchived' => $includearchived,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        // Category names/parents for the path column.
        $categories = $DB->get_records('local_handbook_category', null, '', 'id, name, parentid');
        $catpath = static function (int $categoryid) use ($categories): string {
            $parts = [];
            $guard = 0;
            $current = $categoryid;
            while ($current && isset($categories[$current]) && $guard++ < 20) {
                array_unshift($parts, format_string($categories[$current]->name));
                $current = (int)$categories[$current]->parentid;
            }
            return implode(' / ', $parts);
        };

        // All non-excluded pages, with the published revision hash/version.
        $where = "p.aiaccess <> :excluded";
        $sqlparams = ['excluded' => 'excluded'];
        if (!$params['includearchived']) {
            $where .= " AND p.archived = 0";
        }
        $sql = "SELECT p.*, r.versionnumber AS pubversion, r.contenthash AS pubhash,
                       r.timepublished AS pubtime
                  FROM {local_handbook_page} p
             LEFT JOIN {local_handbook_revision} r ON r.id = p.publishedrevisionid
                 WHERE {$where}
              ORDER BY p.categoryid ASC, p.sortorder ASC, p.title ASC";
        $pages = $DB->get_records_sql($sql, $sqlparams);

        if (!$pages) {
            return [];
        }

        // Working-draft presence per page (one query).
        $draftpageids = $DB->get_fieldset_select('local_handbook_revision', 'DISTINCT pageid',
            'status IN (:s1, :s2, :s3, :s4)', [
                's1' => 'draft', 's2' => 'in_review', 's3' => 'changes_requested', 's4' => 'approved',
            ]);
        $draftpageids = array_flip(array_map('intval', $draftpageids));

        // Relations across all pages, grouped by page, excluding AI-excluded ends.
        $relations = $DB->get_records_sql(
            "SELECT rel.id, rel.relationtype, rel.sourcepageid, rel.targetpageid,
                    s.slug AS sourceslug, s.aiaccess AS sourceai,
                    t.slug AS targetslug, t.aiaccess AS targetai
               FROM {local_handbook_relation} rel
               JOIN {local_handbook_page} s ON s.id = rel.sourcepageid
               JOIN {local_handbook_page} t ON t.id = rel.targetpageid
           ORDER BY rel.sortorder ASC, rel.id ASC");
        $relbypage = [];
        foreach ($relations as $rel) {
            if ($rel->sourceai === 'excluded' || $rel->targetai === 'excluded') {
                continue;
            }
            $relbypage[(int)$rel->sourcepageid][] = [
                'relationtype' => $rel->relationtype,
                'direction' => 'outgoing',
                'otherpageid' => (int)$rel->targetpageid,
                'otherslug' => $rel->targetslug,
            ];
            $relbypage[(int)$rel->targetpageid][] = [
                'relationtype' => $rel->relationtype,
                'direction' => 'incoming',
                'otherpageid' => (int)$rel->sourcepageid,
                'otherslug' => $rel->sourceslug,
            ];
        }

        $result = [];
        foreach ($pages as $page) {
            $result[] = [
                'id' => (int)$page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'summary' => (string)$page->summary,
                'categoryid' => (int)$page->categoryid,
                'categorypath' => $catpath((int)$page->categoryid),
                'contenttype' => $page->contenttype,
                'authoritylevel' => (int)$page->authoritylevel,
                'criticality' => $page->criticality,
                'responsiblearea' => (string)$page->responsiblearea,
                'requiredreading' => (bool)$page->requiredreading,
                'aiaccess' => $page->aiaccess,
                'language' => $page->language,
                'effectivedate' => (int)$page->effectivedate,
                'reviewdate' => (int)$page->reviewdate,
                'archived' => (bool)$page->archived,
                'publishedrevisionid' => (int)$page->publishedrevisionid,
                'publishedversion' => (int)($page->pubversion ?? 0),
                'contenthash' => (string)($page->pubhash ?? ''),
                'timepublished' => (int)($page->pubtime ?? 0),
                'timemodified' => (int)$page->timemodified,
                'hasworkingdraft' => isset($draftpageids[(int)$page->id]),
                'relations' => $relbypage[(int)$page->id] ?? [],
            ];
        }
        return $result;
    }

    /**
     * Return definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Page id'),
            'slug' => new external_value(PARAM_ALPHANUMEXT, 'Stable slug'),
            'title' => new external_value(PARAM_TEXT, 'Title'),
            'summary' => new external_value(PARAM_RAW, 'Short summary'),
            'categoryid' => new external_value(PARAM_INT, 'Category id'),
            'categorypath' => new external_value(PARAM_TEXT, 'Category path, root first'),
            'contenttype' => new external_value(PARAM_ALPHANUMEXT, 'Content type key'),
            'authoritylevel' => new external_value(PARAM_INT, 'Authority level 1-6'),
            'criticality' => new external_value(PARAM_ALPHANUMEXT, 'Criticality key'),
            'responsiblearea' => new external_value(PARAM_TEXT, 'Responsible area'),
            'requiredreading' => new external_value(PARAM_BOOL, 'Required reading'),
            'aiaccess' => new external_value(PARAM_ALPHANUMEXT, 'AI access level'),
            'language' => new external_value(PARAM_TEXT, 'Content language'),
            'effectivedate' => new external_value(PARAM_INT, 'Effective date (0 = unset)'),
            'reviewdate' => new external_value(PARAM_INT, 'Next review date (0 = unset)'),
            'archived' => new external_value(PARAM_BOOL, 'Archived'),
            'publishedrevisionid' => new external_value(PARAM_INT, 'Published revision id (0 = none)'),
            'publishedversion' => new external_value(PARAM_INT, 'Published version number (0 = none)'),
            'contenthash' => new external_value(PARAM_ALPHANUMEXT, 'SHA1 of the published content'),
            'timepublished' => new external_value(PARAM_INT, 'Publication time (0 = none)'),
            'timemodified' => new external_value(PARAM_INT, 'Page last modification time'),
            'hasworkingdraft' => new external_value(PARAM_BOOL, 'An editable working revision exists'),
            'relations' => new external_multiple_structure(new external_single_structure([
                'relationtype' => new external_value(PARAM_ALPHANUMEXT, 'Relation type key'),
                'direction' => new external_value(PARAM_ALPHA, 'outgoing or incoming'),
                'otherpageid' => new external_value(PARAM_INT, 'The other page id'),
                'otherslug' => new external_value(PARAM_ALPHANUMEXT, 'The other page slug'),
            ])),
        ]));
    }
}
