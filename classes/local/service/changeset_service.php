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
 * Change sets: coordinated multi-page draft proposals (specification 36).
 *
 * A change set groups draft proposals across pages, PR-like. This service
 * orchestrates page_service (it never writes revisions directly) so the
 * "all workflow transitions happen in page_service" rule holds. Its authority
 * ends at submitting drafts for human review: there is no approve or publish
 * operation here, by design (36.1).
 *
 * Each change item carries a kind (see KIND_* ). Today every item is a
 * page_revision: the model guarantees at most one working revision per page,
 * so such an item maps a page to its single working revision, and the
 * conservative upsert (36.4) reuses that draft when this change set owns it and
 * refuses to overwrite a human draft or a draft owned by another change set.
 * The schema also holds non-revision kinds (metadata, taxonomy, lifecycle,
 * glossary) that later phases add; those are never approved or published here —
 * they are applied only by the human-gated publish path.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class changeset_service {

    /** @var string Change-set states (specification 36.3). */
    public const STATUS_DRAFT = 'draft';
    /** @var string */
    public const STATUS_IN_REVIEW = 'in_review';
    /** @var string */
    public const STATUS_PARTIALLY_COMPLETED = 'partially_completed';
    /** @var string */
    public const STATUS_COMPLETED = 'completed';
    /** @var string */
    public const STATUS_CANCELLED = 'cancelled';

    /** @var string Change-item states (specification 36.3). */
    public const ITEM_DRAFT = 'draft';
    /** @var string */
    public const ITEM_CONFLICT = 'conflict';
    /** @var string */
    public const ITEM_IN_REVIEW = 'in_review';
    /** @var string */
    public const ITEM_APPROVED = 'approved';
    /** @var string */
    public const ITEM_PUBLISHED = 'published';
    /** @var string */
    public const ITEM_REJECTED = 'rejected';
    /** @var string */
    public const ITEM_SKIPPED = 'skipped';

    /**
     * @var string A change item that proposes a new working content revision
     * for a page — the only kind today. Later phases add metadata, taxonomy,
     * lifecycle and glossary kinds; each is applied only by the human-gated
     * publish path, never by the API (spec 36.1).
     */
    public const KIND_PAGE_REVISION = 'page_revision';

    /**
     * @var string A change item that proposes a partial patch to a page's
     * fiche (metadata) — versioned and reviewed like content, applied to the
     * page row only by the human-gated publish path (Phase 1).
     */
    public const KIND_PAGE_METADATA = 'page_metadata';

    /**
     * Page fiche fields a metadata proposal may patch. Deliberately excludes
     * structural and sensitive fields (slug, categoryid, audience, aiaccess,
     * owner/approver, archived) — those arrive with the taxonomy, lifecycle
     * and sensitive-field work in later increments.
     *
     * @return string[]
     */
    public static function metadata_fields(): array {
        return ['title', 'summary', 'contenttype', 'authoritylevel', 'criticality',
            'responsiblearea', 'reviewdate', 'requiredreading'];
    }

    /** @var string[] Change-set states that reject further drafting. */
    private const LOCKED_STATUSES = [self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    /** @var string[] Terminal item states. */
    private const ITEM_TERMINAL = [self::ITEM_PUBLISHED, self::ITEM_REJECTED, self::ITEM_SKIPPED];

    /** @var string[] Item states in the human review pipeline. */
    private const ITEM_ACTIVE = [self::ITEM_IN_REVIEW, self::ITEM_APPROVED];

    /**
     * Create a change set.
     *
     * @param stdClass $data title (required), instructionsummary, source
     *        ('human' default or 'ai'), externalreference, sponsoruserid.
     * @param int $userid Acting user / technical creator (0 = current user).
     * @return stdClass The change-set record.
     */
    public static function create(stdClass $data, int $userid = 0): stdClass {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $now = time();

        if (trim((string)($data->title ?? '')) === '') {
            throw new moodle_exception('invalidparameter', 'debug', '', null, 'title');
        }

        $changeset = new stdClass();
        $changeset->title = \core_text::substr(trim($data->title), 0, 255);
        $changeset->instructionsummary = (string)($data->instructionsummary ?? '');
        $changeset->status = self::STATUS_DRAFT;
        $changeset->source = ($data->source ?? 'human') === 'ai' ? 'ai' : 'human';
        $changeset->externalreference = \core_text::substr((string)($data->externalreference ?? ''), 0, 255);
        $changeset->sponsoruserid = (int)($data->sponsoruserid ?? 0);
        $changeset->timecreated = $now;
        $changeset->timemodified = $now;
        $changeset->timesubmitted = 0;
        $changeset->timecompleted = 0;
        $changeset->createdby = $userid;
        $changeset->modifiedby = $userid;
        $changeset->submittedby = 0;
        $changeset->id = $DB->insert_record('local_handbook_changeset', $changeset);

        $event = \local_handbook\event\changeset_created::create([
            'context' => context_system::instance(),
            'objectid' => $changeset->id,
            'other' => ['source' => $changeset->source],
        ]);
        $event->trigger();

        return $changeset;
    }

    /**
     * Get a change set with its items (ordered).
     *
     * @param int $changesetid Change-set id.
     * @return stdClass Change-set record with ->items array.
     */
    public static function get(int $changesetid): stdClass {
        global $DB;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        $changeset->items = $DB->get_records('local_handbook_changeitem',
            ['changesetid' => $changesetid], 'sortorder ASC');
        return $changeset;
    }

    /**
     * List change sets, newest activity first.
     *
     * @param array $filters Optional 'status' and/or 'source'.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size (0 = all).
     * @return stdClass[]
     */
    public static function list_changesets(array $filters = [], int $limitfrom = 0,
            int $limitnum = 0): array {
        global $DB;

        $conditions = [];
        if (!empty($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        if (!empty($filters['source'])) {
            $conditions['source'] = $filters['source'];
        }
        return $DB->get_records('local_handbook_changeset', $conditions,
            'timemodified DESC', '*', $limitfrom, $limitnum);
    }

    /**
     * Create or update this change set's draft for one page (specification
     * 36.4, the conservative upsert).
     *
     * Decision per page:
     * - no working revision -> create one from the published revision
     *   (expectedpublishedrevisionid guards against a stale base);
     * - a working revision this change set owns and can still edit -> update
     *   it (expectedtimemodified guards against a concurrent edit);
     * - anything else (a human draft, another change set's draft, or an
     *   in-review/approved revision) -> a structured conflict that never
     *   overwrites.
     *
     * @param int $changesetid Change-set id.
     * @param int $pageid Page id.
     * @param string $content Proposed content HTML (headings start at h2).
     * @param int $contentformat Content format.
     * @param string $changesummary Change summary for the item and revision.
     * @param int $expectedpublishedrevisionid Base check for a new draft (0 = skip).
     * @param int $expectedtimemodified Concurrency token for an update (0 = skip).
     * @param bool|null $requiresreack Whether publishing demands renewed acks.
     * @param int $userid Acting user (0 = current user).
     * @return array Per-item result (see item_result()).
     */
    public static function upsert_draft(int $changesetid, int $pageid, string $content,
            int $contentformat, string $changesummary, int $expectedpublishedrevisionid = 0,
            int $expectedtimemodified = 0, ?bool $requiresreack = null, int $userid = 0): array {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if (in_array($changeset->status, self::LOCKED_STATUSES, true)) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }
        $page = $DB->get_record('local_handbook_page', ['id' => $pageid], '*', MUST_EXIST);

        $working = page_service::get_working_revision($pageid);

        if ($working) {
            $ownsit = $DB->record_exists('local_handbook_changeitem', [
                'changesetid' => $changesetid, 'pageid' => $pageid, 'revisionid' => $working->id,
            ]);
            if (!$ownsit) {
                // Another change set's draft, or a human/manual draft: never overwrite.
                $foreign = $DB->record_exists_select('local_handbook_changeitem',
                    'revisionid = :rid AND changesetid <> :cs',
                    ['rid' => $working->id, 'cs' => $changesetid]);
                $notekey = $foreign ? 'conflict_foreignchangeset' : 'conflict_humandraft';
                return self::record_conflict($changeset, $pageid, 0, $notekey,
                    (int)$working->versionnumber, $changesummary, $userid);
            }
            if (!in_array($working->status, page_service::EDITABLE_STATUSES, true)) {
                // We own it, but it is in review or approved: a human must return it.
                return self::record_conflict($changeset, $pageid, (int)$working->id, 'conflict_inreview',
                    (int)$working->versionnumber, $changesummary, $userid);
            }
            if ($expectedtimemodified && (int)$working->timemodified !== $expectedtimemodified) {
                return self::record_conflict($changeset, $pageid, (int)$working->id, 'conflict_concurrency',
                    (int)$working->versionnumber, $changesummary, $userid);
            }
            // Reuse the same editable draft (no version churn).
            page_service::update_draft($working, $content, $contentformat, $changesummary,
                $userid, $requiresreack);
            $working = $DB->get_record('local_handbook_revision', ['id' => $working->id], '*', MUST_EXIST);
            $item = self::write_item($changeset, $pageid, (int)$working->id, self::ITEM_DRAFT,
                '', $changesummary, $userid);
            return self::item_result($item, $working);
        }

        // No working revision: create one from the current published revision.
        if ($expectedpublishedrevisionid
                && (int)$page->publishedrevisionid !== $expectedpublishedrevisionid) {
            return self::record_conflict($changeset, $pageid, 0, 'conflict_basemismatch',
                0, $changesummary, $userid);
        }

        $revision = page_service::create_revision_draft($page, $userid);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        page_service::update_draft($revision, $content, $contentformat, $changesummary,
            $userid, $requiresreack);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        $item = self::write_item($changeset, $pageid, (int)$revision->id, self::ITEM_DRAFT,
            '', $changesummary, $userid);
        return self::item_result($item, $revision);
    }

    /**
     * Create or update this change set's proposed metadata (fiche) patch for
     * one page. Draft authority only — the patch is applied to the page row
     * exclusively by the human-gated publish path (publish_item()).
     *
     * Partial-patch semantics (spec 6): only the fields present in $patch are
     * proposed; omitted fields keep their published value.
     *
     * @param int $changesetid Change-set id.
     * @param int $pageid Page id.
     * @param array $patch Field => value map (subset of metadata_fields()).
     * @param int $expectedtimemodified Page timemodified the caller based the
     *        patch on (0 = skip the concurrency check).
     * @param int $userid Acting user (0 = current user).
     * @return array Per-item result.
     */
    public static function upsert_metadata(int $changesetid, int $pageid, array $patch,
            int $expectedtimemodified = 0, int $userid = 0): array {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if (in_array($changeset->status, self::LOCKED_STATUSES, true)) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }
        $page = $DB->get_record('local_handbook_page', ['id' => $pageid], '*', MUST_EXIST);

        if ($expectedtimemodified && (int)$page->timemodified !== $expectedtimemodified) {
            return self::record_conflict($changeset, $pageid, 0, 'conflict_metadataconcurrency',
                0, '', $userid, self::KIND_PAGE_METADATA);
        }

        // Validate now so a bad proposal never reaches the review queue; it is
        // re-validated at apply time in case the page changed meanwhile.
        $normalized = self::validate_metadata_patch($page, $patch);
        $summary = self::summarise_metadata_patch($normalized);

        $item = self::write_item($changeset, $pageid, 0, self::ITEM_DRAFT, '',
            $summary, $userid, self::KIND_PAGE_METADATA, json_encode($normalized));
        return self::item_result($item, null);
    }

    /**
     * Validate and normalise a metadata patch (throws on any invalid field).
     *
     * @param stdClass $page The page the patch targets (for context).
     * @param array $patch Raw field => value map.
     * @return array Normalised, typed patch (only supported fields).
     */
    public static function validate_metadata_patch(stdClass $page, array $patch): array {
        $allowed = self::metadata_fields();
        $normalised = [];

        foreach ($patch as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                throw new moodle_exception('errormetadatafieldunsupported', 'local_handbook', '', $field);
            }
            switch ($field) {
                case 'title':
                    $title = trim((string)$value);
                    if ($title === '' || \core_text::strlen($title) > 255) {
                        throw new moodle_exception('errormetadatavalue', 'local_handbook', '', $field);
                    }
                    $normalised['title'] = $title;
                    break;
                case 'summary':
                    $normalised['summary'] = (string)$value;
                    break;
                case 'responsiblearea':
                    $area = trim((string)$value);
                    if ($area === '' || \core_text::strlen($area) > 255) {
                        throw new moodle_exception('errormetadatavalue', 'local_handbook', '', $field);
                    }
                    $normalised['responsiblearea'] = $area;
                    break;
                case 'contenttype':
                    if (!in_array($value, page_service::content_types(), true)) {
                        throw new moodle_exception('errormetadatavalue', 'local_handbook', '', $field);
                    }
                    $normalised['contenttype'] = (string)$value;
                    break;
                case 'criticality':
                    if (!in_array($value, page_service::criticalities(), true)) {
                        throw new moodle_exception('errormetadatavalue', 'local_handbook', '', $field);
                    }
                    $normalised['criticality'] = (string)$value;
                    break;
                case 'authoritylevel':
                    $level = (int)$value;
                    if ($level < 1 || $level > 5) {
                        throw new moodle_exception('errormetadatavalue', 'local_handbook', '', $field);
                    }
                    $normalised['authoritylevel'] = $level;
                    break;
                case 'reviewdate':
                    $date = (int)$value;
                    if ($date < 0) {
                        throw new moodle_exception('errormetadatavalue', 'local_handbook', '', $field);
                    }
                    $normalised['reviewdate'] = $date;
                    break;
                case 'requiredreading':
                    $normalised['requiredreading'] = (int)((bool)$value);
                    break;
            }
        }

        if (!$normalised) {
            throw new moodle_exception('errormetadatapatchempty', 'local_handbook');
        }
        return $normalised;
    }

    /**
     * A short, human summary of which fiche fields a patch changes.
     *
     * @param array $normalised Normalised patch.
     * @return string
     */
    private static function summarise_metadata_patch(array $normalised): string {
        $labels = [];
        foreach (array_keys($normalised) as $field) {
            $labels[] = get_string('metafield_' . $field, 'local_handbook');
        }
        return get_string('metadatachangesummary', 'local_handbook', implode(', ', $labels));
    }

    /**
     * Submit every eligible item of a change set for human review (36.4).
     *
     * Eligible items (editable drafts owned by this change set) are submitted;
     * conflicts and already-submitted items are skipped, not failed. Returns a
     * per-item result so one page's problem never hides the others.
     *
     * @param int $changesetid Change-set id.
     * @param int $userid Acting user (0 = current user).
     * @return array List of per-item results.
     */
    public static function submit(int $changesetid, int $userid = 0): array {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if (in_array($changeset->status, self::LOCKED_STATUSES, true)) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }

        $items = $DB->get_records('local_handbook_changeitem',
            ['changesetid' => $changesetid], 'sortorder ASC');

        $results = [];
        foreach ($items as $item) {
            if ($item->itemstatus !== self::ITEM_DRAFT) {
                // Conflicts / already-submitted / skipped items pass through untouched.
                $results[] = self::item_result($item, null);
                continue;
            }
            if ($item->kind !== self::KIND_PAGE_REVISION) {
                // A non-revision proposal (metadata, taxonomy, lifecycle,
                // glossary) has no draft revision to submit; move it straight
                // into review for a human to approve and apply.
                $item->itemstatus = self::ITEM_IN_REVIEW;
                $item->timemodified = time();
                $DB->update_record('local_handbook_changeitem', $item);
                $results[] = self::item_result($item, null);
                continue;
            }
            if (!$item->revisionid) {
                $results[] = self::item_result($item, null);
                continue;
            }
            $revision = $DB->get_record('local_handbook_revision', ['id' => $item->revisionid]);
            if (!$revision || !in_array($revision->status, page_service::EDITABLE_STATUSES, true)) {
                $item->itemstatus = self::ITEM_CONFLICT;
                $item->conflictnote = get_string('conflict_inreview', 'local_handbook',
                    (int)($revision->versionnumber ?? 0));
                $item->timemodified = time();
                $DB->update_record('local_handbook_changeitem', $item);
                $results[] = self::item_result($item, $revision);
                continue;
            }
            $summary = trim((string)$item->changesummary) !== ''
                ? $item->changesummary
                : get_string('changesetdefaultsummary', 'local_handbook', $changeset->title);
            page_service::submit_for_review($revision, $summary, $userid);
            $item->itemstatus = self::ITEM_IN_REVIEW;
            $item->timemodified = time();
            $DB->update_record('local_handbook_changeitem', $item);
            $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id]);
            $results[] = self::item_result($item, $revision);
        }

        $DB->update_record('local_handbook_changeset', (object)[
            'id' => $changesetid,
            'timesubmitted' => time(),
            'submittedby' => $userid,
            'timemodified' => time(),
            'modifiedby' => $userid,
        ]);
        self::recompute_status($changesetid, $userid);

        $event = \local_handbook\event\changeset_submitted::create([
            'context' => context_system::instance(),
            'objectid' => $changesetid,
            'other' => ['itemcount' => count($items)],
        ]);
        $event->trigger();

        return $results;
    }

    /**
     * Remove an item from a change set while it is still safe to do so.
     *
     * Only draft/conflict/skipped items may be removed. The underlying draft
     * revision (if any) is left intact — it simply stops being part of this
     * change set; no revision history is deleted.
     *
     * @param int $itemid Change-item id.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function remove_item(int $itemid, int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        $item = $DB->get_record('local_handbook_changeitem', ['id' => $itemid], '*', MUST_EXIST);
        if (in_array($item->itemstatus, self::ITEM_ACTIVE, true)
                || $item->itemstatus === self::ITEM_PUBLISHED) {
            throw new moodle_exception('errorchangeitemlocked', 'local_handbook');
        }

        $DB->delete_records('local_handbook_changeitem', ['id' => $itemid]);
        self::recompute_status((int)$item->changesetid, $userid);
    }

    /**
     * Cancel a change set without deleting any revision audit records (36.4).
     *
     * @param int $changesetid Change-set id.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function cancel(int $changesetid, int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if ($changeset->status === self::STATUS_COMPLETED) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }

        $DB->update_record('local_handbook_changeset', (object)[
            'id' => $changesetid,
            'status' => self::STATUS_CANCELLED,
            'timemodified' => time(),
            'modifiedby' => $userid,
        ]);
    }

    /**
     * Approve a non-revision proposal item (in_review -> approved).
     *
     * The caller MUST have checked local/handbook:approve first. Page-revision
     * items are approved through the revision workflow (page_service::approve),
     * never here.
     *
     * @param int $itemid Change-item id.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function approve_item(int $itemid, int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $item = $DB->get_record('local_handbook_changeitem', ['id' => $itemid], '*', MUST_EXIST);
        self::assert_non_revision_item($item);
        if ($item->itemstatus !== self::ITEM_IN_REVIEW) {
            throw new moodle_exception('errorworkflowstate', 'local_handbook');
        }

        $DB->update_record('local_handbook_changeitem', (object)[
            'id' => (int)$item->id, 'itemstatus' => self::ITEM_APPROVED, 'timemodified' => time(),
        ]);
        self::recompute_status((int)$item->changesetid, $userid);
    }

    /**
     * Reject a non-revision proposal item (in_review -> rejected, terminal).
     *
     * The caller MUST have checked local/handbook:review first.
     *
     * @param int $itemid Change-item id.
     * @param string $note Reason for rejection.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function reject_item(int $itemid, string $note = '', int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $item = $DB->get_record('local_handbook_changeitem', ['id' => $itemid], '*', MUST_EXIST);
        self::assert_non_revision_item($item);
        if ($item->itemstatus !== self::ITEM_IN_REVIEW) {
            throw new moodle_exception('errorworkflowstate', 'local_handbook');
        }

        $DB->update_record('local_handbook_changeitem', (object)[
            'id' => (int)$item->id, 'itemstatus' => self::ITEM_REJECTED,
            'conflictnote' => $note, 'timemodified' => time(),
        ]);
        self::recompute_status((int)$item->changesetid, $userid);
    }

    /**
     * Apply an approved non-revision proposal to the published state and mark
     * the item published (approved -> published). This is the single, human-
     * gated apply path: the caller MUST have checked local/handbook:publish.
     *
     * The apply and the status change happen in one transaction so the
     * published state and the audit record can never diverge. There is no
     * external/MCP function that reaches this method (spec 17.3, 36.1).
     *
     * @param int $itemid Change-item id.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function publish_item(int $itemid, int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $item = $DB->get_record('local_handbook_changeitem', ['id' => $itemid], '*', MUST_EXIST);
        self::assert_non_revision_item($item);
        if ($item->itemstatus !== self::ITEM_APPROVED) {
            throw new moodle_exception('errorworkflowstate', 'local_handbook');
        }

        $transaction = $DB->start_delegated_transaction();
        self::apply_item($item, $userid);
        $DB->update_record('local_handbook_changeitem', (object)[
            'id' => (int)$item->id, 'itemstatus' => self::ITEM_PUBLISHED, 'timemodified' => time(),
        ]);
        $transaction->allow_commit();

        self::recompute_status((int)$item->changesetid, $userid);
    }

    /**
     * Guard: the workflow/apply methods act only on non-revision items.
     *
     * @param stdClass $item Change-item record.
     * @return void
     */
    private static function assert_non_revision_item(stdClass $item): void {
        if ($item->kind === self::KIND_PAGE_REVISION) {
            throw new moodle_exception('errorwrongitemkind', 'local_handbook');
        }
    }

    /**
     * Apply a non-revision proposal to the published state by kind.
     *
     * @param stdClass $item Change-item record (approved).
     * @param int $userid Acting user (the human publisher).
     * @return void
     */
    private static function apply_item(stdClass $item, int $userid): void {
        switch ($item->kind) {
            case self::KIND_PAGE_METADATA:
                self::apply_metadata_patch($item, $userid);
                break;
            default:
                throw new moodle_exception('errorunsupportedkind', 'local_handbook', '', $item->kind);
        }
    }

    /**
     * Apply a metadata patch to its page row (re-validated at apply time).
     *
     * @param stdClass $item Change-item record (kind page_metadata).
     * @param int $userid Human publisher (recorded as modifiedby).
     * @return void
     */
    private static function apply_metadata_patch(stdClass $item, int $userid): void {
        global $DB;

        $page = $DB->get_record('local_handbook_page', ['id' => $item->pageid], '*', MUST_EXIST);
        $patch = json_decode((string)$item->payloadjson, true);
        if (!is_array($patch)) {
            throw new moodle_exception('errormetadatapatchempty', 'local_handbook');
        }
        $normalised = self::validate_metadata_patch($page, $patch);

        $update = (object)['id' => (int)$page->id];
        foreach ($normalised as $field => $value) {
            $update->$field = $value;
        }
        $update->timemodified = time();
        $update->modifiedby = $userid;
        $DB->update_record('local_handbook_page', $update);
    }

    /**
     * Synchronise change-item status from a revision's workflow state (36.4).
     *
     * Called by the event observer when a revision is submitted, approved,
     * returned for changes, rejected or published. It writes only change-set
     * tables and fires no events, so there is no recursion.
     *
     * @param int $revisionid Revision id.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function sync_item_for_revision(int $revisionid, int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        $items = $DB->get_records('local_handbook_changeitem',
            ['revisionid' => $revisionid, 'kind' => self::KIND_PAGE_REVISION]);
        if (!$items) {
            return;
        }
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revisionid]);
        if (!$revision) {
            return;
        }
        $page = $DB->get_record('local_handbook_page', ['id' => $revision->pageid]);
        $target = self::item_status_for_revision($revision, $page);

        foreach ($items as $item) {
            if ($item->itemstatus === $target) {
                continue;
            }
            $item->itemstatus = $target;
            if ($target !== self::ITEM_CONFLICT) {
                $item->conflictnote = '';
            }
            $item->timemodified = time();
            $DB->update_record('local_handbook_changeitem', $item);
            self::recompute_status((int)$item->changesetid, $userid);
        }
    }

    /**
     * Recompute and persist a change set's status from its items (36.4).
     *
     * @param int $changesetid Change-set id.
     * @param int $userid Acting user (0 = current user).
     * @return void
     */
    public static function recompute_status(int $changesetid, int $userid = 0): void {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid]);
        if (!$changeset || $changeset->status === self::STATUS_CANCELLED) {
            return;
        }
        $items = $DB->get_records('local_handbook_changeitem', ['changesetid' => $changesetid]);
        if (!$items) {
            return;
        }

        $allterminal = true;
        $anyterminal = false;
        $anyactive = false;
        foreach ($items as $item) {
            $terminal = in_array($item->itemstatus, self::ITEM_TERMINAL, true);
            $allterminal = $allterminal && $terminal;
            $anyterminal = $anyterminal || $terminal;
            $anyactive = $anyactive || in_array($item->itemstatus, self::ITEM_ACTIVE, true);
        }

        if ($allterminal) {
            $new = self::STATUS_COMPLETED;
        } else if ($anyterminal) {
            $new = self::STATUS_PARTIALLY_COMPLETED;
        } else if ($anyactive) {
            $new = self::STATUS_IN_REVIEW;
        } else {
            $new = self::STATUS_DRAFT;
        }

        $update = new stdClass();
        $update->id = $changesetid;
        $update->status = $new;
        $update->timemodified = time();
        $update->modifiedby = $userid;
        if ($new === self::STATUS_COMPLETED && !$changeset->timecompleted) {
            $update->timecompleted = time();
        }
        if ($changeset->status !== $new || isset($update->timecompleted)) {
            $DB->update_record('local_handbook_changeset', $update);
        }
    }

    /**
     * Map a revision's workflow state to a change-item status.
     *
     * @param stdClass $revision Revision record.
     * @param stdClass|null $page Owning page record.
     * @return string One of the ITEM_* constants.
     */
    private static function item_status_for_revision(stdClass $revision, ?stdClass $page): string {
        if ($page && (int)$page->publishedrevisionid === (int)$revision->id) {
            return self::ITEM_PUBLISHED;
        }
        switch ($revision->status) {
            case page_service::STATUS_PUBLISHED:
            case page_service::STATUS_SUPERSEDED:
                return self::ITEM_PUBLISHED;
            case page_service::STATUS_IN_REVIEW:
                return self::ITEM_IN_REVIEW;
            case page_service::STATUS_APPROVED:
                return self::ITEM_APPROVED;
            case page_service::STATUS_REJECTED:
                return self::ITEM_REJECTED;
            default:
                // draft / changes_requested: editable again.
                return self::ITEM_DRAFT;
        }
    }

    /**
     * Record a conflict on the item and return its result.
     *
     * @param stdClass $changeset Change-set record.
     * @param int $pageid Page id.
     * @param int $revisionid Revision id to link (0 when we do not own it).
     * @param string $notekey Language key describing the conflict.
     * @param int $version Version number for the note placeholder.
     * @param string $changesummary Proposed change summary.
     * @param int $userid Acting user.
     * @param string $kind Item kind (default page_revision).
     * @return array Per-item result.
     */
    private static function record_conflict(stdClass $changeset, int $pageid, int $revisionid,
            string $notekey, int $version, string $changesummary, int $userid,
            string $kind = self::KIND_PAGE_REVISION): array {
        $note = get_string($notekey, 'local_handbook', $version);
        $item = self::write_item($changeset, $pageid, $revisionid, self::ITEM_CONFLICT,
            $note, $changesummary, $userid, $kind);
        return self::item_result($item, null);
    }

    /**
     * Insert or update a change item.
     *
     * A change set holds at most one item of a given kind per page, so the item
     * is keyed by (changesetid, pageid, kind). With the unique DB index relaxed
     * for polymorphism, that one-per-(page,kind) guarantee is enforced here.
     *
     * @param stdClass $changeset Change-set record.
     * @param int $pageid Page id (0 for an item with no bound page yet).
     * @param int $revisionid Working revision id (0 = none/unowned).
     * @param string $itemstatus Item status.
     * @param string $conflictnote Conflict note ('' when clear).
     * @param string $changesummary Change summary (kept if non-empty).
     * @param int $userid Acting user.
     * @param string $kind Item kind (default page_revision).
     * @param string|null $payloadjson Proposed change for non-revision kinds
     *        (null leaves any stored payload unchanged).
     * @return stdClass The change-item record.
     */
    private static function write_item(stdClass $changeset, int $pageid, int $revisionid,
            string $itemstatus, string $conflictnote, string $changesummary, int $userid,
            string $kind = self::KIND_PAGE_REVISION, ?string $payloadjson = null): stdClass {
        global $DB;

        $now = time();
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $pageid, 'kind' => $kind]);

        if ($item) {
            $item->revisionid = $revisionid;
            $item->itemstatus = $itemstatus;
            $item->conflictnote = $conflictnote;
            if (trim($changesummary) !== '') {
                $item->changesummary = $changesummary;
            }
            if ($payloadjson !== null) {
                $item->payloadjson = $payloadjson;
            }
            $item->timemodified = $now;
            $DB->update_record('local_handbook_changeitem', $item);
        } else {
            $sortorder = (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {local_handbook_changeitem} '
                . 'WHERE changesetid = ?', [$changeset->id]);
            $item = new stdClass();
            $item->changesetid = (int)$changeset->id;
            $item->kind = $kind;
            $item->pageid = $pageid;
            $item->revisionid = $revisionid;
            $item->itemstatus = $itemstatus;
            $item->payloadjson = $payloadjson;
            $item->changesummary = $changesummary;
            $item->conflictnote = $conflictnote;
            $item->sortorder = $sortorder;
            $item->timecreated = $now;
            $item->timemodified = $now;
            $item->id = $DB->insert_record('local_handbook_changeitem', $item);
        }

        $DB->update_record('local_handbook_changeset', (object)[
            'id' => $changeset->id, 'timemodified' => $now, 'modifiedby' => $userid,
        ]);

        return $item;
    }

    /**
     * Shape a per-item result for callers and the API.
     *
     * @param stdClass $item Change-item record.
     * @param stdClass|null $revision Associated revision (for timemodified).
     * @return array
     */
    private static function item_result(stdClass $item, ?stdClass $revision): array {
        return [
            'itemid' => (int)$item->id,
            'changesetid' => (int)$item->changesetid,
            'pageid' => (int)$item->pageid,
            'revisionid' => (int)$item->revisionid,
            'status' => $item->itemstatus,
            'conflictnote' => (string)$item->conflictnote,
            'timemodified' => (int)($revision->timemodified ?? 0),
        ];
    }
}
