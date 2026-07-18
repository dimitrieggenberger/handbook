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
 * Wikipedia-style automatic cross-links.
 *
 * When a page mentions the exact title of another published handbook page
 * ("Consejería Estudiantil", "Coordinación Académica", …) the mention is
 * turned into a link to that page — at render time only. Stored content is
 * never modified: the editor keeps clean text, links appear and disappear
 * as pages are published, renamed or archived, and switching the setting
 * off removes them everywhere instantly.
 *
 * Rules (deliberately Wikipedia-like):
 * - Whole-title matches only, case-insensitive, at word boundaries.
 * - Only the FIRST mention per page is linked — no link soup.
 * - The longest title wins where titles overlap ("Coordinación Académica"
 *   before a hypothetical "Coordinación").
 * - A page never links to itself.
 * - No links inside existing links, headings, code/pre, or the hb-*
 *   patterns that carry their own navigation (cross-references, next
 *   cards, org charts, document badges).
 * - Titles shorter than MIN_TITLE_LENGTH characters never auto-link
 *   (too many false positives).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autolink_service {

    /** @var int Titles shorter than this never auto-link. */
    const MIN_TITLE_LENGTH = 4;

    /** @var string[] Never link inside these elements. */
    const EXCLUDED_TAGS = ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'code',
        'script', 'style', 'button'];

    /** @var string[] Never link inside elements carrying one of these class tokens. */
    const EXCLUDED_CLASSES = ['hb-ref', 'hb-seealso', 'hb-refbox', 'hb-refs',
        'hb-next', 'hb-next-group', 'hb-doc', 'hb-org', 'hb-art-no'];

    /**
     * Whether automatic cross-linking is enabled.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $value = get_config('local_handbook', 'autolink');
        // Default ON when the setting has never been saved.
        return $value === false || (bool)$value;
    }

    /**
     * Auto-link mentions of other published pages in rendered content.
     *
     * @param string $html Rendered page content (post format_text).
     * @param int $currentpageid Page being displayed (never self-links).
     * @return string
     */
    public static function apply(string $html, int $currentpageid): string {
        if (!self::enabled() || trim($html) === '') {
            return $html;
        }
        return self::link_html($html, self::targets($currentpageid));
    }

    /**
     * Link targets: lowercased title => URL, longest titles first.
     *
     * @param int $excludepageid Page id to leave out (the current page).
     * @return string[] Map of mb_strtolower(title) => URL string.
     */
    public static function targets(int $excludepageid): array {
        global $DB;

        static $cache = null;
        static $cachedexclude = null;
        if ($cache !== null && $cachedexclude === $excludepageid) {
            return $cache;
        }

        $records = $DB->get_records_select('local_handbook_page',
            'publishedrevisionid > 0 AND archived = 0 AND id <> :id',
            ['id' => $excludepageid], '', 'id, title, slug');

        $targets = [];
        foreach ($records as $record) {
            $title = trim((string)$record->title);
            if (mb_strlen($title) < self::MIN_TITLE_LENGTH) {
                continue;
            }
            $url = new \moodle_url('/local/handbook/view.php', ['page' => $record->slug]);
            $targets[mb_strtolower($title)] = $url->out(false);
        }

        uksort($targets, static function (string $a, string $b): int {
            return mb_strlen($b) <=> mb_strlen($a);
        });

        $cache = $targets;
        $cachedexclude = $excludepageid;
        return $targets;
    }

    /**
     * Pure HTML transformation: first mention of each target becomes a link.
     *
     * Separated from the DB/config reads so it can be tested standalone.
     *
     * @param string $html Content HTML.
     * @param string[] $targets Map of lowercased title => URL, longest first.
     * @return string
     */
    public static function link_html(string $html, array $targets): string {
        if (!$targets || trim($html) === '') {
            return $html;
        }

        $pattern = self::build_pattern(array_keys($targets));

        // Cheap pre-check before any DOM work: most pages mention nothing.
        if (!preg_match($pattern, html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'))) {
            return $html;
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="utf-8"?><html><body><div id="hbautolinkroot">' . $html . '</div></body></html>',
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return $html;
        }
        $root = $doc->getElementById('hbautolinkroot');
        if (!$root) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $used = [];
        // Snapshot the node list: we replace nodes while iterating.
        $textnodes = [];
        foreach ($xpath->query('.//text()', $root) as $node) {
            $textnodes[] = $node;
        }

        foreach ($textnodes as $node) {
            if (trim($node->nodeValue) === '' || self::is_excluded($node, $root)) {
                continue;
            }
            $parts = preg_split($pattern, $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts === false || count($parts) < 2) {
                continue;
            }

            $fragment = $doc->createDocumentFragment();
            $changed = false;
            foreach ($parts as $i => $part) {
                if ($part === '') {
                    continue;
                }
                $key = mb_strtolower($part);
                if ($i % 2 === 1 && isset($targets[$key]) && empty($used[$key])) {
                    $used[$key] = true;
                    $changed = true;
                    $link = $doc->createElement('a');
                    $link->setAttribute('href', $targets[$key]);
                    $link->setAttribute('class', 'local-handbook-autolink');
                    $link->appendChild($doc->createTextNode($part));
                    $fragment->appendChild($link);
                } else {
                    $fragment->appendChild($doc->createTextNode($part));
                }
            }
            if ($changed && $node->parentNode) {
                $node->parentNode->replaceChild($fragment, $node);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    /**
     * One alternation regex over all titles, longest first, whole-word.
     *
     * @param string[] $titles Lowercased titles, longest first.
     * @return string
     */
    protected static function build_pattern(array $titles): string {
        $quoted = array_map(static function (string $title): string {
            return preg_quote($title, '/');
        }, $titles);
        return '/(?<![\p{L}\p{N}])(' . implode('|', $quoted) . ')(?![\p{L}\p{N}])/iu';
    }

    /**
     * Whether a text node sits inside an element that must not gain links.
     *
     * @param \DOMNode $node Text node.
     * @param \DOMNode $root Walk stops here.
     * @return bool
     */
    protected static function is_excluded(\DOMNode $node, \DOMNode $root): bool {
        for ($el = $node->parentNode; $el !== null && !$el->isSameNode($root); $el = $el->parentNode) {
            if (!($el instanceof \DOMElement)) {
                continue;
            }
            if (in_array(strtolower($el->tagName), self::EXCLUDED_TAGS, true)) {
                return true;
            }
            $classes = preg_split('/\s+/', trim((string)$el->getAttribute('class'))) ?: [];
            if (array_intersect($classes, self::EXCLUDED_CLASSES)) {
                return true;
            }
        }
        return false;
    }
}
