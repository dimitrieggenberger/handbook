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
        return ['title', 'slug', 'summary', 'contenttype', 'authoritylevel', 'criticality',
            'responsiblearea', 'reviewdate', 'requiredreading'];
    }

    /**
     * @var string A change item that proposes a brand-new page. It has no page
     * id yet; it is identified within the change set by a tempkey and applied
     * (page created + first revision published) only by the human publish path.
     */
    public const KIND_PAGE_CREATE = 'page_create';

    /**
     * @var string A change item that proposes edits to a page's outgoing
     * typed relations (create/remove/retype), applied only by the human path.
     */
    public const KIND_RELATION_CHANGE = 'relation_change';

    /** @var string A change item that proposes a category create/update/move/merge (spec 11). */
    public const KIND_CATEGORY_CHANGE = 'category_change';

    /**
     * Category operations a proposal may carry (spec 11).
     *
     * @return string[]
     */
    public static function category_ops(): array {
        return ['create', 'update', 'move', 'merge', 'delete_empty'];
    }

    /** @var string A change item that proposes archiving a page (spec 21). */
    public const KIND_PAGE_ARCHIVE = 'page_archive';

    /** @var string A change item that proposes restoring an archived page (spec 26). */
    public const KIND_PAGE_RESTORE = 'page_restore';

    /** @var string A change item that proposes moving a page to another category. */
    public const KIND_PAGE_MOVE = 'page_move';

    /**
     * Structured archive reasons (spec 22). 'other' requires a note.
     *
     * @return string[]
     */
    public static function archive_reasons(): array {
        return ['obsolete', 'superseded', 'duplicate', 'merged', 'temporary_content_expired',
            'role_no_longer_exists', 'procedure_no_longer_used', 'incorrect_legacy_import', 'other'];
    }

    /**
     * Redirect behaviours for an archived page (spec 24).
     *
     * @return string[]
     */
    public static function redirect_modes(): array {
        return ['notice_only', 'redirect_with_notice', 'automatic_redirect', 'no_redirect'];
    }

    /**
     * Fields a new-page proposal may carry (spec 13).
     *
     * @return string[]
     */
    public static function new_page_fields(): array {
        return ['title', 'slug', 'summary', 'categoryid', 'content', 'contenttype',
            'authoritylevel', 'criticality', 'responsiblearea', 'reviewdate',
            'requiredreading', 'language'];
    }

    /**
     * Typed relation kinds a proposal may use (spec 9.2, 10).
     *
     * @return string[]
     */
    public static function relation_types(): array {
        return ['relatedto', 'dependson', 'implements', 'replaces', 'supersedes',
            'exceptionto', 'procedurefor', 'quickguidefor', 'templatefor',
            'assessmentfor', 'translationof'];
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
                case 'slug':
                    // Normalise to a URL-safe slug now; uniqueness is enforced
                    // at apply time (it may change between proposal and apply).
                    $slug = page_service::slugify((string)$value);
                    if ($slug === '') {
                        throw new moodle_exception('errormetadatavalue', 'local_handbook', '', $field);
                    }
                    $normalised['slug'] = $slug;
                    break;
                case 'summary':
                    $normalised['summary'] = (string)$value;
                    break;
                case 'responsiblearea':
                    // Route through the controlled vocabulary (throws if unknown
                    // once the catalogue is governed).
                    $normalised['responsiblearea'] = area_service::resolve_name((string)$value);
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
     * Create or update this change set's proposal for a brand-new page.
     *
     * The page is identified within the set by a stable tempkey; it is created
     * and its first revision published only by the human publish path.
     *
     * @param int $changesetid Change-set id.
     * @param string $tempkey Stable id within the set (e.g. newpage:slug).
     * @param array $data New-page fields (see new_page_fields()).
     * @param int $userid Acting user (0 = current user).
     * @return array Per-item result.
     */
    public static function upsert_new_page(int $changesetid, string $tempkey, array $data,
            int $userid = 0): array {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if (in_array($changeset->status, self::LOCKED_STATUSES, true)) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }

        $tempkey = self::normalise_tempkey($tempkey);
        $normalised = self::validate_new_page($data);
        $summary = get_string('newpagechangesummary', 'local_handbook', $normalised['title']);

        $item = self::write_item($changeset, 0, 0, self::ITEM_DRAFT, '', $summary,
            $userid, self::KIND_PAGE_CREATE, json_encode($normalised), $tempkey);
        return self::item_result($item, null);
    }

    /**
     * Validate and normalise a new-page proposal (throws on any problem).
     *
     * @param array $data Raw new-page fields.
     * @return array Normalised fields for page_service::create_page().
     */
    public static function validate_new_page(array $data): array {
        global $DB;

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '' || \core_text::strlen($title) > 255) {
            throw new moodle_exception('errormetadatavalue', 'local_handbook', '', 'title');
        }
        $categoryid = (int)($data['categoryid'] ?? 0);
        if (!$categoryid || !$DB->record_exists('local_handbook_category', ['id' => $categoryid])) {
            throw new moodle_exception('errornewpagecategory', 'local_handbook');
        }
        $content = (string)($data['content'] ?? '');
        if (trim($content) === '') {
            throw new moodle_exception('errornewpagecontent', 'local_handbook');
        }

        $out = [
            'title' => $title,
            'categoryid' => $categoryid,
            'content' => $content,
            'summary' => (string)($data['summary'] ?? ''),
        ];
        if (!empty($data['slug'])) {
            $out['slug'] = page_service::slugify((string)$data['slug']);
        }
        $out['contenttype'] = in_array($data['contenttype'] ?? null, page_service::content_types(), true)
            ? (string)$data['contenttype'] : 'procedure';
        $out['criticality'] = in_array($data['criticality'] ?? null, page_service::criticalities(), true)
            ? (string)$data['criticality'] : 'operational';
        $level = (int)($data['authoritylevel'] ?? 4);
        $out['authoritylevel'] = ($level >= 1 && $level <= 5) ? $level : 4;
        $out['reviewdate'] = max(0, (int)($data['reviewdate'] ?? 0));
        $out['requiredreading'] = (int)((bool)($data['requiredreading'] ?? false));
        $out['language'] = \core_text::substr(trim((string)($data['language'] ?? 'es')), 0, 10) ?: 'es';
        if (isset($data['responsiblearea']) && trim((string)$data['responsiblearea']) !== '') {
            $out['responsiblearea'] = area_service::resolve_name((string)$data['responsiblearea']);
        }
        return $out;
    }

    /**
     * Create or update this change set's proposed relation edits for one page.
     *
     * @param int $changesetid Change-set id.
     * @param int $sourcepageid The page whose outgoing relations change.
     * @param array $ops Operation list; each: op (create|remove), relationtype,
     *        and a target (targetpageid or targettempkey for a new page).
     * @param int $userid Acting user (0 = current user).
     * @return array Per-item result.
     */
    public static function upsert_relations(int $changesetid, int $sourcepageid, array $ops,
            int $userid = 0): array {
        global $DB, $USER;

        $userid = $userid ?: (int)$USER->id;
        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if (in_array($changeset->status, self::LOCKED_STATUSES, true)) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }
        $DB->get_record('local_handbook_page', ['id' => $sourcepageid], '*', MUST_EXIST);

        $normalised = self::validate_relations($sourcepageid, $ops);
        $summary = get_string('relationchangesummary', 'local_handbook', count($normalised));

        $item = self::write_item($changeset, $sourcepageid, 0, self::ITEM_DRAFT, '', $summary,
            $userid, self::KIND_RELATION_CHANGE, json_encode(['ops' => $normalised]));
        return self::item_result($item, null);
    }

    /**
     * Validate and normalise a relation operation list (throws on any problem).
     *
     * @param int $sourcepageid Source page id.
     * @param array $ops Raw operations.
     * @return array Normalised operations.
     */
    public static function validate_relations(int $sourcepageid, array $ops): array {
        global $DB;

        $out = [];
        foreach ($ops as $op) {
            $action = $op['op'] ?? '';
            if (!in_array($action, ['create', 'remove'], true)) {
                throw new moodle_exception('errorrelationop', 'local_handbook');
            }
            $type = $op['relationtype'] ?? '';
            if (!in_array($type, self::relation_types(), true)) {
                throw new moodle_exception('errorrelationtype', 'local_handbook', '', (string)$type);
            }
            $targetpageid = (int)($op['targetpageid'] ?? 0);
            $targettempkey = trim((string)($op['targettempkey'] ?? ''));
            if ($targetpageid) {
                if ($targetpageid === $sourcepageid) {
                    throw new moodle_exception('errorrelationself', 'local_handbook');
                }
                if (!$DB->record_exists('local_handbook_page', ['id' => $targetpageid])) {
                    throw new moodle_exception('errorrelationtarget', 'local_handbook');
                }
            } else if ($targettempkey === '') {
                throw new moodle_exception('errorrelationtarget', 'local_handbook');
            }
            $out[] = [
                'op' => $action,
                'relationtype' => $type,
                'targetpageid' => $targetpageid,
                'targettempkey' => $targettempkey,
            ];
        }
        if (!$out) {
            throw new moodle_exception('errorrelationempty', 'local_handbook');
        }
        return $out;
    }

    /**
     * Propose archiving a page (spec 21). Draft only — the state change and any
     * redirect are applied by the human publish path.
     *
     * @param int $changesetid Change-set id.
     * @param int $pageid Page to archive.
     * @param array $data reason, replacementpageid (0 = none), redirectmode, note.
     * @param int $userid Acting user (0 = current user).
     * @return array Per-item result.
     */
    public static function upsert_page_archive(int $changesetid, int $pageid, array $data,
            int $userid = 0): array {
        global $DB, $USER;
        $userid = $userid ?: (int)$USER->id;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if (in_array($changeset->status, self::LOCKED_STATUSES, true)) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }
        $page = $DB->get_record('local_handbook_page', ['id' => $pageid], '*', MUST_EXIST);

        $normalised = self::validate_archive($page, $data);
        $summary = get_string('archivechangesummary', 'local_handbook', format_string($page->title));

        $item = self::write_item($changeset, $pageid, 0, self::ITEM_DRAFT, '', $summary,
            $userid, self::KIND_PAGE_ARCHIVE, json_encode($normalised));
        return self::item_result($item, null);
    }

    /**
     * Validate and normalise an archive proposal (throws on any problem).
     *
     * @param stdClass $page Page being archived.
     * @param array $data Raw proposal.
     * @return array Normalised proposal.
     */
    public static function validate_archive(stdClass $page, array $data): array {
        global $DB;

        $reason = (string)($data['reason'] ?? '');
        if (!in_array($reason, self::archive_reasons(), true)) {
            throw new moodle_exception('errorarchivereason', 'local_handbook');
        }
        $note = trim((string)($data['note'] ?? ''));
        if ($reason === 'other' && $note === '') {
            throw new moodle_exception('errorarchivenote', 'local_handbook');
        }
        $mode = (string)($data['redirectmode'] ?? 'notice_only');
        if (!in_array($mode, self::redirect_modes(), true)) {
            throw new moodle_exception('errorredirectmode', 'local_handbook');
        }
        $replacementid = (int)($data['replacementpageid'] ?? 0);
        if ($replacementid) {
            if ($replacementid === (int)$page->id) {
                throw new moodle_exception('errorreplacementself', 'local_handbook');
            }
            $replacement = $DB->get_record('local_handbook_page', ['id' => $replacementid]);
            if (!$replacement || (int)$replacement->archived === 1) {
                throw new moodle_exception('errorreplacementinvalid', 'local_handbook');
            }
        }
        if (in_array($mode, ['redirect_with_notice', 'automatic_redirect'], true) && !$replacementid) {
            throw new moodle_exception('errorreplacementrequired', 'local_handbook');
        }
        return [
            'reason' => $reason,
            'replacementpageid' => $replacementid,
            'redirectmode' => $mode,
            'note' => $note,
        ];
    }

    /**
     * Propose restoring an archived page (spec 26). Draft only.
     *
     * @param int $changesetid Change-set id.
     * @param int $pageid Archived page to restore.
     * @param string $note Optional explanation.
     * @param int $userid Acting user (0 = current user).
     * @return array Per-item result.
     */
    public static function upsert_page_restore(int $changesetid, int $pageid, string $note = '',
            int $userid = 0): array {
        global $DB, $USER;
        $userid = $userid ?: (int)$USER->id;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if (in_array($changeset->status, self::LOCKED_STATUSES, true)) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }
        $page = $DB->get_record('local_handbook_page', ['id' => $pageid], '*', MUST_EXIST);
        if ((int)$page->archived !== 1) {
            throw new moodle_exception('errornotarchived', 'local_handbook');
        }

        $summary = get_string('restorechangesummary', 'local_handbook', format_string($page->title));
        $item = self::write_item($changeset, $pageid, 0, self::ITEM_DRAFT, '', $summary,
            $userid, self::KIND_PAGE_RESTORE, json_encode(['note' => trim($note)]));
        return self::item_result($item, null);
    }

    /**
     * Propose moving a page to another category (spec 4.1). Draft only — the
     * page id, slug, revisions, acknowledgements and relations are preserved;
     * only categoryid changes, applied by the human publish path.
     *
     * @param int $changesetid Change-set id.
     * @param int $pageid Page to move.
     * @param int $targetcategoryid Destination category.
     * @param int $expectedcategoryid Category the caller saw (0 = skip).
     * @param int $expectedpagetimemodified Page timemodified seen (0 = skip).
     * @param string $changesummary Optional summary.
     * @param int $userid Acting user (0 = current user).
     * @return array Per-item result.
     */
    public static function upsert_page_move(int $changesetid, int $pageid, int $targetcategoryid,
            int $expectedcategoryid = 0, int $expectedpagetimemodified = 0,
            string $changesummary = '', int $userid = 0): array {
        global $DB, $USER;
        $userid = $userid ?: (int)$USER->id;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if (in_array($changeset->status, self::LOCKED_STATUSES, true)) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }
        $page = $DB->get_record('local_handbook_page', ['id' => $pageid], '*', MUST_EXIST);
        if (!$DB->record_exists('local_handbook_category', ['id' => $targetcategoryid])) {
            throw new moodle_exception('errorcategorynotfound', 'local_handbook');
        }
        if ($targetcategoryid === (int)$page->categoryid) {
            throw new moodle_exception('errorpagemovesame', 'local_handbook');
        }

        // Optimistic concurrency: a page already moved or edited elsewhere
        // yields a structured conflict instead of a silent overwrite.
        if (($expectedcategoryid && (int)$page->categoryid !== $expectedcategoryid)
                || ($expectedpagetimemodified && (int)$page->timemodified !== $expectedpagetimemodified)) {
            return self::record_conflict($changeset, $pageid, 0, 'conflict_pagemove',
                0, $changesummary, $userid, self::KIND_PAGE_MOVE);
        }

        $payload = [
            'sourcecategoryid' => (int)$page->categoryid,
            'targetcategoryid' => $targetcategoryid,
            'expectedpagetimemodified' => $expectedpagetimemodified,
        ];
        $summary = trim($changesummary) !== '' ? $changesummary
            : get_string('pagemovechangesummary', 'local_handbook', format_string($page->title));
        $item = self::write_item($changeset, $pageid, 0, self::ITEM_DRAFT, '', $summary,
            $userid, self::KIND_PAGE_MOVE, json_encode($payload));
        return self::item_result($item, null);
    }

    /**
     * Impact of archiving a page: inbound relations, active-path memberships,
     * and whether it is required reading (spec 25). Read-only.
     *
     * @param int $pageid Page id.
     * @return array
     */
    public static function archive_impact(int $pageid): array {
        global $DB;

        $inboundrelations = $DB->count_records('local_handbook_relation', ['targetpageid' => $pageid]);
        $activepaths = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_handbook_pathitem} pi
               JOIN {local_handbook_path} p ON p.id = pi.pathid
              WHERE pi.pageid = ? AND p.active = 1", [$pageid]);
        $page = $DB->get_record('local_handbook_page', ['id' => $pageid], 'id, requiredreading', IGNORE_MISSING);

        return [
            'inboundrelations' => (int)$inboundrelations,
            'activepaths' => (int)$activepaths,
            'requiredreading' => $page ? (int)$page->requiredreading : 0,
        ];
    }

    /**
     * Create or update this change set's proposal for one category operation
     * (create/update/move/merge, spec 11). Draft only — applied by the human
     * publish path.
     *
     * @param int $changesetid Change-set id.
     * @param array $op Operation (see validate_category_op()).
     * @param int $userid Acting user (0 = current user).
     * @return array Per-item result.
     */
    public static function upsert_category(int $changesetid, array $op, int $userid = 0): array {
        global $DB, $USER;
        $userid = $userid ?: (int)$USER->id;

        $changeset = $DB->get_record('local_handbook_changeset', ['id' => $changesetid], '*', MUST_EXIST);
        if (in_array($changeset->status, self::LOCKED_STATUSES, true)) {
            throw new moodle_exception('errorchangesetlocked', 'local_handbook');
        }

        $normalised = self::validate_category_op($op);
        // New categories use the provided tempkey; operations on an existing
        // category are keyed by (category, op) so several may coexist.
        if ($normalised['op'] === 'create') {
            $tempkey = self::normalise_tempkey((string)($op['tempkey'] ?? ''));
        } else if ($normalised['op'] === 'merge') {
            $tempkey = 'cat:' . $normalised['sourceid'] . ':merge';
        } else {
            $tempkey = 'cat:' . $normalised['categoryid'] . ':' . $normalised['op'];
        }

        $summary = get_string('categorychangesummary_' . $normalised['op'], 'local_handbook');
        $item = self::write_item($changeset, 0, 0, self::ITEM_DRAFT, '', $summary,
            $userid, self::KIND_CATEGORY_CHANGE, json_encode($normalised), $tempkey);
        return self::item_result($item, null);
    }

    /**
     * Validate and normalise a category operation (throws on any problem).
     *
     * @param array $op Raw operation: op (create|update|move|merge) plus its
     *        fields (name/parentid/description/icon/visible/sortorder for
     *        create/update; categoryid/newparentid for move; sourceid/targetid
     *        for merge).
     * @return array Normalised operation.
     */
    public static function validate_category_op(array $op): array {
        global $DB;

        $action = (string)($op['op'] ?? '');
        if (!in_array($action, self::category_ops(), true)) {
            throw new moodle_exception('errorcategoryop', 'local_handbook');
        }

        if ($action === 'create') {
            $name = trim((string)($op['name'] ?? ''));
            if ($name === '' || \core_text::strlen($name) > 255) {
                throw new moodle_exception('errorcategoryname', 'local_handbook');
            }
            $parentid = (int)($op['parentid'] ?? 0);
            if ($parentid && !$DB->record_exists('local_handbook_category', ['id' => $parentid])) {
                throw new moodle_exception('errorcategoryparent', 'local_handbook');
            }
            return [
                'op' => 'create',
                'name' => $name,
                'slug' => !empty($op['slug']) ? page_service::slugify((string)$op['slug']) : '',
                'parentid' => $parentid,
                'description' => (string)($op['description'] ?? ''),
                'icon' => preg_match('/^fa-[a-z0-9-]+$/', trim((string)($op['icon'] ?? '')))
                    ? trim((string)$op['icon']) : '',
                'visible' => (int)((bool)($op['visible'] ?? true)),
                'sortorder' => (int)($op['sortorder'] ?? 0),
            ];
        }

        if ($action === 'update') {
            $categoryid = (int)($op['categoryid'] ?? 0);
            if (!$categoryid || !$DB->record_exists('local_handbook_category', ['id' => $categoryid])) {
                throw new moodle_exception('errorcategorynotfound', 'local_handbook');
            }
            $out = ['op' => 'update', 'categoryid' => $categoryid];
            if (isset($op['name'])) {
                $name = trim((string)$op['name']);
                if ($name === '' || \core_text::strlen($name) > 255) {
                    throw new moodle_exception('errorcategoryname', 'local_handbook');
                }
                $out['name'] = $name;
            }
            if (isset($op['description'])) {
                $out['description'] = (string)$op['description'];
            }
            if (isset($op['icon'])) {
                $out['icon'] = preg_match('/^fa-[a-z0-9-]+$/', trim((string)$op['icon']))
                    ? trim((string)$op['icon']) : '';
            }
            if (isset($op['visible'])) {
                $out['visible'] = (int)((bool)$op['visible']);
            }
            if (isset($op['sortorder'])) {
                $out['sortorder'] = (int)$op['sortorder'];
            }
            if (count($out) <= 2) {
                throw new moodle_exception('errorcategorynochange', 'local_handbook');
            }
            return $out;
        }

        if ($action === 'move') {
            $categoryid = (int)($op['categoryid'] ?? 0);
            if (!$categoryid || !$DB->record_exists('local_handbook_category', ['id' => $categoryid])) {
                throw new moodle_exception('errorcategorynotfound', 'local_handbook');
            }
            $newparentid = (int)($op['newparentid'] ?? 0);
            if ($newparentid) {
                if (!$DB->record_exists('local_handbook_category', ['id' => $newparentid])) {
                    throw new moodle_exception('errorcategoryparent', 'local_handbook');
                }
                if (self::category_is_descendant($newparentid, $categoryid)) {
                    throw new moodle_exception('errorcategorycycle', 'local_handbook');
                }
            }
            return ['op' => 'move', 'categoryid' => $categoryid, 'newparentid' => $newparentid];
        }

        if ($action === 'delete_empty') {
            $categoryid = (int)($op['categoryid'] ?? 0);
            if (!$categoryid || !$DB->record_exists('local_handbook_category', ['id' => $categoryid])) {
                throw new moodle_exception('errorcategorynotfound', 'local_handbook');
            }
            // Only a truly empty category may be dissolved (spec 4.2).
            if ($DB->record_exists('local_handbook_page', ['categoryid' => $categoryid])
                    || $DB->record_exists('local_handbook_category', ['parentid' => $categoryid])) {
                throw new moodle_exception('categorynotempty', 'local_handbook');
            }
            return ['op' => 'delete_empty', 'categoryid' => $categoryid];
        }

        // merge.
        $sourceid = (int)($op['sourceid'] ?? 0);
        $targetid = (int)($op['targetid'] ?? 0);
        if (!$sourceid || !$targetid
                || !$DB->record_exists('local_handbook_category', ['id' => $sourceid])
                || !$DB->record_exists('local_handbook_category', ['id' => $targetid])) {
            throw new moodle_exception('errorcategorynotfound', 'local_handbook');
        }
        if ($sourceid === $targetid) {
            throw new moodle_exception('errorcategorymergeself', 'local_handbook');
        }
        if (self::category_is_descendant($targetid, $sourceid)) {
            throw new moodle_exception('errorcategorycycle', 'local_handbook');
        }
        return ['op' => 'merge', 'sourceid' => $sourceid, 'targetid' => $targetid];
    }

    /**
     * Whether $candidateid is $ancestorid or a descendant of it (cycle guard).
     *
     * @param int $candidateid Category to test.
     * @param int $ancestorid Potential ancestor.
     * @return bool
     */
    private static function category_is_descendant(int $candidateid, int $ancestorid): bool {
        global $DB;

        $cur = $candidateid;
        $guard = 0;
        while ($cur && $guard++ < 100) {
            if ($cur === $ancestorid) {
                return true;
            }
            $cur = (int)$DB->get_field('local_handbook_category', 'parentid', ['id' => $cur]);
        }
        return false;
    }

    /**
     * Normalise a tempkey (non-empty, capped).
     *
     * @param string $tempkey Raw tempkey.
     * @return string
     */
    private static function normalise_tempkey(string $tempkey): string {
        $tempkey = trim($tempkey);
        if ($tempkey === '') {
            throw new moodle_exception('errortempkeyrequired', 'local_handbook');
        }
        return \core_text::substr($tempkey, 0, 100);
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
            case self::KIND_PAGE_CREATE:
                self::apply_page_create($item, $userid);
                break;
            case self::KIND_RELATION_CHANGE:
                self::apply_relations($item, $userid);
                break;
            case self::KIND_CATEGORY_CHANGE:
                self::apply_category($item, $userid);
                break;
            case self::KIND_PAGE_MOVE:
                self::apply_page_move($item, $userid);
                break;
            case self::KIND_PAGE_ARCHIVE:
                self::apply_page_archive($item, $userid);
                break;
            case self::KIND_PAGE_RESTORE:
                self::apply_page_restore($item, $userid);
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

        // A slug rename keeps the old slug resolvable and must stay unique.
        if (isset($normalised['slug']) && $normalised['slug'] !== $page->slug) {
            self::apply_slug_change($page, $normalised['slug'], $userid);
        } else {
            unset($normalised['slug']);
        }

        $update = (object)['id' => (int)$page->id];
        foreach ($normalised as $field => $value) {
            $update->$field = $value;
        }
        $update->timemodified = time();
        $update->modifiedby = $userid;
        $DB->update_record('local_handbook_page', $update);
    }

    /**
     * Apply a slug rename: verify uniqueness and register the old slug as an
     * alias so existing addresses keep resolving (spec 7.3).
     *
     * @param stdClass $page Page being renamed.
     * @param string $newslug New canonical slug.
     * @param int $userid Acting user.
     * @return void
     */
    private static function apply_slug_change(stdClass $page, string $newslug, int $userid): void {
        global $DB;

        if ($DB->record_exists_select('local_handbook_page', 'slug = :slug AND id <> :id',
                ['slug' => $newslug, 'id' => $page->id])) {
            throw new moodle_exception('errorslugtaken', 'local_handbook', '', $newslug);
        }
        if ($DB->record_exists_select('local_handbook_pagealias', 'oldslug = :slug AND pageid <> :id',
                ['slug' => $newslug, 'id' => $page->id])) {
            throw new moodle_exception('errorslugtaken', 'local_handbook', '', $newslug);
        }

        // Keep the current slug resolvable.
        if (!$DB->record_exists('local_handbook_pagealias', ['oldslug' => $page->slug])) {
            $DB->insert_record('local_handbook_pagealias', (object)[
                'pageid' => (int)$page->id,
                'oldslug' => $page->slug,
                'timecreated' => time(),
                'createdby' => $userid,
            ]);
        }
        // If the new slug was previously an alias for this page, it is canonical now.
        $DB->delete_records('local_handbook_pagealias',
            ['oldslug' => $newslug, 'pageid' => (int)$page->id]);
    }

    /**
     * Apply a new-page proposal: create the page and publish its first
     * revision through the governed workflow, then bind the item to the new id.
     *
     * @param stdClass $item Change-item record (kind page_create).
     * @param int $userid Human publisher.
     * @return void
     */
    private static function apply_page_create(stdClass $item, int $userid): void {
        global $DB;

        $data = json_decode((string)$item->payloadjson, true);
        if (!is_array($data)) {
            throw new moodle_exception('errornewpagecontent', 'local_handbook');
        }
        // Re-validate defensively (category may have changed since proposal).
        $data = self::validate_new_page($data);

        $record = (object)$data;
        $record->contentformat = FORMAT_HTML;
        $page = page_service::create_page($record, $userid);

        // Publish the first revision through the governed transitions.
        $revision = $page->draftrevision;
        page_service::submit_for_review($revision,
            get_string('newpagesubmitsummary', 'local_handbook'), $userid);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        page_service::approve($revision, $userid);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        page_service::publish($revision, $userid);

        // Bind the item to the created page so later items and the UI resolve it.
        $DB->update_record('local_handbook_changeitem', (object)[
            'id' => (int)$item->id, 'pageid' => (int)$page->id,
        ]);
        $item->pageid = (int)$page->id;
    }

    /**
     * Apply proposed relation edits to a page's outgoing relations.
     *
     * @param stdClass $item Change-item record (kind relation_change).
     * @param int $userid Human publisher.
     * @return void
     */
    private static function apply_relations(stdClass $item, int $userid): void {
        global $DB;

        $payload = json_decode((string)$item->payloadjson, true);
        $ops = is_array($payload) ? ($payload['ops'] ?? []) : [];
        $source = (int)$item->pageid;

        foreach ($ops as $op) {
            $targetid = (int)($op['targetpageid'] ?? 0);
            if (!$targetid && !empty($op['targettempkey'])) {
                $targetid = self::resolve_tempkey_page((int)$item->changesetid, (string)$op['targettempkey']);
            }
            if (!$targetid || !$DB->record_exists('local_handbook_page', ['id' => $targetid])) {
                throw new moodle_exception('errorrelationunresolved', 'local_handbook');
            }
            $type = (string)$op['relationtype'];
            $key = ['sourcepageid' => $source, 'relationtype' => $type, 'targetpageid' => $targetid];

            if ($op['op'] === 'create') {
                if (!$DB->record_exists('local_handbook_relation', $key)) {
                    $sortorder = (int)$DB->get_field_sql(
                        'SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {local_handbook_relation} '
                        . 'WHERE sourcepageid = ?', [$source]);
                    $DB->insert_record('local_handbook_relation', (object)[
                        'sourcepageid' => $source,
                        'targetpageid' => $targetid,
                        'relationtype' => $type,
                        'sortorder' => $sortorder,
                        'timecreated' => time(),
                        'createdby' => $userid,
                    ]);
                }
            } else {
                $DB->delete_records('local_handbook_relation', $key);
            }
        }
    }

    /**
     * Resolve a new-page tempkey to its created page id (0 = not applied yet).
     *
     * @param int $changesetid Change-set id.
     * @param string $tempkey New-page tempkey.
     * @return int Created page id.
     */
    private static function resolve_tempkey_page(int $changesetid, string $tempkey): int {
        global $DB;

        $item = $DB->get_record('local_handbook_changeitem', [
            'changesetid' => $changesetid, 'tempkey' => $tempkey, 'kind' => self::KIND_PAGE_CREATE,
        ]);
        if ($item && (int)$item->pageid > 0) {
            return (int)$item->pageid;
        }
        throw new moodle_exception('errorrelationunresolved', 'local_handbook', '', $tempkey);
    }

    /**
     * Apply a category operation (create/update/move/merge, spec 11).
     *
     * @param stdClass $item Change-item record (kind category_change).
     * @param int $userid Human publisher.
     * @return void
     */
    private static function apply_category(stdClass $item, int $userid): void {
        global $DB;

        $op = json_decode((string)$item->payloadjson, true);
        if (!is_array($op)) {
            throw new moodle_exception('errorcategoryop', 'local_handbook');
        }
        $op = self::validate_category_op($op);
        $now = time();

        switch ($op['op']) {
            case 'create':
                $slug = page_service::unique_slug('local_handbook_category',
                    page_service::slugify($op['slug'] !== '' ? $op['slug'] : $op['name']));
                $DB->insert_record('local_handbook_category', (object)[
                    'parentid' => (int)$op['parentid'],
                    'name' => $op['name'],
                    'slug' => $slug,
                    'description' => $op['description'],
                    'descriptionformat' => FORMAT_HTML,
                    'sortorder' => (int)$op['sortorder'],
                    'visible' => (int)$op['visible'],
                    'icon' => $op['icon'],
                    'audiencekey' => '',
                    'timecreated' => $now,
                    'timemodified' => $now,
                    'createdby' => $userid,
                    'modifiedby' => $userid,
                ]);
                break;

            case 'update':
                $update = (object)['id' => (int)$op['categoryid'],
                    'timemodified' => $now, 'modifiedby' => $userid];
                foreach (['name', 'description', 'icon', 'visible', 'sortorder'] as $field) {
                    if (array_key_exists($field, $op)) {
                        $update->$field = $op[$field];
                    }
                }
                $DB->update_record('local_handbook_category', $update);
                break;

            case 'move':
                if ($op['newparentid']
                        && self::category_is_descendant((int)$op['newparentid'], (int)$op['categoryid'])) {
                    throw new moodle_exception('errorcategorycycle', 'local_handbook');
                }
                $DB->update_record('local_handbook_category', (object)[
                    'id' => (int)$op['categoryid'], 'parentid' => (int)$op['newparentid'],
                    'timemodified' => $now, 'modifiedby' => $userid,
                ]);
                break;

            case 'merge':
                // Move the source's pages and child categories into the target,
                // then delete the now-empty source (no page is left orphaned).
                // Capture the moved pages first so their timestamp/attribution
                // can be updated (audit, spec 4.6).
                $movedpageids = $DB->get_fieldset_select('local_handbook_page', 'id',
                    'categoryid = ?', [(int)$op['sourceid']]);
                $DB->set_field('local_handbook_page', 'categoryid', (int)$op['targetid'],
                    ['categoryid' => (int)$op['sourceid']]);
                if ($movedpageids) {
                    [$insql, $inparams] = $DB->get_in_or_equal($movedpageids);
                    $DB->execute("UPDATE {local_handbook_page}
                                     SET timemodified = ?, modifiedby = ?
                                   WHERE id $insql",
                        array_merge([$now, $userid], $inparams));
                }
                $DB->set_field('local_handbook_category', 'parentid', (int)$op['targetid'],
                    ['parentid' => (int)$op['sourceid']]);
                $DB->delete_records('local_handbook_category', ['id' => (int)$op['sourceid']]);
                break;

            case 'delete_empty':
                // Re-check emptiness at apply time; a category that gained pages
                // or subcategories since the proposal must not be deleted.
                $catid = (int)$op['categoryid'];
                if ($DB->record_exists('local_handbook_page', ['categoryid' => $catid])
                        || $DB->record_exists('local_handbook_category', ['parentid' => $catid])) {
                    throw new moodle_exception('categorynotempty', 'local_handbook');
                }
                $DB->delete_records('local_handbook_category', ['id' => $catid]);
                break;
        }
    }

    /**
     * Apply a page move: change the page's category, preserving everything
     * else, and record a page-moved event with the before/after category.
     *
     * @param stdClass $item Change-item record (kind page_move).
     * @param int $userid Human publisher.
     * @return void
     */
    private static function apply_page_move(stdClass $item, int $userid): void {
        global $DB;

        $page = $DB->get_record('local_handbook_page', ['id' => $item->pageid], '*', MUST_EXIST);
        $payload = json_decode((string)$item->payloadjson, true);
        $targetid = is_array($payload) ? (int)($payload['targetcategoryid'] ?? 0) : 0;
        if (!$targetid || !$DB->record_exists('local_handbook_category', ['id' => $targetid])) {
            throw new moodle_exception('errorcategorynotfound', 'local_handbook');
        }

        $fromcat = (int)$page->categoryid;
        $DB->update_record('local_handbook_page', (object)[
            'id' => (int)$page->id, 'categoryid' => $targetid,
            'timemodified' => time(), 'modifiedby' => $userid,
        ]);

        \local_handbook\event\page_moved::create([
            'context' => context_system::instance(),
            'objectid' => (int)$page->id,
            'other' => ['fromcategoryid' => $fromcat, 'tocategoryid' => $targetid],
        ])->trigger();
    }

    /**
     * Apply an archive proposal: mark the page archived (governed transition)
     * and set the replacement/redirect lifecycle fields.
     *
     * @param stdClass $item Change-item record (kind page_archive).
     * @param int $userid Human publisher.
     * @return void
     */
    private static function apply_page_archive(stdClass $item, int $userid): void {
        global $DB;

        $page = $DB->get_record('local_handbook_page', ['id' => $item->pageid], '*', MUST_EXIST);
        $data = json_decode((string)$item->payloadjson, true);
        if (!is_array($data)) {
            throw new moodle_exception('errorarchivereason', 'local_handbook');
        }
        $data = self::validate_archive($page, $data);

        page_service::set_archived($page, true, $userid);
        $DB->update_record('local_handbook_page', (object)[
            'id' => (int)$page->id,
            'archivereason' => $data['reason'],
            'replacementpageid' => (int)$data['replacementpageid'],
            'redirectmode' => $data['redirectmode'],
            'archivenote' => $data['note'],
            'timemodified' => time(),
            'modifiedby' => $userid,
        ]);
    }

    /**
     * Apply a restore proposal: unarchive the page and clear its redirect
     * lifecycle fields so it behaves normally again.
     *
     * @param stdClass $item Change-item record (kind page_restore).
     * @param int $userid Human publisher.
     * @return void
     */
    private static function apply_page_restore(stdClass $item, int $userid): void {
        global $DB;

        $page = $DB->get_record('local_handbook_page', ['id' => $item->pageid], '*', MUST_EXIST);
        page_service::set_archived($page, false, $userid);
        $DB->update_record('local_handbook_page', (object)[
            'id' => (int)$page->id,
            'archivereason' => '',
            'replacementpageid' => 0,
            'redirectmode' => '',
            'archivenote' => null,
            'timemodified' => time(),
            'modifiedby' => $userid,
        ]);
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
     * @param string $tempkey Stable id within the set for a page-less item
     *        (new entities); items with a page id are keyed by page instead.
     * @return stdClass The change-item record.
     */
    private static function write_item(stdClass $changeset, int $pageid, int $revisionid,
            string $itemstatus, string $conflictnote, string $changesummary, int $userid,
            string $kind = self::KIND_PAGE_REVISION, ?string $payloadjson = null,
            string $tempkey = ''): stdClass {
        global $DB;

        $now = time();
        // Items bound to a page are keyed by (set, page, kind); page-less items
        // (new entities) are keyed by (set, tempkey, kind).
        $lookup = $pageid > 0
            ? ['changesetid' => $changeset->id, 'pageid' => $pageid, 'kind' => $kind]
            : ['changesetid' => $changeset->id, 'tempkey' => $tempkey, 'kind' => $kind];
        $item = $DB->get_record('local_handbook_changeitem', $lookup);

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
            $item->tempkey = $tempkey;
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
