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
use local_handbook\local\service\audience_service;
use local_handbook\local\service\changeset_service;
use local_handbook\local\service\import_service;

/**
 * Tests for the content-repository round trip functions (0.43.0).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\external\export_page
 * @covers    \local_handbook\external\attach_changeset_file
 */
final class roundtrip_api_test extends advanced_testcase {

    /**
     * Seed a published guide (tagged for families, with one image) and a
     * metadata-only page; return their slugs.
     *
     * @return array [$guideslug, $metaslug]
     */
    private function seed(): array {
        global $DB;

        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [['slug' => 'plataforma', 'name' => 'Plataforma']],
            'pages' => [
                [
                    'slug' => 'ver-las-calificaciones',
                    'title' => 'Ver las calificaciones',
                    'category' => 'plataforma',
                    'contenttype' => 'quickguide',
                    'content' => '<h2>Pasos</h2><p><img src="@@PLUGINFILE@@/notas.png" alt="Notas"></p>',
                ],
                [
                    'slug' => 'nomina-interna',
                    'title' => 'Nómina interna',
                    'category' => 'plataforma',
                    'contenttype' => 'procedure',
                    'aiaccess' => 'metadata_only',
                    'content' => '<h2>Interno</h2><p>No sale por la API.</p>',
                ],
            ],
        ])), true);
        set_config('bootstrapmode', 0, 'local_handbook');

        $page = $DB->get_record('local_handbook_page', ['slug' => 'ver-las-calificaciones'], '*', MUST_EXIST);
        get_file_storage()->create_file_from_string([
            'contextid' => context_system::instance()->id, 'component' => 'local_handbook',
            'filearea' => 'revision', 'itemid' => (int)$page->publishedrevisionid,
            'filepath' => '/', 'filename' => 'notas.png',
        ], $this->png());

        $audienceid = (int)$DB->get_field('local_handbook_audience', 'id', ['audiencekey' => 'familias'])
            ?: audience_service::save((object)['audiencekey' => 'familias', 'name' => 'Familias',
                'matchtype' => 'profile', 'profilefield' => 'city', 'profilevalue' => 'familias',
                'active' => 1], 2);
        audience_service::set_page_audiences((int)$page->id, [$audienceid]);

        return ['ver-las-calificaciones', 'nomina-interna'];
    }

    /**
     * A small valid PNG.
     *
     * @return string Raw bytes.
     */
    private function png(): string {
        $image = imagecreatetruecolor(30, 30);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));
        ob_start();
        imagepng($image);
        return (string)ob_get_clean();
    }

    public function test_export_page_returns_fiche_audiences_content_and_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$slug] = $this->seed();

        $result = export_page::clean_returnvalue(export_page::execute_returns(), export_page::execute($slug));

        $this->assertSame('plataforma', $result['categoryslug']);
        $this->assertSame(['familias'], $result['audiences']);
        $this->assertFalse($result['hasworkingdraft']);
        $this->assertCount(1, $result['published']);
        $this->assertStringContainsString('@@PLUGINFILE@@/notas.png', $result['published'][0]['content']);
        $this->assertCount(1, $result['files']);
        $this->assertSame('notas.png', $result['files'][0]['filename']);
        $this->assertSame($this->png(), base64_decode($result['files'][0]['content']));
    }

    public function test_export_page_withholds_content_of_a_metadata_only_page(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $metaslug] = $this->seed();

        $result = export_page::clean_returnvalue(export_page::execute_returns(), export_page::execute($metaslug));

        $this->assertFalse($result['published'][0]['contentincluded']);
        $this->assertSame('', $result['published'][0]['content']);
        $this->assertSame([], $result['files']);
    }

    public function test_attach_changeset_file_stores_the_image_in_the_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$slug] = $this->seed();

        $page = helper::get_page_by_identifier($slug);
        $changeset = changeset_service::create((object)['title' => 'Capturas', 'source' => 'ai']);
        $item = changeset_service::upsert_draft($changeset->id, (int)$page->id,
            '<h2>Pasos</h2><p><img src="@@PLUGINFILE@@/paso.png" alt=""></p>', FORMAT_HTML, 'Capturas');

        $result = attach_changeset_file::clean_returnvalue(attach_changeset_file::execute_returns(),
            attach_changeset_file::execute($changeset->id, $item['itemid'], 'paso.png',
                base64_encode($this->png())));

        $this->assertSame('@@PLUGINFILE@@/paso.png', $result['reference']);
        $this->assertTrue((bool)get_file_storage()->get_file(context_system::instance()->id,
            'local_handbook', 'revision', $item['revisionid'], '/', 'paso.png'));
    }

    public function test_attach_changeset_file_refuses_an_item_of_another_set(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$slug] = $this->seed();

        $page = helper::get_page_by_identifier($slug);
        $mine = changeset_service::create((object)['title' => 'Mío', 'source' => 'ai']);
        $other = changeset_service::create((object)['title' => 'Otro', 'source' => 'ai']);
        $item = changeset_service::upsert_draft($mine->id, (int)$page->id, '<p>x</p>', FORMAT_HTML, 'x');

        $this->expectException(\dml_missing_record_exception::class);
        attach_changeset_file::execute($other->id, $item['itemid'], 'paso.png', base64_encode($this->png()));
    }
}
