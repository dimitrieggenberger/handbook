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

use stdClass;

/**
 * End-of-article reading-comprehension tests.
 *
 * A page with questions loses its "confirm reading" button: passing the
 * test with 100% records the same read receipt / acknowledgement the
 * button records, so paths, the dashboard and re-acknowledgement keep
 * working unchanged. All-or-nothing, unlimited attempts, options shuffled
 * per attempt; wrong answers show the pauta's mandatory feedback.
 *
 * Questions belong to REVISIONS and ride the editorial workflow: they are
 * imported into a working draft, reviewed together with the content, and go
 * live when the revision is published. Readers always answer the published
 * revision's questions. Imported from Moodle XML (the institutional pauta)
 * by a human editor — the AI can author XML but has no import surface.
 * Supported types: multichoice (single answer, 4 options recommended) and
 * ordering (qtype_ordering convention: answers listed in correct order).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_service {

    /** @var string Single-answer multiple choice. */
    const TYPE_MULTICHOICE = 'multichoice';

    /** @var string Order-the-steps (tap in sequence). */
    const TYPE_ORDERING = 'ordering';

    /**
     * Whether a revision has comprehension questions.
     *
     * @param int $revisionid Revision id (0 returns false).
     * @return bool
     */
    public static function has_questions(int $revisionid): bool {
        global $DB;
        return $revisionid > 0
            && $DB->record_exists('local_handbook_question', ['revisionid' => $revisionid]);
    }

    /**
     * Questions of a revision with their options, ordered.
     *
     * @param int $revisionid Revision id (0 returns []).
     * @return stdClass[] Questions, each with ->options (by sortorder).
     */
    public static function get_questions(int $revisionid): array {
        global $DB;

        if ($revisionid <= 0) {
            return [];
        }
        $questions = $DB->get_records('local_handbook_question', ['revisionid' => $revisionid],
            'sortorder ASC, id ASC');
        if (!$questions) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($questions), SQL_PARAMS_NAMED);
        $options = $DB->get_records_select('local_handbook_qoption', "questionid $insql",
            $params, 'sortorder ASC, id ASC');
        foreach ($questions as $question) {
            $question->options = [];
        }
        foreach ($options as $option) {
            $questions[(int)$option->questionid]->options[] = $option;
        }
        return array_values($questions);
    }

    /**
     * Parse Moodle XML (the institutional pauta) into normalized question
     * definitions. Pure: no DB access, so it is testable standalone.
     *
     * Supported: multichoice (single; the fraction=100 answer is correct;
     * per-option feedback) and ordering (answers in document order are the
     * correct sequence; generalfeedback becomes the wrong-order feedback).
     * Category dummies and unsupported types are skipped with a warning.
     *
     * @param string $xml Moodle XML.
     * @return stdClass {questions: array, warnings: string[], errors: string[]}
     */
    public static function parse_xml(string $xml): stdClass {
        $result = (object)['questions' => [], 'warnings' => [], 'errors' => []];

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($doc === false || $doc->getName() !== 'quiz') {
            $result->errors[] = get_string('qimporterrorxml', 'local_handbook');
            return $result;
        }

        foreach ($doc->question as $qnode) {
            $type = (string)($qnode['type'] ?? '');
            if ($type === 'category') {
                continue;
            }
            if (!in_array($type, [self::TYPE_MULTICHOICE, self::TYPE_ORDERING], true)) {
                $result->warnings[] = get_string('qimportskippedtype', 'local_handbook', $type);
                continue;
            }

            $name = trim((string)($qnode->name->text ?? ''));
            $rawtext = trim((string)($qnode->questiontext->text ?? ''));
            $parts = self::split_pauta_text($rawtext);
            $question = (object)[
                'qtype' => $type,
                'bloomlabel' => $parts->bloom,
                'title' => $parts->title !== '' ? $parts->title : $name,
                'questiontext' => $parts->text,
                'feedback' => trim((string)($qnode->generalfeedback->text ?? '')),
                'options' => [],
            ];

            $position = 0;
            foreach ($qnode->answer as $answernode) {
                $fraction = (float)($answernode['fraction'] ?? 0);
                $question->options[] = (object)[
                    'optiontext' => trim((string)$answernode->text),
                    'feedback' => trim((string)($answernode->feedback->text ?? '')),
                    'iscorrect' => $type === self::TYPE_MULTICHOICE ? (int)($fraction >= 100) : 0,
                    'sortorder' => $position++,
                ];
            }

            // Validation.
            if ($question->title === '') {
                $result->warnings[] = get_string('qimportnotitle', 'local_handbook');
            }
            if ($type === self::TYPE_MULTICHOICE) {
                $correct = array_filter($question->options,
                    static fn(stdClass $o): bool => (bool)$o->iscorrect);
                if (count($question->options) < 2 || count($correct) !== 1) {
                    $result->errors[] = get_string('qimportbadmc', 'local_handbook',
                        s($question->title));
                    continue;
                }
                foreach ($question->options as $option) {
                    if ($option->feedback === '') {
                        $result->warnings[] = get_string('qimportnofeedback', 'local_handbook',
                            s($question->title));
                        break;
                    }
                }
            } else {
                if (count($question->options) < 2) {
                    $result->errors[] = get_string('qimportbadordering', 'local_handbook',
                        s($question->title));
                    continue;
                }
            }

            $result->questions[] = $question;
        }

        $count = count($result->questions);
        if ($count > 0 && ($count < 2 || $count > 6)) {
            $result->warnings[] = get_string('qimportcountwarning', 'local_handbook', $count);
        }
        if ($count === 0 && !$result->errors) {
            $result->errors[] = get_string('qimportempty', 'local_handbook');
        }

        return $result;
    }

    /**
     * Split the pauta's questiontext structure (p.q-subtitle / p.q-title /
     * p.q-text ...) into its parts. Tolerant: content without the pauta
     * classes lands whole in ->text. The q-teachercomment block (editor
     * guidance, not for readers) is dropped.
     *
     * @param string $html Question text HTML.
     * @return stdClass {bloom, title, text}
     */
    public static function split_pauta_text(string $html): stdClass {
        $out = (object)['bloom' => '', 'title' => '', 'text' => $html];
        if ($html === '' || stripos($html, 'q-title') === false && stripos($html, 'q-text') === false
                && stripos($html, 'q-subtitle') === false) {
            return $out;
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="utf-8"?><div id="r">' . $html . '</div>',
            LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !($root = $doc->getElementById('r'))) {
            return $out;
        }

        $rest = '';
        foreach (iterator_to_array($root->childNodes) as $node) {
            $classes = $node instanceof \DOMElement
                ? preg_split('/\s+/', trim($node->getAttribute('class'))) : [];
            $text = trim($node->textContent);
            if (in_array('q-subtitle', $classes, true)) {
                $out->bloom = $text;
            } else if (in_array('q-title', $classes, true)) {
                $out->title = $text;
            } else if (in_array('q-teachercomment', $classes, true)) {
                continue;
            } else {
                $rest .= $doc->saveHTML($node);
            }
        }
        $out->text = trim($rest);
        return $out;
    }

    /**
     * Replace a revision's questions with an imported set (transactional).
     * Callers must ensure the revision is an editable draft: published
     * questions are immutable, like published content.
     *
     * @param int $revisionid Revision id.
     * @param stdClass[] $questions Normalized questions from parse_xml().
     * @param int $userid Importing editor.
     * @return void
     */
    public static function import(int $revisionid, array $questions, int $userid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        self::delete_all($revisionid);
        $now = time();
        $sortorder = 0;
        foreach ($questions as $question) {
            $questionid = $DB->insert_record('local_handbook_question', (object)[
                'revisionid' => $revisionid,
                'qtype' => $question->qtype,
                'bloomlabel' => quiz_truncate255($question->bloomlabel),
                'title' => quiz_truncate255($question->title),
                'questiontext' => $question->questiontext,
                'feedback' => $question->feedback,
                'sortorder' => $sortorder++,
                'timemodified' => $now,
                'modifiedby' => $userid,
            ]);
            foreach ($question->options as $option) {
                $DB->insert_record('local_handbook_qoption', (object)[
                    'questionid' => $questionid,
                    'optiontext' => $option->optiontext,
                    'feedback' => $option->feedback,
                    'iscorrect' => (int)$option->iscorrect,
                    'sortorder' => (int)$option->sortorder,
                ]);
            }
        }
        $transaction->allow_commit();
    }

    /**
     * Remove every question of a revision.
     *
     * @param int $revisionid Revision id.
     * @return void
     */
    public static function delete_all(int $revisionid): void {
        global $DB;
        $questionids = $DB->get_fieldset_select('local_handbook_question', 'id',
            'revisionid = :revisionid', ['revisionid' => $revisionid]);
        if ($questionids) {
            $DB->delete_records_list('local_handbook_qoption', 'questionid', $questionids);
        }
        $DB->delete_records('local_handbook_question', ['revisionid' => $revisionid]);
    }

    /**
     * Copy a revision's question set to another revision (draft creation:
     * the new draft inherits the published questions, like content).
     *
     * @param int $fromrevisionid Source revision (0 or empty set = no-op).
     * @param int $torevisionid Target revision.
     * @return void
     */
    public static function copy_questions(int $fromrevisionid, int $torevisionid): void {
        global $DB;

        if ($fromrevisionid <= 0 || $torevisionid <= 0 || $fromrevisionid === $torevisionid) {
            return;
        }
        foreach (self::get_questions($fromrevisionid) as $question) {
            $copy = clone $question;
            $options = $question->options;
            unset($copy->id, $copy->options);
            $copy->revisionid = $torevisionid;
            $newid = $DB->insert_record('local_handbook_question', $copy);
            foreach ($options as $option) {
                $optioncopy = clone $option;
                unset($optioncopy->id);
                $optioncopy->questionid = $newid;
                $DB->insert_record('local_handbook_qoption', $optioncopy);
            }
        }
    }

    /**
     * Content fingerprint of a revision's question set, for "questions
     * changed in this draft" indicators. Ignores ids and timestamps.
     *
     * @param int $revisionid Revision id.
     * @return string Empty string when there are no questions.
     */
    public static function fingerprint(int $revisionid): string {
        $normalized = [];
        foreach (self::get_questions($revisionid) as $question) {
            $options = [];
            foreach (self::sorted_options($question) as $option) {
                $options[] = [$option->optiontext, $option->feedback, (int)$option->iscorrect];
            }
            $normalized[] = [$question->qtype, $question->bloomlabel, $question->title,
                $question->questiontext, $question->feedback, $options];
        }
        return $normalized ? sha1(json_encode($normalized)) : '';
    }

    /**
     * Grade a submission. Pure: operates on the questions array from
     * get_questions() and the raw responses, no DB access.
     *
     * Responses: [questionid => value]; multichoice value = option id,
     * ordering value = comma-separated option ids in the chosen order.
     *
     * @param stdClass[] $questions Questions with options.
     * @param array $responses Raw responses keyed by question id.
     * @return stdClass {passed, ncorrect, ntotal, perquestion: [questionid => detail]}
     */
    public static function grade(array $questions, array $responses): stdClass {
        $result = (object)[
            'passed' => false,
            'ncorrect' => 0,
            'ntotal' => count($questions),
            'perquestion' => [],
        ];

        foreach ($questions as $question) {
            $qid = (int)$question->id;
            $raw = trim((string)($responses[$qid] ?? ''));
            $detail = (object)['ok' => false, 'chosen' => [], 'positions' => []];

            if ($question->qtype === self::TYPE_ORDERING) {
                $chosen = array_values(array_filter(array_map('intval',
                    $raw === '' ? [] : explode(',', $raw))));
                $correct = array_map(static fn(stdClass $o): int => (int)$o->id,
                    self::sorted_options($question));
                $detail->chosen = $chosen;
                $valid = count($chosen) === count($correct)
                    && !array_diff($chosen, $correct) && !array_diff($correct, $chosen);
                if ($valid) {
                    foreach ($chosen as $index => $optionid) {
                        $detail->positions[$optionid] = ($optionid === $correct[$index]);
                    }
                    $detail->ok = ($chosen === $correct);
                }
            } else {
                $chosenid = (int)$raw;
                $detail->chosen = $chosenid ? [$chosenid] : [];
                foreach ($question->options as $option) {
                    if ((int)$option->id === $chosenid) {
                        $detail->ok = (bool)$option->iscorrect;
                        break;
                    }
                }
            }

            if ($detail->ok) {
                $result->ncorrect++;
            }
            $result->perquestion[$qid] = $detail;
        }

        $result->passed = $result->ntotal > 0 && $result->ncorrect === $result->ntotal;
        return $result;
    }

    /**
     * A question's options in correct order (by sortorder).
     *
     * @param stdClass $question Question with options.
     * @return stdClass[]
     */
    public static function sorted_options(stdClass $question): array {
        $options = $question->options;
        usort($options, static fn(stdClass $a, stdClass $b): int =>
            [(int)$a->sortorder, (int)$a->id] <=> [(int)$b->sortorder, (int)$b->id]);
        return $options;
    }

    /**
     * Record an attempt (audit trail; passing separately records the receipt).
     *
     * @param int $userid User.
     * @param int $pageid Page.
     * @param int $revisionid Published revision at attempt time.
     * @param stdClass $graded Result from grade().
     * @return void
     */
    public static function record_attempt(int $userid, int $pageid, int $revisionid,
            stdClass $graded): void {
        global $DB;
        $DB->insert_record('local_handbook_qattempt', (object)[
            'userid' => $userid,
            'pageid' => $pageid,
            'revisionid' => $revisionid,
            'ncorrect' => (int)$graded->ncorrect,
            'ntotal' => (int)$graded->ntotal,
            'passed' => (int)$graded->passed,
            'timecreated' => time(),
        ]);
    }
}

/**
 * Truncate to the 255-char columns without mb hazards.
 *
 * @param string $value Raw value.
 * @return string
 */
function quiz_truncate255(string $value): string {
    return \core_text::substr($value, 0, 255);
}
