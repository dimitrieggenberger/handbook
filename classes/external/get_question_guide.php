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

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: the authoring guide for end-of-article comprehension
 * questions, plus which published pages already have questions.
 *
 * Read-only and advisory: the AI uses it to WRITE valid Moodle XML (per the
 * institutional pauta) that a human editor pastes into the import form at
 * manage/questions.php. There is deliberately no API surface to import,
 * modify or delete questions — the reading-accreditation gate stays under
 * human control, like approval and publication.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_question_guide extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return the guide, the XML template and per-page question counts.
     *
     * @return array
     */
    public static function execute(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/handbook/locallib.php');

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_read($context);

        $counts = [];
        $sql = "SELECT p.slug, COUNT(q.id) AS questioncount
                  FROM {local_handbook_page} p
                  JOIN {local_handbook_question} q ON q.pageid = p.id
                 WHERE p.publishedrevisionid > 0 AND p.archived = 0
              GROUP BY p.slug";
        foreach ($DB->get_records_sql($sql) as $record) {
            $counts[] = ['slug' => $record->slug, 'questioncount' => (int)$record->questioncount];
        }

        return [
            'guide' => self::guide_text(),
            'xmltemplate' => self::xml_template(),
            'pageswithquestions' => $counts,
        ];
    }

    /**
     * The authoring rules: the institutional pauta condensed, plus exactly
     * what the plugin's importer enforces and how the test behaves.
     *
     * @return string
     */
    protected static function guide_text(): string {
        return <<<'TXT'
GUÍA DE PREGUNTAS DE COMPRENSIÓN DE LECTURA (Manual Institucional)

Propósito: cada artículo puede llevar 2–6 preguntas al final. Aciertan todas
o no se acredita la lectura (sin crédito parcial, intentos ilimitados, las
opciones se barajan en cada intento). Al aprobar con 100% se registra la
lectura del artículo — el equivalente del botón "Confirmar lectura".

FLUJO: 1) Lee el artículo completo (get_page). 2) Redacta el XML según esta
guía. 3) Entrega el XML al editor humano: él lo pega en el formulario de
importación de la página (manage/questions.php). LA IA NO PUEDE IMPORTAR —
la importación es exclusivamente humana.

TIPOS ADMITIDOS (los demás se omiten al importar):
1. multichoice — opción múltiple, 4 opciones, EXACTAMENTE una correcta
   (fraction="100"); distractores con fraction="-50". Retroalimentación
   OBLIGATORIA en cada opción: explica por qué es correcta/incorrecta y, en
   las incorrectas, remite a la sección del artículo a repasar (puede
   incluir un enlace <a href="#anchor">).
2. ordering — ordenar pasos de un procedimiento (3–6 pasos). Las respuestas
   se listan EN EL ORDEN CORRECTO en el XML. REGLA CRÍTICA: solo incluir
   pasos cuyo orden es obligatorio; si dos pasos pueden ocurrir en cualquier
   orden, no van en la misma pregunta de ordenar. <generalfeedback> es la
   retroalimentación mostrada cuando el orden es incorrecto (sin revelar la
   secuencia completa) — remite a la sección del procedimiento.

ESTRUCTURA DEL TEXTO DE CADA PREGUNTA (dentro de CDATA):
- <p class="q-subtitle">: nivel de Bloom y tipo, p. ej. "Comprender: Opción
  múltiple" o "Aplicar: Ordenar".
- <p class="q-title">: título natural que NUNCA insinúa la respuesta.
- <p class="q-text">: el enunciado (puede usar <ul>, <li>, <p>).
- <p class="q-teachercomment">: opcional; guía para el editor, se elimina al
  importar y los lectores no la ven.

CALIDAD:
- Preguntar solo sobre lo que el artículo realmente dice; nada de
  conocimiento externo ni trucos.
- Mezclar niveles de Bloom: recordar (datos: plazos, montos, responsables),
  comprender (por qué), aplicar (mini-casos: "¿qué hace el docente si…?").
- Los distractores deben ser errores plausibles que un lector apresurado
  cometería — nunca opciones absurdas.
- 2 preguntas para artículos cortos, hasta 6 para largos o críticos.
- Español institucional, nombres inventados en los mini-casos.

VALIDACIÓN DEL IMPORTADOR (lo que rechaza o advierte):
- XML mal formado → rechazo total. multichoice sin exactamente 1 correcta →
  pregunta rechazada. Opciones sin feedback → advertencia. Menos de 2 o más
  de 6 preguntas → advertencia. Tipos no admitidos (essay, cloze, matching,
  truefalse) → omitidos con aviso.
TXT;
    }

    /**
     * Copy-adapt XML skeleton with one multichoice and one ordering question.
     *
     * @return string
     */
    protected static function xml_template(): string {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<quiz>
  <question type="multichoice">
    <name><text>[Título corto interno]</text></name>
    <questiontext format="html">
      <text><![CDATA[
        <p class="q-subtitle">Comprender: Opción múltiple</p>
        <p class="q-title">[Título natural, sin pistas]</p>
        <p class="q-text">[Enunciado de la pregunta]</p>
      ]]></text>
    </questiontext>
    <answer fraction="100">
      <text>[Respuesta correcta]</text>
      <feedback><text>Correcto: [por qué].</text></feedback>
    </answer>
    <answer fraction="-50">
      <text>[Distractor plausible]</text>
      <feedback><text>No: [por qué no; revisar la sección X].</text></feedback>
    </answer>
    <answer fraction="-50">
      <text>[Distractor plausible]</text>
      <feedback><text>No: [por qué no].</text></feedback>
    </answer>
    <answer fraction="-50">
      <text>[Distractor plausible]</text>
      <feedback><text>No: [por qué no].</text></feedback>
    </answer>
    <shuffleanswers>1</shuffleanswers>
    <single>true</single>
    <answernumbering>abc</answernumbering>
  </question>
  <question type="ordering">
    <name><text>[Título corto interno]</text></name>
    <questiontext format="html">
      <text><![CDATA[
        <p class="q-subtitle">Aplicar: Ordenar</p>
        <p class="q-title">[Título natural]</p>
        <p class="q-text">Ordena los pasos según el procedimiento.</p>
      ]]></text>
    </questiontext>
    <generalfeedback><text>[Principio violado al ordenar mal; sección a repasar.]</text></generalfeedback>
    <answer fraction="1"><text>[Primer paso]</text></answer>
    <answer fraction="2"><text>[Segundo paso]</text></answer>
    <answer fraction="3"><text>[Tercer paso]</text></answer>
  </question>
</quiz>
XML;
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'guide' => new external_value(PARAM_RAW, 'Authoring rules (pauta condensed + importer validation)'),
            'xmltemplate' => new external_value(PARAM_RAW, 'Moodle XML skeleton to copy and adapt'),
            'pageswithquestions' => new external_multiple_structure(new external_single_structure([
                'slug' => new external_value(PARAM_ALPHANUMEXT, 'Page slug'),
                'questioncount' => new external_value(PARAM_INT, 'Number of comprehension questions'),
            ]), 'Published pages that already have questions (absent slug = no questions yet)'),
        ]);
    }
}
