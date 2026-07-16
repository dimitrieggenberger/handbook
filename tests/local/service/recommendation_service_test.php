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
use stdClass;

/**
 * Tests for reading-path recommendations & audits (specification 10).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\recommendation_service
 */
final class recommendation_service_test extends advanced_testcase {

    /**
     * Publish a page and return its record.
     *
     * @param string $slug Slug.
     * @param bool $required Required-reading flag.
     * @return stdClass
     */
    private function page(string $slug, bool $required = false): stdClass {
        global $DB;

        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [['slug' => 'ops', 'name' => 'Operaciones']],
            'pages' => [[
                'slug' => $slug, 'title' => ucfirst($slug), 'category' => 'ops',
                'contenttype' => 'procedure', 'requiredreading' => $required ? 1 : 0,
                'summary' => 'S.', 'content' => '<h2>H</h2><p>C.</p>',
            ]],
        ])), true);
        return $DB->get_record('local_handbook_page', ['slug' => $slug], '*', MUST_EXIST);
    }

    /**
     * Create an active path with the given pages as required items.
     *
     * @param string $name Path name.
     * @param stdClass[] $pages Pages to add.
     * @return stdClass Path record.
     */
    private function path(string $name, array $pages = []): stdClass {
        global $DB;

        $now = time();
        $id = (int)$DB->insert_record('local_handbook_path', (object)[
            'name' => $name, 'slug' => strtolower($name) . '-' . random_string(5), 'description' => '',
            'descriptionformat' => 1, 'audiencejson' => '', 'schoolyear' => '2026', 'active' => 1,
            'pathtype' => '', 'estimatedminutes' => 0, 'reviewdate' => 0, 'quizcmid' => 0,
            'timecreated' => $now, 'timemodified' => $now, 'createdby' => 2, 'modifiedby' => 2,
        ]);
        foreach ($pages as $i => $page) {
            $DB->insert_record('local_handbook_pathitem', (object)[
                'pathid' => $id, 'pageid' => (int)$page->id, 'sectionname' => 'S1',
                'sortorder' => $i, 'required' => 1, 'quizcmid' => 0, 'rationale' => null,
            ]);
        }
        return $DB->get_record('local_handbook_path', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_candidate_from_typed_relation(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $canonical = $this->page('procedimiento');
        $quickguide = $this->page('guia-rapida');
        // Quick guide points at the canonical procedure.
        $DB->insert_record('local_handbook_relation', (object)[
            'sourcepageid' => $quickguide->id, 'targetpageid' => $canonical->id,
            'relationtype' => 'quickguidefor', 'sortorder' => 0, 'timecreated' => time(), 'createdby' => 2,
        ]);
        // A path already contains the canonical procedure.
        $path = $this->path('Ruta', [$canonical]);

        $candidates = recommendation_service::candidates_for_page((int)$quickguide->id);
        $this->assertCount(1, $candidates);
        $this->assertSame((int)$path->id, $candidates[0]->pathid);
        $this->assertSame('high', $candidates[0]->confidence);
    }

    public function test_create_is_idempotent_for_open(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->page('a');
        $path = $this->path('R');
        $first = recommendation_service::create((object)[
            'pathid' => $path->id, 'pageid' => $page->id, 'rectype' => 'add']);
        $second = recommendation_service::create((object)[
            'pathid' => $path->id, 'pageid' => $page->id, 'rectype' => 'add']);
        $this->assertEquals($first->id, $second->id);
    }

    public function test_accept_drafts_into_changeset_without_touching_active_path(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $inpath = $this->page('canonica');
        $newpage = $this->page('nueva');
        $path = $this->path('Ruta', [$inpath]);

        $rec = recommendation_service::create((object)[
            'pathid' => $path->id, 'pageid' => $newpage->id, 'rectype' => 'add',
            'suggestedrequired' => 1]);

        $changeset = changeset_service::create((object)['title' => 'CS', 'source' => 'human']);
        recommendation_service::accept_into_changeset((int)$rec->id, (int)$changeset->id);

        // A draft reading-path change item now exists.
        $item = $DB->get_record('local_handbook_changeitem', [
            'changesetid' => $changeset->id, 'kind' => changeset_service::KIND_READING_PATH]);
        $this->assertNotFalse($item);
        $this->assertSame(changeset_service::ITEM_DRAFT, $item->itemstatus);

        // The ACTIVE path is unchanged: still only the canonical page.
        $this->assertSame(1, $DB->count_records('local_handbook_pathitem', ['pathid' => $path->id]));
        $this->assertFalse($DB->record_exists('local_handbook_pathitem',
            ['pathid' => $path->id, 'pageid' => $newpage->id]));

        // The recommendation is marked accepted and linked to the change set.
        $rec = $DB->get_record('local_handbook_pathrec', ['id' => $rec->id], '*', MUST_EXIST);
        $this->assertSame(recommendation_service::STATUS_ACCEPTED, $rec->status);
        $this->assertSame((int)$changeset->id, (int)$rec->changesetid);

        // Publishing the change set applies the addition to the (now) path.
        changeset_service::approve_item((int)$item->id);
        changeset_service::publish_item((int)$item->id);
        $this->assertTrue($DB->record_exists('local_handbook_pathitem',
            ['pathid' => $path->id, 'pageid' => $newpage->id]));
    }

    public function test_coverage_counts_orphans(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $inpath = $this->page('dentro');
        $this->page('fuera');
        $this->path('Ruta', [$inpath]);

        $coverage = recommendation_service::coverage();
        $this->assertSame(2, $coverage->totalpages);
        $this->assertSame(1, $coverage->pagescovered);
        $this->assertSame(1, $coverage->orphans);
        $this->assertSame(1, $coverage->activepaths);
    }

    public function test_audit_flags_orphaned_required_page(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Required reading, in no path.
        $this->page('obligatoria-suelta', true);

        $audit = recommendation_service::audit();
        $kinds = array_map(static fn(stdClass $f): string => $f->kind, $audit);
        $this->assertContains('orphan_required', $kinds);
    }
}
