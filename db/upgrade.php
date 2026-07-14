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

    return true;
}
