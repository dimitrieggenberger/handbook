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

namespace local_handbook\external;

use advanced_testcase;
use context_system;
use local_handbook\local\service\import_service;
use local_handbook\local\service\page_service;
use moodle_exception;

/**
 * Tests for the handbook external functions (specification 17, 29.1).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\external\helper
 * @covers    \local_handbook\external\get_page
 * @covers    \local_handbook\external\list_pages
 * @covers    \local_handbook\external\search_pages
 * @covers    \local_handbook\external\create_revision_draft
 * @covers    \local_handbook\external\update_draft
 * @covers    \local_handbook\external\submit_draft_for_review
 */
final class api_test extends advanced_testcase {

    /**
     * Seed two published pages (one full, one excluded) and return them.
     *
     * @return array [$fullpage, $excludedpage]
     */
    private function seed_published_pages(): array {
        global $DB;

        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [
                ['slug' => 'vida-estudiantil', 'name' => 'Vida estudiantil'],
            ],
            'pages' => [
                [
                    'slug' => 'supervision-durante-los-recreos',
                    'title' => 'Supervisión durante los recreos',
                    'category' => 'vida-estudiantil',
                    'contenttype' => 'procedure',
                    'summary' => 'Zonas, turnos y relevos.',
                    'content' => '<h2>Objetivo</h2><p>Supervisión activa en el patio.</p>',
                ],
                [
                    'slug' => 'procedimiento-confidencial',
                    'title' => 'Procedimiento confidencial',
                    'category' => 'vida-estudiantil',
                    'contenttype' => 'procedure',
                    'aiaccess' => 'excluded',
                    'summary' => 'Contenido restringido.',
                    'content' => '<h2>Secreto</h2><p>No debe salir por la API.</p>',
                ],
            ],
        ])), true);
        set_config('bootstrapmode', 0, 'local_handbook');

        return [
            $DB->get_record('local_handbook_page',
                ['slug' => 'supervision-durante-los-recreos'], '*', MUST_EXIST),
            $DB->get_record('local_handbook_page',
                ['slug' => 'procedimiento-confidencial'], '*', MUST_EXIST),
        ];
    }

    public function test_get_page_returns_published_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$page] = $this->seed_published_pages();

        $result = get_page::execute($page->slug);
        $result = get_page::clean_returnvalue(get_page::execute_returns(), $result);

        $this->assertSame($page->slug, $result['page']['slug']);
        $this->assertSame(1, $result['page']['publishedversion']);
        $this->assertTrue($result['published']['contentincluded']);
        $this->assertStringContainsString('Supervisión activa', $result['published']['content']);
        $this->assertNotEmpty($result['published']['plaintext']);
        $this->assertNotEmpty($result['page']['contenthash']);
    }

    public function test_excluded_pages_are_denied_and_omitted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $excluded] = $this->seed_published_pages();

        // Omitted from listings and search.
        $list = list_pages::execute();
        $slugs = array_column($list, 'slug');
        $this->assertNotContains($excluded->slug, $slugs);

        $search = search_pages::execute('Secreto');
        $this->assertSame([], $search);

        // Denied on direct access without revealing content.
        $this->expectException(moodle_exception::class);
        get_page::execute($excluded->slug);
    }

    public function test_api_requires_apiaccess_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->seed_published_pages();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        list_pages::execute();
    }

    public function test_draft_cycle_via_api(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$page] = $this->seed_published_pages();

        // Create draft with base check.
        $draft = create_revision_draft::execute($page->slug, (int)$page->publishedrevisionid);
        $this->assertSame(page_service::STATUS_DRAFT, $draft['status']);
        $this->assertSame(2, $draft['versionnumber']);

        // Update with the concurrency token.
        $updated = update_draft::execute($draft['id'], '<h2>Objetivo</h2><p>Actualizado por API.</p>',
            $draft['timemodified'], 'Ajuste de zonas.');
        $this->assertStringContainsString('Actualizado por API', $updated['content']);

        // Stale token fails clearly.
        try {
            update_draft::execute($draft['id'], '<p>x</p>', $draft['timemodified'] - 1, '');
            $this->fail('Expected a conflict exception.');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('errorrevisionconflict', $e->errorcode);
        }

        // Submit for review: ends in the human queue, never published.
        $submitted = submit_draft_for_review::execute($draft['id'], 'Ajuste de zonas.');
        $this->assertSame(page_service::STATUS_IN_REVIEW, $submitted['status']);

        $pageafter = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertEquals($page->publishedrevisionid, $pageafter->publishedrevisionid);
    }

    public function test_create_revision_draft_base_mismatch_fails(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$page] = $this->seed_published_pages();

        $this->expectException(moodle_exception::class);
        create_revision_draft::execute($page->slug, (int)$page->publishedrevisionid + 999);
    }
}
