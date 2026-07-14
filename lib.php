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
 * Moodle callbacks for local_handbook.
 *
 * Kept deliberately small (specification 6.2): only callbacks Moodle
 * requires here. Shared helpers live in locallib.php; business logic lives
 * in classes/local/service/.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve files embedded in handbook revision content.
 *
 * File area "revision" stores editor attachments; the itemid is the
 * revision id. Access mirrors the reader rules: published revisions need
 * local/handbook:view, unpublished ones need editorial access.
 *
 * @param stdClass $course Course object (unused, system context).
 * @param stdClass $cm Course module (unused).
 * @param context $context Context of the request.
 * @param string $filearea File area name.
 * @param array $args Remaining path arguments: itemid, then path.
 * @param bool $forcedownload Whether the file must be downloaded.
 * @param array $options Additional options.
 * @return bool False when the file is not found or not permitted.
 */
function local_handbook_pluginfile($course, $cm, $context, string $filearea, array $args,
        bool $forcedownload, array $options = []): bool {
    global $DB;

    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'revision') {
        return false;
    }

    require_login();

    if (!has_capability('local/handbook:view', $context)) {
        return false;
    }

    $revisionid = (int)array_shift($args);
    $revision = $DB->get_record('local_handbook_revision', ['id' => $revisionid]);
    if (!$revision) {
        return false;
    }

    $page = $DB->get_record('local_handbook_page', ['id' => $revision->pageid]);
    if (!$page) {
        return false;
    }

    // Unpublished content is only visible to editorial roles.
    $ispublished = (int)$page->publishedrevisionid === (int)$revision->id
        || $revision->status === \local_handbook\local\service\page_service::STATUS_SUPERSEDED;
    if (!$ispublished && !has_any_capability(
            ['local/handbook:edit', 'local/handbook:review', 'local/handbook:publish'], $context)) {
        return false;
    }
    if ($revision->status === \local_handbook\local\service\page_service::STATUS_SUPERSEDED
            && !has_capability('local/handbook:viewhistory', $context)) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_handbook', $filearea, $revisionid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
    return true;
}
