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

namespace local_handbook;

use advanced_testcase;

/**
 * Guards the Handbook AI authority boundary at the external-service layer.
 *
 * The invariant (spec 17.3, 36.1): the API/MCP account may propose drafts and
 * submit them for human review, but no external function may approve, publish,
 * archive, restore or delete — and none may require a human authority
 * capability. This test fails if a future change adds such a function, so the
 * polymorphic change-set work of Phase 0 (and the apply logic of later phases)
 * cannot accidentally open an apply path to the API.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class authority_boundary_test extends advanced_testcase {

    /**
     * The registered external functions expose no apply/publish verb and
     * require no human authority capability.
     */
    public function test_external_functions_cannot_apply_or_publish(): void {
        global $CFG;

        $functions = [];
        require($CFG->dirroot . '/local/handbook/db/services.php');
        $this->assertNotEmpty($functions, 'No external functions were loaded.');

        // Verbs that would represent applying a change to published state.
        $forbiddenverbs = ['approve', 'publish', 'apply', 'archive', 'restore', 'delete'];
        // Capabilities that authorise applying a change; the API must never need one.
        $authoritycaps = ['review', 'approve', 'publish', 'managechangesets', 'manageapi', 'manage'];

        foreach ($functions as $name => $definition) {
            foreach ($forbiddenverbs as $verb) {
                $this->assertStringNotContainsStringIgnoringCase($verb, $name,
                    "External function {$name} must not expose a '{$verb}' operation.");
            }

            $caps = array_filter(array_map('trim',
                explode(',', (string)($definition['capabilities'] ?? ''))));
            foreach ($authoritycaps as $cap) {
                $this->assertNotContains("local/handbook:{$cap}", $caps,
                    "External function {$name} must not require local/handbook:{$cap}.");
            }
        }
    }
}
