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
use context_system;
use stdClass;

/**
 * Tests for reading-path audiences (specification 15.3).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\path_service
 */
final class path_service_test extends advanced_testcase {

    /**
     * Create a path with the given audience JSON.
     *
     * @param string $audiencejson Audience definition.
     * @return stdClass Path record.
     */
    private function create_path(string $audiencejson): stdClass {
        global $DB;

        $id = $DB->insert_record('local_handbook_path', (object)[
            'name' => 'Ruta', 'slug' => 'ruta-' . random_string(5), 'description' => '',
            'descriptionformat' => 1, 'audiencejson' => $audiencejson, 'schoolyear' => '2026-2027',
            'active' => 1, 'quizcmid' => 0, 'timecreated' => time(), 'timemodified' => time(),
            'createdby' => 2, 'modifiedby' => 2,
        ]);
        return $DB->get_record('local_handbook_path', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_unrestricted_path_visible_to_everyone(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $path = $this->create_path('');
        $this->assertTrue(path_service::is_visible($path, (int)$user->id));
    }

    public function test_cohort_audience(): void {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);

        $path = $this->create_path(path_service::encode_audience([(int)$cohort->id], []));

        $this->assertTrue(path_service::is_visible($path, (int)$member->id));
        $this->assertFalse(path_service::is_visible($path, (int)$outsider->id));

        $audienceusers = path_service::get_audience_users($path);
        $this->assertArrayHasKey((int)$member->id, $audienceusers);
        $this->assertArrayNotHasKey((int)$outsider->id, $audienceusers);
    }

    public function test_role_audience_at_system_context(): void {
        $this->resetAfterTest();

        $holder = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        $roleid = create_role('Docente', 'docentetest', '');
        role_assign($roleid, $holder->id, context_system::instance()->id);

        $path = $this->create_path(path_service::encode_audience([], [$roleid]));

        $this->assertTrue(path_service::is_visible($path, (int)$holder->id));
        $this->assertFalse(path_service::is_visible($path, (int)$outsider->id));
    }

    public function test_visible_paths_filters_and_manager_override(): void {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $outsider = $this->getDataGenerator()->create_user();

        $this->create_path('');
        $this->create_path(path_service::encode_audience([(int)$cohort->id], []));

        $this->assertCount(1, path_service::visible_paths((int)$outsider->id));
        $this->assertCount(2, path_service::visible_paths((int)$outsider->id, true));
    }

    public function test_encode_audience_empty_means_unrestricted(): void {
        $this->assertSame('', path_service::encode_audience([], []));
        $this->assertSame('', path_service::encode_audience([0], ['']));

        $encoded = path_service::encode_audience([3, 5], [7]);
        $decoded = json_decode($encoded);
        $this->assertSame([3, 5], $decoded->cohorts);
        $this->assertSame([7], $decoded->roles);
    }

    /**
     * Publish a page (optionally globally required) and add it to a path.
     *
     * @param stdClass $path Path record.
     * @param string $slug Page slug.
     * @param bool $globalrequired Global required-reading flag.
     * @param bool $pathrequired Whether the path item is required.
     * @return stdClass Page record.
     */
    private function add_page(stdClass $path, string $slug, bool $globalrequired,
            bool $pathrequired): stdClass {
        global $DB;

        set_config('bootstrapmode', 1, 'local_handbook');
        import_service::import(json_decode(json_encode([
            'categories' => [['slug' => 'ops', 'name' => 'Operaciones']],
            'pages' => [[
                'slug' => $slug, 'title' => ucfirst($slug), 'category' => 'ops',
                'contenttype' => 'procedure', 'requiredreading' => $globalrequired ? 1 : 0,
                'summary' => 'S.', 'content' => '<h2>H</h2><p>C.</p>',
            ]],
        ])), true);
        $page = $DB->get_record('local_handbook_page', ['slug' => $slug], '*', MUST_EXIST);

        $sort = (int)$DB->count_records('local_handbook_pathitem', ['pathid' => $path->id]);
        $DB->insert_record('local_handbook_pathitem', (object)[
            'pathid' => (int)$path->id, 'pageid' => (int)$page->id, 'sectionname' => 'S1',
            'sortorder' => $sort, 'required' => $pathrequired ? 1 : 0, 'quizcmid' => 0,
            'rationale' => null,
        ]);
        return $page;
    }

    public function test_progress_counts_path_required_even_when_not_globally_required(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $path = $this->create_path('');

        // Path-required, but NOT globally required reading.
        $page = $this->add_page($path, 'induccion', false, true);

        $before = path_service::user_progress($path, (int)$user->id);
        $this->assertSame(1, $before->total);
        $this->assertSame(0, $before->confirmed);
        $this->assertNotNull($before->nextitem);

        completion_service::record_receipt((int)$user->id, $page);

        $after = path_service::user_progress($path, (int)$user->id);
        $this->assertSame(1, $after->total);
        $this->assertSame(1, $after->confirmed);
        $this->assertNull($after->nextitem);
    }

    public function test_optional_items_do_not_block_completion(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $path = $this->create_path('');

        $this->add_page($path, 'requerida', false, true);
        $this->add_page($path, 'opcional', false, false);

        $progress = path_service::user_progress($path, (int)$user->id);
        // Only the required item is counted in the total.
        $this->assertSame(1, $progress->total);
    }

    public function test_is_required_in_active_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $path = $this->create_path('');
        $required = $this->add_page($path, 'obligatoria', false, true);
        $optional = $this->add_page($path, 'sugerida', false, false);

        $this->assertTrue(path_service::is_required_in_active_path((int)$required->id));
        $this->assertFalse(path_service::is_required_in_active_path((int)$optional->id));
    }
}
