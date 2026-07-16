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

    public function test_new_page_proposal_creates_and_publishes_on_apply(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $changeset = changeset_service::create((object)['title' => 'New pages', 'source' => 'ai']);

        changeset_service::upsert_new_page($changeset->id, 'newpage:direccion', [
            'title' => 'Dirección oficial',
            'categoryid' => $cat,
            'content' => '<h2>Introducción</h2><p>Texto.</p>',
            'summary' => 'Resumen.',
            'contenttype' => 'policy',
        ]);

        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'tempkey' => 'newpage:direccion',
                'kind' => changeset_service::KIND_PAGE_CREATE], '*', MUST_EXIST);
        $this->assertSame(changeset_service::ITEM_IN_REVIEW, $item->itemstatus);

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $item = $DB->get_record('local_handbook_changeitem', ['id' => $item->id], '*', MUST_EXIST);
        $this->assertSame(changeset_service::ITEM_PUBLISHED, $item->itemstatus);
        $this->assertGreaterThan(0, (int)$item->pageid);
        $page = $DB->get_record('local_handbook_page', ['id' => $item->pageid], '*', MUST_EXIST);
        $this->assertSame('Dirección oficial', $page->title);
        $this->assertGreaterThan(0, (int)$page->publishedrevisionid);
    }

    public function test_new_page_requires_a_category(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $changeset = changeset_service::create((object)['title' => 'New', 'source' => 'ai']);
        $this->expectException(\moodle_exception::class);
        changeset_service::upsert_new_page($changeset->id, 'newpage:x',
            ['title' => 'X', 'content' => '<p>y</p>']);
    }

    public function test_relation_proposal_creates_a_relation_on_apply(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $a = $this->publish_page($cat, 'Política');
        $b = $this->publish_page($cat, 'Procedimiento');
        $changeset = changeset_service::create((object)['title' => 'Relations', 'source' => 'ai']);

        changeset_service::upsert_relations($changeset->id, (int)$a->id, [
            ['op' => 'create', 'relationtype' => 'relatedto', 'targetpageid' => (int)$b->id],
        ]);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $a->id,
                'kind' => changeset_service::KIND_RELATION_CHANGE], '*', MUST_EXIST);
        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $this->assertTrue($DB->record_exists('local_handbook_relation',
            ['sourcepageid' => $a->id, 'relationtype' => 'relatedto', 'targetpageid' => $b->id]));
    }

    public function test_relation_to_new_page_resolves_tempkey_on_apply(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $a = $this->publish_page($cat, 'Política');
        $changeset = changeset_service::create((object)['title' => 'Coordinated', 'source' => 'ai']);

        changeset_service::upsert_new_page($changeset->id, 'newpage:b', [
            'title' => 'Guía nueva', 'categoryid' => $cat, 'content' => '<h2>x</h2><p>y</p>']);
        changeset_service::upsert_relations($changeset->id, (int)$a->id, [
            ['op' => 'create', 'relationtype' => 'quickguidefor',
                'targetpageid' => 0, 'targettempkey' => 'newpage:b'],
        ]);
        changeset_service::submit($changeset->id);

        // The new page must be applied before the relation can resolve.
        $np = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'tempkey' => 'newpage:b',
                'kind' => changeset_service::KIND_PAGE_CREATE], '*', MUST_EXIST);
        changeset_service::approve_item((int)$np->id);
        changeset_service::publish_item((int)$np->id);
        $np = $DB->get_record('local_handbook_changeitem', ['id' => $np->id], '*', MUST_EXIST);

        $rel = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $a->id,
                'kind' => changeset_service::KIND_RELATION_CHANGE], '*', MUST_EXIST);
        changeset_service::approve_item((int)$rel->id);
        changeset_service::publish_item((int)$rel->id);

        $this->assertTrue($DB->record_exists('local_handbook_relation',
            ['sourcepageid' => $a->id, 'relationtype' => 'quickguidefor',
                'targetpageid' => (int)$np->pageid]));
    }

    public function test_slug_rename_registers_an_alias_on_apply(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Reglamento interno');
        $oldslug = $page->slug;
        $changeset = changeset_service::create((object)['title' => 'Rename', 'source' => 'ai']);

        changeset_service::upsert_metadata($changeset->id, (int)$page->id, ['slug' => 'reglamento-2027']);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id,
                'kind' => changeset_service::KIND_PAGE_METADATA], '*', MUST_EXIST);
        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $updated = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertSame('reglamento-2027', $updated->slug);
        $this->assertTrue($DB->record_exists('local_handbook_pagealias',
            ['oldslug' => $oldslug, 'pageid' => $page->id]));
    }

    public function test_archive_proposal_sets_lifecycle_fields_on_apply(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Reglamento antiguo');
        $rep = $this->publish_page($cat, 'Reglamento vigente');
        $changeset = changeset_service::create((object)['title' => 'Retire', 'source' => 'ai']);

        changeset_service::upsert_page_archive($changeset->id, (int)$page->id, [
            'reason' => 'superseded',
            'replacementpageid' => (int)$rep->id,
            'redirectmode' => 'redirect_with_notice',
            'note' => 'Replaced for 2027.',
        ]);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id,
                'kind' => changeset_service::KIND_PAGE_ARCHIVE], '*', MUST_EXIST);

        // Staged only: still published/visible before apply.
        $this->assertSame(0, (int)$DB->get_field('local_handbook_page', 'archived', ['id' => $page->id]));

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $updated = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertSame(1, (int)$updated->archived);
        $this->assertSame('superseded', $updated->archivereason);
        $this->assertSame((int)$rep->id, (int)$updated->replacementpageid);
        $this->assertSame('redirect_with_notice', $updated->redirectmode);
    }

    public function test_archive_rejects_invalid_reason(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Página');
        $changeset = changeset_service::create((object)['title' => 'x', 'source' => 'ai']);

        $this->expectException(\moodle_exception::class);
        changeset_service::upsert_page_archive($changeset->id, (int)$page->id, ['reason' => 'bogus']);
    }

    public function test_redirecting_archive_requires_a_replacement(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Página');
        $changeset = changeset_service::create((object)['title' => 'x', 'source' => 'ai']);

        $this->expectException(\moodle_exception::class);
        changeset_service::upsert_page_archive($changeset->id, (int)$page->id,
            ['reason' => 'obsolete', 'redirectmode' => 'automatic_redirect']);
    }

    public function test_restore_proposal_unarchives_on_apply(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Archivada');
        page_service::set_archived($page, true);

        $changeset = changeset_service::create((object)['title' => 'Bring back', 'source' => 'ai']);
        changeset_service::upsert_page_restore($changeset->id, (int)$page->id, 'Still needed.');
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id,
                'kind' => changeset_service::KIND_PAGE_RESTORE], '*', MUST_EXIST);

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $this->assertSame(0, (int)$DB->get_field('local_handbook_page', 'archived', ['id' => $page->id]));
    }

    public function test_category_create_applies(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $changeset = changeset_service::create((object)['title' => 'Cats', 'source' => 'ai']);
        changeset_service::upsert_category($changeset->id,
            ['op' => 'create', 'tempkey' => 'newcat:gob', 'name' => 'Gobernanza nueva']);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'tempkey' => 'newcat:gob',
                'kind' => changeset_service::KIND_CATEGORY_CHANGE], '*', MUST_EXIST);

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $this->assertTrue($DB->record_exists('local_handbook_category', ['name' => 'Gobernanza nueva']));
    }

    public function test_category_move_rejects_a_cycle(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $parent = $this->create_category();
        $now = time();
        $child = (int)$DB->insert_record('local_handbook_category', (object)[
            'parentid' => $parent, 'name' => 'Hija', 'slug' => 'hija-' . random_string(5),
            'description' => '', 'descriptionformat' => FORMAT_HTML, 'sortorder' => 0, 'visible' => 1,
            'icon' => '', 'audiencekey' => '', 'timecreated' => $now, 'timemodified' => $now,
            'createdby' => 2, 'modifiedby' => 2,
        ]);

        $changeset = changeset_service::create((object)['title' => 'x', 'source' => 'ai']);
        // Moving the parent under its own child would create a cycle.
        $this->expectException(\moodle_exception::class);
        changeset_service::upsert_category($changeset->id,
            ['op' => 'move', 'categoryid' => $parent, 'newparentid' => $child]);
    }

    public function test_category_merge_moves_pages_and_deletes_source(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $source = $this->create_category();
        $target = $this->create_category();
        $page = $this->publish_page($source, 'Página en origen');

        $changeset = changeset_service::create((object)['title' => 'Merge', 'source' => 'ai']);
        changeset_service::upsert_category($changeset->id,
            ['op' => 'merge', 'sourceid' => $source, 'targetid' => $target]);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'tempkey' => 'cat:' . $source . ':merge',
                'kind' => changeset_service::KIND_CATEGORY_CHANGE], '*', MUST_EXIST);

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $this->assertFalse($DB->record_exists('local_handbook_category', ['id' => $source]));
        $this->assertSame($target,
            (int)$DB->get_field('local_handbook_page', 'categoryid', ['id' => $page->id]));
    }

    public function test_page_move_applies_and_preserves_the_page(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $from = $this->create_category();
        $to = $this->create_category();
        $page = $this->publish_page($from, 'Página movible');
        $publishedrev = (int)$page->publishedrevisionid;

        $changeset = changeset_service::create((object)['title' => 'Move', 'source' => 'ai']);
        changeset_service::upsert_page_move($changeset->id, (int)$page->id, $to);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id,
                'kind' => changeset_service::KIND_PAGE_MOVE], '*', MUST_EXIST);

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $updated = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertSame($to, (int)$updated->categoryid);
        // Identity and history are preserved by a move.
        $this->assertSame($page->slug, $updated->slug);
        $this->assertSame($publishedrev, (int)$updated->publishedrevisionid);
    }

    public function test_page_move_flags_a_stale_category(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $from = $this->create_category();
        $to = $this->create_category();
        $page = $this->publish_page($from, 'Página');
        $changeset = changeset_service::create((object)['title' => 'Move', 'source' => 'ai']);

        // Expected category does not match the page's real one.
        $result = changeset_service::upsert_page_move($changeset->id, (int)$page->id, $to, 999999);
        $this->assertSame(changeset_service::ITEM_CONFLICT, $result['status']);
    }

    public function test_delete_empty_category_applies(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $empty = $this->create_category();
        $changeset = changeset_service::create((object)['title' => 'Tidy', 'source' => 'ai']);
        changeset_service::upsert_category($changeset->id,
            ['op' => 'delete_empty', 'categoryid' => $empty]);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'tempkey' => 'cat:' . $empty . ':delete_empty',
                'kind' => changeset_service::KIND_CATEGORY_CHANGE], '*', MUST_EXIST);

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $this->assertFalse($DB->record_exists('local_handbook_category', ['id' => $empty]));
    }

    public function test_delete_empty_rejects_a_non_empty_category(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $this->publish_page($cat, 'Con contenido');
        $changeset = changeset_service::create((object)['title' => 'x', 'source' => 'ai']);

        $this->expectException(\moodle_exception::class);
        changeset_service::upsert_category($changeset->id,
            ['op' => 'delete_empty', 'categoryid' => $cat]);
    }

    public function test_category_slug_rename_registers_an_alias(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $oldslug = (string)$DB->get_field('local_handbook_category', 'slug', ['id' => $cat]);
        $changeset = changeset_service::create((object)['title' => 'Rename', 'source' => 'ai']);
        changeset_service::upsert_category($changeset->id,
            ['op' => 'update', 'categoryid' => $cat, 'slug' => 'nuevo-slug-cat']);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'tempkey' => 'cat:' . $cat . ':update',
                'kind' => changeset_service::KIND_CATEGORY_CHANGE], '*', MUST_EXIST);

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $this->assertSame('nuevo-slug-cat',
            (string)$DB->get_field('local_handbook_category', 'slug', ['id' => $cat]));
        $this->assertTrue($DB->record_exists('local_handbook_categoryalias',
            ['oldslug' => $oldslug, 'categoryid' => $cat]));
    }

    public function test_page_move_into_a_proposed_category_resolves_the_tempkey(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $from = $this->create_category();
        $page = $this->publish_page($from, 'Movible');
        $changeset = changeset_service::create((object)['title' => 'Migrate', 'source' => 'ai']);

        changeset_service::upsert_category($changeset->id,
            ['op' => 'create', 'tempkey' => 'newcat:destino', 'name' => 'Destino nuevo']);
        changeset_service::upsert_page_move($changeset->id, (int)$page->id, 0, 0, 0, '', 0,
            'newcat:destino');
        changeset_service::submit($changeset->id);

        // Apply the category creation first so the tempkey resolves.
        $catitem = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'tempkey' => 'newcat:destino',
                'kind' => changeset_service::KIND_CATEGORY_CHANGE], '*', MUST_EXIST);
        changeset_service::approve_item((int)$catitem->id);
        changeset_service::publish_item((int)$catitem->id);
        $newcatid = (int)$DB->get_field('local_handbook_tempref', 'entityid',
            ['changesetid' => $changeset->id, 'tempkey' => 'newcat:destino']);
        $this->assertGreaterThan(0, $newcatid);

        // Then the page move resolves to the created category.
        $moveitem = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'pageid' => $page->id,
                'kind' => changeset_service::KIND_PAGE_MOVE], '*', MUST_EXIST);
        changeset_service::approve_item((int)$moveitem->id);
        changeset_service::publish_item((int)$moveitem->id);

        $this->assertSame($newcatid,
            (int)$DB->get_field('local_handbook_page', 'categoryid', ['id' => $page->id]));
    }

    public function test_category_created_under_a_proposed_parent(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $changeset = changeset_service::create((object)['title' => 'Tree', 'source' => 'ai']);
        changeset_service::upsert_category($changeset->id,
            ['op' => 'create', 'tempkey' => 'newcat:parent', 'name' => 'Padre']);
        changeset_service::upsert_category($changeset->id,
            ['op' => 'create', 'tempkey' => 'newcat:child', 'name' => 'Hija',
                'parenttempkey' => 'newcat:parent']);
        changeset_service::submit($changeset->id);

        $parentitem = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'tempkey' => 'newcat:parent',
                'kind' => changeset_service::KIND_CATEGORY_CHANGE], '*', MUST_EXIST);
        changeset_service::approve_item((int)$parentitem->id);
        changeset_service::publish_item((int)$parentitem->id);
        $parentid = (int)$DB->get_field('local_handbook_tempref', 'entityid',
            ['changesetid' => $changeset->id, 'tempkey' => 'newcat:parent']);

        $childitem = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'tempkey' => 'newcat:child',
                'kind' => changeset_service::KIND_CATEGORY_CHANGE], '*', MUST_EXIST);
        changeset_service::approve_item((int)$childitem->id);
        changeset_service::publish_item((int)$childitem->id);
        $childid = (int)$DB->get_field('local_handbook_tempref', 'entityid',
            ['changesetid' => $changeset->id, 'tempkey' => 'newcat:child']);

        $this->assertSame($parentid,
            (int)$DB->get_field('local_handbook_category', 'parentid', ['id' => $childid]));
    }

    public function test_approve_all_then_publish_all_applies_a_metadata_item(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Ficha');
        $changeset = changeset_service::create((object)['title' => 'Batch', 'source' => 'ai']);
        changeset_service::upsert_metadata($changeset->id, (int)$page->id, ['title' => 'Título nuevo']);
        changeset_service::submit($changeset->id);

        changeset_service::approve_all($changeset->id);
        changeset_service::publish_all($changeset->id);

        $this->assertSame('Título nuevo',
            (string)$DB->get_field('local_handbook_page', 'title', ['id' => $page->id]));
        $this->assertSame(changeset_service::STATUS_COMPLETED,
            (string)$DB->get_field('local_handbook_changeset', 'status', ['id' => $changeset->id]));
    }

    public function test_publish_all_applies_items_in_dependency_order(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a category and move a page into it — in ONE set. publish_all
        // must apply the category creation before the page move.
        $from = $this->create_category();
        $page = $this->publish_page($from, 'Migrable');
        $changeset = changeset_service::create((object)['title' => 'Migration', 'source' => 'ai']);
        changeset_service::upsert_category($changeset->id,
            ['op' => 'create', 'tempkey' => 'newcat:dest', 'name' => 'Destino']);
        changeset_service::upsert_page_move($changeset->id, (int)$page->id, 0, 0, 0, '', 0,
            'newcat:dest');
        changeset_service::submit($changeset->id);

        changeset_service::approve_all($changeset->id);
        changeset_service::publish_all($changeset->id);

        $newcatid = (int)$DB->get_field('local_handbook_tempref', 'entityid',
            ['changesetid' => $changeset->id, 'tempkey' => 'newcat:dest']);
        $this->assertGreaterThan(0, $newcatid);
        $this->assertSame($newcatid,
            (int)$DB->get_field('local_handbook_page', 'categoryid', ['id' => $page->id]));
    }

    public function test_validate_all_reports_ok_for_a_valid_item(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Validable');
        $changeset = changeset_service::create((object)['title' => 'V', 'source' => 'ai']);
        changeset_service::upsert_metadata($changeset->id, (int)$page->id, ['title' => 'X']);

        $results = changeset_service::validate_all($changeset->id);
        $this->assertNotEmpty($results);
        $this->assertTrue($results[0]['ok']);
        $this->assertSame('', $results[0]['error']);
    }

    public function test_reading_path_create_applies_on_publish(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $one = $this->publish_page($cat, 'Bienvenida');
        $two = $this->publish_page($cat, 'Reglamento');

        $changeset = changeset_service::create((object)['title' => 'Path', 'source' => 'ai']);
        changeset_service::upsert_reading_path($changeset->id, [
            'name' => 'Onboarding docentes',
            'pathtype' => 'onboarding',
            'schoolyear' => '2025-2026',
            'estimatedminutes' => 45,
            'sections' => [
                ['name' => 'Primer día', 'items' => [
                    ['pageid' => (int)$one->id, 'required' => true, 'rationale' => 'Empezar aquí'],
                    ['pageid' => (int)$two->id, 'required' => false],
                ]],
            ],
        ]);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'kind' => changeset_service::KIND_READING_PATH],
            '*', MUST_EXIST);

        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $path = $DB->get_record('local_handbook_path', ['name' => 'Onboarding docentes'], '*', MUST_EXIST);
        $this->assertSame('onboarding', (string)$path->pathtype);
        $this->assertSame(45, (int)$path->estimatedminutes);

        $rows = array_values($DB->get_records('local_handbook_pathitem',
            ['pathid' => $path->id], 'sortorder ASC'));
        $this->assertCount(2, $rows);
        $this->assertSame((int)$one->id, (int)$rows[0]->pageid);
        $this->assertSame('Primer día', (string)$rows[0]->sectionname);
        $this->assertSame(1, (int)$rows[0]->required);
        $this->assertSame('Empezar aquí', (string)$rows[0]->rationale);
        $this->assertSame(0, (int)$rows[1]->required);
        // The created path is resolvable by its tempkey.
        $this->assertSame((int)$path->id, (int)$DB->get_field('local_handbook_tempref', 'entityid',
            ['changesetid' => $changeset->id, 'tempkey' => 'newpath:' . $path->slug]));
    }

    public function test_reading_path_update_replaces_items_wholesale(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $a = $this->publish_page($cat, 'A');
        $b = $this->publish_page($cat, 'B');
        $c = $this->publish_page($cat, 'C');

        // Seed an existing path with A and B.
        $now = time();
        $pathid = (int)$DB->insert_record('local_handbook_path', (object)[
            'name' => 'Ruta', 'slug' => 'ruta-' . random_string(5), 'description' => '',
            'descriptionformat' => FORMAT_HTML, 'audiencejson' => '', 'schoolyear' => '',
            'active' => 1, 'pathtype' => '', 'estimatedminutes' => 0, 'reviewdate' => 0,
            'quizcmid' => 0, 'timecreated' => $now, 'timemodified' => $now,
            'createdby' => 2, 'modifiedby' => 2,
        ]);
        foreach ([$a, $b] as $i => $page) {
            $DB->insert_record('local_handbook_pathitem', (object)[
                'pathid' => $pathid, 'pageid' => (int)$page->id, 'sectionname' => '',
                'sortorder' => $i, 'required' => 1, 'quizcmid' => 0, 'rationale' => null,
            ]);
        }

        // Propose a snapshot with only B and C.
        $changeset = changeset_service::create((object)['title' => 'Edit', 'source' => 'ai']);
        changeset_service::upsert_reading_path($changeset->id, [
            'pathid' => $pathid,
            'name' => 'Ruta renombrada',
            'sections' => [
                ['name' => '', 'items' => [
                    ['pageid' => (int)$b->id, 'required' => true],
                    ['pageid' => (int)$c->id, 'required' => true],
                ]],
            ],
        ]);
        changeset_service::submit($changeset->id);
        $item = $DB->get_record('local_handbook_changeitem',
            ['changesetid' => $changeset->id, 'kind' => changeset_service::KIND_READING_PATH],
            '*', MUST_EXIST);
        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);

        $this->assertSame('Ruta renombrada',
            (string)$DB->get_field('local_handbook_path', 'name', ['id' => $pathid]));
        $pageids = $DB->get_fieldset_select('local_handbook_pathitem', 'pageid',
            'pathid = ? ORDER BY sortorder ASC', [$pathid]);
        $this->assertSame([(int)$b->id, (int)$c->id], array_map('intval', $pageids));
    }

    public function test_reading_path_snapshot_round_trips(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Única');
        $now = time();
        $pathid = (int)$DB->insert_record('local_handbook_path', (object)[
            'name' => 'Snap', 'slug' => 'snap-' . random_string(5), 'description' => 'D',
            'descriptionformat' => FORMAT_HTML, 'audiencejson' => '', 'schoolyear' => '2024',
            'active' => 1, 'pathtype' => 'refresher', 'estimatedminutes' => 12, 'reviewdate' => 0,
            'quizcmid' => 0, 'timecreated' => $now, 'timemodified' => $now,
            'createdby' => 2, 'modifiedby' => 2,
        ]);
        $DB->insert_record('local_handbook_pathitem', (object)[
            'pathid' => $pathid, 'pageid' => (int)$page->id, 'sectionname' => 'S1',
            'sortorder' => 0, 'required' => 1, 'quizcmid' => 0, 'rationale' => 'porque sí',
        ]);

        $snapshot = changeset_service::reading_path_snapshot($pathid);
        $this->assertSame('refresher', $snapshot['pathtype']);
        $this->assertSame(12, $snapshot['estimatedminutes']);
        $this->assertCount(1, $snapshot['sections']);
        $this->assertSame('S1', $snapshot['sections'][0]['name']);
        $this->assertSame((int)$page->id, $snapshot['sections'][0]['items'][0]['pageid']);
        $this->assertSame('porque sí', $snapshot['sections'][0]['items'][0]['rationale']);
    }

    public function test_reading_path_item_resolves_a_new_page_tempkey(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $changeset = changeset_service::create((object)['title' => 'Combo', 'source' => 'ai']);

        // A new page proposed in the same set, referenced by the path.
        changeset_service::upsert_new_page($changeset->id, 'newpage:intro', [
            'title' => 'Introducción', 'categoryid' => $cat,
            'content' => '<h2>Hola</h2><p>Bienvenido.</p>',
        ]);
        changeset_service::upsert_reading_path($changeset->id, [
            'name' => 'Ruta con página nueva',
            'sections' => [
                ['name' => '', 'items' => [
                    ['pagetempkey' => 'newpage:intro', 'required' => true],
                ]],
            ],
        ]);
        changeset_service::submit($changeset->id);

        changeset_service::approve_all($changeset->id);
        changeset_service::publish_all($changeset->id);

        $path = $DB->get_record('local_handbook_path',
            ['name' => 'Ruta con página nueva'], '*', MUST_EXIST);
        $newpageid = (int)$DB->get_field('local_handbook_page', 'id', ['title' => 'Introducción']);
        $this->assertGreaterThan(0, $newpageid);
        $this->assertSame($newpageid, (int)$DB->get_field('local_handbook_pathitem', 'pageid',
            ['pathid' => $path->id]));
    }

    public function test_reading_path_flags_a_stale_path(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'P');
        $now = time();
        $pathid = (int)$DB->insert_record('local_handbook_path', (object)[
            'name' => 'Stale', 'slug' => 'stale-' . random_string(5), 'description' => '',
            'descriptionformat' => FORMAT_HTML, 'audiencejson' => '', 'schoolyear' => '',
            'active' => 1, 'pathtype' => '', 'estimatedminutes' => 0, 'reviewdate' => 0,
            'quizcmid' => 0, 'timecreated' => $now, 'timemodified' => $now,
            'createdby' => 2, 'modifiedby' => 2,
        ]);

        $changeset = changeset_service::create((object)['title' => 'x', 'source' => 'ai']);
        $result = changeset_service::upsert_reading_path($changeset->id, [
            'pathid' => $pathid,
            'name' => 'Stale',
            'expectedtimemodified' => 999999,
            'sections' => [['name' => '', 'items' => [['pageid' => (int)$page->id]]]],
        ]);
        $this->assertSame(changeset_service::ITEM_CONFLICT, $result['status']);
    }

    public function test_reading_path_rejects_a_duplicate_page(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $page = $this->publish_page($cat, 'Dup');
        $changeset = changeset_service::create((object)['title' => 'x', 'source' => 'ai']);

        $this->expectException(\moodle_exception::class);
        changeset_service::upsert_reading_path($changeset->id, [
            'name' => 'Duplicada',
            'sections' => [
                ['name' => 'A', 'items' => [['pageid' => (int)$page->id]]],
                ['name' => 'B', 'items' => [['pageid' => (int)$page->id]]],
            ],
        ]);
    }

    public function test_reading_path_diff_reports_added_removed_and_changes(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $cat = $this->create_category();
        $a = $this->publish_page($cat, 'A');
        $b = $this->publish_page($cat, 'B');
        $c = $this->publish_page($cat, 'C');

        // Current path: A (required, "Intro") and B (required, "Intro").
        $now = time();
        $pathid = (int)$DB->insert_record('local_handbook_path', (object)[
            'name' => 'Ruta', 'slug' => 'ruta-' . random_string(5), 'description' => '',
            'descriptionformat' => FORMAT_HTML, 'audiencejson' => '', 'schoolyear' => '2024',
            'active' => 1, 'pathtype' => '', 'estimatedminutes' => 0, 'reviewdate' => 0,
            'quizcmid' => 0, 'timecreated' => $now, 'timemodified' => $now,
            'createdby' => 2, 'modifiedby' => 2,
        ]);
        foreach ([$a, $b] as $i => $page) {
            $DB->insert_record('local_handbook_pathitem', (object)[
                'pathid' => $pathid, 'pageid' => (int)$page->id, 'sectionname' => 'Intro',
                'sortorder' => $i, 'required' => 1, 'quizcmid' => 0, 'rationale' => null,
            ]);
        }

        // Proposed: rename, B moved to "Avanzado" and now optional, C added, A dropped.
        $snapshot = [
            'pathid' => $pathid,
            'name' => 'Ruta nueva',
            'schoolyear' => '2024',
            'active' => 1,
            'sections' => [
                ['name' => 'Avanzado', 'items' => [
                    ['pageid' => (int)$b->id, 'required' => false],
                    ['pageid' => (int)$c->id, 'required' => true],
                ]],
            ],
        ];
        $diff = changeset_service::reading_path_diff($snapshot);

        $this->assertFalse($diff['iscreate']);
        $this->assertArrayHasKey('name', $diff['fields']);
        $this->assertSame('Ruta', $diff['fields']['name']['old']);
        $this->assertSame([(int)$a->id], $diff['removed']);

        $byid = [];
        foreach ($diff['items'] as $it) {
            $byid[$it['pageid']] = $it;
        }
        $this->assertSame('kept', $byid[(int)$b->id]['status']);
        $this->assertTrue($byid[(int)$b->id]['sectionchanged']);
        $this->assertTrue($byid[(int)$b->id]['requiredchanged']);
        $this->assertSame('Intro', $byid[(int)$b->id]['oldsection']);
        $this->assertSame('new', $byid[(int)$c->id]['status']);
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
