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
use core_external\external_single_structure;
use core_external\external_value;
use local_handbook\local\service\page_service;
use moodle_exception;
use stdClass;

/**
 * Shared logic for the handbook external functions (specification 17).
 *
 * AI-access rules (17.4) are enforced here for every API caller:
 * - excluded pages are omitted from lists and denied on direct access
 *   without revealing content;
 * - metadata_only pages return metadata but never body content;
 * - draft writes are refused for excluded and metadata_only pages.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Capability check for read functions.
     *
     * @param context_system $context System context.
     * @return void
     */
    public static function require_read(context_system $context): void {
        require_capability('local/handbook:apiaccess', $context);
        require_capability('local/handbook:view', $context);
    }

    /**
     * Capability check for draft-write functions.
     *
     * @param context_system $context System context.
     * @return void
     */
    public static function require_write(context_system $context): void {
        self::require_read($context);
        require_capability('local/handbook:edit', $context);
    }

    /**
     * Resolve a page by numeric id or slug.
     *
     * @param string $identifier Page id or slug.
     * @return stdClass Page record.
     */
    public static function get_page_by_identifier(string $identifier): stdClass {
        global $DB;

        if (ctype_digit($identifier)) {
            $page = $DB->get_record('local_handbook_page', ['id' => (int)$identifier]);
        } else {
            $page = $DB->get_record('local_handbook_page', ['slug' => $identifier]);
        }
        if (!$page) {
            throw new moodle_exception('errorpagenotfound', 'local_handbook');
        }
        return $page;
    }

    /**
     * Deny access to excluded pages with a clear, content-free error.
     *
     * @param stdClass $page Page record.
     * @return void
     */
    public static function assert_not_excluded(stdClass $page): void {
        if ($page->aiaccess === 'excluded') {
            throw new moodle_exception('errorexcludedpage', 'local_handbook');
        }
    }

    /**
     * Refuse draft writes on pages whose content is not API-readable.
     *
     * @param stdClass $page Page record.
     * @return void
     */
    public static function assert_writable(stdClass $page): void {
        self::assert_not_excluded($page);
        if ($page->aiaccess === 'metadata_only') {
            throw new moodle_exception('errormetadataonly', 'local_handbook');
        }
    }

    /**
     * Whether body content may be returned for this page.
     *
     * @param stdClass $page Page record.
     * @return bool
     */
    public static function content_allowed(stdClass $page): bool {
        return $page->aiaccess === 'full';
    }

    /**
     * Structure of a page summary (no body content).
     *
     * @return external_single_structure
     */
    public static function page_summary_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Page id'),
            'slug' => new external_value(PARAM_ALPHANUMEXT, 'Stable URL slug'),
            'title' => new external_value(PARAM_TEXT, 'Title'),
            'summary' => new external_value(PARAM_RAW, 'Short summary'),
            'categoryid' => new external_value(PARAM_INT, 'Category id'),
            'contenttype' => new external_value(PARAM_ALPHANUMEXT, 'Content type key'),
            'authoritylevel' => new external_value(PARAM_INT, 'Authority level 1-6'),
            'criticality' => new external_value(PARAM_ALPHANUMEXT, 'Criticality key'),
            'requiredreading' => new external_value(PARAM_BOOL, 'Required reading'),
            'aiaccess' => new external_value(PARAM_ALPHANUMEXT, 'AI access level'),
            'language' => new external_value(PARAM_TEXT, 'Content language'),
            'responsiblearea' => new external_value(PARAM_TEXT, 'Responsible area'),
            'effectivedate' => new external_value(PARAM_INT, 'Effective date (0 = unset)'),
            'reviewdate' => new external_value(PARAM_INT, 'Next review date (0 = unset)'),
            'archived' => new external_value(PARAM_BOOL, 'Archived'),
            'timemodified' => new external_value(PARAM_INT, 'Page last modification time'),
            'publishedversion' => new external_value(PARAM_INT, 'Published version number (0 = none)'),
            'timepublished' => new external_value(PARAM_INT, 'Publication time of the published revision (0 = none)'),
            'contenthash' => new external_value(PARAM_ALPHANUMEXT, 'SHA1 of the published content (empty = none)'),
        ]);
    }

    /**
     * Export a page summary. Accepts records pre-joined with the published
     * revision (pubversion/pubtime/pubhash) or plain page records.
     *
     * @param stdClass $page Page record, optionally with join fields.
     * @return array
     */
    public static function export_page_summary(stdClass $page): array {
        global $DB;

        if (!property_exists($page, 'pubversion')) {
            $page->pubversion = 0;
            $page->pubtime = 0;
            $page->pubhash = '';
            if ($page->publishedrevisionid) {
                $revision = $DB->get_record('local_handbook_revision',
                    ['id' => $page->publishedrevisionid], 'id, versionnumber, timepublished, contenthash');
                if ($revision) {
                    $page->pubversion = (int)$revision->versionnumber;
                    $page->pubtime = (int)$revision->timepublished;
                    $page->pubhash = $revision->contenthash;
                }
            }
        }

        return [
            'id' => (int)$page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'summary' => (string)$page->summary,
            'categoryid' => (int)$page->categoryid,
            'contenttype' => $page->contenttype,
            'authoritylevel' => (int)$page->authoritylevel,
            'criticality' => $page->criticality,
            'requiredreading' => (bool)$page->requiredreading,
            'aiaccess' => $page->aiaccess,
            'language' => $page->language,
            'responsiblearea' => (string)$page->responsiblearea,
            'effectivedate' => (int)$page->effectivedate,
            'reviewdate' => (int)$page->reviewdate,
            'archived' => (bool)$page->archived,
            'timemodified' => (int)$page->timemodified,
            'publishedversion' => (int)($page->pubversion ?? 0),
            'timepublished' => (int)($page->pubtime ?? 0),
            'contenthash' => (string)($page->pubhash ?? ''),
        ];
    }

    /**
     * Structure of a revision record.
     *
     * @param bool $withcontent Include content fields.
     * @return external_single_structure
     */
    public static function revision_structure(bool $withcontent): external_single_structure {
        $fields = [
            'id' => new external_value(PARAM_INT, 'Revision id'),
            'pageid' => new external_value(PARAM_INT, 'Page id'),
            'versionnumber' => new external_value(PARAM_INT, 'Sequential version number'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Workflow status'),
            'baserevisionid' => new external_value(PARAM_INT, 'Revision this draft is based on (0 = none)'),
            'changesummary' => new external_value(PARAM_RAW, 'Change summary'),
            'contenthash' => new external_value(PARAM_ALPHANUMEXT, 'SHA1 of the content'),
            'effectivefrom' => new external_value(PARAM_INT, 'Effective date (0 = unset)'),
            'timecreated' => new external_value(PARAM_INT, 'Creation time'),
            'timemodified' => new external_value(PARAM_INT,
                'Last modification time; pass back as expectedtimemodified on update'),
            'timepublished' => new external_value(PARAM_INT, 'Publication time (0 = not published)'),
            'contentincluded' => new external_value(PARAM_BOOL, 'Whether content fields are populated'),
        ];
        if ($withcontent) {
            $fields['content'] = new external_value(PARAM_RAW, 'Content HTML (empty when not permitted)');
            $fields['plaintext'] = new external_value(PARAM_RAW, 'Normalized plain text (empty when not permitted)');
        }
        return new external_single_structure($fields);
    }

    /**
     * Export a revision record.
     *
     * @param stdClass $revision Revision record.
     * @param bool $withcontent Include content fields in the export.
     * @param bool $contentallowed Whether the caller may see the content.
     * @return array
     */
    public static function export_revision(stdClass $revision, bool $withcontent,
            bool $contentallowed = false): array {
        $export = [
            'id' => (int)$revision->id,
            'pageid' => (int)$revision->pageid,
            'versionnumber' => (int)$revision->versionnumber,
            'status' => $revision->status,
            'baserevisionid' => (int)$revision->baserevisionid,
            'changesummary' => (string)$revision->changesummary,
            'contenthash' => (string)$revision->contenthash,
            'effectivefrom' => (int)$revision->effectivefrom,
            'timecreated' => (int)$revision->timecreated,
            'timemodified' => (int)$revision->timemodified,
            'timepublished' => (int)$revision->timepublished,
            'contentincluded' => $withcontent && $contentallowed,
        ];
        if ($withcontent) {
            $export['content'] = $contentallowed ? (string)$revision->content : '';
            $export['plaintext'] = $contentallowed ? (string)$revision->plaintext : '';
        }
        return $export;
    }

    /**
     * Normalise pagination parameters.
     *
     * @param int $page Zero-based page number.
     * @param int $perpage Requested page size.
     * @return array [$limitfrom, $limitnum]
     */
    public static function paginate(int $page, int $perpage): array {
        $perpage = min(max($perpage, 1), 200);
        $page = max($page, 0);
        return [$page * $perpage, $perpage];
    }
}
