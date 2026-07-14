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
 * Tests for the reports service (specification 12.5, 15.3).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\report_service
 */
final class report_service_test extends advanced_testcase {

    /**
     * Seed a path with two required published pages; return the path.
     *
     * @return stdClass Path record.
     */
    private function seed_path(): stdClass {
        global $DB;

        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [['slug' => 'vida-estudiantil', 'name' => 'Vida estudiantil']],
            'pages' => [
                [
                    'slug' => 'supervision-durante-los-recreos',
                    'title' => 'Supervisión durante los recreos',
                    'category' => 'vida-estudiantil',
                    'contenttype' => 'procedure',
                    'requiredreading' => 1,
                    'summary' => 'Zonas y turnos.',
                    'content' => '<h2>Objetivo</h2><p>V1.</p>',
                ],
                [
                    'slug' => 'protocolo-de-proteccion-del-menor',
                    'title' => 'Protocolo de protección del menor',
                    'category' => 'vida-estudiantil',
                    'contenttype' => 'procedure',
                    'requiredreading' => 1,
                    'summary' => 'Protocolo.',
                    'content' => '<h2>Objetivo</h2><p>V1.</p>',
                ],
            ],
            'paths' => [[
                'slug' => 'induccion',
                'name' => 'Inducción',
                'schoolyear' => '2026-2027',
                'items' => [
                    ['page' => 'supervision-durante-los-recreos', 'section' => '1', 'required' => 1],
                    ['page' => 'protocolo-de-proteccion-del-menor', 'section' => '1', 'required' => 1],
                ],
            ]],
        ])), true);

        return $DB->get_record('local_handbook_path', ['slug' => 'induccion'], '*', MUST_EXIST);
    }

    /**
     * Create a staff user holding the view capability.
     *
     * @return stdClass User record.
     */
    private function create_staff_user(): stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $roleid = create_role('Staff', 'handbookstaff' . $user->id, '');
        assign_capability('local/handbook:view', CAP_ALLOW, $roleid,
            \context_system::instance()->id, true);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        return $user;
    }

    public function test_path_completion_counts_valid_acks(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $path = $this->seed_path();
        $user = $this->create_staff_user();

        $page = $DB->get_record('local_handbook_page',
            ['slug' => 'supervision-durante-los-recreos'], '*', MUST_EXIST);
        ack_service::acknowledge((int)$user->id, $page);

        $report = report_service::path_completion($path);
        $this->assertSame(2, $report->totalrequired);

        $row = null;
        foreach ($report->users as $candidate) {
            if ((int)$candidate->user->id === (int)$user->id) {
                $row = $candidate;
            }
        }
        $this->assertNotNull($row);
        $this->assertSame(1, $row->confirmed);
        $this->assertSame(50, $row->percent);
    }

    public function test_page_acknowledgements_split_confirmed_and_pending(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed_path();
        $confirmer = $this->create_staff_user();
        $pendinguser = $this->create_staff_user();

        $page = $DB->get_record('local_handbook_page',
            ['slug' => 'supervision-durante-los-recreos'], '*', MUST_EXIST);
        ack_service::acknowledge((int)$confirmer->id, $page);

        $report = report_service::page_acknowledgements($page);
        $confirmedids = array_map(static fn($row) => (int)$row->user->id, $report->confirmed);
        $pendingids = array_map(static fn($user) => (int)$user->id, $report->pending);

        $this->assertContains((int)$confirmer->id, $confirmedids);
        $this->assertContains((int)$pendinguser->id, $pendingids);
        $this->assertNotContains((int)$confirmer->id, $pendingids);
    }

    public function test_reack_boundary_invalidates_old_acks(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed_path();
        $user = $this->create_staff_user();

        $page = $DB->get_record('local_handbook_page',
            ['slug' => 'supervision-durante-los-recreos'], '*', MUST_EXIST);
        ack_service::acknowledge((int)$user->id, $page);
        $this->assertArrayHasKey((int)$user->id, report_service::valid_ack_users((int)$page->id));

        // Publish v2 with the re-acknowledgement flag.
        $draft = page_service::create_revision_draft($page);
        page_service::update_draft($draft, '<h2>Objetivo</h2><p>V2.</p>', FORMAT_HTML, 'Cambio.',
            0, true);
        $draft = $DB->get_record('local_handbook_revision', ['id' => $draft->id], '*', MUST_EXIST);
        page_service::direct_publish($draft);

        $this->assertArrayNotHasKey((int)$user->id, report_service::valid_ack_users((int)$page->id));
    }

    public function test_editorial_health_lists(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed_path();

        // Make one page overdue and ownerless.
        $page = $DB->get_record('local_handbook_page',
            ['slug' => 'supervision-durante-los-recreos'], '*', MUST_EXIST);
        $DB->set_field('local_handbook_page', 'reviewdate', time() - DAYSECS, ['id' => $page->id]);
        $DB->set_field('local_handbook_page', 'owneruserid', 0, ['id' => $page->id]);

        $health = report_service::editorial_health();
        $this->assertArrayHasKey($page->id, $health->overduereview);
        $this->assertArrayHasKey($page->id, $health->missingowner);
        $this->assertSame(0, $health->openfindings);
    }
}
