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

namespace local_handbook\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_handbook.
 *
 * Personal data handled:
 * - Required-reading acknowledgements: exported and deleted on request.
 * - Editorial attribution (who created/modified/reviewed/approved/published
 *   institutional content): exported, but retained on deletion requests as
 *   part of the institution's editorial audit record (legitimate-interest
 *   retention; the content itself is institutional, not personal).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection to extend.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_handbook_category', [
            'createdby' => 'privacy:metadata:local_handbook_category',
            'modifiedby' => 'privacy:metadata:local_handbook_category',
        ], 'privacy:metadata:local_handbook_category');

        $collection->add_database_table('local_handbook_page', [
            'owneruserid' => 'privacy:metadata:local_handbook_page:owneruserid',
            'approveruserid' => 'privacy:metadata:local_handbook_page',
            'createdby' => 'privacy:metadata:local_handbook_page',
            'modifiedby' => 'privacy:metadata:local_handbook_page',
        ], 'privacy:metadata:local_handbook_page');

        $collection->add_database_table('local_handbook_revision', [
            'createdby' => 'privacy:metadata:local_handbook_revision:createdby',
            'modifiedby' => 'privacy:metadata:local_handbook_revision:modifiedby',
            'reviewedby' => 'privacy:metadata:local_handbook_revision',
            'approvedby' => 'privacy:metadata:local_handbook_revision',
            'publishedby' => 'privacy:metadata:local_handbook_revision:publishedby',
        ], 'privacy:metadata:local_handbook_revision');

        $collection->add_database_table('local_handbook_ack', [
            'userid' => 'privacy:metadata:local_handbook_ack:userid',
            'revisionid' => 'privacy:metadata:local_handbook_ack',
            'timeacknowledged' => 'privacy:metadata:local_handbook_ack:timeacknowledged',
        ], 'privacy:metadata:local_handbook_ack');

        $collection->add_database_table('local_handbook_finding', [
            'createdby' => 'privacy:metadata:local_handbook_finding',
            'assigneduserid' => 'privacy:metadata:local_handbook_finding',
            'resolvedby' => 'privacy:metadata:local_handbook_finding',
        ], 'privacy:metadata:local_handbook_finding');

        $collection->add_database_table('local_handbook_readreceipt', [
            'userid' => 'privacy:metadata:local_handbook_readreceipt:userid',
            'revisionid' => 'privacy:metadata:local_handbook_readreceipt',
            'timecompleted' => 'privacy:metadata:local_handbook_readreceipt:timecompleted',
        ], 'privacy:metadata:local_handbook_readreceipt');

        $collection->add_database_table('local_handbook_qattempt', [
            'userid' => 'privacy:metadata:local_handbook_qattempt:userid',
            'pageid' => 'privacy:metadata:local_handbook_qattempt',
            'passed' => 'privacy:metadata:local_handbook_qattempt',
            'timecreated' => 'privacy:metadata:local_handbook_qattempt:timecreated',
        ], 'privacy:metadata:local_handbook_qattempt');

        $collection->add_database_table('local_handbook_readerhide', [
            'userid' => 'privacy:metadata:local_handbook_readerhide:userid',
            'note' => 'privacy:metadata:local_handbook_readerhide:note',
            'createdby' => 'privacy:metadata:local_handbook_readerhide',
        ], 'privacy:metadata:local_handbook_readerhide');

        return $collection;
    }

    /**
     * Contexts holding personal data for a user (system only).
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $hasdata = $DB->record_exists('local_handbook_ack', ['userid' => $userid])
            || $DB->record_exists('local_handbook_readreceipt', ['userid' => $userid])
            || $DB->record_exists('local_handbook_qattempt', ['userid' => $userid])
            || $DB->record_exists('local_handbook_readerhide', ['userid' => $userid])
            || $DB->record_exists('local_handbook_revision', ['createdby' => $userid])
            || $DB->record_exists('local_handbook_page', ['owneruserid' => $userid])
            || $DB->record_exists('local_handbook_finding', ['createdby' => $userid]);

        if ($hasdata) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users with personal data in a context.
     *
     * @param userlist $userlist Target userlist.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }

        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_handbook_ack}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_handbook_readreceipt}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_handbook_qattempt}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_handbook_readerhide}', []);
        $userlist->add_from_sql('createdby', 'SELECT createdby FROM {local_handbook_revision}', []);
        $userlist->add_from_sql('owneruserid',
            'SELECT owneruserid FROM {local_handbook_page} WHERE owneruserid > 0', []);
        $userlist->add_from_sql('createdby', 'SELECT createdby FROM {local_handbook_finding}', []);
    }

    /**
     * Export a user's handbook data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        $syscontext = context_system::instance();

        $hassystem = false;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $hassystem = true;
            }
        }
        if (!$hassystem) {
            return;
        }

        $subcontext = [get_string('pluginname', 'local_handbook')];

        // Acknowledgements.
        $sql = "SELECT a.id, a.timeacknowledged, a.confirmationversion,
                       p.title, p.slug, r.versionnumber
                  FROM {local_handbook_ack} a
                  JOIN {local_handbook_page} p ON p.id = a.pageid
                  JOIN {local_handbook_revision} r ON r.id = a.revisionid
                 WHERE a.userid = :userid
              ORDER BY a.timeacknowledged ASC";
        $acks = [];
        foreach ($DB->get_records_sql($sql, ['userid' => $userid]) as $ack) {
            $acks[] = (object)[
                'page' => $ack->title,
                'slug' => $ack->slug,
                'acknowledgedversion' => (int)$ack->versionnumber,
                'confirmationwordingversion' => (int)$ack->confirmationversion,
                'timeacknowledged' => transform::datetime($ack->timeacknowledged),
            ];
        }
        if ($acks) {
            writer::with_context($syscontext)->export_data(
                array_merge($subcontext, [get_string('privacy:acknowledgementspath', 'local_handbook')]),
                (object)['acknowledgements' => $acks]);
        }

        // Reading-completion receipts (reading paths / mark-as-read).
        $sql = "SELECT rr.id, rr.timecompleted, rr.completionmethod,
                       p.title, p.slug, r.versionnumber
                  FROM {local_handbook_readreceipt} rr
                  JOIN {local_handbook_page} p ON p.id = rr.pageid
                  JOIN {local_handbook_revision} r ON r.id = rr.revisionid
                 WHERE rr.userid = :userid
              ORDER BY rr.timecompleted ASC";
        $receipts = [];
        foreach ($DB->get_records_sql($sql, ['userid' => $userid]) as $receipt) {
            $receipts[] = (object)[
                'page' => $receipt->title,
                'slug' => $receipt->slug,
                'readversion' => (int)$receipt->versionnumber,
                'method' => $receipt->completionmethod,
                'timecompleted' => transform::datetime($receipt->timecompleted),
            ];
        }
        if ($receipts) {
            writer::with_context($syscontext)->export_data(
                array_merge($subcontext, [get_string('privacy:receiptspath', 'local_handbook')]),
                (object)['readreceipts' => $receipts]);
        }

        // Comprehension-test attempts.
        $sql = "SELECT qa.id, qa.ncorrect, qa.ntotal, qa.passed, qa.timecreated, p.title
                  FROM {local_handbook_qattempt} qa
                  JOIN {local_handbook_page} p ON p.id = qa.pageid
                 WHERE qa.userid = :userid
              ORDER BY qa.timecreated ASC";
        $attempts = [];
        foreach ($DB->get_records_sql($sql, ['userid' => $userid]) as $attempt) {
            $attempts[] = (object)[
                'page' => $attempt->title,
                'score' => (int)$attempt->ncorrect . '/' . (int)$attempt->ntotal,
                'passed' => transform::yesno($attempt->passed),
                'timecreated' => transform::datetime($attempt->timecreated),
            ];
        }
        if ($attempts) {
            writer::with_context($syscontext)->export_data(
                array_merge($subcontext, [get_string('privacy:attemptspath', 'local_handbook')]),
                (object)['attempts' => $attempts]);
        }

        // Reading-dashboard hide entry (staff on leave).
        if ($hide = $DB->get_record('local_handbook_readerhide', ['userid' => $userid])) {
            writer::with_context($syscontext)->export_data(
                array_merge($subcontext, [get_string('privacy:readerhidepath', 'local_handbook')]),
                (object)[
                    'note' => $hide->note,
                    'timecreated' => transform::datetime($hide->timecreated),
                ]);
        }

        // Editorial attribution (summary of authored revisions).
        $sql = "SELECT r.id, r.versionnumber, r.status, r.timecreated, p.title
                  FROM {local_handbook_revision} r
                  JOIN {local_handbook_page} p ON p.id = r.pageid
                 WHERE r.createdby = :userid
              ORDER BY r.timecreated ASC";
        $authored = [];
        foreach ($DB->get_records_sql($sql, ['userid' => $userid]) as $revision) {
            $authored[] = (object)[
                'page' => $revision->title,
                'version' => (int)$revision->versionnumber,
                'status' => $revision->status,
                'timecreated' => transform::datetime($revision->timecreated),
            ];
        }
        if ($authored) {
            writer::with_context($syscontext)->export_data(
                array_merge($subcontext, [get_string('privacy:authoredpath', 'local_handbook')]),
                (object)['revisions' => $authored]);
        }
    }

    /**
     * Delete all users' data in a context (acknowledgements only;
     * editorial attribution is retained as institutional audit record).
     *
     * @param context $context Target context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context instanceof context_system) {
            $DB->delete_records('local_handbook_ack');
            $DB->delete_records('local_handbook_readreceipt');
            $DB->delete_records('local_handbook_qattempt');
            $DB->delete_records('local_handbook_readerhide');
        }
    }

    /**
     * Delete one user's data (acknowledgements only).
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $DB->delete_records('local_handbook_ack',
                    ['userid' => (int)$contextlist->get_user()->id]);
                $DB->delete_records('local_handbook_readreceipt',
                    ['userid' => (int)$contextlist->get_user()->id]);
                $DB->delete_records('local_handbook_qattempt',
                    ['userid' => (int)$contextlist->get_user()->id]);
                $DB->delete_records('local_handbook_readerhide',
                    ['userid' => (int)$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Delete data for multiple users in a context (acknowledgements only).
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        if (!$userlist->get_context() instanceof context_system) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_handbook_ack', "userid $insql", $params);
        $DB->delete_records_select('local_handbook_readreceipt', "userid $insql", $params);
        $DB->delete_records_select('local_handbook_qattempt', "userid $insql", $params);
        $DB->delete_records_select('local_handbook_readerhide', "userid $insql", $params);
    }
}
