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
 * Admin settings for local_handbook.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_handbook',
        get_string('pluginname', 'local_handbook'));
    $ADMIN->add('localplugins', $settings);

    // Bootstrap mode (spec 4.10, progressive implementation): while enabled,
    // users with local/handbook:publish may publish directly from the editor
    // and imports may auto-publish, skipping the review queue. Revision
    // history is recorded either way. Switch OFF once the initial content is
    // in place; from then on the full editorial workflow applies.
    $settings->add(new admin_setting_configcheckbox('local_handbook/bootstrapmode',
        get_string('bootstrapmode', 'local_handbook'),
        get_string('bootstrapmode_desc', 'local_handbook'), 0));

    // Automatic image optimisation: on save, images wider than the maximum
    // are downscaled and re-encoded (EXIF-rotated, metadata stripped).
    // Images are never upscaled; replacements are only kept when smaller.
    $settings->add(new admin_setting_configcheckbox('local_handbook/imageoptimize',
        get_string('imageoptimize', 'local_handbook'),
        get_string('imageoptimize_desc', 'local_handbook'), 1));

    $settings->add(new admin_setting_configtext('local_handbook/imagemaxwidth',
        get_string('imagemaxwidth', 'local_handbook'),
        get_string('imagemaxwidth_desc', 'local_handbook'), 1500, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_handbook/imagejpegquality',
        get_string('imagejpegquality', 'local_handbook'),
        get_string('imagejpegquality_desc', 'local_handbook'), 85, PARAM_INT));
}
