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
use moodle_exception;
use stdClass;

/**
 * Page and revision workflow service (specification sections 10, 11).
 *
 * All workflow state transitions happen inside this class, each inside a
 * database transaction (11.3). The page's publishedrevisionid is the single
 * authoritative record of what is published; revision status values are
 * historical workflow metadata.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_service {

    /** @var string Revision workflow states (specification 11.1). */
    public const STATUS_DRAFT = 'draft';
    /** @var string */
    public const STATUS_IN_REVIEW = 'in_review';
    /** @var string */
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';
    /** @var string */
    public const STATUS_APPROVED = 'approved';
    /** @var string */
    public const STATUS_PUBLISHED = 'published';
    /** @var string */
    public const STATUS_SUPERSEDED = 'superseded';
    /** @var string */
    public const STATUS_REJECTED = 'rejected';

    /** @var string[] States in which an author may still edit the revision. */
    public const EDITABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_CHANGES_REQUESTED];

    /**
     * Content type keys (specification 10.1). Labels come from lang strings
     * contenttype_<key>.
     *
     * @return string[]
     */
    public static function content_types(): array {
        return ['policy', 'procedure', 'standard', 'guideline', 'quickguide',
            'template', 'example', 'roledescription'];
    }

    /**
     * Criticality keys (specification 10.1). Labels: criticality_<key>.
     *
     * @return string[]
     */
    public static function criticalities(): array {
        return ['reference', 'operational', 'mandatory', 'safetycritical'];
    }

    /**
     * AI access keys (specification 10.1). Labels: aiaccess_<key>.
     *
     * @return string[]
     */
    public static function ai_access_levels(): array {
        return ['full', 'metadata_only', 'excluded'];
    }

    /**
     * Build a URL-safe slug from a title.
     *
     * @param string $text Source text.
     * @return string
     */
    public static function slugify(string $text): string {
        $slug = \core_text::strtolower(trim($text));
        // Fold common Spanish/German diacritics before stripping.
        $slug = strtr($slug, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'ä' => 'a', 'ö' => 'o', 'ß' => 'ss',
        ]);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return \core_text::substr($slug !== '' ? $slug : 'page', 0, 100);
    }

    /**
     * Ensure a slug is unique in a table, appending -2, -3, ... when needed.
     *
     * @param string $table Table name without prefix.
     * @param string $slug Candidate slug.
     * @param int $excludeid Record id to ignore (when updating).
     * @return string
     */
    public static function unique_slug(string $table, string $slug, int $excludeid = 0): string {
        global $DB;

        $candidate = $slug;
        $suffix = 2;
        while ($DB->record_exists_select($table, 'slug = :slug AND id <> :id',
                ['slug' => $candidate, 'id' => $excludeid])) {
            $tail = '-' . $suffix++;
            $candidate = \core_text::substr($slug, 0, 100 - \core_text::strlen($tail)) . $tail;
        }
        return $candidate;
    }

    /**
     * Create a new page together with its first draft revision (v1).
     *
     * @param stdClass $data Page metadata: title, categoryid, contenttype,
     *        summary and optional fields matching the page table, plus
     *        content/contentformat for the initial draft.
     * @param int $userid Acting user (0 = current user).
     * @return stdClass The page record with ->draftrevision attached.
     */
    public static function create_page(stdClass $data, int $userid = 0): stdClass {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $now = time();

        $transaction = $DB->start_delegated_transaction();

        $page = new stdClass();
        $page->categoryid = (int)$data->categoryid;
        $page->title = trim($data->title);
        $page->slug = self::unique_slug('local_handbook_page',
            !empty($data->slug) ? self::slugify($data->slug) : self::slugify($page->title));
        $page->summary = $data->summary ?? '';
        $page->contenttype = $data->contenttype ?? 'procedure';
        $page->authoritylevel = (int)($data->authoritylevel ?? 4);
        $page->scopejson = $data->scopejson ?? '';
        $page->audiencejson = $data->audiencejson ?? '';
        $page->responsiblearea = $data->responsiblearea ?? '';
        $page->owneruserid = (int)($data->owneruserid ?? $userid);
        $page->approveruserid = (int)($data->approveruserid ?? 0);
        $page->publishedrevisionid = 0;
        $page->effectivedate = 0;
        $page->reviewdate = (int)($data->reviewdate ?? 0);
        $page->requiredreading = (int)($data->requiredreading ?? 0);
        $page->criticality = $data->criticality ?? 'operational';
        $page->aiaccess = $data->aiaccess ?? 'full';
        $page->language = $data->language ?? 'es';
        $page->translationgroupid = 0;
        $page->sortorder = (int)($data->sortorder ?? 0);
        $page->archived = 0;
        $page->timecreated = $now;
        $page->timemodified = $now;
        $page->createdby = $userid;
        $page->modifiedby = $userid;
        $page->id = $DB->insert_record('local_handbook_page', $page);

        $revision = self::insert_revision($page->id, 1, 0,
            $data->content ?? '', (int)($data->contentformat ?? FORMAT_HTML), '', $userid);

        $transaction->allow_commit();

        $event = \local_handbook\event\page_created::create([
            'context' => context_system::instance(),
            'objectid' => $page->id,
            'other' => ['slug' => $page->slug],
        ]);
        $event->trigger();

        $page->draftrevision = $revision;
        return $page;
    }

    /**
     * Return the page's editable or in-flight working revision, if any.
     *
     * A page may have at most one working revision newer than the published
     * one (specification 11.1).
     *
     * @param int $pageid Page id.
     * @return stdClass|null
     */
    public static function get_working_revision(int $pageid): ?stdClass {
        global $DB;

        $records = $DB->get_records_select('local_handbook_revision',
            'pageid = :pageid AND status IN (:s1, :s2, :s3, :s4)',
            [
                'pageid' => $pageid,
                's1' => self::STATUS_DRAFT,
                's2' => self::STATUS_IN_REVIEW,
                's3' => self::STATUS_CHANGES_REQUESTED,
                's4' => self::STATUS_APPROVED,
            ], 'versionnumber DESC', '*', 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * Create a new draft revision based on the current published revision.
     *
     * @param stdClass $page Page record.
     * @param int $userid Acting user (0 = current user).
     * @return stdClass The new draft revision.
     */
    public static function create_revision_draft(stdClass $page, int $userid = 0): stdClass {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        if (self::get_working_revision($page->id) !== null) {
            throw new moodle_exception('errordraftexists', 'local_handbook');
        }

        $base = null;
        if ($page->publishedrevisionid) {
            $base = $DB->get_record('local_handbook_revision',
                ['id' => $page->publishedrevisionid], '*', MUST_EXIST);
        }

        $maxversion = (int)$DB->get_field_sql(
            'SELECT MAX(versionnumber) FROM {local_handbook_revision} WHERE pageid = ?', [$page->id]);

        $transaction = $DB->start_delegated_transaction();
        $revision = self::insert_revision($page->id, $maxversion + 1,
            $base->id ?? 0, $base->content ?? '', (int)($base->contentformat ?? FORMAT_HTML), '', $userid);
        $transaction->allow_commit();

        // Inherited content may reference @@PLUGINFILE@@ files stored under
        // the base revision's item id; copy them so the draft is complete.
        if ($base) {
            self::copy_revision_files((int)$base->id, (int)$revision->id);
        }

        return $revision;
    }

    /**
     * Update a draft's content with an optimistic concurrency check (11.3).
     *
     * @param stdClass $revision Revision record as loaded by the caller.
     * @param string $content New content HTML.
     * @param int $contentformat Content format.
     * @param string $changesummary Change summary (may be empty until submit).
     * @param int $userid Acting user (0 = current user).
     * @param bool|null $requiresreack Whether publishing this revision demands
     *        renewed acknowledgements (null = leave unchanged, spec 16).
     * @return void
     */
    public static function update_draft(stdClass $revision, string $content, int $contentformat,
            string $changesummary, int $userid = 0, ?bool $requiresreack = null): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        if (!in_array($revision->status, self::EDITABLE_STATUSES, true)) {
            throw new moodle_exception('errorworkflowstate', 'local_handbook');
        }

        // Optimistic concurrency: fail clearly if someone saved meanwhile.
        $current = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        if ((int)$current->timemodified !== (int)$revision->timemodified) {
            throw new moodle_exception('errorrevisionconflict', 'local_handbook');
        }

        $update = new stdClass();
        $update->id = $revision->id;
        $update->content = $content;
        $update->contentformat = $contentformat;
        $update->plaintext = html_to_text($content, 0, false);
        $update->contenthash = sha1($content);
        $update->changesummary = $changesummary;
        if ($requiresreack !== null) {
            $update->requiresreacknowledgement = (int)$requiresreack;
        }
        $update->timemodified = time();
        $update->modifiedby = $userid;
        $DB->update_record('local_handbook_revision', $update);

        $event = \local_handbook\event\draft_updated::create([
            'context' => context_system::instance(),
            'objectid' => $revision->id,
            'other' => ['pageid' => (int)$revision->pageid],
        ]);
        $event->trigger();
    }

    /**
     * Submit a draft for review. Requires a change summary (11.3).
     *
     * @param stdClass $revision Draft revision.
     * @param string $changesummary Non-empty change summary.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function submit_for_review(stdClass $revision, string $changesummary, int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        if (trim($changesummary) === '') {
            throw new moodle_exception('changesummary_help', 'local_handbook');
        }

        self::transition($revision, self::EDITABLE_STATUSES, self::STATUS_IN_REVIEW,
            $userid, ['changesummary' => $changesummary]);

        $event = \local_handbook\event\draft_submitted::create([
            'context' => context_system::instance(),
            'objectid' => $revision->id,
            'other' => ['pageid' => (int)$revision->pageid, 'versionnumber' => (int)$revision->versionnumber],
        ]);
        $event->trigger();

        $page = $DB->get_record('local_handbook_page', ['id' => $revision->pageid]);
        if ($page) {
            notification_service::draft_submitted($revision, $page, $userid);
        }
    }

    /**
     * Return a submitted draft to its author with a review note.
     *
     * @param stdClass $revision Revision in review.
     * @param string $note Reason for the request (11.3).
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function request_changes(stdClass $revision, string $note, int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        self::transition($revision, [self::STATUS_IN_REVIEW], self::STATUS_CHANGES_REQUESTED,
            $userid, ['reviewnote' => $note, 'reviewedby' => $userid]);

        $event = \local_handbook\event\changes_requested::create([
            'context' => context_system::instance(),
            'objectid' => $revision->id,
            'other' => ['pageid' => (int)$revision->pageid, 'versionnumber' => (int)$revision->versionnumber],
        ]);
        $event->trigger();

        $page = $DB->get_record('local_handbook_page', ['id' => $revision->pageid]);
        if ($page) {
            notification_service::changes_requested($revision, $page, $note);
        }
    }

    /**
     * Approve a revision for publication.
     *
     * The staff-facing published author defaults to the approving human
     * (spec 36.5); an authorized editor may pass an explicit human or
     * institutional author. It is never derived from createdby, so an
     * AI-created draft never presents Handbook AI as its published author.
     *
     * @param stdClass $revision Revision in review.
     * @param int $userid Acting user (0 = current user).
     * @param int $authoruserid Explicit published author (0 = the approver).
     * @return void
     */
    public static function approve(stdClass $revision, int $userid = 0, int $authoruserid = 0): void {
        global $USER;

        $userid = $userid ?: (int)$USER->id;
        $author = $authoruserid ?: $userid;
        self::transition($revision, [self::STATUS_IN_REVIEW], self::STATUS_APPROVED,
            $userid, ['approvedby' => $userid, 'timeapproved' => time(), 'authoruserid' => $author]);

        $event = \local_handbook\event\revision_approved::create([
            'context' => context_system::instance(),
            'objectid' => $revision->id,
            'other' => ['pageid' => (int)$revision->pageid, 'versionnumber' => (int)$revision->versionnumber],
        ]);
        $event->trigger();
    }

    /**
     * Reject a revision in review (spec 36.4): it leaves the workflow without
     * publishing. History is preserved; the page keeps its current published
     * revision. Used chiefly from the change-set review interface.
     *
     * @param stdClass $revision Revision in review.
     * @param string $note Reason for rejection.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function reject(stdClass $revision, string $note = '', int $userid = 0): void {
        global $USER;

        $userid = $userid ?: (int)$USER->id;
        self::transition($revision, [self::STATUS_IN_REVIEW], self::STATUS_REJECTED,
            $userid, ['reviewnote' => $note, 'reviewedby' => $userid]);

        $event = \local_handbook\event\revision_rejected::create([
            'context' => context_system::instance(),
            'objectid' => $revision->id,
            'other' => ['pageid' => (int)$revision->pageid, 'versionnumber' => (int)$revision->versionnumber],
        ]);
        $event->trigger();
    }

    /**
     * Publish an approved revision (one transaction, specification 11.3).
     *
     * Supersedes the previously published revision, updates the page's
     * publishedrevisionid pointer and effective date, and records publisher
     * and time.
     *
     * @param stdClass $revision Approved revision.
     * @param int $userid Acting user (0 = current user).
     * @param int $effectivefrom Effective date (0 = now).
     * @return void
     */
    public static function publish(stdClass $revision, int $userid = 0, int $effectivefrom = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $now = time();
        $effectivefrom = $effectivefrom ?: $now;

        if ($revision->status !== self::STATUS_APPROVED) {
            throw new moodle_exception('errorworkflowstate', 'local_handbook');
        }

        $transaction = $DB->start_delegated_transaction();

        $page = $DB->get_record('local_handbook_page', ['id' => $revision->pageid], '*', MUST_EXIST);

        if ($page->publishedrevisionid) {
            $DB->set_field('local_handbook_revision', 'status', self::STATUS_SUPERSEDED,
                ['id' => $page->publishedrevisionid]);
        }

        $update = new stdClass();
        $update->id = $revision->id;
        $update->status = self::STATUS_PUBLISHED;
        $update->publishedby = $userid;
        $update->timepublished = $now;
        $update->effectivefrom = $effectivefrom;
        // Safety net: governed publishing sets the author at approval, but any
        // path that reaches publish with no author yet falls back to the
        // publisher (never createdby; spec 36.5).
        $update->authoruserid = (int)$revision->authoruserid ?: $userid;
        $update->timemodified = $now;
        $update->modifiedby = $userid;
        $DB->update_record('local_handbook_revision', $update);

        $pageupdate = new stdClass();
        $pageupdate->id = $page->id;
        $pageupdate->publishedrevisionid = $revision->id;
        $pageupdate->effectivedate = $effectivefrom;
        $pageupdate->timemodified = $now;
        $pageupdate->modifiedby = $userid;
        $DB->update_record('local_handbook_page', $pageupdate);

        $transaction->allow_commit();

        $event = \local_handbook\event\revision_published::create([
            'context' => context_system::instance(),
            'objectid' => $revision->id,
            'other' => ['pageid' => (int)$page->id, 'versionnumber' => (int)$revision->versionnumber],
        ]);
        $event->trigger();
    }

    /**
     * Archive or unarchive a page (spec 11.3: archiving never deletes the
     * revision history; readers stop seeing the page, editors still can).
     *
     * @param stdClass $page Page record.
     * @param bool $archived Target state.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function set_archived(stdClass $page, bool $archived, int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        $update = new stdClass();
        $update->id = $page->id;
        $update->archived = (int)$archived;
        $update->timemodified = time();
        $update->modifiedby = $userid;
        $DB->update_record('local_handbook_page', $update);

        $event = \local_handbook\event\page_archived::create([
            'context' => context_system::instance(),
            'objectid' => $page->id,
            'other' => ['archived' => (int)$archived],
        ]);
        $event->trigger();
    }

    /**
     * Restore an older revision as a new working draft (spec 11.3):
     * creates a new revision based on the old content; it does not erase
     * later history.
     *
     * @param stdClass $revision Superseded (or rejected) revision to restore.
     * @param int $userid Acting user (0 = current user).
     * @return stdClass The new draft revision.
     */
    public static function restore_revision(stdClass $revision, int $userid = 0): stdClass {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        if (!in_array($revision->status, [self::STATUS_SUPERSEDED, self::STATUS_REJECTED], true)) {
            throw new moodle_exception('errorworkflowstate', 'local_handbook');
        }

        $page = $DB->get_record('local_handbook_page', ['id' => $revision->pageid], '*', MUST_EXIST);
        if (self::get_working_revision((int)$page->id) !== null) {
            throw new moodle_exception('errordraftexists', 'local_handbook');
        }

        $maxversion = (int)$DB->get_field_sql(
            'SELECT MAX(versionnumber) FROM {local_handbook_revision} WHERE pageid = ?', [$page->id]);

        $transaction = $DB->start_delegated_transaction();
        $draft = self::insert_revision((int)$page->id, $maxversion + 1,
            (int)$page->publishedrevisionid, (string)$revision->content,
            (int)$revision->contentformat,
            get_string('restoredsummary', 'local_handbook', (int)$revision->versionnumber),
            $userid);
        $transaction->allow_commit();

        // The restored content's embedded files live under the OLD
        // revision's item id; copy them to the new draft.
        self::copy_revision_files((int)$revision->id, (int)$draft->id);

        return $draft;
    }

    /**
     * Whether bootstrap mode is enabled (settings.php).
     *
     * While enabled, direct publishing bypasses the review queue for users
     * holding local/handbook:publish. Intended only for the initial
     * population phase (spec 4.10); revision history is recorded either way.
     *
     * @return bool
     */
    public static function bootstrap_mode_enabled(): bool {
        return (bool)get_config('local_handbook', 'bootstrapmode');
    }

    /**
     * Publish an editable draft directly, skipping review (bootstrap only).
     *
     * The revision passes through the same approved -> published transition
     * and the same publish() method as the governed workflow, so audit
     * fields, events and the supersede step are identical.
     *
     * @param stdClass $revision Draft or changes-requested revision.
     * @param int $userid Acting user (0 = current user).
     * @param int $effectivefrom Effective date (0 = now).
     * @return void
     */
    public static function direct_publish(stdClass $revision, int $userid = 0, int $effectivefrom = 0): void {
        global $DB, $USER;

        if (!self::bootstrap_mode_enabled()) {
            throw new moodle_exception('errorbootstrapoff', 'local_handbook');
        }

        $userid = $userid ?: (int)$USER->id;

        self::transition($revision, array_merge(self::EDITABLE_STATUSES, [self::STATUS_IN_REVIEW]),
            self::STATUS_APPROVED, $userid,
            ['approvedby' => $userid, 'timeapproved' => time(), 'authoruserid' => $userid]);

        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        self::publish($revision, $userid, $effectivefrom);
    }

    /**
     * Copy a revision's stored files to another revision's file area.
     *
     * Content copied between revisions keeps its @@PLUGINFILE@@
     * placeholders; the files must follow, or images break once the new
     * revision is published (file area "revision", itemid = revision id).
     *
     * @param int $fromrevisionid Source revision id.
     * @param int $torevisionid Target revision id.
     * @return void
     */
    private static function copy_revision_files(int $fromrevisionid, int $torevisionid): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $fs = get_file_storage();
        $contextid = context_system::instance()->id;

        $files = $fs->get_area_files($contextid, 'local_handbook', 'revision',
            $fromrevisionid, 'id', false);
        foreach ($files as $file) {
            if (!$fs->file_exists($contextid, 'local_handbook', 'revision', $torevisionid,
                    $file->get_filepath(), $file->get_filename())) {
                $fs->create_file_from_storedfile(['itemid' => $torevisionid], $file);
            }
        }
    }

    /**
     * Perform a guarded status transition.
     *
     * @param stdClass $revision Revision record.
     * @param string[] $fromstatuses Allowed source states.
     * @param string $tostatus Target state.
     * @param int $userid Acting user.
     * @param array $extrafields Additional revision fields to set.
     * @return void
     */
    private static function transition(stdClass $revision, array $fromstatuses, string $tostatus,
            int $userid, array $extrafields = []): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // Re-read inside the transaction so the state check is authoritative.
        $current = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        if (!in_array($current->status, $fromstatuses, true)) {
            throw new moodle_exception('errorworkflowstate', 'local_handbook');
        }

        $update = new stdClass();
        $update->id = $revision->id;
        $update->status = $tostatus;
        $update->timemodified = time();
        $update->modifiedby = $userid;
        foreach ($extrafields as $field => $value) {
            $update->$field = $value;
        }
        $DB->update_record('local_handbook_revision', $update);

        $transaction->allow_commit();

        $revision->status = $tostatus;
        $revision->timemodified = $update->timemodified;
    }

    /**
     * Insert a revision row.
     *
     * @param int $pageid Page id.
     * @param int $versionnumber Sequential version number.
     * @param int $baserevisionid Revision this draft is based on (0 = none).
     * @param string $content Content HTML.
     * @param int $contentformat Content format.
     * @param string $changesummary Change summary.
     * @param int $userid Acting user.
     * @return stdClass The inserted revision.
     */
    private static function insert_revision(int $pageid, int $versionnumber, int $baserevisionid,
            string $content, int $contentformat, string $changesummary, int $userid): stdClass {
        global $DB;

        $now = time();

        $revision = new stdClass();
        $revision->pageid = $pageid;
        $revision->versionnumber = $versionnumber;
        $revision->status = self::STATUS_DRAFT;
        $revision->baserevisionid = $baserevisionid;
        $revision->content = $content;
        $revision->contentformat = $contentformat;
        $revision->plaintext = html_to_text($content, 0, false);
        $revision->contenthash = sha1($content);
        $revision->changesummary = $changesummary;
        $revision->reviewnote = '';
        $revision->effectivefrom = 0;
        $revision->requiresreacknowledgement = 0;
        $revision->timecreated = $now;
        $revision->timemodified = $now;
        $revision->createdby = $userid;
        $revision->modifiedby = $userid;
        $revision->reviewedby = 0;
        $revision->approvedby = 0;
        $revision->publishedby = 0;
        $revision->timeapproved = 0;
        $revision->timepublished = 0;
        $revision->id = $DB->insert_record('local_handbook_revision', $revision);

        return $revision;
    }
}
