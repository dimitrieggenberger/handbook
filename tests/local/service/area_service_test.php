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
 * Tests for the responsible-area controlled vocabulary (specification 9).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\area_service
 */
final class area_service_test extends advanced_testcase {

    /**
     * Insert an active area.
     *
     * @param string $key Area key.
     * @param string $name Display name.
     * @return void
     */
    private function add_area(string $key, string $name): void {
        global $DB;
        $DB->insert_record('local_handbook_area', (object)[
            'areakey' => $key, 'name' => $name, 'active' => 1, 'sortorder' => 0,
            'timecreated' => 1, 'timemodified' => 1, 'createdby' => 0, 'modifiedby' => 0,
        ]);
    }

    public function test_free_text_is_accepted_when_the_catalogue_is_empty(): void {
        $this->resetAfterTest();
        $this->assertSame('Coordinación Académica',
            area_service::resolve_name('Coordinación Académica'));
    }

    public function test_key_and_name_resolve_to_the_canonical_name(): void {
        $this->resetAfterTest();
        $this->add_area('academic', 'Coordinación Académica');

        $this->assertSame('Coordinación Académica', area_service::resolve_name('academic'));
        $this->assertSame('Coordinación Académica',
            area_service::resolve_name('coordinación académica'));
    }

    public function test_unknown_area_is_rejected_once_governed(): void {
        $this->resetAfterTest();
        $this->add_area('academic', 'Coordinación Académica');

        $this->expectException(\moodle_exception::class);
        area_service::resolve_name('Área inexistente');
    }

    public function test_save_generates_a_unique_key_and_resolves(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $id1 = area_service::save((object)['name' => 'Gerencia General', 'active' => 1], 2);
        $id2 = area_service::save((object)['name' => 'Gerencia General', 'active' => 1], 2);

        $a1 = $DB->get_record('local_handbook_area', ['id' => $id1], '*', MUST_EXIST);
        $a2 = $DB->get_record('local_handbook_area', ['id' => $id2], '*', MUST_EXIST);
        $this->assertSame('gerencia-general', $a1->areakey);
        $this->assertNotSame($a1->areakey, $a2->areakey, 'Keys must be unique');
        $this->assertSame('Gerencia General', area_service::resolve_name('gerencia-general'));
    }

    public function test_inactive_area_is_not_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = area_service::save((object)['name' => 'Antigua Área', 'active' => 1], 2);
        area_service::set_active($id, false, 2);

        $this->expectException(\moodle_exception::class);
        area_service::resolve_name('Antigua Área');
    }
}
