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
 * Upgrade steps for local_handbook.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_handbook database schema.
 *
 * @param int $oldversion Version we are upgrading from.
 * @return bool
 */
function xmldb_local_handbook_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071404) {
        // Phase 4 (spec 15, 16, 20.5-20.7): reading paths and acknowledgements.
        $table = new xmldb_table('local_handbook_path');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('slug', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT);
        $table->add_field('descriptionformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('audiencejson', XMLDB_TYPE_TEXT);
        $table->add_field('schoolyear', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('quizcmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
        $table->add_key('modifiedby', XMLDB_KEY_FOREIGN, ['modifiedby'], 'user', ['id']);
        $table->add_index('slug', XMLDB_INDEX_UNIQUE, ['slug']);
        $table->add_index('active', XMLDB_INDEX_NOTUNIQUE, ['active']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_handbook_pathitem');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('pathid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('pageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('sectionname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('required', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('quizcmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('pathid', XMLDB_KEY_FOREIGN, ['pathid'], 'local_handbook_path', ['id']);
        $table->add_key('pageid', XMLDB_KEY_FOREIGN, ['pageid'], 'local_handbook_page', ['id']);
        $table->add_index('pathorder', XMLDB_INDEX_NOTUNIQUE, ['pathid', 'sortorder']);
        $table->add_index('pathpage', XMLDB_INDEX_UNIQUE, ['pathid', 'pageid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_handbook_ack');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('pageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('revisionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('pathid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('confirmationversion', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timeacknowledged', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('pageid', XMLDB_KEY_FOREIGN, ['pageid'], 'local_handbook_page', ['id']);
        $table->add_key('revisionid', XMLDB_KEY_FOREIGN, ['revisionid'], 'local_handbook_revision', ['id']);
        $table->add_index('userrevision', XMLDB_INDEX_UNIQUE, ['userid', 'revisionid']);
        $table->add_index('userpage', XMLDB_INDEX_NOTUNIQUE, ['userid', 'pageid']);
        $table->add_index('pagetime', XMLDB_INDEX_NOTUNIQUE, ['pageid', 'timeacknowledged']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071404, 'local', 'handbook');
    }

    if ($oldversion < 2026071405) {
        // Phase 5 (spec 19, 20.8-20.9): quality findings.
        $table = new xmldb_table('local_handbook_finding');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('findingtype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL);
        $table->add_field('severity', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'medium');
        $table->add_field('confidence', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'medium');
        $table->add_field('status', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'open');
        $table->add_field('summary', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('explanation', XMLDB_TYPE_TEXT);
        $table->add_field('recommendation', XMLDB_TYPE_TEXT);
        $table->add_field('source', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'human');
        $table->add_field('externalreference', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('assigneduserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('resolutionnote', XMLDB_TYPE_TEXT);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('resolvedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeresolved', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
        $table->add_key('modifiedby', XMLDB_KEY_FOREIGN, ['modifiedby'], 'user', ['id']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('typestatus', XMLDB_INDEX_NOTUNIQUE, ['findingtype', 'status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_handbook_findpage');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('findingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('pageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('revisionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('anchor', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('excerpt', XMLDB_TYPE_TEXT);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('findingid', XMLDB_KEY_FOREIGN, ['findingid'], 'local_handbook_finding', ['id']);
        // No single-column index on pageid: the foreign key already provides
        // one, and a duplicate index collides during upgrade.
        $table->add_key('pageid', XMLDB_KEY_FOREIGN, ['pageid'], 'local_handbook_page', ['id']);
        $table->add_index('findingpage', XMLDB_INDEX_NOTUNIQUE, ['findingid', 'pageid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071405, 'local', 'handbook');
    }

    if ($oldversion < 2026071413) {
        // Category icons (Font Awesome solid class, empty = default folder).
        $table = new xmldb_table('local_handbook_category');
        $field = new xmldb_field('icon', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, '',
            'visible');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026071413, 'local', 'handbook');
    }

    if ($oldversion < 2026071419) {
        // Handbook AI change sets (spec 36): public authorship + grouped
        // multi-page draft proposals.

        // Staff-facing published author on the revision (0 = fall back to
        // owner/responsible area; set at approval, never from createdby).
        $table = new xmldb_table('local_handbook_revision');
        $field = new xmldb_field('authoruserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL,
            null, '0', 'publishedby');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('local_handbook_changeset');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('instructionsummary', XMLDB_TYPE_TEXT);
        $table->add_field('status', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'draft');
        $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'human');
        $table->add_field('externalreference', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('sponsoruserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('submittedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
        $table->add_key('modifiedby', XMLDB_KEY_FOREIGN, ['modifiedby'], 'user', ['id']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('sourcestatus', XMLDB_INDEX_NOTUNIQUE, ['source', 'status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_handbook_changeitem');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('changesetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('pageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('revisionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('itemstatus', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'draft');
        $table->add_field('changesummary', XMLDB_TYPE_TEXT);
        $table->add_field('conflictnote', XMLDB_TYPE_TEXT);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('changesetid', XMLDB_KEY_FOREIGN, ['changesetid'], 'local_handbook_changeset', ['id']);
        $table->add_key('pageid', XMLDB_KEY_FOREIGN, ['pageid'], 'local_handbook_page', ['id']);
        $table->add_index('changesetorder', XMLDB_INDEX_NOTUNIQUE, ['changesetid', 'sortorder']);
        $table->add_index('changesetpage', XMLDB_INDEX_UNIQUE, ['changesetid', 'pageid']);
        $table->add_index('revisionid', XMLDB_INDEX_NOTUNIQUE, ['revisionid']);
        $table->add_index('itemstatus', XMLDB_INDEX_NOTUNIQUE, ['itemstatus']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071419, 'local', 'handbook');
    }

    if ($oldversion < 2026071502) {
        // Phase 0: make change items polymorphic so a change set can carry more
        // than a page content revision (metadata/taxonomy/lifecycle/glossary
        // proposals arrive in later phases). Existing rows become page_revision.
        $table = new xmldb_table('local_handbook_changeitem');

        $kind = new xmldb_field('kind', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL,
            null, 'page_revision', 'changesetid');
        if (!$dbman->field_exists($table, $kind)) {
            $dbman->add_field($table, $kind);
        }

        $tempkey = new xmldb_field('tempkey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL,
            null, '', 'pageid');
        if (!$dbman->field_exists($table, $tempkey)) {
            $dbman->add_field($table, $tempkey);
        }

        $payloadjson = new xmldb_field('payloadjson', XMLDB_TYPE_TEXT, null, null, null,
            null, null, 'itemstatus');
        if (!$dbman->field_exists($table, $payloadjson)) {
            $dbman->add_field($table, $payloadjson);
        }

        // pageid may now be 0 for page-less items (new entities keyed by
        // tempkey); it is always written explicitly, so it needs no default —
        // and Moodle refuses to modify a field that is part of an index.

        // Relax the one-item-per-page rule: several items may touch one page.
        $oldunique = new xmldb_index('changesetpage', XMLDB_INDEX_UNIQUE, ['changesetid', 'pageid']);
        if ($dbman->index_exists($table, $oldunique)) {
            $dbman->drop_index($table, $oldunique);
        }
        $newindex = new xmldb_index('changesetpage', XMLDB_INDEX_NOTUNIQUE, ['changesetid', 'pageid']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        $kindindex = new xmldb_index('changesetkind', XMLDB_INDEX_NOTUNIQUE, ['changesetid', 'kind']);
        if (!$dbman->index_exists($table, $kindindex)) {
            $dbman->add_index($table, $kindindex);
        }

        upgrade_plugin_savepoint(true, 2026071502, 'local', 'handbook');
    }

    if ($oldversion < 2026071504) {
        // Phase 1: retired page slugs that still resolve (spec 7.3).
        $table = new xmldb_table('local_handbook_pagealias');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('pageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('oldslug', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('pageid', XMLDB_KEY_FOREIGN, ['pageid'], 'local_handbook_page', ['id']);
        $table->add_index('oldslug', XMLDB_INDEX_UNIQUE, ['oldslug']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071504, 'local', 'handbook');
    }

    if ($oldversion < 2026071505) {
        // Phase 1: controlled vocabulary of responsible areas (spec 9).
        $table = new xmldb_table('local_handbook_area');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('areakey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('areakey', XMLDB_INDEX_UNIQUE, ['areakey']);
        $table->add_index('activesort', XMLDB_INDEX_NOTUNIQUE, ['active', 'sortorder']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Seed the catalogue from the responsible-area values already in use so
        // the vocabulary starts populated rather than empty.
        if (!$DB->count_records('local_handbook_area')) {
            $now = time();
            $sort = 0;
            $seen = [];
            $rs = $DB->get_recordset_sql(
                "SELECT DISTINCT responsiblearea FROM {local_handbook_page}
                  WHERE responsiblearea IS NOT NULL AND responsiblearea <> ''");
            foreach ($rs as $row) {
                $name = trim($row->responsiblearea);
                if ($name === '') {
                    continue;
                }
                $key = \local_handbook\local\service\page_service::slugify($name);
                $candidate = $key;
                $n = 2;
                while (isset($seen[$candidate])) {
                    $candidate = $key . '-' . $n++;
                }
                $seen[$candidate] = true;
                $DB->insert_record('local_handbook_area', (object)[
                    'areakey' => $candidate,
                    'name' => \core_text::substr($name, 0, 255),
                    'active' => 1,
                    'sortorder' => $sort++,
                    'timecreated' => $now,
                    'timemodified' => $now,
                    'createdby' => 0,
                    'modifiedby' => 0,
                ]);
            }
            $rs->close();
        }

        upgrade_plugin_savepoint(true, 2026071505, 'local', 'handbook');
    }

    if ($oldversion < 2026071508) {
        // Phase 2: page archive/restore lifecycle fields (spec 22-24).
        $table = new xmldb_table('local_handbook_page');
        $fields = [
            new xmldb_field('archivereason', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '', 'archived'),
            new xmldb_field('replacementpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'archivereason'),
            new xmldb_field('redirectmode', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, '', 'replacementpageid'),
            new xmldb_field('archivenote', XMLDB_TYPE_TEXT, null, null, null, null, null, 'redirectmode'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026071508, 'local', 'handbook');
    }

    if ($oldversion < 2026071512) {
        // Taxonomy phase 1: category slug aliases + change-set temp references.
        $alias = new xmldb_table('local_handbook_categoryalias');
        $alias->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $alias->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $alias->add_field('oldslug', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $alias->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $alias->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $alias->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $alias->add_key('categoryid', XMLDB_KEY_FOREIGN, ['categoryid'], 'local_handbook_category', ['id']);
        $alias->add_index('oldslug', XMLDB_INDEX_UNIQUE, ['oldslug']);
        if (!$dbman->table_exists($alias)) {
            $dbman->create_table($alias);
        }

        $tempref = new xmldb_table('local_handbook_tempref');
        $tempref->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $tempref->add_field('changesetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $tempref->add_field('tempkey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $tempref->add_field('entitytype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL);
        $tempref->add_field('entityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $tempref->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $tempref->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $tempref->add_key('changesetid', XMLDB_KEY_FOREIGN, ['changesetid'], 'local_handbook_changeset', ['id']);
        $tempref->add_index('changesettempkey', XMLDB_INDEX_UNIQUE, ['changesetid', 'tempkey']);
        if (!$dbman->table_exists($tempref)) {
            $dbman->create_table($tempref);
        }

        upgrade_plugin_savepoint(true, 2026071512, 'local', 'handbook');
    }

    if ($oldversion < 2026071514) {
        // Reading-path product-model fields (spec 6).
        $path = new xmldb_table('local_handbook_path');
        $pathfields = [
            new xmldb_field('pathtype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, '', 'active'),
            new xmldb_field('estimatedminutes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'pathtype'),
            new xmldb_field('reviewdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'estimatedminutes'),
        ];
        foreach ($pathfields as $field) {
            if (!$dbman->field_exists($path, $field)) {
                $dbman->add_field($path, $field);
            }
        }

        $pathitem = new xmldb_table('local_handbook_pathitem');
        $rationale = new xmldb_field('rationale', XMLDB_TYPE_TEXT, null, null, null, null, null, 'quizcmid');
        if (!$dbman->field_exists($pathitem, $rationale)) {
            $dbman->add_field($pathitem, $rationale);
        }

        upgrade_plugin_savepoint(true, 2026071514, 'local', 'handbook');
    }

    return true;
}
