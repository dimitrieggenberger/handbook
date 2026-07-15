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

use advanced_testcase;
use stdClass;

/**
 * Tests for the change-set service (specification 36, 29.1).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\changeset_service
 */
final class changeset_service_test extends advanced_testcase {

    /**
     * Create a category and return its id.
     *
     * @return int
     */
    private function create_category(): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('local_handbook_category', (object)[
            'parentid' => 0,
            'name' => 'Gobernanza',
            'slug' => 'gobernanza-' . random_string(6),
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'sortorder' => 0,
            'visible' => 1,
            'icon' => '',
            'audiencekey' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => 2,
            'modifiedby' => 2,
        ]);
    }

    /**
     * Create and publish a page; return the fresh page record.
     *
     * @param int $categoryid Category id.
     * @param string $title Page title.
     * @param int $userid Author (0 = current).
     * @return stdClass
     */
    private function publish_page(int $categoryid, string $title, int $userid = 0): stdClass {
        global $DB;

        $page = page_service::create_page((object)[
            'title' => $title,
            'categoryid' => $categoryid,
            'contenttype' => 'procedure',
            'summary' => 'Resumen.',
            'content' => '<h2>Original</h2><p>Contenido original.</p>',
            'contentformat' => FORMAT_HTML,
        ], $userid);

        $revision = $page->draftrevision;
        page_service::submit_for_review($revision, 'v1', $userid);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id]);
        page_service::approve($revision, $userid);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id]);
        page_service::publish($revision, $userid);

        return $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
    }

    /**
     * Count all revisions of a page.
     *
     * @param int $pageid Page id.
     * @return int
     */
    private function revision_count(int $pageid): int {
        global $DB;
        return $DB->count_records('local_handbook_revision', ['pageid' => $pageid]);
    }

    public function test_create_changeset(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $changeset = changeset_service::create((object)[
            'title' => 'Rename Coordinación Académica',
            'instructionsummary' => 'Terminology update across the handbook.',
            'source' => 'ai',
            'externalreference' => 'conv-123',
        ]);

        $this->assertSame(changeset_service::STATUS_DRAFT, $changeset->status);
        $this->assertSame('ai', $changeset->source);
        $this->assertGreaterThan(0, (int)$changeset->id);
    }

    public function test_upsert_creates_draft_for_published_page(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->publish_page($this->create_category(), 'Supervisión de recreos');
        $changeset = changeset_service::create((object)['title' => 'CS']);

        $result = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>Nuevo</h2><p>Contenido propuesto.</p>', FORMAT_HTML, 'Ajuste de zonas');

        $this->assertSame(changeset_service::ITEM_DRAFT, $result['status']);
        $this->assertGreaterThan(0, $result['revisionid']);
        // A new working revision (v2) was created; v1 stays published.
        $this->assertSame(2, $this->revision_count((int)$page->id));
        $revision = $DB->get_record('local_handbook_revision', ['id' => $result['revisionid']]);
        $this->assertStringContainsString('Contenido propuesto', $revision->content);
        $this->assertSame(page_service::STATUS_DRAFT, $revision->status);
    }

    public function test_upsert_multiple_pages_one_changeset(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $categoryid = $this->create_category();
        $p1 = $this->publish_page($categoryid, 'Página uno');
        $p2 = $this->publish_page($categoryid, 'Página dos');
        $changeset = changeset_service::create((object)['title' => 'Multi']);

        $r1 = changeset_service::upsert_draft((int)$changeset->id, (int)$p1->id,
            '<h2>Uno</h2><p>a</p>', FORMAT_HTML, 'c1');
        $r2 = changeset_service::upsert_draft((int)$changeset->id, (int)$p2->id,
            '<h2>Dos</h2><p>b</p>', FORMAT_HTML, 'c2');

        $this->assertSame(changeset_service::ITEM_DRAFT, $r1['status']);
        $this->assertSame(changeset_service::ITEM_DRAFT, $r2['status']);
        $full = changeset_service::get((int)$changeset->id);
        $this->assertCount(2, $full->items);
    }

    public function test_repeated_upsert_reuses_same_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->publish_page($this->create_category(), 'Reuso');
        $changeset = changeset_service::create((object)['title' => 'CS']);

        $first = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>V1</h2><p>uno</p>', FORMAT_HTML, 'primera');
        $second = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>V2</h2><p>dos</p>', FORMAT_HTML, 'segunda', 0, $first['timemodified']);

        // Same revision reused; no version churn.
        $this->assertSame($first['revisionid'], $second['revisionid']);
        $this->assertSame(changeset_service::ITEM_DRAFT, $second['status']);
        $this->assertSame(2, $this->revision_count((int)$page->id));
        $this->assertCount(1, changeset_service::get((int)$changeset->id)->items);
    }

    public function test_refuse_to_overwrite_human_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->publish_page($this->create_category(), 'Humano');
        // A draft created outside any change set (a human editing directly).
        page_service::create_revision_draft($page);

        $changeset = changeset_service::create((object)['title' => 'CS']);
        $result = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>IA</h2><p>x</p>', FORMAT_HTML, 'ia');

        $this->assertSame(changeset_service::ITEM_CONFLICT, $result['status']);
        $this->assertNotEmpty($result['conflictnote']);
        // No extra revision was created (still v1 + the human draft = 2).
        $this->assertSame(2, $this->revision_count((int)$page->id));
    }

    public function test_refuse_to_overwrite_foreign_changeset_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->publish_page($this->create_category(), 'Ajeno');
        $csa = changeset_service::create((object)['title' => 'A']);
        changeset_service::upsert_draft((int)$csa->id, (int)$page->id,
            '<h2>A</h2><p>a</p>', FORMAT_HTML, 'a');

        $csb = changeset_service::create((object)['title' => 'B']);
        $result = changeset_service::upsert_draft((int)$csb->id, (int)$page->id,
            '<h2>B</h2><p>b</p>', FORMAT_HTML, 'b');

        $this->assertSame(changeset_service::ITEM_CONFLICT, $result['status']);
        $this->assertNotEmpty($result['conflictnote']);
    }

    public function test_base_mismatch_conflict(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->publish_page($this->create_category(), 'Base');
        $changeset = changeset_service::create((object)['title' => 'CS']);

        $result = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>x</h2>', FORMAT_HTML, 'c', (int)$page->publishedrevisionid + 999);

        $this->assertSame(changeset_service::ITEM_CONFLICT, $result['status']);
        $this->assertNotEmpty($result['conflictnote']);
        // No draft created on a stale base.
        $this->assertSame(1, $this->revision_count((int)$page->id));
    }

    public function test_concurrency_conflict(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->publish_page($this->create_category(), 'Concurrencia');
        $changeset = changeset_service::create((object)['title' => 'CS']);

        $first = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>1</h2>', FORMAT_HTML, 'uno');
        // Stale token: someone else moved the draft on.
        $result = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>2</h2>', FORMAT_HTML, 'dos', 0, (int)$first['timemodified'] + 1000);

        $this->assertSame(changeset_service::ITEM_CONFLICT, $result['status']);
        $this->assertSame(2, $this->revision_count((int)$page->id));
    }

    public function test_submit_returns_partial_results(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $categoryid = $this->create_category();
        $good = $this->publish_page($categoryid, 'Buena');
        $blocked = $this->publish_page($categoryid, 'Bloqueada');
        // Give the blocked page a human draft so its upsert conflicts.
        page_service::create_revision_draft($blocked);

        $changeset = changeset_service::create((object)['title' => 'Parcial']);
        changeset_service::upsert_draft((int)$changeset->id, (int)$good->id,
            '<h2>ok</h2>', FORMAT_HTML, 'ok');
        changeset_service::upsert_draft((int)$changeset->id, (int)$blocked->id,
            '<h2>no</h2>', FORMAT_HTML, 'no');

        $results = changeset_service::submit((int)$changeset->id);

        $this->assertCount(2, $results);
        $bypage = [];
        foreach ($results as $r) {
            $bypage[$r['pageid']] = $r['status'];
        }
        $this->assertSame(changeset_service::ITEM_IN_REVIEW, $bypage[(int)$good->id]);
        $this->assertSame(changeset_service::ITEM_CONFLICT, $bypage[(int)$blocked->id]);
        // One item under review, one still conflicting: the set is in review.
        $changeset = changeset_service::get((int)$changeset->id);
        $this->assertSame(changeset_service::STATUS_IN_REVIEW, $changeset->status);
    }

    public function test_status_sync_through_approve_and_publish(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->publish_page($this->create_category(), 'Ciclo');
        $changeset = changeset_service::create((object)['title' => 'CS']);
        $r = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>nuevo</h2><p>y</p>', FORMAT_HTML, 'cambio');
        changeset_service::submit((int)$changeset->id);

        $this->assertSame(changeset_service::ITEM_IN_REVIEW, $this->item_status($changeset->id, $page->id));

        $revision = $DB->get_record('local_handbook_revision', ['id' => $r['revisionid']]);
        page_service::approve($revision);
        $this->assertSame(changeset_service::ITEM_APPROVED, $this->item_status($changeset->id, $page->id));

        $revision = $DB->get_record('local_handbook_revision', ['id' => $r['revisionid']]);
        page_service::publish($revision);
        $this->assertSame(changeset_service::ITEM_PUBLISHED, $this->item_status($changeset->id, $page->id));
        // Single item, now published -> the whole change set is completed.
        $this->assertSame(changeset_service::STATUS_COMPLETED,
            changeset_service::get((int)$changeset->id)->status);
    }

    public function test_status_sync_on_request_changes(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->publish_page($this->create_category(), 'Devolución');
        $changeset = changeset_service::create((object)['title' => 'CS']);
        $r = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>n</h2>', FORMAT_HTML, 'c');
        changeset_service::submit((int)$changeset->id);

        $revision = $DB->get_record('local_handbook_revision', ['id' => $r['revisionid']]);
        page_service::request_changes($revision, 'Necesita ajustes');

        // Returned for changes: editable again for the change set.
        $this->assertSame(changeset_service::ITEM_DRAFT, $this->item_status($changeset->id, $page->id));
    }

    public function test_status_sync_on_reject(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->publish_page($this->create_category(), 'Rechazo');
        $changeset = changeset_service::create((object)['title' => 'CS']);
        $r = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>n</h2>', FORMAT_HTML, 'c');
        changeset_service::submit((int)$changeset->id);

        $revision = $DB->get_record('local_handbook_revision', ['id' => $r['revisionid']]);
        page_service::reject($revision, 'No procede');

        $this->assertSame(changeset_service::ITEM_REJECTED, $this->item_status($changeset->id, $page->id));
    }

    public function test_published_author_is_approver_not_ai_creator(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // A service-like account drafts; a human (admin) approves.
        $ai = $this->getDataGenerator()->create_user();
        $page = $this->publish_page($this->create_category(), 'Autoría');

        $changeset = changeset_service::create((object)['title' => 'CS', 'source' => 'ai'], (int)$ai->id);
        $r = changeset_service::upsert_draft((int)$changeset->id, (int)$page->id,
            '<h2>ia</h2><p>z</p>', FORMAT_HTML, 'propuesta de la IA', 0, 0, null, (int)$ai->id);
        changeset_service::submit((int)$changeset->id, (int)$ai->id);

        // The human approver/publisher.
        $this->setAdminUser();
        $revision = $DB->get_record('local_handbook_revision', ['id' => $r['revisionid']]);
        page_service::approve($revision);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $r['revisionid']]);
        page_service::publish($revision);

        $revision = $DB->get_record('local_handbook_revision', ['id' => $r['revisionid']]);
        // Technical attribution stays truthful (the AI account); the public
        // author is the approving human — never createdby.
        $this->assertSame((int)$ai->id, (int)$revision->createdby);
        $this->assertSame((int)get_admin()->id, (int)$revision->authoruserid);
        $this->assertNotEquals((int)$revision->createdby, (int)$revision->authoruserid);
    }

    public function test_page_revision_item_uses_default_kind_and_empty_payload(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Política de evaluación');
        $changeset = changeset_service::create((object)['title' => 'Update', 'source' => 'ai']);

        changeset_service::upsert_draft($changeset->id, (int)$page->id,
            '<h2>Nuevo</h2><p>Texto.</p>', FORMAT_HTML, 'Cambio');

        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id], '*', MUST_EXIST);
        $this->assertSame(changeset_service::KIND_PAGE_REVISION, $item->kind);
        $this->assertSame('', $item->tempkey);
        $this->assertNull($item->payloadjson);
    }

    public function test_change_set_allows_several_item_kinds_for_one_page(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Estructura institucional');
        $changeset = changeset_service::create((object)['title' => 'Coordinated', 'source' => 'ai']);

        // A page_revision item for this page.
        changeset_service::upsert_draft($changeset->id, (int)$page->id,
            '<h2>Nuevo</h2><p>Texto.</p>', FORMAT_HTML, 'Contenido');

        // A second item of a different kind for the SAME page — impossible under
        // the old unique (changesetid, pageid) index; allowed after Phase 0.
        $now = time();
        $DB->insert_record('local_handbook_changeitem', (object)[
            'changesetid' => (int)$changeset->id,
            'kind' => 'page_metadata',
            'pageid' => (int)$page->id,
            'tempkey' => '',
            'revisionid' => 0,
            'itemstatus' => changeset_service::ITEM_DRAFT,
            'payloadjson' => json_encode(['title' => 'Nuevo título']),
            'changesummary' => 'Metadatos',
            'conflictnote' => '',
            'sortorder' => 5,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $this->assertSame(2, $DB->count_records('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id]));
    }

    public function test_upsert_metadata_stages_a_page_metadata_item(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Política de evaluación');
        $changeset = changeset_service::create((object)['title' => 'Fiche', 'source' => 'ai']);

        $result = changeset_service::upsert_metadata($changeset->id, (int)$page->id,
            ['title' => 'Evaluación del aprendizaje', 'requiredreading' => true]);

        $this->assertSame(changeset_service::ITEM_DRAFT, $result['status']);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id,
                'kind' => changeset_service::KIND_PAGE_METADATA], '*', MUST_EXIST);
        $this->assertSame(0, (int)$item->revisionid);
        $patch = json_decode((string)$item->payloadjson, true);
        $this->assertSame('Evaluación del aprendizaje', $patch['title']);
        $this->assertSame(1, $patch['requiredreading']);
    }

    public function test_upsert_metadata_rejects_unsupported_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Estructura institucional');
        $changeset = changeset_service::create((object)['title' => 'Bad', 'source' => 'ai']);

        $this->expectException(\moodle_exception::class);
        changeset_service::upsert_metadata($changeset->id, (int)$page->id, ['aiaccess' => 'excluded']);
    }

    public function test_metadata_apply_writes_the_page_row_only_after_human_publish(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Convivencia escolar');
        $changeset = changeset_service::create((object)['title' => 'Fiche', 'source' => 'ai']);

        changeset_service::upsert_metadata($changeset->id, (int)$page->id,
            ['title' => 'Política de convivencia', 'responsiblearea' => 'Coordinación de Bienestar',
                'requiredreading' => true]);

        // Staged only: the published page row is untouched.
        $this->assertSame('Convivencia escolar',
            (string)$DB->get_field('local_handbook_page', 'title', ['id' => $page->id]));

        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id,
                'kind' => changeset_service::KIND_PAGE_METADATA], '*', MUST_EXIST);
        $this->assertSame(changeset_service::ITEM_IN_REVIEW, $item->itemstatus);

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $updated = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertSame('Política de convivencia', $updated->title);
        $this->assertSame('Coordinación de Bienestar', $updated->responsiblearea);
        $this->assertSame(1, (int)$updated->requiredreading);
        $this->assertSame(changeset_service::ITEM_PUBLISHED,
            (string)$DB->get_field('local_handbook_changeitem', 'itemstatus', ['id' => $item->id]));
    }

    public function test_publish_item_requires_an_approved_item(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Modalidad híbrida');
        $changeset = changeset_service::create((object)['title' => 'Fiche', 'source' => 'ai']);
        changeset_service::upsert_metadata($changeset->id, (int)$page->id, ['title' => 'Nuevo']);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id,
                'kind' => changeset_service::KIND_PAGE_METADATA], '*', MUST_EXIST);

        // Still a draft (never approved): applying must be refused.
        $this->expectException(\moodle_exception::class);
        changeset_service::publish_item((int)$item->id);
    }

    public function test_metadata_upsert_flags_a_stale_fiche(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Calendario académico');
        $changeset = changeset_service::create((object)['title' => 'Fiche', 'source' => 'ai']);

        $result = changeset_service::upsert_metadata($changeset->id, (int)$page->id,
            ['title' => 'Nuevo'], (int)$page->timemodified + 1000);

        $this->assertSame(changeset_service::ITEM_CONFLICT, $result['status']);
    }

    /**
     * Read one item's status.
     *
     * @param int $changesetid Change-set id.
     * @param int $pageid Page id.
     * @return string
     */
    private function item_status(int $changesetid, int $pageid): string {
        global $DB;
        return (string)$DB->get_field('local_handbook_changeitem', 'itemstatus',
            ['changesetid' => $changesetid, 'pageid' => $pageid]);
    }
}
