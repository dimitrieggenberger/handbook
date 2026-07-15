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
 * Tests for heading anchors and TOC extraction (specification 10.2, 12.2).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\toc_service
 */
final class toc_service_test extends advanced_testcase {

    public function test_anchors_are_injected_and_toc_extracted(): void {
        $html = '<h3>Objetivo</h3><p>a</p><h3>Zonas de supervisión</h3><p>b</p>';
        $result = toc_service::add_anchors($html);

        $this->assertCount(2, $result->toc);
        $this->assertSame('objetivo', $result->toc[0]->id);
        $this->assertSame('Objetivo', $result->toc[0]->text);
        $this->assertSame('zonas-de-supervision', $result->toc[1]->id);
        $this->assertStringContainsString('id="objetivo"', $result->html);
        $this->assertStringContainsString('id="zonas-de-supervision"', $result->html);
    }

    public function test_existing_ids_are_preserved(): void {
        $html = '<h3 id="custom-anchor">Objetivo</h3>';
        $result = toc_service::add_anchors($html);

        $this->assertSame('custom-anchor', $result->toc[0]->id);
        $this->assertSame(1, substr_count($result->html, 'id='));
    }

    public function test_duplicate_headings_get_unique_ids(): void {
        $html = '<h3>Registro</h3><p>a</p><h3>Registro</h3>';
        $result = toc_service::add_anchors($html);

        $this->assertSame('registro', $result->toc[0]->id);
        $this->assertSame('registro-2', $result->toc[1]->id);
    }

    public function test_inner_markup_is_kept_and_text_flattened(): void {
        $html = '<h3><strong>Zonas</strong> y turnos</h3>';
        $result = toc_service::add_anchors($html);

        $this->assertSame('Zonas y turnos', $result->toc[0]->text);
        $this->assertStringContainsString('<strong>Zonas</strong> y turnos', $result->html);
    }

    public function test_content_without_headings_is_untouched(): void {
        $html = '<p>Sin encabezados.</p>';
        $result = toc_service::add_anchors($html);

        $this->assertSame([], $result->toc);
        $this->assertSame($html, $result->html);
    }
}
