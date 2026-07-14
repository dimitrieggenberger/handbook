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

namespace local_handbook\task;

use advanced_testcase;
use local_handbook\local\service\import_service;

/**
 * Tests for the link checker task (specification 15.4, 19).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\task\link_checker
 */
final class link_checker_test extends advanced_testcase {

    /**
     * Seed a page whose content links to an existing and a missing page.
     *
     * @return void
     */
    private function seed(): void {
        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [['slug' => 'vida-estudiantil', 'name' => 'Vida estudiantil']],
            'pages' => [
                [
                    'slug' => 'politica-de-convivencia-escolar',
                    'title' => 'Política de convivencia escolar',
                    'category' => 'vida-estudiantil',
                    'contenttype' => 'policy',
                    'summary' => 'Política.',
                    'content' => '<h2>Principios</h2><p>Texto.</p>',
                ],
                [
                    'slug' => 'supervision-durante-los-recreos',
                    'title' => 'Supervisión durante los recreos',
                    'category' => 'vida-estudiantil',
                    'contenttype' => 'procedure',
                    'summary' => 'Zonas.',
                    'content' => '<h2>Objetivo</h2>'
                        . '<p><a href="/local/handbook/view.php?page=politica-de-convivencia-escolar">ok</a>'
                        . ' y <a href="/local/handbook/view.php?page=pagina-inexistente">rota</a>.</p>',
                ],
            ],
        ])), true);
    }

    public function test_broken_links_become_deduped_findings(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->seed();

        (new link_checker())->execute();

        $findings = $DB->get_records('local_handbook_finding', ['findingtype' => 'broken_link']);
        $this->assertCount(1, $findings);
        $finding = reset($findings);
        $this->assertSame('audit', $finding->source);
        $this->assertStringContainsString('pagina-inexistente', $finding->summary);

        // Second run: still exactly one open finding (deduped).
        (new link_checker())->execute();
        $this->assertSame(1, $DB->count_records('local_handbook_finding',
            ['findingtype' => 'broken_link']));
    }

    public function test_missing_quiz_cmid_becomes_finding(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->seed();

        $pageid = (int)$DB->get_field('local_handbook_page', 'id',
            ['slug' => 'supervision-durante-los-recreos']);
        $pathid = $DB->insert_record('local_handbook_path', (object)[
            'name' => 'Ruta', 'slug' => 'ruta', 'description' => '', 'descriptionformat' => 1,
            'audiencejson' => '', 'schoolyear' => '2026-2027', 'active' => 1, 'quizcmid' => 0,
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => 2, 'modifiedby' => 2,
        ]);
        $DB->insert_record('local_handbook_pathitem', (object)[
            'pathid' => $pathid, 'pageid' => $pageid, 'sectionname' => '1',
            'sortorder' => 10, 'required' => 1, 'quizcmid' => 999999,
        ]);

        (new link_checker())->execute();

        $findings = $DB->get_records('local_handbook_finding', ['findingtype' => 'broken_link']);
        $anchors = [];
        foreach ($findings as $finding) {
            foreach ($DB->get_records('local_handbook_findpage', ['findingid' => $finding->id]) as $fp) {
                $anchors[] = $fp->anchor;
            }
        }
        $this->assertContains('quizcmid:999999', $anchors);
    }
}
