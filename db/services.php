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
 * External functions and services for local_handbook (specification 17).
 *
 * The prebuilt service is restricted: administrators authorise specific
 * service-account users. There is deliberately NO publish function; the
 * API's authority ends at submitting drafts for human review (17.3).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    // Read functions (17.2).
    'local_handbook_list_categories' => [
        'classname' => 'local_handbook\external\list_categories',
        'description' => 'List handbook categories.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_list_pages' => [
        'classname' => 'local_handbook\external\list_pages',
        'description' => 'List handbook pages (summaries, no content).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_get_page' => [
        'classname' => 'local_handbook\external\get_page',
        'description' => 'Get one page with its published content (AI-access rules apply).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_search_pages' => [
        'classname' => 'local_handbook\external\search_pages',
        'description' => 'Search published pages by title, summary and text.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_list_page_revisions' => [
        'classname' => 'local_handbook\external\list_page_revisions',
        'description' => 'List a page\'s revisions (metadata only).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_get_revision' => [
        'classname' => 'local_handbook\external\get_revision',
        'description' => 'Get one revision including content when permitted.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_list_changes' => [
        'classname' => 'local_handbook\external\list_changes',
        'description' => 'Incremental change listing for synchronisation.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_list_relations' => [
        'classname' => 'local_handbook\external\list_relations',
        'description' => 'Typed relations of a page, both directions.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],

    // Findings (17.2, 17.3, 19). Advisory only, never change content.
    'local_handbook_list_findings' => [
        'classname' => 'local_handbook\external\list_findings',
        'description' => 'List quality findings (default: open and under review).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_create_finding' => [
        'classname' => 'local_handbook\external\create_finding',
        'description' => 'Create an advisory quality finding linked to one or more pages.',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],

    // Draft functions (17.3). No publish function by design.
    'local_handbook_create_page_draft' => [
        'classname' => 'local_handbook\external\create_page_draft',
        'description' => 'Create a new page with its first draft revision (unpublished).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:edit',
    ],
    'local_handbook_create_revision_draft' => [
        'classname' => 'local_handbook\external\create_revision_draft',
        'description' => 'Create a draft revision based on the published one.',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:edit',
    ],
    'local_handbook_update_draft' => [
        'classname' => 'local_handbook\external\update_draft',
        'description' => 'Update a draft revision (optimistic concurrency required).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:edit',
    ],
    'local_handbook_submit_draft_for_review' => [
        'classname' => 'local_handbook\external\submit_draft_for_review',
        'description' => 'Submit a draft revision for human review.',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:edit',
    ],
];

$services = [
    'Institutional Handbook API' => [
        'functions' => array_keys($functions),
        'shortname' => 'local_handbook_api',
        'restrictedusers' => 1,
        'enabled' => 1,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
