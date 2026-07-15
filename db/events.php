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

/**
 * Event observers for local_handbook (specification 36.4).
 *
 * Every revision-workflow event maps to the same handler, which keeps the
 * change-item status in sync with the revision. Handlers touch only change-set
 * tables and fire no events, so there is no recursion.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callback = '\local_handbook\observer::revision_workflow_changed';

$observers = [
    [
        'eventname' => '\local_handbook\event\draft_submitted',
        'callback' => $callback,
    ],
    [
        'eventname' => '\local_handbook\event\revision_approved',
        'callback' => $callback,
    ],
    [
        'eventname' => '\local_handbook\event\changes_requested',
        'callback' => $callback,
    ],
    [
        'eventname' => '\local_handbook\event\revision_rejected',
        'callback' => $callback,
    ],
    [
        'eventname' => '\local_handbook\event\revision_published',
        'callback' => $callback,
    ],
];
