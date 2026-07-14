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
 * Word-level text diff for revision comparison (specification 11.4).
 *
 * Uses the longest-common-block recursion (Paul Butler's simplediff
 * approach): find the longest run of words common to both texts, recurse
 * on the parts before and after it. Operates on the revisions' normalized
 * plain text, not their HTML.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diff_service {

    /** @var int Token guard: beyond this, texts are compared paragraph-wise. */
    private const MAX_TOKENS = 6000;

    /**
     * Compute a word-level diff.
     *
     * @param string $old Old text.
     * @param string $new New text.
     * @return array List of segments: ['type' => 'same'|'del'|'ins', 'text' => string].
     */
    public static function diff_words(string $old, string $new): array {
        $oldtokens = self::tokenize($old);
        $newtokens = self::tokenize($new);

        // Very large texts: fall back to paragraph tokens to bound cost.
        if (count($oldtokens) > self::MAX_TOKENS || count($newtokens) > self::MAX_TOKENS) {
            $oldtokens = preg_split('/\n{2,}/', $old) ?: [];
            $newtokens = preg_split('/\n{2,}/', $new) ?: [];
            $separator = "\n\n";
        } else {
            $separator = ' ';
        }

        $segments = [];
        self::diff_recursive($oldtokens, $newtokens, $separator, $segments);
        return self::merge_segments($segments);
    }

    /**
     * Render a diff as HTML with <ins>/<del> marks. All text is escaped.
     *
     * @param array $segments Segments from diff_words().
     * @return string
     */
    public static function render_html(array $segments): string {
        $html = '';
        foreach ($segments as $segment) {
            $text = s($segment['text']);
            if ($segment['type'] === 'ins') {
                $html .= '<ins>' . $text . '</ins> ';
            } else if ($segment['type'] === 'del') {
                $html .= '<del>' . $text . '</del> ';
            } else {
                $html .= $text . ' ';
            }
        }
        return \html_writer::div(trim($html), 'local-handbook-diff');
    }

    /**
     * Whether a diff contains any change.
     *
     * @param array $segments Segments from diff_words().
     * @return bool
     */
    public static function has_changes(array $segments): bool {
        foreach ($segments as $segment) {
            if ($segment['type'] !== 'same') {
                return true;
            }
        }
        return false;
    }

    /**
     * Split text into word tokens.
     *
     * @param string $text Source text.
     * @return string[]
     */
    private static function tokenize(string $text): array {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        return $tokens ?: [];
    }

    /**
     * Recursive diff on token arrays.
     *
     * @param string[] $old Old tokens.
     * @param string[] $new New tokens.
     * @param string $separator Token separator for output.
     * @param array $segments Accumulator.
     * @return void
     */
    private static function diff_recursive(array $old, array $new, string $separator,
            array &$segments): void {
        if (!$old && !$new) {
            return;
        }
        if (!$old) {
            $segments[] = ['type' => 'ins', 'text' => implode($separator, $new)];
            return;
        }
        if (!$new) {
            $segments[] = ['type' => 'del', 'text' => implode($separator, $old)];
            return;
        }

        // Index old-token positions, then find the longest common block.
        $positions = [];
        foreach ($old as $index => $token) {
            $positions[$token][] = $index;
        }

        $beststart1 = 0;
        $beststart2 = 0;
        $bestlength = 0;
        $runs = [];
        foreach ($new as $index2 => $token) {
            $newruns = [];
            foreach ($positions[$token] ?? [] as $index1) {
                $length = ($runs[$index1 - 1] ?? 0) + 1;
                $newruns[$index1] = $length;
                if ($length > $bestlength) {
                    $bestlength = $length;
                    $beststart1 = $index1 - $length + 1;
                    $beststart2 = $index2 - $length + 1;
                }
            }
            $runs = $newruns;
        }

        if ($bestlength === 0) {
            $segments[] = ['type' => 'del', 'text' => implode($separator, $old)];
            $segments[] = ['type' => 'ins', 'text' => implode($separator, $new)];
            return;
        }

        self::diff_recursive(
            array_slice($old, 0, $beststart1),
            array_slice($new, 0, $beststart2),
            $separator, $segments);
        $segments[] = ['type' => 'same',
            'text' => implode($separator, array_slice($old, $beststart1, $bestlength))];
        self::diff_recursive(
            array_slice($old, $beststart1 + $bestlength),
            array_slice($new, $beststart2 + $bestlength),
            $separator, $segments);
    }

    /**
     * Merge adjacent segments of the same type.
     *
     * @param array $segments Raw segments.
     * @return array
     */
    private static function merge_segments(array $segments): array {
        $merged = [];
        foreach ($segments as $segment) {
            if ($segment['text'] === '') {
                continue;
            }
            $last = count($merged) - 1;
            if ($last >= 0 && $merged[$last]['type'] === $segment['type']) {
                $merged[$last]['text'] .= ' ' . $segment['text'];
            } else {
                $merged[] = $segment;
            }
        }
        return $merged;
    }
}
