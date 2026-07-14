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

use context_system;
use core_user;
use moodle_url;
use stdClass;

/**
 * Workflow notifications (specification 21.3).
 *
 * All notifications go through Moodle's Message API, so each user controls
 * delivery (web/email/mobile) in their messaging preferences. Failures are
 * non-fatal: notifying never blocks the underlying workflow action.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_service {

    /**
     * Notify reviewers that a draft was submitted.
     *
     * @param stdClass $revision Submitted revision.
     * @param stdClass $page Page record.
     * @param int $actorid User who submitted (excluded from recipients).
     * @return void
     */
    public static function draft_submitted(stdClass $revision, stdClass $page, int $actorid): void {
        $a = (object)[
            'title' => format_string($page->title),
            'version' => (int)$revision->versionnumber,
            'summary' => (string)$revision->changesummary,
        ];
        $url = new moodle_url('/local/handbook/review.php');

        foreach (self::capability_holders('local/handbook:review') as $user) {
            if ((int)$user->id === $actorid) {
                continue;
            }
            self::send($user, 'draftsubmitted',
                get_string('notifydraftsubmitted_subject', 'local_handbook', $a),
                get_string('notifydraftsubmitted_body', 'local_handbook', $a),
                $url, get_string('reviewqueue', 'local_handbook'));
        }
    }

    /**
     * Notify the author that changes were requested on their draft.
     *
     * @param stdClass $revision Revision returned to the author.
     * @param stdClass $page Page record.
     * @param string $note Review note.
     * @return void
     */
    public static function changes_requested(stdClass $revision, stdClass $page, string $note): void {
        $author = core_user::get_user((int)$revision->createdby, '*', IGNORE_MISSING);
        if (!$author) {
            return;
        }

        $a = (object)[
            'title' => format_string($page->title),
            'version' => (int)$revision->versionnumber,
            'note' => $note,
        ];
        self::send($author, 'changesrequested',
            get_string('notifychangesrequested_subject', 'local_handbook', $a),
            get_string('notifychangesrequested_body', 'local_handbook', $a),
            new moodle_url('/local/handbook/edit.php', ['id' => $page->id]),
            get_string('editpage', 'local_handbook'));
    }

    /**
     * Notify findings managers about a new finding.
     *
     * @param stdClass $finding Finding record.
     * @param int $actorid Reporting user (excluded from recipients).
     * @return void
     */
    public static function finding_created(stdClass $finding, int $actorid): void {
        $a = (object)[
            'id' => (int)$finding->id,
            'type' => get_string('findingtype_' . $finding->findingtype, 'local_handbook'),
            'summary' => (string)$finding->summary,
        ];
        $url = new moodle_url('/local/handbook/manage/findings.php');

        foreach (self::capability_holders('local/handbook:managefindings') as $user) {
            if ((int)$user->id === $actorid) {
                continue;
            }
            self::send($user, 'findingcreated',
                get_string('notifyfindingcreated_subject', 'local_handbook', $a),
                get_string('notifyfindingcreated_body', 'local_handbook', $a),
                $url, get_string('managefindings', 'local_handbook'));
        }
    }

    /**
     * Remind a page owner that the review date is due or overdue.
     *
     * @param stdClass $page Page record (owneruserid > 0).
     * @return void
     */
    public static function review_due(stdClass $page): void {
        $owner = core_user::get_user((int)$page->owneruserid, '*', IGNORE_MISSING);
        if (!$owner || $owner->deleted || $owner->suspended) {
            return;
        }

        $a = (object)[
            'title' => format_string($page->title),
            'reviewdate' => userdate((int)$page->reviewdate, get_string('strftimedate', 'langconfig')),
        ];
        self::send($owner, 'reviewdue',
            get_string('notifyreviewdue_subject', 'local_handbook', $a),
            get_string('notifyreviewdue_body', 'local_handbook', $a),
            new moodle_url('/local/handbook/view.php', ['page' => $page->slug]),
            format_string($page->title));
    }

    /**
     * Users holding a capability in system context (active, confirmed).
     *
     * @param string $capability Capability name.
     * @return stdClass[]
     */
    private static function capability_holders(string $capability): array {
        $namefields = implode(', ', array_map(
            static fn(string $field): string => 'u.' . $field,
            \core_user\fields::get_name_fields()
        ));
        return get_users_by_capability(context_system::instance(), $capability,
            'u.id, u.email, u.deleted, u.suspended, u.auth, u.emailstop, u.mailformat, ' . $namefields);
    }

    /**
     * Send one notification, swallowing delivery errors.
     *
     * @param stdClass $userto Recipient.
     * @param string $provider Message provider name (db/messages.php).
     * @param string $subject Subject line.
     * @param string $body Plain-text body.
     * @param moodle_url $url Context URL.
     * @param string $urlname Context URL label.
     * @return void
     */
    private static function send(stdClass $userto, string $provider, string $subject,
            string $body, moodle_url $url, string $urlname): void {
        $message = new \core\message\message();
        $message->component = 'local_handbook';
        $message->name = $provider;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $userto;
        $message->subject = $subject;
        $message->fullmessage = $body . "\n\n" . $url->out(false);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s($body) . '</p><p><a href="' . $url->out() . '">'
            . s($urlname) . '</a></p>';
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $urlname;

        try {
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_handbook notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
