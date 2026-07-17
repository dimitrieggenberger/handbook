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

/**
 * Tests for Wikipedia-style automatic cross-links (pure HTML transform).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\autolink_service
 */
final class autolink_service_test extends \advanced_testcase {

    /**
     * Targets in the shape the service builds: lowercased title => URL,
     * longest first.
     *
     * @return string[]
     */
    private function targets(): array {
        $titles = [
            'Consejería Estudiantil' => 'consejeria-estudiantil',
            'Coordinación Académica' => 'coordinacion-academica',
            'Dirección Oficial' => 'direccion-oficial',
            'Coordinación' => 'coordinacion',
        ];
        $targets = [];
        foreach ($titles as $title => $slug) {
            $targets[mb_strtolower($title)] = '/local/handbook/view.php?page=' . $slug;
        }
        uksort($targets, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        return $targets;
    }

    public function test_first_mention_links_later_mentions_do_not(): void {
        $html = '<p>Consejería Estudiantil coordina con Coordinación Académica.</p>'
            . '<p>Consejería Estudiantil registra el acuerdo.</p>';

        $out = autolink_service::link_html($html, $this->targets());

        $this->assertSame(1, substr_count($out, '>Consejería Estudiantil</a>'));
        $this->assertSame(1, substr_count($out, '>Coordinación Académica</a>'));
        $this->assertSame(2, substr_count($out, 'Consejería Estudiantil'));
        $this->assertStringContainsString('page=consejeria-estudiantil', $out);
        $this->assertStringContainsString('local-handbook-autolink', $out);
    }

    public function test_longest_title_wins_on_overlap(): void {
        $out = autolink_service::link_html(
            '<p>Coordinación Académica decide.</p>', $this->targets());
        $this->assertStringContainsString('>Coordinación Académica</a>', $out);
        $this->assertStringNotContainsString('>Coordinación</a> Académica', $out);

        $out = autolink_service::link_html('<p>La Coordinación decide.</p>', $this->targets());
        $this->assertStringContainsString('page=coordinacion', $out);
    }

    public function test_case_insensitive_match_keeps_author_casing(): void {
        $out = autolink_service::link_html(
            '<p>según la COORDINACIÓN ACADÉMICA.</p>', $this->targets());
        $this->assertStringContainsString('>COORDINACIÓN ACADÉMICA</a>', $out);
    }

    public function test_no_partial_word_matches(): void {
        $out = autolink_service::link_html(
            '<p>La Coordinacióndeportiva no existe.</p>', $this->targets());
        $this->assertStringNotContainsString('autolink', $out);
    }

    public function test_excluded_contexts_are_untouched(): void {
        $html = '<p><a href="/x">Coordinación Académica</a></p>'
            . '<h3>Coordinación Académica</h3>'
            . '<pre>Coordinación Académica</pre>'
            . '<div class="hb-seealso">Coordinación Académica</div>'
            . '<div class="hb-org"><span class="unit">Coordinación Académica</span></div>';

        $out = autolink_service::link_html($html, $this->targets());

        $this->assertStringNotContainsString('autolink', $out);
    }

    public function test_prose_and_callouts_still_link(): void {
        $out = autolink_service::link_html(
            '<h3>Coordinación Académica</h3>'
            . '<div class="hb-note"><p>Avisar a <strong>Dirección Oficial</strong>.</p></div>'
            . '<p>Consulte a Coordinación Académica.</p>', $this->targets());

        $this->assertStringContainsString('>Coordinación Académica</a>', $out);
        $this->assertStringContainsString('>Dirección Oficial</a>', $out);
    }

    public function test_attributes_and_utf8_are_preserved(): void {
        $html = '<p><img src="/x.png" alt="Coordinación Académica en reunión"> ñandú «café» 4.º B</p>';

        $out = autolink_service::link_html($html, $this->targets());

        $this->assertStringContainsString('alt="Coordinación Académica en reunión"', $out);
        $this->assertStringNotContainsString('autolink', $out);
        $this->assertStringContainsString('ñandú «café» 4.º B', $out);
    }

    public function test_unchanged_when_nothing_matches(): void {
        $this->assertSame('<p>Hola</p>', autolink_service::link_html('<p>Hola</p>', []));
        $plain = '<p>Nada que enlazar.</p>';
        $this->assertSame($plain, autolink_service::link_html($plain, $this->targets()));
    }
}
