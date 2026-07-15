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
use local_handbook\local\service\changeset_service;
use local_handbook\local\service\import_service;
use local_handbook\local\service\page_service;
use moodle_exception;

/**
 * Tests for the change-set and context external functions (spec 36.4, 36.6).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\external\get_context_index
 * @covers    \local_handbook\external\get_working_page
 * @covers    \local_handbook\external\create_changeset
 * @covers    \local_handbook\external\get_changeset
 * @covers    \local_handbook\external\list_changesets
 * @covers    \local_handbook\external\upsert_changeset_draft
 * @covers    \local_handbook\external\submit_changeset_for_review
 */
final class changeset_api_test extends advanced_testcase {

    /**
     * Seed three published pages: full, excluded and metadata_only.
     *
     * @return void
     */
    private function seed(): void {
        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [['slug' => 'gobernanza', 'name' => 'Gobernanza']],
            'pages' => [
                [
                    'slug' => 'coordinacion-academica',
                    'title' => 'Coordinación Académica',
                    'category' => 'gobernanza',
                    'contenttype' => 'roledescription',
                    'summary' => 'Funciones y responsabilidades.',
                    'content' => '<h2>Funciones</h2><p>Coordina lo académico.</p>',
                ],
                [
                    'slug' => 'pagina-excluida',
                    'title' => 'Página excluida',
                    'category' => 'gobernanza',
                    'contenttype' => 'procedure',
                    'aiaccess' => 'excluded',
                    'summary' => 'No visible para IA.',
                    'content' => '<h2>Secreto</h2><p>Oculto.</p>',
                ],
                [
                    'slug' => 'pagina-solo-metadatos',
                    'title' => 'Página solo metadatos',
                    'category' => 'gobernanza',
                    'contenttype' => 'procedure',
                    'aiaccess' => 'metadata_only',
                    'summary' => 'Metadatos visibles, contenido no.',
                    'content' => '<h2>Reservado</h2><p>Solo metadatos.</p>',
                ],
            ],
        ])), true);
        set_config('bootstrapmode', 0, 'local_handbook');
    }

    public function test_context_index_omits_excluded_and_has_no_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->seed();

        $result = get_context_index::execute(false);
        $result = get_context_index::clean_returnvalue(get_context_index::execute_returns(), $result);

        $slugs = array_column($result, 'slug');
        $this->assertContains('coordinacion-academica', $slugs);
        $this->assertContains('pagina-solo-metadatos', $slugs);
        $this->assertNotContains('pagina-excluida', $slugs);

        foreach ($result as $entry) {
            $this->assertArrayNotHasKey('content', $entry);
            $this->assertArrayHasKey('contenthash', $entry);
            $this->assertArrayHasKey('hasworkingdraft', $entry);
            $this->assertFalse($entry['hasworkingdraft']);
        }
    }

    public function test_working_page_reports_draft_without_changing_state(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->seed();
        $page = $DB->get_record('local_handbook_page', ['slug' => 'coordinacion-academica'], '*', MUST_EXIST);

        // No working draft yet.
        $before = get_working_page::execute('coordinacion-academica');
        $before = get_working_page::clean_returnvalue(get_working_page::execute_returns(), $before);
        $this->assertFalse($before['hasworkingdraft']);

        // Create one through a change set, then read it.
        $cs = create_changeset::execute('Rename');
        upsert_changeset_draft::execute((int)$cs['id'], 'coordinacion-academica',
            '<h2>Funciones</h2><p>Dirección Académica.</p>', 'Rename');

        $after = get_working_page::execute('coordinacion-academica');
        $after = get_working_page::clean_returnvalue(get_working_page::execute_returns(), $after);
        $this->assertTrue($after['hasworkingdraft']);
        $this->assertSame((int)$cs['id'], $after['changesetid']);
        $this->assertStringContainsString('Dirección Académica', $after['working']['content']);

        // Reading did not publish anything.
        $pageafter = $DB->get_record('local_handbook_page', ['id' => $page->id], '*', MUST_EXIST);
        $this->assertEquals($page->publishedrevisionid, $pageafter->publishedrevisionid);
    }

    public function test_create_get_and_list_changeset(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cs = create_changeset::execute('Terminology', 'Rename a role', 'conv-1');
        $cs = create_changeset::clean_returnvalue(create_changeset::execute_returns(), $cs);
        $this->assertSame('ai', $cs['source']);
        $this->assertSame(changeset_service::STATUS_DRAFT, $cs['status']);

        $got = get_changeset::execute((int)$cs['id']);
        $got = get_changeset::clean_returnvalue(get_changeset::execute_returns(), $got);
        $this->assertSame((int)$cs['id'], $got['id']);
        $this->assertSame([], $got['items']);

        $list = list_changesets::execute();
        $this->assertContains((int)$cs['id'], array_column($list, 'id'));
    }

    public function test_upsert_and_submit_via_api(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->seed();

        $cs = create_changeset::execute('CS');
        $result = upsert_changeset_draft::execute((int)$cs['id'], 'coordinacion-academica',
            '<h2>Funciones</h2><p>Dirección Académica.</p>', 'Rename');
        $result = upsert_changeset_draft::clean_returnvalue(
            upsert_changeset_draft::execute_returns(), $result);
        $this->assertSame(changeset_service::ITEM_DRAFT, $result['status']);
        $this->assertGreaterThan(0, $result['revisionid']);

        $submitted = submit_changeset_for_review::execute((int)$cs['id']);
        $submitted = submit_changeset_for_review::clean_returnvalue(
            submit_changeset_for_review::execute_returns(), $submitted);
        $this->assertCount(1, $submitted);
        $this->assertSame(changeset_service::ITEM_IN_REVIEW, $submitted[0]['status']);
    }

    public function test_upsert_conflict_does_not_overwrite_human_draft(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->seed();
        $page = $DB->get_record('local_handbook_page', ['slug' => 'coordinacion-academica'], '*', MUST_EXIST);

        // A human draft outside any change set.
        page_service::create_revision_draft($page);

        $cs = create_changeset::execute('CS');
        $result = upsert_changeset_draft::execute((int)$cs['id'], 'coordinacion-academica',
            '<h2>x</h2>', 'IA');
        $this->assertSame(changeset_service::ITEM_CONFLICT, $result['status']);
        $this->assertNotEmpty($result['conflictnote']);
    }

    public function test_metadata_only_page_refuses_upsert(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->seed();

        $cs = create_changeset::execute('CS');
        $this->expectException(moodle_exception::class);
        upsert_changeset_draft::execute((int)$cs['id'], 'pagina-solo-metadatos', '<h2>x</h2>', 'x');
    }

    public function test_excluded_page_refuses_working_page(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->seed();

        $this->expectException(moodle_exception::class);
        get_working_page::execute('pagina-excluida');
    }

    /**
     * The authority boundary: no external function may approve or publish
     * (specification 36.1). This guards against a future regression.
     *
     * @return void
     */
    public function test_no_approve_or_publish_external_function_exists(): void {
        global $CFG;

        $functions = [];
        require($CFG->dirroot . '/local/handbook/db/services.php');

        foreach ($functions as $name => $definition) {
            $this->assertStringNotContainsStringIgnoringCase('publish', $name,
                "Unexpected publishing function: {$name}");
            $this->assertStringNotContainsStringIgnoringCase('approve', $name,
                "Unexpected approval function: {$name}");
            $this->assertStringNotContainsStringIgnoringCase('publish', $definition['classname']);
            $this->assertStringNotContainsStringIgnoringCase('approve', $definition['classname']);
        }
    }
}
