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

/**
 * Tests for the JSON seed importer.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\import_service
 */
final class import_service_test extends advanced_testcase {

    /**
     * A small seed with nested categories, one page and one relation.
     *
     * @return \stdClass
     */
    private function seed(): \stdClass {
        return json_decode(json_encode([
            'categories' => [
                ['slug' => 'vida-estudiantil', 'name' => 'Vida estudiantil', 'sortorder' => 40],
                ['slug' => 'supervision', 'name' => 'Supervisión', 'parent' => 'vida-estudiantil'],
            ],
            'pages' => [
                [
                    'slug' => 'supervision-durante-los-recreos',
                    'title' => 'Supervisión durante los recreos',
                    'category' => 'supervision',
                    'contenttype' => 'procedure',
                    'authoritylevel' => 2,
                    'criticality' => 'safetycritical',
                    'requiredreading' => 1,
                    'summary' => 'Zonas, turnos y relevos.',
                    'content' => '<h2>Objetivo</h2><p>Supervisión activa.</p>',
                ],
                [
                    'slug' => 'politica-de-convivencia-escolar',
                    'title' => 'Política de convivencia escolar',
                    'category' => 'vida-estudiantil',
                    'contenttype' => 'policy',
                    'authoritylevel' => 1,
                    'summary' => 'Principios de convivencia.',
                    'content' => '<h2>Principios</h2><p>Respeto mutuo.</p>',
                ],
            ],
            'relations' => [
                ['source' => 'supervision-durante-los-recreos', 'type' => 'implements',
                    'target' => 'politica-de-convivencia-escolar'],
            ],
        ]));
    }

    public function test_import_creates_structure_as_drafts(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $report = import_service::import($this->seed());

        $this->assertSame(2, $report->categoriescreated);
        $this->assertSame(2, $report->pagescreated);
        $this->assertSame(0, $report->pagespublished);
        $this->assertSame(1, $report->relationscreated);
        $this->assertSame([], $report->errors);

        $child = $DB->get_record('local_handbook_category', ['slug' => 'supervision'], '*', MUST_EXIST);
        $parent = $DB->get_record('local_handbook_category', ['slug' => 'vida-estudiantil'], '*', MUST_EXIST);
        $this->assertEquals($parent->id, $child->parentid);

        // Pages exist but are unpublished drafts.
        $page = $DB->get_record('local_handbook_page',
            ['slug' => 'supervision-durante-los-recreos'], '*', MUST_EXIST);
        $this->assertEquals(0, $page->publishedrevisionid);
        $draft = page_service::get_working_revision((int)$page->id);
        $this->assertSame(page_service::STATUS_DRAFT, $draft->status);
    }

    public function test_import_publishes_in_bootstrap_mode(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('bootstrapmode', 1, 'local_handbook');

        $report = import_service::import($this->seed(), true);

        $this->assertSame(2, $report->pagespublished);
        $page = $DB->get_record('local_handbook_page',
            ['slug' => 'politica-de-convivencia-escolar'], '*', MUST_EXIST);
        $this->assertGreaterThan(0, (int)$page->publishedrevisionid);
        $this->assertSame(page_service::STATUS_PUBLISHED,
            $DB->get_field('local_handbook_revision', 'status', ['id' => $page->publishedrevisionid]));
    }

    public function test_import_publish_requires_bootstrap_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        import_service::import($this->seed(), true);
    }

    public function test_reimport_updates_by_slug(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('bootstrapmode', 1, 'local_handbook');

        import_service::import($this->seed(), true);

        // Re-import with a changed title and content: updates, no duplicates.
        $seed = $this->seed();
        $seed->pages[0]->title = 'Supervisión durante los recreos (v2)';
        $seed->pages[0]->content = '<h2>Objetivo</h2><p>Actualizado.</p>';
        $report = import_service::import($seed, true);

        $this->assertSame(0, $report->pagescreated);
        $this->assertSame(2, $report->pagesupdated);
        $this->assertSame(2, $report->categoriesupdated);
        $this->assertSame(0, $report->relationscreated);

        $this->assertSame(1, $DB->count_records('local_handbook_page',
            ['slug' => 'supervision-durante-los-recreos']));
        $page = $DB->get_record('local_handbook_page',
            ['slug' => 'supervision-durante-los-recreos'], '*', MUST_EXIST);
        $this->assertSame('Supervisión durante los recreos (v2)', $page->title);

        // The republished revision is v2 and v1 is superseded.
        $published = $DB->get_record('local_handbook_revision',
            ['id' => $page->publishedrevisionid], '*', MUST_EXIST);
        $this->assertEquals(2, $published->versionnumber);
        $this->assertSame(page_service::STATUS_SUPERSEDED,
            $DB->get_field('local_handbook_revision', 'status',
                ['pageid' => $page->id, 'versionnumber' => 1]));
    }

    public function test_import_reports_unknown_references(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $seed = json_decode(json_encode([
            'pages' => [
                ['slug' => 'orphan', 'title' => 'Orphan', 'category' => 'missing-category'],
            ],
            'relations' => [
                ['source' => 'orphan', 'type' => 'implements', 'target' => 'nowhere'],
            ],
        ]));

        $report = import_service::import($seed);
        $this->assertCount(2, $report->errors);
        $this->assertSame(0, $report->pagescreated);
    }
}
