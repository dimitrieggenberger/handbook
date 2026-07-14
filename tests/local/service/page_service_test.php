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
use moodle_exception;
use stdClass;

/**
 * Tests for the page/revision workflow service (specification 11, 29.1).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\page_service
 */
final class page_service_test extends advanced_testcase {

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
            'name' => 'Vida estudiantil',
            'slug' => 'vida-estudiantil',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'sortorder' => 0,
            'visible' => 1,
            'audiencekey' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => 2,
            'modifiedby' => 2,
        ]);
    }

    /**
     * Create a page through the service with sensible defaults.
     *
     * @param int $categoryid Category id.
     * @param string $title Page title.
     * @return stdClass Page record with ->draftrevision.
     */
    private function create_page(int $categoryid, string $title = 'Supervisión durante los recreos'): stdClass {
        return page_service::create_page((object)[
            'title' => $title,
            'categoryid' => $categoryid,
            'contenttype' => 'procedure',
            'summary' => 'Test summary.',
            'content' => '<h2>Objetivo</h2><p>Contenido.</p>',
            'contentformat' => FORMAT_HTML,
        ]);
    }

    public function test_create_page_creates_draft_v1(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->create_page($this->create_category());

        $this->assertSame('supervision-durante-los-recreos', $page->slug);
        $this->assertEquals(0, $page->publishedrevisionid);

        $revision = $page->draftrevision;
        $this->assertEquals(1, $revision->versionnumber);
        $this->assertSame(page_service::STATUS_DRAFT, $revision->status);
        $this->assertSame(sha1($revision->content), $revision->contenthash);
        $this->assertNotEmpty($revision->plaintext);
        $this->assertTrue($DB->record_exists('local_handbook_revision', ['id' => $revision->id]));
    }

    public function test_slugs_are_unique(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $categoryid = $this->create_category();
        $first = $this->create_page($categoryid, 'Misma página');
        $second = $this->create_page($categoryid, 'Misma página');

        $this->assertSame('misma-pagina', $first->slug);
        $this->assertSame('misma-pagina-2', $second->slug);
    }

    public function test_full_workflow_publishes_and_supersedes(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->create_page($this->create_category());
        $revision = $page->draftrevision;

        // Draft -> in review -> approved -> published.
        page_service::submit_for_review($revision, 'Initial version.');
        $this->assertSame(page_service::STATUS_IN_REVIEW,
            $DB->get_field('local_handbook_revision', 'status', ['id' => $revision->id]));

        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        page_service::approve($revision);
        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        page_service::publish($revision);

        $page = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertEquals($revision->id, $page->publishedrevisionid);
        $this->assertGreaterThan(0, (int)$page->effectivedate);

        // Second cycle: new draft is v2, based on v1; publishing supersedes v1.
        $draft2 = page_service::create_revision_draft($page);
        $this->assertEquals(2, $draft2->versionnumber);
        $this->assertEquals($revision->id, $draft2->baserevisionid);

        page_service::submit_for_review($draft2, 'Second version.');
        $draft2 = $DB->get_record('local_handbook_revision', ['id' => $draft2->id], '*', MUST_EXIST);
        page_service::approve($draft2);
        $draft2 = $DB->get_record('local_handbook_revision', ['id' => $draft2->id], '*', MUST_EXIST);
        page_service::publish($draft2);

        $page = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertEquals($draft2->id, $page->publishedrevisionid);
        $this->assertSame(page_service::STATUS_SUPERSEDED,
            $DB->get_field('local_handbook_revision', 'status', ['id' => $revision->id]));
    }

    public function test_only_one_working_revision(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->create_page($this->create_category());

        $this->expectException(moodle_exception::class);
        page_service::create_revision_draft($page);
    }

    public function test_submit_requires_change_summary(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->create_page($this->create_category());

        $this->expectException(moodle_exception::class);
        page_service::submit_for_review($page->draftrevision, '   ');
    }

    public function test_publish_requires_approved_status(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->create_page($this->create_category());

        $this->expectException(moodle_exception::class);
        page_service::publish($page->draftrevision);
    }

    public function test_concurrent_update_fails_clearly(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->create_page($this->create_category());
        $stale = clone $page->draftrevision;

        // Another editor saves in the meantime.
        $DB->set_field('local_handbook_revision', 'timemodified',
            $stale->timemodified + 100, ['id' => $stale->id]);

        $this->expectException(moodle_exception::class);
        page_service::update_draft($stale, '<p>Conflicting edit.</p>', FORMAT_HTML, '');
    }

    public function test_archive_and_unarchive_preserve_history(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->create_page($this->create_category());

        page_service::set_archived($page, true);
        $record = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertEquals(1, $record->archived);
        $this->assertSame(1, $DB->count_records('local_handbook_revision', ['pageid' => $page->id]));

        page_service::set_archived($page, false);
        $this->assertEquals(0, $DB->get_field('local_handbook_page', 'archived', ['id' => $page->id]));
    }

    public function test_restore_creates_new_draft_from_old_content(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Publish v1, then v2 (superseding v1).
        $page = $this->create_page($this->create_category());
        $v1 = $page->draftrevision;
        page_service::submit_for_review($v1, 'v1');
        $v1 = $DB->get_record('local_handbook_revision', ['id' => $v1->id], '*', MUST_EXIST);
        page_service::approve($v1);
        $v1 = $DB->get_record('local_handbook_revision', ['id' => $v1->id], '*', MUST_EXIST);
        page_service::publish($v1);

        $page = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $v2 = page_service::create_revision_draft($page);
        page_service::update_draft($v2, '<h2>Objetivo</h2><p>Contenido v2.</p>', FORMAT_HTML, 'v2');
        $v2 = $DB->get_record('local_handbook_revision', ['id' => $v2->id], '*', MUST_EXIST);
        page_service::submit_for_review($v2, 'v2');
        $v2 = $DB->get_record('local_handbook_revision', ['id' => $v2->id], '*', MUST_EXIST);
        page_service::approve($v2);
        $v2 = $DB->get_record('local_handbook_revision', ['id' => $v2->id], '*', MUST_EXIST);
        page_service::publish($v2);

        // Restore v1: new draft v3 with v1's content, based on published v2.
        $v1 = $DB->get_record('local_handbook_revision', ['id' => $v1->id], '*', MUST_EXIST);
        $this->assertSame(page_service::STATUS_SUPERSEDED, $v1->status);

        $draft = page_service::restore_revision($v1);
        $this->assertEquals(3, $draft->versionnumber);
        $this->assertSame(page_service::STATUS_DRAFT, $draft->status);
        $this->assertSame($v1->content, $draft->content);
        $this->assertEquals($v2->id, $draft->baserevisionid);

        // v2 remains published; later history untouched.
        $page = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertEquals($v2->id, $page->publishedrevisionid);

        // A second restore is blocked while the draft exists.
        $this->expectException(moodle_exception::class);
        page_service::restore_revision($v1);
    }

    public function test_request_changes_returns_draft_to_author(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->create_page($this->create_category());
        page_service::submit_for_review($page->draftrevision, 'Please review.');

        $revision = $DB->get_record('local_handbook_revision',
            ['id' => $page->draftrevision->id], '*', MUST_EXIST);
        page_service::request_changes($revision, 'Falta la sección de escalamiento.');

        $revision = $DB->get_record('local_handbook_revision', ['id' => $revision->id], '*', MUST_EXIST);
        $this->assertSame(page_service::STATUS_CHANGES_REQUESTED, $revision->status);
        $this->assertSame('Falta la sección de escalamiento.', $revision->reviewnote);
    }
}
