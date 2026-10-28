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
namespace local_handbook\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_handbook\local\service\changeset_service;
use moodle_exception;

/**
 * External function: attach one image (a screenshot) to a change item's
 * draft (0.43.0).
 *
 * A page_revision item keeps it in its working revision; a page_create item
 * keeps it with the proposal until a human publishes the page. Content
 * references the file as @@PLUGINFILE@@/<filename> (returned as
 * 'reference'). PNG, JPEG, GIF and WebP up to 5 MB; a file of the same name
 * is replaced. Draft authority only: nothing is published.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attach_changeset_file extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'changesetid' => new external_value(PARAM_INT, 'Change-set id'),
            'itemid' => new external_value(PARAM_INT,
                'Change-item id (a page draft or a new-page proposal of this set)'),
            'filename' => new external_value(PARAM_FILE, 'File name, e.g. asistencia-paso-1.png'),
            'content' => new external_value(PARAM_RAW, 'File content, base64'),
        ]);
    }

    /**
     * Attach the file.
     *
     * @param int $changesetid Change-set id.
     * @param int $itemid Change-item id.
     * @param string $filename File name.
     * @param string $content Base64 file content.
     * @return array
     */
    public static function execute(int $changesetid, int $itemid, string $filename,
            string $content): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'changesetid' => $changesetid,
            'itemid' => $itemid,
            'filename' => $filename,
            'content' => $content,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        helper::require_write($context);

        $item = $DB->get_record('local_handbook_changeitem',
            ['id' => $params['itemid'], 'changesetid' => $params['changesetid']], '*', MUST_EXIST);
        if ((int)$item->pageid) {
            helper::assert_writable(helper::get_page_by_identifier((string)$item->pageid));
        }

        $bytes = base64_decode($params['content'], true);
        if ($bytes === false) {
            throw new moodle_exception('errorattachfiletype', 'local_handbook');
        }

        return changeset_service::attach_file($params['changesetid'], $params['itemid'],
            $params['filename'], $bytes);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'filename' => new external_value(PARAM_FILE, 'Stored file name'),
            'filesize' => new external_value(PARAM_INT, 'Stored size in bytes (after optimisation)'),
            'contenthash' => new external_value(PARAM_ALPHANUMEXT, 'SHA1 of the stored content'),
            'mimetype' => new external_value(PARAM_RAW, 'Stored MIME type'),
            'reference' => new external_value(PARAM_RAW, 'How content refers to it: @@PLUGINFILE@@/<filename>'),
        ]);
    }
}
