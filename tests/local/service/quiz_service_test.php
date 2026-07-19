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
 * Tests for the comprehension-test parser and grader (pure parts).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\quiz_service
 */
final class quiz_service_test extends \advanced_testcase {

    /**
     * Pauta-shaped XML with a multichoice, an ordering, a category dummy
     * and an unsupported essay.
     *
     * @return string
     */
    private function samplexml(): string {
        return <<<'XML'
<?xml version="1.0" ?>
<quiz>
  <question type="category"><category><text>$course$/Manual</text></category></question>
  <question type="multichoice">
    <name><text>P1</text></name>
    <questiontext format="html"><text><![CDATA[
      <p class="q-subtitle">Recordar: Opción múltiple</p>
      <p class="q-title">Plazo de autorizaciones</p>
      <p class="q-text">¿Cuál es el plazo <strong>máximo</strong>?</p>
      <p class="q-teachercomment">Editor: sin imagen.</p>
    ]]></text></questiontext>
    <answer fraction="100"><text>Tres días hábiles antes.</text>
      <feedback><text>Correcto.</text></feedback></answer>
    <answer fraction="-50"><text>El mismo día.</text>
      <feedback><text>No.</text></feedback></answer>
    <answer fraction="-50"><text>Una semana después.</text>
      <feedback><text>No.</text></feedback></answer>
    <answer fraction="-50"><text>Solo si lo piden.</text>
      <feedback><text>No.</text></feedback></answer>
    <single>true</single>
  </question>
  <question type="ordering">
    <name><text>Orden incidente</text></name>
    <questiontext format="html"><text><![CDATA[
      <p class="q-title">Pasos ante un incidente</p>]]></text></questiontext>
    <generalfeedback><text>La atención precede a la comunicación.</text></generalfeedback>
    <answer fraction="1"><text>Asegurar atención</text></answer>
    <answer fraction="2"><text>Notificar a enfermería</text></answer>
    <answer fraction="3"><text>Comunicar a la familia</text></answer>
  </question>
  <question type="essay">
    <name><text>Ensayo</text></name>
    <questiontext format="html"><text>Escribe.</text></questiontext>
  </question>
</quiz>
XML;
    }

    public function test_parse_supported_types_and_pauta_structure(): void {
        $parsed = quiz_service::parse_xml($this->samplexml());

        $this->assertCount(2, $parsed->questions);
        $this->assertEmpty($parsed->errors);

        $mc = $parsed->questions[0];
        $this->assertSame('Recordar: Opción múltiple', $mc->bloomlabel);
        $this->assertSame('Plazo de autorizaciones', $mc->title);
        $this->assertStringContainsString('<strong>máximo</strong>', $mc->questiontext);
        $this->assertStringNotContainsString('Editor:', $mc->questiontext);
        $this->assertCount(4, $mc->options);
        $this->assertSame(1, array_sum(array_map(
            static fn($o) => (int)$o->iscorrect, $mc->options)));

        $ordering = $parsed->questions[1];
        $this->assertSame(quiz_service::TYPE_ORDERING, $ordering->qtype);
        $this->assertStringContainsString('atención precede', $ordering->feedback);
        $this->assertSame('Asegurar atención', $ordering->options[0]->optiontext);
        $this->assertSame(2, $ordering->options[2]->sortorder);
    }

    public function test_parse_rejects_invalid_definitions(): void {
        $twocorrect = quiz_service::parse_xml('<quiz><question type="multichoice">'
            . '<name><text>X</text></name>'
            . '<questiontext format="html"><text>t</text></questiontext>'
            . '<answer fraction="100"><text>a</text></answer>'
            . '<answer fraction="100"><text>b</text></answer></question></quiz>');
        $this->assertEmpty($twocorrect->questions);
        $this->assertNotEmpty($twocorrect->errors);

        $garbage = quiz_service::parse_xml('not xml');
        $this->assertNotEmpty($garbage->errors);
    }

    public function test_grading_is_all_or_nothing_and_tamper_proof(): void {
        $questions = [
            (object)['id' => 11, 'qtype' => 'multichoice', 'options' => [
                (object)['id' => 111, 'iscorrect' => 1, 'sortorder' => 0],
                (object)['id' => 112, 'iscorrect' => 0, 'sortorder' => 1],
            ]],
            (object)['id' => 22, 'qtype' => 'ordering', 'options' => [
                (object)['id' => 221, 'iscorrect' => 0, 'sortorder' => 0],
                (object)['id' => 222, 'iscorrect' => 0, 'sortorder' => 1],
                (object)['id' => 223, 'iscorrect' => 0, 'sortorder' => 2],
            ]],
        ];

        $pass = quiz_service::grade($questions, [11 => '111', 22 => '221,222,223']);
        $this->assertTrue($pass->passed);

        $wrongmc = quiz_service::grade($questions, [11 => '112', 22 => '221,222,223']);
        $this->assertFalse($wrongmc->passed);
        $this->assertSame(1, $wrongmc->ncorrect);

        $wrongorder = quiz_service::grade($questions, [11 => '111', 22 => '222,221,223']);
        $this->assertFalse($wrongorder->passed);
        $this->assertFalse($wrongorder->perquestion[22]->positions[222]);
        $this->assertTrue($wrongorder->perquestion[22]->positions[223]);

        // Tampered submissions never pass.
        $this->assertFalse(quiz_service::grade($questions,
            [11 => '111', 22 => '221,221,223'])->perquestion[22]->ok);
        $this->assertFalse(quiz_service::grade($questions,
            [11 => '111', 22 => '221,222,999'])->perquestion[22]->ok);
        $this->assertFalse(quiz_service::grade($questions, [11 => '111'])->passed);
    }
}
