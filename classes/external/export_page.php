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
use local_handbook\local\service\changeset_service;
use local_handbook\local\service\page_service;

/**
 * External function: one page as a complete, portable record, so a content
 * repository can mirror the handbook (the round trip, 0.43.0).
 *
 * Returns the page fiche, its category slug, its audience keys and, when the
 * page is published and AI-readable (aiaccess full), the published content
 * exactly as stored (with @@PLUGINFILE@@ references) together with every
 * file of that revision, base64-encoded. metadata_only pages come back
 * without content or files; excluded pages are refused. Read only.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_page extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Page id or slug'),
        ]);
    }

    /**
     * Export one page.
     *
     * @param string $identifier Page id or slug.
     * @return array
     */
    public static function execute(string $identifier): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['identifier' => $identifier]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $page = helper::get_page_by_identifier($params['identifier']);
        helper::assert_not_excluded($page);

        $contentallowed = helper::content_allowed($page);
        $revision = $page->publishedrevisionid
            ? $DB->get_record('local_handbook_revision', ['id' => $page->publishedrevisionid])
            : null;

        $files = [];
        if ($revision && $contentallowed) {
            $fs = get_file_storage();
            foreach ($fs->get_area_files($context->id, 'local_handbook', 'revision', $revision->id,
                    'filepath, filename', false) as $file) {
                $files[] = [
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                    'mimetype' => (string)$file->get_mimetype(),
                    'filesize' => (int)$file->get_filesize(),
                    'contenthash' => $file->get_contenthash(),
                    'content' => base64_encode($file->get_content()),
                ];
            }
        }

        $publishedby = '';
        if ($revision && $revision->publishedby) {
            $user = \core_user::get_user((int)$revision->publishedby);
            $publishedby = $user ? fullname($user) : '';
        }
        $hasworkingdraft = (bool)page_service::get_working_revision((int)$page->id);

        return [
            'page' => helper::export_page_summary($page),
            'categoryslug' => (string)$DB->get_field('local_handbook_category', 'slug',
                ['id' => $page->categoryid]),
            'audiences' => changeset_service::page_audience_keys((int)$page->id),
            'hasworkingdraft' => $hasworkingdraft,
            'published' => $revision ? [helper::export_revision($revision, true, $contentallowed)] : [],
            'publishedby' => $publishedby,
            'files' => $files,
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'page' => helper::page_summary_structure(),
            'categoryslug' => new external_value(PARAM_ALPHANUMEXT, 'Slug of the page category'),
            'audiences' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Audience key'),
                'Audience keys the page is tagged with (none = internal staff article)'),
            'hasworkingdraft' => new external_value(PARAM_BOOL,
                'Whether an unpublished revision (draft, in review or approved) exists'),
            'published' => new external_multiple_structure(helper::revision_structure(true),
                'The published revision (empty when the page is not published)'),
            'publishedby' => new external_value(PARAM_TEXT, 'Who published that revision'),
            'files' => new external_multiple_structure(new external_single_structure([
                'filepath' => new external_value(PARAM_PATH, 'File path inside the revision'),
                'filename' => new external_value(PARAM_FILE, 'File name'),
                'mimetype' => new external_value(PARAM_RAW, 'MIME type'),
                'filesize' => new external_value(PARAM_INT, 'Size in bytes'),
                'contenthash' => new external_value(PARAM_ALPHANUMEXT, 'SHA1 of the file content'),
                'content' => new external_value(PARAM_RAW, 'File content, base64'),
            ]), 'Files of the published revision (empty when content is not permitted)'),
        ]);
    }
}
