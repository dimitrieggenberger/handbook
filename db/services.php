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

    // Context and working-draft reads (36.6).
    'local_handbook_get_context_index' => [
        'classname' => 'local_handbook\external\get_context_index',
        'description' => 'Compact context index of AI-permitted pages (no content).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_get_working_page' => [
        'classname' => 'local_handbook\external\get_working_page',
        'description' => 'Read a page\'s current working draft without changing state.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:edit',
    ],

    // Change sets (36.4). Draft authority only; no approve/publish function.
    'local_handbook_create_changeset' => [
        'classname' => 'local_handbook\external\create_changeset',
        'description' => 'Create a change set grouping multi-page draft proposals.',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:edit',
    ],
    'local_handbook_get_changeset' => [
        'classname' => 'local_handbook\external\get_changeset',
        'description' => 'Get one change set with its items.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_list_changesets' => [
        'classname' => 'local_handbook\external\list_changesets',
        'description' => 'List change sets with optional status/source filters.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_upsert_changeset_draft' => [
        'classname' => 'local_handbook\external\upsert_changeset_draft',
        'description' => 'Create or update a change set\'s draft for one page (conservative; never publishes).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:edit',
    ],
    'local_handbook_upsert_changeset_metadata' => [
        'classname' => 'local_handbook\external\upsert_changeset_metadata',
        'description' => 'Propose a page metadata (fiche) patch inside a change set (draft only; never applies).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:apiproposemetadata',
    ],
    'local_handbook_upsert_changeset_new_page' => [
        'classname' => 'local_handbook\external\upsert_changeset_new_page',
        'description' => 'Propose a brand-new page inside a change set (draft only; never publishes).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:edit',
    ],
    'local_handbook_upsert_changeset_relations' => [
        'classname' => 'local_handbook\external\upsert_changeset_relations',
        'description' => 'Propose edits to a page\'s outgoing relations inside a change set (draft only).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:apiproposerelations',
    ],
    'local_handbook_list_areas' => [
        'classname' => 'local_handbook\external\list_areas',
        'description' => 'List the controlled vocabulary of responsible areas.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_get_archive_impact' => [
        'classname' => 'local_handbook\external\get_archive_impact',
        'description' => 'Impact of archiving a page: inbound relations, active paths, required reading.',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_validate_changeset' => [
        'classname' => 'local_handbook\external\validate_changeset',
        'description' => 'Validate every item of a change set against current state (read-only).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_upsert_changeset_archive' => [
        'classname' => 'local_handbook\external\upsert_changeset_archive',
        'description' => 'Propose archiving a page inside a change set (draft only; never archives directly).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:apiproposelifecycle',
    ],
    'local_handbook_upsert_changeset_restore' => [
        'classname' => 'local_handbook\external\upsert_changeset_restore',
        'description' => 'Propose restoring an archived page inside a change set (draft only).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:apiproposelifecycle',
    ],
    'local_handbook_upsert_changeset_category' => [
        'classname' => 'local_handbook\external\upsert_changeset_category',
        'description' => 'Propose a category operation (create/update/move/merge/delete_empty) in a change set (draft only).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:apiproposetaxonomy',
    ],
    'local_handbook_upsert_changeset_page_move' => [
        'classname' => 'local_handbook\external\upsert_changeset_page_move',
        'description' => 'Propose moving a page to another category in a change set (draft only).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:apiproposetaxonomy',
    ],
    'local_handbook_get_style_guide' => [
        'classname' => 'local_handbook\external\get_style_guide',
        'description' => 'Handbook content style guide: the hb-* formatting patterns and example HTML (read-only).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_get_question_guide' => [
        'classname' => 'local_handbook\external\get_question_guide',
        'description' => 'Authoring guide for end-of-article comprehension questions: rules, Moodle XML template, and which pages already have questions (read-only; the AI writes XML, a human imports it).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_list_reading_paths' => [
        'classname' => 'local_handbook\external\list_reading_paths',
        'description' => 'List reading paths with item counts (read-only).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_get_reading_path' => [
        'classname' => 'local_handbook\external\get_reading_path',
        'description' => 'Get a reading path\'s complete snapshot (read-only).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_upsert_changeset_reading_path' => [
        'classname' => 'local_handbook\external\upsert_changeset_reading_path',
        'description' => 'Propose a whole reading path (create/update) in a change set (draft only; never applies).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:apiproposepaths',
    ],
    'local_handbook_get_reading_path_coverage' => [
        'classname' => 'local_handbook\external\get_reading_path_coverage',
        'description' => 'Aggregate reading-path coverage and overlap (read-only; no individual completion data).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_audit_reading_paths' => [
        'classname' => 'local_handbook\external\audit_reading_paths',
        'description' => 'Handbook-wide reading-path audit: orphans, review-due, no-required, oversized (read-only).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_recommend_paths_for_page' => [
        'classname' => 'local_handbook\external\recommend_paths_for_page',
        'description' => 'Deterministic reading-path candidates for a page, from relations and category (read-only).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_list_path_recommendations' => [
        'classname' => 'local_handbook\external\list_path_recommendations',
        'description' => 'List advisory reading-path recommendations (read-only).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_get_path_recommendation' => [
        'classname' => 'local_handbook\external\get_path_recommendation',
        'description' => 'Get one advisory reading-path recommendation (read-only).',
        'type' => 'read',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_create_path_recommendation' => [
        'classname' => 'local_handbook\external\create_path_recommendation',
        'description' => 'Record an advisory reading-path recommendation for human triage (never edits a path).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view',
    ],
    'local_handbook_accept_path_recommendation' => [
        'classname' => 'local_handbook\external\accept_path_recommendation',
        'description' => 'Accept a recommendation into a change set as a draft reading-path revision (draft only).',
        'type' => 'write',
        'capabilities' => 'local/handbook:apiaccess, local/handbook:view, local/handbook:apiproposepaths',
    ],
    'local_handbook_submit_changeset_for_review' => [
        'classname' => 'local_handbook\external\submit_changeset_for_review',
        'description' => 'Submit a change set\'s eligible drafts for human review.',
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
