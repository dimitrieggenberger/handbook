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
use core_external\external_single_structure;
use core_external\external_value;
use local_handbook\local\service\page_service;
use moodle_exception;

/**
 * External function: create a new page with its first draft revision.
 *
 * The page is NOT published: it enters the normal editorial workflow.
 * There is no publish function in this service (specification 17.3).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_page_draft extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'title' => new external_value(PARAM_TEXT, 'Page title'),
            'category' => new external_value(PARAM_ALPHANUMEXT, 'Category id or slug'),
            'contenttype' => new external_value(PARAM_ALPHANUMEXT, 'Content type key'),
            'summary' => new external_value(PARAM_RAW, 'Short summary'),
            'content' => new external_value(PARAM_RAW, 'Content HTML (headings start at h2)'),
            'slug' => new external_value(PARAM_RAW, 'Preferred slug (optional)', VALUE_DEFAULT, ''),
            'authoritylevel' => new external_value(PARAM_INT, 'Authority level 1-6', VALUE_DEFAULT, 4),
            'criticality' => new external_value(PARAM_ALPHANUMEXT, 'Criticality key',
                VALUE_DEFAULT, 'operational'),
            'requiredreading' => new external_value(PARAM_BOOL, 'Required reading', VALUE_DEFAULT, false),
            'responsiblearea' => new external_value(PARAM_TEXT, 'Responsible area', VALUE_DEFAULT, ''),
            'language' => new external_value(PARAM_TEXT, 'Content language', VALUE_DEFAULT, 'es'),
        ]);
    }

    /**
     * Create a page and its v1 draft.
     *
     * @param string $title Page title.
     * @param string $category Category id or slug.
     * @param string $contenttype Content type key.
     * @param string $summary Short summary.
     * @param string $content Content HTML.
     * @param string $slug Preferred slug.
     * @param int $authoritylevel Authority level.
     * @param string $criticality Criticality key.
     * @param bool $requiredreading Required reading flag.
     * @param string $responsiblearea Responsible area.
     * @param string $language Content language.
     * @return array
     */
    public static function execute(string $title, string $category, string $contenttype,
            string $summary, string $content, string $slug = '', int $authoritylevel = 4,
            string $criticality = 'operational', bool $requiredreading = false,
            string $responsiblearea = '', string $language = 'es'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'title' => $title,
            'category' => $category,
            'contenttype' => $contenttype,
            'summary' => $summary,
            'content' => $content,
            'slug' => $slug,
            'authoritylevel' => $authoritylevel,
            'criticality' => $criticality,
            'requiredreading' => $requiredreading,
            'responsiblearea' => $responsiblearea,
            'language' => $language,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_write($context);

        if (ctype_digit($params['category'])) {
            $categoryid = (int)$DB->get_field('local_handbook_category', 'id',
                ['id' => (int)$params['category']]);
        } else {
            $categoryid = (int)$DB->get_field('local_handbook_category', 'id',
                ['slug' => $params['category']]);
        }
        if (!$categoryid) {
            throw new moodle_exception('errorcategorynotfound', 'local_handbook');
        }
        if (!in_array($params['contenttype'], page_service::content_types(), true)) {
            throw new moodle_exception('invalidparameter', 'debug', '', null, 'contenttype');
        }
        if (!in_array($params['criticality'], page_service::criticalities(), true)) {
            throw new moodle_exception('invalidparameter', 'debug', '', null, 'criticality');
        }

        $page = page_service::create_page((object)[
            'title' => $params['title'],
            'slug' => $params['slug'],
            'categoryid' => $categoryid,
            'contenttype' => $params['contenttype'],
            'authoritylevel' => min(6, max(1, $params['authoritylevel'])),
            'criticality' => $params['criticality'],
            'requiredreading' => (int)$params['requiredreading'],
            'responsiblearea' => $params['responsiblearea'],
            'language' => $params['language'],
            'summary' => $params['summary'],
            'content' => $params['content'],
            'contentformat' => FORMAT_HTML,
        ]);

        return [
            'page' => helper::export_page_summary(
                $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST)),
            'draft' => helper::export_revision($page->draftrevision, false),
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
            'draft' => helper::revision_structure(false),
        ]);
    }
}
