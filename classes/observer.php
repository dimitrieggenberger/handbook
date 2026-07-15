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

use local_handbook\local\service\changeset_service;

/**
 * Event observers for local_handbook (specification 36.4).
 *
 * Keeps change-item status in sync with the revision workflow without
 * page_service knowing about change sets: page_service fires the workflow
 * events, and the change-set service (which owns only its own tables) reacts.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Sync change items when a revision changes workflow state.
     *
     * Handles draft_submitted, revision_approved, changes_requested,
     * revision_rejected and revision_published — all carry the revision id as
     * objectid. Non-change-set revisions are a cheap no-op.
     *
     * @param \core\event\base $event The workflow event.
     * @return void
     */
    public static function revision_workflow_changed(\core\event\base $event): void {
        changeset_service::sync_item_for_revision((int)$event->objectid);
    }
}
