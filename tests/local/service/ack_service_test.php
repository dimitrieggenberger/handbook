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
 * Tests for required-reading acknowledgements (specification 16).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\ack_service
 */
final class ack_service_test extends advanced_testcase {

    /**
     * Create a published required-reading page and return it.
     *
     * @return stdClass Page record.
     */
    private function create_published_page(): stdClass {
        global $DB;

        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [['slug' => 'vida-estudiantil', 'name' => 'Vida estudiantil']],
            'pages' => [[
                'slug' => 'supervision-durante-los-recreos',
                'title' => 'Supervisión durante los recreos',
                'category' => 'vida-estudiantil',
                'contenttype' => 'procedure',
                'requiredreading' => 1,
                'summary' => 'Zonas y turnos.',
                'content' => '<h2>Objetivo</h2><p>V1.</p>',
            ]],
        ])), true);

        return $DB->get_record('local_handbook_page',
            ['slug' => 'supervision-durante-los-recreos'], '*', MUST_EXIST);
    }

    /**
     * Publish a new revision of a page.
     *
     * @param stdClass $page Page record.
     * @param bool $requiresreack Whether it demands renewed acknowledgement.
     * @return stdClass Fresh page record.
     */
    private function publish_new_version(stdClass $page, bool $requiresreack): stdClass {
        global $DB;

        $draft = page_service::create_revision_draft($page);
        page_service::update_draft($draft, '<h2>Objetivo</h2><p>Actualizado.</p>', FORMAT_HTML,
            'Cambio.', 0, $requiresreack);
        $draft = $DB->get_record('local_handbook_revision', ['id' => $draft->id], '*', MUST_EXIST);
        page_service::direct_publish($draft);

        return $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
    }

    public function test_status_lifecycle(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $page = $this->create_published_page();

        // Pending before any confirmation.
        $this->assertSame(ack_service::STATUS_PENDING,
            ack_service::get_status((int)$user->id, $page)->status);

        // Confirmed after acknowledging.
        $ack = ack_service::acknowledge((int)$user->id, $page);
        $this->assertEquals($page->publishedrevisionid, $ack->revisionid);
        $this->assertSame(ack_service::STATUS_CONFIRMED,
            ack_service::get_status((int)$user->id, $page)->status);

        // A minor new version keeps the confirmation valid.
        $page = $this->publish_new_version($page, false);
        $this->assertSame(ack_service::STATUS_CONFIRMED,
            ack_service::get_status((int)$user->id, $page)->status);

        // A materially changed version demands reconfirmation.
        $page = $this->publish_new_version($page, true);
        $this->assertSame(ack_service::STATUS_RECONFIRM,
            ack_service::get_status((int)$user->id, $page)->status);

        // Reconfirming settles it again.
        ack_service::acknowledge((int)$user->id, $page);
        $this->assertSame(ack_service::STATUS_CONFIRMED,
            ack_service::get_status((int)$user->id, $page)->status);
    }

    public function test_acknowledge_is_idempotent_per_revision(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $page = $this->create_published_page();

        $first = ack_service::acknowledge((int)$user->id, $page);
        $second = ack_service::acknowledge((int)$user->id, $page);

        $this->assertEquals($first->id, $second->id);
        $this->assertSame(1, $DB->count_records('local_handbook_ack', ['userid' => $user->id]));
    }

    public function test_not_required_pages_report_not_required(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->create_published_page();
        $DB->set_field('local_handbook_page', 'requiredreading', 0, ['id' => $page->id]);
        $page = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);

        $user = $this->getDataGenerator()->create_user();
        $this->assertSame(ack_service::STATUS_NOT_REQUIRED,
            ack_service::get_status((int)$user->id, $page)->status);

        $this->expectException(\moodle_exception::class);
        ack_service::acknowledge((int)$user->id, $page);
    }

    public function test_pending_list_for_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $page = $this->create_published_page();

        $pending = ack_service::get_pending_for_user((int)$user->id);
        $this->assertCount(1, $pending);
        $this->assertSame('pending', reset($pending)->ackstatus);

        ack_service::acknowledge((int)$user->id, $page);
        $this->assertCount(0, ack_service::get_pending_for_user((int)$user->id));
    }
}
