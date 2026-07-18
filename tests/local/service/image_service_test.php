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

/**
 * Tests for the image optimisation pipeline (pure processing, no file API).
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_handbook\local\service\image_service
 */
final class image_service_test extends \advanced_testcase {

    /**
     * A photo-like image: gradients plus per-pixel noise so it compresses
     * the way camera output does (badly as PNG, well as JPEG).
     *
     * @param int $w Width.
     * @param int $h Height.
     * @return \GdImage
     */
    private function photo(int $w, int $h) {
        $im = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $n = (($x * 7919 + $y * 104729) ^ (($x >> 2) * 31 + $y * 17)) % 53 - 26;
                $r = max(0, min(255, (int)(127 + 110 * sin($x / 97)) + $n));
                $g = max(0, min(255, (int)(127 + 110 * sin($y / 61)) - $n));
                $b = max(0, min(255, (int)(127 + 110 * sin(($x + $y) / 43)) + ($n >> 1)));
                imagesetpixel($im, $x, $y, ($r << 16) | ($g << 8) | $b);
            }
        }
        return $im;
    }

    /**
     * Encode a GD image to bytes.
     *
     * @param \GdImage $im Image.
     * @param string $format png|jpeg|gif.
     * @param int $quality JPEG quality.
     * @return string
     */
    private function encode($im, string $format, int $quality = 95): string {
        ob_start();
        if ($format === 'png') {
            imagepng($im, null, 6);
        } else if ($format === 'gif') {
            imagegif($im);
        } else {
            imagejpeg($im, null, $quality);
        }
        return ob_get_clean();
    }

    public function test_oversized_jpeg_is_downscaled(): void {
        $src = $this->encode($this->photo(2200, 1100), 'jpeg');
        $out = image_service::process($src);

        $this->assertNotNull($out);
        $this->assertSame('image/jpeg', $out->mimetype);
        [$w, $h] = getimagesizefromstring($out->content);
        $this->assertSame(1500, $w);
        $this->assertSame(750, $h);
        $this->assertLessThan(strlen($src), strlen($out->content));
    }

    public function test_opaque_photo_png_becomes_jpeg(): void {
        $src = $this->encode($this->photo(1800, 900), 'png');
        $out = image_service::process($src);

        $this->assertNotNull($out);
        $this->assertSame('image/jpeg', $out->mimetype);
        $this->assertSame(1500, getimagesizefromstring($out->content)[0]);
        $this->assertLessThan(strlen($src) / 2, strlen($out->content));
    }

    public function test_transparent_png_stays_png_with_alpha(): void {
        $im = imagecreatetruecolor(1800, 600);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocate($im, 250, 250, 250));
        imagefilledrectangle($im, 0, 0, 1799, 9,
            imagecolorallocatealpha($im, 0, 0, 0, 127));
        $src = $this->encode($im, 'png');

        $out = image_service::process($src);

        $this->assertNotNull($out);
        $this->assertSame('image/png', $out->mimetype);
        $this->assertSame(1500, getimagesizefromstring($out->content)[0]);
        $decoded = imagecreatefromstring($out->content);
        $this->assertGreaterThan(0, (imagecolorat($decoded, 700, 2) >> 24) & 0x7F);
    }

    public function test_small_images_pass_through_untouched(): void {
        // A small screenshot-like PNG: narrow AND few bytes.
        $im = imagecreatetruecolor(700, 400);
        imagefill($im, 0, 0, imagecolorallocate($im, 246, 247, 249));
        $this->assertNull(image_service::process($this->encode($im, 'png')));

        // A 500px photo JPEG is never upscaled.
        $this->assertNull(image_service::process(
            $this->encode($this->photo(500, 300), 'jpeg', 80)));
    }

    public function test_gif_and_garbage_are_ignored(): void {
        $im = imagecreatetruecolor(2000, 800);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 210, 220));
        $this->assertNull(image_service::process($this->encode($im, 'gif')));
        $this->assertNull(image_service::process('definitely not an image'));
    }

    public function test_exif_orientation_is_applied(): void {
        // Landscape JPEG tagged orientation=6: readers show it as portrait.
        $plain = $this->encode($this->photo(1800, 1000), 'jpeg', 92);
        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01"
            . "\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00"
            . "\x00\x00\x00\x00";
        $exif = "Exif\x00\x00" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
        $src = substr($plain, 0, 2) . $app1 . substr($plain, 2);

        $out = image_service::process($src);

        $this->assertNotNull($out);
        [$w, $h] = getimagesizefromstring($out->content);
        // Rotated upright (1000x1800), narrower than the cap: kept as-is.
        $this->assertSame(1000, $w);
        $this->assertSame(1800, $h);
    }
}
