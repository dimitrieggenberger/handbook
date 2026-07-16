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
 * Tests for article-level reading completion, shared across paths (spec 8).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\completion_service
 */
final class completion_service_test extends advanced_testcase {

    /**
     * Create a published page (optionally globally required reading).
     *
     * @param string $slug Page slug.
     * @param bool $required Global required-reading flag.
     * @return stdClass Page record.
     */
    private function create_published_page(string $slug, bool $required): stdClass {
        global $DB;

        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [['slug' => 'ops', 'name' => 'Operaciones']],
            'pages' => [[
                'slug' => $slug,
                'title' => ucfirst($slug),
                'category' => 'ops',
                'contenttype' => 'procedure',
                'requiredreading' => $required ? 1 : 0,
                'summary' => 'S.',
                'content' => '<h2>Objetivo</h2><p>V1.</p>',
            ]],
        ])), true);

        return $DB->get_record('local_handbook_page', ['slug' => $slug], '*', MUST_EXIST);
    }

    /**
     * Publish a new revision of a page.
     *
     * @param stdClass $page Page record.
     * @param bool $requiresreack Whether it demands a renewed read.
     * @return stdClass Fresh page record.
     */
    private function publish_new_version(stdClass $page, bool $requiresreack): stdClass {
        global $DB;

        $draft = page_service::create_revision_draft($page);
        page_service::update_draft($draft, '<h2>Objetivo</h2><p>V2.</p>', FORMAT_HTML,
            'Cambio.', 0, $requiresreack);
        $draft = $DB->get_record('local_handbook_revision', ['id' => $draft->id], '*', MUST_EXIST);
        page_service::direct_publish($draft);

        return $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
    }

    public function test_receipt_marks_completion_independent_of_global_flag(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        // A page that is NOT globally required reading.
        $page = $this->create_published_page('induccion-basica', false);

        $this->assertSame(completion_service::STATUS_NOT_STARTED,
            completion_service::completion_status((int)$user->id, $page)->status);

        completion_service::record_receipt((int)$user->id, $page);
        $this->assertTrue(completion_service::is_completed((int)$user->id, $page));
    }

    public function test_receipt_is_idempotent_per_revision(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $page = $this->create_published_page('protocolo', false);

        $first = completion_service::record_receipt((int)$user->id, $page);
        $second = completion_service::record_receipt((int)$user->id, $page);
        $this->assertEquals($first->id, $second->id);
        $this->assertSame(1, $DB->count_records('local_handbook_readreceipt',
            ['userid' => $user->id, 'pageid' => $page->id]));
    }

    public function test_existing_acknowledgement_counts_as_completion(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        // Globally required page confirmed the old way (compliance ack, no receipt).
        $page = $this->create_published_page('reglamento', true);
        ack_service::acknowledge((int)$user->id, $page);

        // Historical evidence must count for path completion (spec 8.4).
        $this->assertTrue(completion_service::is_completed((int)$user->id, $page));
    }

    public function test_material_change_requires_a_renewed_read(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $page = $this->create_published_page('seguridad', false);
        completion_service::record_receipt((int)$user->id, $page);

        // A minor new version keeps completion valid.
        $page = $this->publish_new_version($page, false);
        $this->assertSame(completion_service::STATUS_COMPLETED,
            completion_service::completion_status((int)$user->id, $page)->status);

        // A material change demands one renewed read.
        $page = $this->publish_new_version($page, true);
        $this->assertSame(completion_service::STATUS_RECONFIRM,
            completion_service::completion_status((int)$user->id, $page)->status);

        // Re-reading the new revision settles it, carrying across paths.
        completion_service::record_receipt((int)$user->id, $page);
        $this->assertSame(completion_service::STATUS_COMPLETED,
            completion_service::completion_status((int)$user->id, $page)->status);
    }

    public function test_completion_carries_across_pages_sharing_no_revision(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $a = $this->create_published_page('pagina-a', false);
        $b = $this->create_published_page('pagina-b', false);

        completion_service::record_receipt((int)$user->id, $a);
        // Completion is per revision: reading A does not complete B.
        $this->assertTrue(completion_service::is_completed((int)$user->id, $a));
        $this->assertFalse(completion_service::is_completed((int)$user->id, $b));
    }
}
