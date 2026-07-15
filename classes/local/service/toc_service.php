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
 * Heading anchors and on-page table of contents (specification 10.2, 12.2).
 *
 * Operates on the reader's rendered content (headings already demoted to
 * h3): injects stable ids into headings that lack one and returns the TOC
 * entries. Existing ids are preserved so hand-written anchors stay stable.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toc_service {

    /**
     * Inject heading ids and extract the table of contents.
     *
     * @param string $html Rendered content (top-level headings are h3).
     * @return stdClass {html: string, toc: [{id, text}]}
     */
    public static function add_anchors(string $html): stdClass {
        $toc = [];
        $used = [];

        $result = preg_replace_callback(
            '~<h3([^>]*)>(.*?)</h3>~is',
            static function(array $match) use (&$toc, &$used): string {
                $attributes = $match[1];
                $inner = $match[2];
                $text = trim(html_to_text($inner, 0, false));
                if ($text === '') {
                    return $match[0];
                }

                // Preserve an existing id; otherwise derive one from the text.
                if (preg_match('~\bid\s*=\s*["\']([^"\']+)["\']~i', $attributes, $idmatch)) {
                    $id = $idmatch[1];
                } else {
                    $id = page_service::slugify($text);
                    if ($id === '' || ctype_digit($id)) {
                        $id = 'seccion-' . $id;
                    }
                    $suffix = 2;
                    $candidate = $id;
                    while (isset($used[$candidate])) {
                        $candidate = $id . '-' . $suffix++;
                    }
                    $id = $candidate;
                    $attributes .= ' id="' . s($id) . '"';
                }
                $used[$id] = true;

                $toc[] = (object)['id' => $id, 'text' => $text];
                return '<h3' . $attributes . '>' . $inner . '</h3>';
            },
            $html
        );

        return (object)['html' => $result ?? $html, 'toc' => $toc];
    }
}
