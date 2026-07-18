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

use context;
use stdClass;
use stored_file;

/**
 * Automatic image optimisation for handbook file areas.
 *
 * Camera and phone images arrive at 4000+ px and several megabytes; the
 * handbook never displays wider than ~1500 px. On page save (and on demand
 * from manage/images.php) images in the banner and revision file areas are
 * downscaled to the configured maximum width, rotated per their EXIF
 * orientation flag, stripped of metadata (including GPS positions from
 * phone photos) and re-encoded.
 *
 * Format rules:
 * - JPEG stays JPEG, re-encoded at the configured quality.
 * - PNG/WebP with transparency stays PNG (screenshots stay pixel-crisp).
 * - Opaque PNG/WebP is converted to JPEG only when that is dramatically
 *   smaller (photos exported as PNG); otherwise it stays PNG.
 * - GIF (animation) and SVG (vector) are never touched.
 *
 * Images are NEVER upscaled, and a replacement is only kept when it is
 * actually smaller than the original. The filename never changes, so HTML
 * references keep working; the stored mimetype is updated on conversion.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_service {

    /** @var int Never scale below this width even if misconfigured. */
    const MIN_MAXWIDTH = 400;

    /** @var int Upper bound for the configured maximum width. */
    const MAX_MAXWIDTH = 6000;

    /** @var float Keep an unresized re-encode only if it saves at least this share. */
    const MIN_SAVING = 0.10;

    /** @var float Convert opaque PNG to JPEG only when JPEG is below this share of the PNG size. */
    const JPEG_WIN_RATIO = 0.5;

    /** @var string[] File areas that hold optimisable images. */
    const FILE_AREAS = ['bannerimage', 'revision'];

    /**
     * Whether automatic optimisation on save is enabled.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $value = get_config('local_handbook', 'imageoptimize');
        // Default ON when the setting has never been saved.
        return $value === false || (bool)$value;
    }

    /**
     * Configured maximum display width in pixels.
     *
     * @return int
     */
    public static function max_width(): int {
        $width = (int)get_config('local_handbook', 'imagemaxwidth');
        if ($width <= 0) {
            $width = 1500;
        }
        return max(self::MIN_MAXWIDTH, min(self::MAX_MAXWIDTH, $width));
    }

    /**
     * Configured JPEG re-encode quality.
     *
     * @return int
     */
    public static function jpeg_quality(): int {
        $quality = (int)get_config('local_handbook', 'imagejpegquality');
        if ($quality <= 0) {
            $quality = 85;
        }
        return max(50, min(100, $quality));
    }

    /**
     * Optimise every image in one plugin file area.
     *
     * @param context $context Plugin context (system).
     * @param string $filearea One of self::FILE_AREAS.
     * @param int|null $itemid A single item id, or null for all items in the area.
     * @return stdClass Report: {scanned, optimized, beforebytes, afterbytes}.
     */
    public static function optimize_area(context $context, string $filearea, ?int $itemid = null): stdClass {
        $report = (object)['scanned' => 0, 'optimized' => 0, 'beforebytes' => 0, 'afterbytes' => 0];
        if (!in_array($filearea, self::FILE_AREAS, true)) {
            return $report;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_handbook', $filearea,
            $itemid ?? false, 'itemid, filepath, filename', false);

        foreach ($files as $file) {
            if (strpos((string)$file->get_mimetype(), 'image/') !== 0) {
                continue;
            }
            $report->scanned++;
            $before = (int)$file->get_filesize();
            $result = self::optimize_file($file);
            $report->beforebytes += $before;
            if ($result !== null) {
                $report->optimized++;
                $report->afterbytes += (int)$result->after;
            } else {
                $report->afterbytes += $before;
            }
        }

        return $report;
    }

    /**
     * Optimise a single stored file in place.
     *
     * The stored_file object is stale after a successful replacement; use
     * the returned byte counts, not the original object.
     *
     * @param stored_file $file Image file to optimise.
     * @return stdClass|null {before, after} when replaced, null when kept as-is.
     */
    public static function optimize_file(stored_file $file): ?stdClass {
        $content = $file->get_content();
        if ($content === false || $content === '') {
            return null;
        }

        $processed = self::process($content);
        if ($processed === null) {
            return null;
        }

        $before = strlen($content);
        $fs = get_file_storage();
        $record = [
            'contextid' => $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea' => $file->get_filearea(),
            'itemid' => $file->get_itemid(),
            'filepath' => $file->get_filepath(),
            'filename' => $file->get_filename(),
            'userid' => $file->get_userid(),
            'mimetype' => $processed->mimetype,
            'source' => $file->get_source(),
            'author' => $file->get_author(),
            'license' => $file->get_license(),
            'sortorder' => $file->get_sortorder(),
            'timecreated' => $file->get_timecreated(),
            'timemodified' => time(),
        ];
        $file->delete();
        $fs->create_file_from_string($record, $processed->content);

        return (object)['before' => $before, 'after' => strlen($processed->content)];
    }

    /**
     * Pure image processing: decide whether raw image bytes should be
     * replaced and produce the replacement.
     *
     * Separated from the file API so it can be exercised without Moodle.
     *
     * @param string $content Raw image bytes.
     * @return stdClass|null {content, mimetype} to replace with, or null to keep the original.
     */
    public static function process(string $content): ?stdClass {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $info = @getimagesizefromstring($content);
        if ($info === false) {
            return null;
        }
        [$width, $height] = $info;
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            // GIF may be animated, SVG is vector, anything else is unknown.
            return null;
        }

        $maxwidth = self::max_width();
        $orientation = $mime === 'image/jpeg' ? self::exif_orientation($content) : 1;
        $needsrotation = $orientation > 1;
        $needsresize = $width > $maxwidth;

        // Small and already narrow enough: not worth re-encoding.
        if (!$needsresize && !$needsrotation && strlen($content) < 102400) {
            return null;
        }

        $image = @imagecreatefromstring($content);
        if ($image === false) {
            return null;
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if ($needsrotation) {
            $image = self::apply_orientation($image, $orientation);
            // Rotation by 90/270 swaps the dimensions.
            $width = imagesx($image);
            $height = imagesy($image);
            $needsresize = $width > $maxwidth;
        }

        if ($needsresize) {
            $newwidth = $maxwidth;
            $newheight = max(1, (int)round($height * $maxwidth / $width));
            $resized = imagecreatetruecolor($newwidth, $newheight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $quality = self::jpeg_quality();
        $candidate = null;
        $candidatemime = null;

        if ($mime === 'image/jpeg') {
            $candidate = self::encode_jpeg($image, $quality);
            $candidatemime = 'image/jpeg';
        } else {
            // PNG or WebP source.
            $png = self::encode_png($image);
            if (self::has_transparency($image)) {
                // Screenshots and graphics with alpha stay PNG: crisp text,
                // and JPEG cannot represent transparency anyway.
                $candidate = $png;
                $candidatemime = 'image/png';
            } else {
                $jpeg = self::encode_jpeg($image, $quality);
                if ($jpeg !== null && $png !== null
                        && strlen($jpeg) < strlen($png) * self::JPEG_WIN_RATIO) {
                    // Photo saved as PNG: JPEG wins dramatically.
                    $candidate = $jpeg;
                    $candidatemime = 'image/jpeg';
                } else {
                    $candidate = $png;
                    $candidatemime = 'image/png';
                }
            }
        }
        imagedestroy($image);

        if ($candidate === null) {
            return null;
        }

        $before = strlen($content);
        $after = strlen($candidate);
        if ($needsresize || $needsrotation) {
            // The image itself changed: keep the result unless it somehow
            // came out bigger than the original bytes.
            if ($after >= $before) {
                return null;
            }
        } else {
            // Pure re-encode: only worth replacing for a meaningful saving.
            if ($after >= $before * (1 - self::MIN_SAVING)) {
                return null;
            }
        }

        return (object)['content' => $candidate, 'mimetype' => $candidatemime];
    }

    /**
     * Read the EXIF orientation flag from JPEG bytes.
     *
     * @param string $content Raw JPEG bytes.
     * @return int Orientation 1-8 (1 = upright / unknown).
     */
    protected static function exif_orientation(string $content): int {
        if (!function_exists('exif_read_data')) {
            return 1;
        }
        $stream = fopen('php://memory', 'r+b');
        if ($stream === false) {
            return 1;
        }
        fwrite($stream, $content);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);
        $orientation = (int)($exif['Orientation'] ?? 1);
        return ($orientation >= 1 && $orientation <= 8) ? $orientation : 1;
    }

    /**
     * Apply an EXIF orientation to a GD image.
     *
     * @param \GdImage $image Source image (consumed).
     * @param int $orientation EXIF orientation 2-8.
     * @return \GdImage Upright image.
     */
    protected static function apply_orientation($image, int $orientation) {
        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = self::rotate($image, 180);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                $image = self::rotate($image, -90);
                break;
            case 6:
                $image = self::rotate($image, -90);
                break;
            case 7:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                $image = self::rotate($image, 90);
                break;
            case 8:
                $image = self::rotate($image, 90);
                break;
        }
        return $image;
    }

    /**
     * Rotate preserving alpha.
     *
     * @param \GdImage $image Source image (consumed).
     * @param int $degrees Counter-clockwise degrees.
     * @return \GdImage
     */
    protected static function rotate($image, int $degrees) {
        $rotated = imagerotate($image, $degrees, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
        return $rotated;
    }

    /**
     * Whether the image uses any non-opaque pixel (sampled).
     *
     * @param \GdImage $image Image to test.
     * @return bool
     */
    protected static function has_transparency($image): bool {
        $width = imagesx($image);
        $height = imagesy($image);
        // Sample a grid of at most ~64x64 positions: cheap and reliable for
        // real transparency (borders, rounded corners, cut-outs).
        $stepx = max(1, (int)floor($width / 64));
        $stepy = max(1, (int)floor($height / 64));
        for ($y = 0; $y < $height; $y += $stepy) {
            for ($x = 0; $x < $width; $x += $stepx) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Encode as JPEG (flattened onto white where alpha existed).
     *
     * @param \GdImage $image Image to encode.
     * @param int $quality JPEG quality.
     * @return string|null
     */
    protected static function encode_jpeg($image, int $quality): ?string {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        ob_start();
        $ok = imagejpeg($flat, null, $quality);
        $bytes = ob_get_clean();
        imagedestroy($flat);
        return $ok && $bytes !== false ? $bytes : null;
    }

    /**
     * Encode as PNG at maximum compression.
     *
     * @param \GdImage $image Image to encode.
     * @return string|null
     */
    protected static function encode_png($image): ?string {
        ob_start();
        $ok = imagepng($image, null, 9);
        $bytes = ob_get_clean();
        return $ok && $bytes !== false ? $bytes : null;
    }
}
