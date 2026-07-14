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
 * Tests for quality findings (specification 19).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\finding_service
 */
final class finding_service_test extends advanced_testcase {

    /**
     * Seed one published page.
     *
     * @return stdClass Page record.
     */
    private function create_page(): stdClass {
        global $DB;

        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [['slug' => 'vida-estudiantil', 'name' => 'Vida estudiantil']],
            'pages' => [[
                'slug' => 'supervision-durante-los-recreos',
                'title' => 'Supervisión durante los recreos',
                'category' => 'vida-estudiantil',
                'contenttype' => 'procedure',
                'summary' => 'Zonas y turnos.',
                'content' => '<h2>Objetivo</h2><p>V1.</p>',
            ]],
        ])), true);

        return $DB->get_record('local_handbook_page',
            ['slug' => 'supervision-durante-los-recreos'], '*', MUST_EXIST);
    }

    public function test_create_links_pages_and_opens(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $page = $this->create_page();

        $finding = finding_service::create((object)[
            'findingtype' => 'outdated_reference',
            'summary' => 'La tabla de zonas menciona el pabellón B, ya demolido.',
            'source' => 'human',
            'confidence' => 'high',
        ], [[
            'pageid' => (int)$page->id,
            'revisionid' => (int)$page->publishedrevisionid,
            'anchor' => 'Zonas de supervisión',
        ]]);

        $this->assertSame(finding_service::STATUS_OPEN, $finding->status);
        $this->assertSame(1, finding_service::count_open());

        $pages = finding_service::get_pages((int)$finding->id);
        $this->assertCount(1, $pages);
        $this->assertSame('supervision-durante-los-recreos', reset($pages)->slug);
        $this->assertSame('Zonas de supervisión', reset($pages)->anchor);
    }

    public function test_status_transitions_record_resolution(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $page = $this->create_page();

        $finding = finding_service::create((object)[
            'findingtype' => 'contradiction',
            'summary' => 'Contradice la jornada escolar.',
        ], [['pageid' => (int)$page->id]]);

        finding_service::set_status($finding, finding_service::STATUS_UNDER_REVIEW);
        $this->assertSame(1, finding_service::count_open());

        finding_service::set_status($finding, finding_service::STATUS_RESOLVED,
            'Corregido en la v2.');
        $record = $DB->get_record('local_handbook_finding', ['id' => $finding->id], '*', MUST_EXIST);
        $this->assertSame(finding_service::STATUS_RESOLVED, $record->status);
        $this->assertSame('Corregido en la v2.', $record->resolutionnote);
        $this->assertGreaterThan(0, (int)$record->timeresolved);
        $this->assertSame(0, finding_service::count_open());
    }

    public function test_invalid_type_and_missing_page_fail(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        try {
            finding_service::create((object)['findingtype' => 'nonsense', 'summary' => 'x'], []);
            $this->fail('Expected an exception for an unknown type.');
        } catch (\moodle_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->expectException(\moodle_exception::class);
        finding_service::create((object)['findingtype' => 'other', 'summary' => 'x'],
            [['pageid' => 999999]]);
    }
}
