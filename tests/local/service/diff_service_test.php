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
 * Tests for the word-level diff service (specification 11.4).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\diff_service
 */
final class diff_service_test extends advanced_testcase {

    public function test_identical_texts_have_no_changes(): void {
        $segments = diff_service::diff_words('uno dos tres', 'uno dos tres');

        $this->assertFalse(diff_service::has_changes($segments));
        $this->assertCount(1, $segments);
        $this->assertSame('same', $segments[0]['type']);
    }

    public function test_insertion_and_deletion_are_detected(): void {
        $segments = diff_service::diff_words(
            'el docente supervisa la zona asignada',
            'el docente de turno supervisa toda la zona');

        $this->assertTrue(diff_service::has_changes($segments));

        $inserted = implode(' ', array_column(
            array_filter($segments, fn($s) => $s['type'] === 'ins'), 'text'));
        $deleted = implode(' ', array_column(
            array_filter($segments, fn($s) => $s['type'] === 'del'), 'text'));

        $this->assertStringContainsString('de turno', $inserted);
        $this->assertStringContainsString('toda', $inserted);
        $this->assertStringContainsString('asignada', $deleted);
    }

    public function test_full_replacement(): void {
        $segments = diff_service::diff_words('texto antiguo', 'contenido nuevo');

        $types = array_column($segments, 'type');
        $this->assertContains('del', $types);
        $this->assertContains('ins', $types);
        $this->assertNotContains('same', $types);
    }

    public function test_empty_sides(): void {
        $this->assertSame([], diff_service::diff_words('', ''));

        $segments = diff_service::diff_words('', 'todo nuevo');
        $this->assertCount(1, $segments);
        $this->assertSame('ins', $segments[0]['type']);

        $segments = diff_service::diff_words('todo borrado', '');
        $this->assertCount(1, $segments);
        $this->assertSame('del', $segments[0]['type']);
    }

    public function test_render_html_escapes_and_marks(): void {
        $segments = diff_service::diff_words('a <script> b', 'a <b> b');
        $html = diff_service::render_html($segments);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;', $html);
        $this->assertStringContainsString('<ins>', $html);
        $this->assertStringContainsString('<del>', $html);
        $this->assertStringContainsString('local-handbook-diff', $html);
    }

    public function test_adjacent_segments_are_merged(): void {
        $segments = diff_service::diff_words('a b c d e', 'a b x y e');

        // Expect: same "a b", del "c d", ins "x y", same "e" (order of the
        // del/ins pair may vary, but no two adjacent segments share a type).
        for ($i = 1; $i < count($segments); $i++) {
            $this->assertNotSame($segments[$i - 1]['type'], $segments[$i]['type']);
        }
    }
}
