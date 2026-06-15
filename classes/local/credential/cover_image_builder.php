<?php
// This file is part of Moodle - https://moodle.org/.
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Cover image and EDC MediaObject builder.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edc_exporter\local\credential;


/**
 * Builds reusable EDC MediaObject structures and default credential cover images.
 */
class cover_image_builder {
    /**
     * Builds a MediaObject with the issuer logo stored in Moodle File API.
     *
     * @param string $id MediaObject id.
     * @return array|null MediaObject or null if no valid logo exists.
     */
    public static function build_logo_media_object(string $id): ?array {
        return self::stored_image_media_object('issuerlogo', $id);
    }

    /**
     * Builds a MediaObject from a stored plugin image.
     *
     * @param string $filearea Moodle File API area.
     * @param string $id MediaObject id.
     * @return array|null MediaObject or null if the file is missing or invalid.
     */
    public static function stored_image_media_object(string $filearea, string $id): ?array {
        $context = \context_system::instance();
        $fs = get_file_storage();

        $files = $fs->get_area_files(
            $context->id,
            'local_edc_exporter',
            $filearea,
            0,
            'filename',
            false
        );

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        if (!$file || $file->is_directory()) {
            return null;
        }

        $mimetype = $file->get_mimetype();
        if ($mimetype === 'image/png') {
            $filetype = 'PNG';
        } else if ($mimetype === 'image/jpeg') {
            $filetype = 'JPEG';
        } else {
            return null;
        }

        $content = base64_encode($file->get_content());
        if ($content === '') {
            return null;
        }

        return self::media_object($id, $content, $filetype);
    }

    /**
     * Builds a reusable EDC MediaObject.
     *
     * @param string $id MediaObject id.
     * @param string $base64content Base64 file content.
     * @param string $filetype EDC file type label.
     * @return array MediaObject.
     */
    public static function media_object(string $id, string $base64content, string $filetype): array {
        return [
            'id'   => $id,
            'type' => 'MediaObject',
            'content' => $base64content,
            'contentEncoding' => [
                'id'   => 'http://data.europa.eu/snb/encoding/6146cde7dd',
                'type' => 'Concept',
                'inScheme' => [
                    'id'   => 'http://data.europa.eu/snb/encoding/25831c2',
                    'type' => 'ConceptScheme',
                ],
                'prefLabel' => ['en' => ['base64']],
            ],
            'contentType' => [
                'id'   => 'http://publications.europa.eu/resource/authority/file-type/' . $filetype,
                'type' => 'Concept',
                'inScheme' => [
                    'id'   => 'http://publications.europa.eu/resource/authority/file-type',
                    'type' => 'ConceptScheme',
                ],
                'prefLabel' => ['en' => [$filetype]],
                'notation'  => 'file-type',
            ],
        ];
    }

    /**
     * Loads a stored plugin image as a GD image resource.
     *
     * This is used by the template builder for header and footer images.
     *
     * @param string $filearea Moodle File API area.
     * @return \GdImage|null Loaded image or null when missing/invalid.
     */
    private static function load_stored_image_resource(string $filearea): ?\GdImage {
        $context = \context_system::instance();
        $fs = get_file_storage();

        $files = $fs->get_area_files(
            $context->id,
            'local_edc_exporter',
            $filearea,
            0,
            'filename',
            false
        );

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        if (!$file || $file->is_directory()) {
            return null;
        }

        $image = @imagecreatefromstring($file->get_content());

        return ($image instanceof \GdImage) ? $image : null;
    }

    /**
     * Loads one of the bundled background images.
     *
     * @param string $background Background key selected in settings.
     * @return \GdImage|null Loaded image or null when missing/invalid.
     */
    private static function load_bundled_background(string $background): ?\GdImage {
        global $CFG;

        $allowed = [
            'light' => 'background-light.png',
            'green' => 'background-green.png',
            'blue' => 'background-blue.png',
        ];

        // The selected background is used as the full-page base image.
        // Header and footer may cover the top/bottom areas, but the body must remain visible.

        $filename = $allowed[$background] ?? $allowed['light'];
        $path = $CFG->dirroot . '/local/edc_exporter/pix/backgrounds/' . $filename;

        if (!file_exists($path)) {
            return null;
        }

        $image = @imagecreatefrompng($path);

        return ($image instanceof \GdImage) ? $image : null;
    }

    /**
     * Converts a hex colour setting into RGB values.
     *
     * @param string $hex Colour in #RRGGBB format.
     * @param array $fallback Fallback RGB value.
     * @return array RGB values.
     */
    private static function hex_to_rgb(string $hex, array $fallback): array {
        $hex = trim($hex);

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }

        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    /**
     * Builds the default EDC cover image, 794x1123 px, JPEG, using GD.
     *
     * @param string $credentialtitle Credential title.
     * @param string $studentname Learner full name.
     * @param string $claimtitle Achievement title.
     * @param string $issuername Issuer legal name.
     * @param array $settings Visual design settings selected by the administrator.
     * @return array|null MediaObject with base64 JPEG content, or null if GD is unavailable.
     */
    public static function build_cover_image_media_object(
        string $credentialtitle,
        string $studentname,
        string $claimtitle,
        string $issuername,
        array $settings = []
    ): ?array {
        if (!extension_loaded('gd')) {
            return null;
        }

        $w = 794;
        $h = 1123;

        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);

        $cdarkgreen = imagecolorallocate($img, 10, 71, 51);
        $clightgreen = imagecolorallocate($img, 172, 255, 208);
        $cblue = imagecolorallocate($img, 0, 68, 148);
        $cwhite = imagecolorallocate($img, 255, 255, 255);
        $cdarktext = imagecolorallocate($img, 28, 58, 46);
        $cgray = imagecolorallocate($img, 130, 130, 130);
        $cbglight = imagecolorallocate($img, 242, 250, 246);
        $cborder = imagecolorallocate($img, 210, 228, 218);

        global $CFG;
        $fontbase = $CFG->dirroot . '/local/edc_exporter/fonts/';
        $fsans = $fontbase . 'LiberationSans-Regular.ttf';
        $fserif = $fontbase . 'LiberationSerif-Regular.ttf';
        $fserifi = $fontbase . 'LiberationSerif-Italic.ttf';

        // Draws a filled five-point star.
        // PHP 8.1+ deprecates the old imagefilledpolygon() signature with $num_points.
        // We pass only the image, point list, and colour to avoid generation failures.
        $drawstar = function (int $cx, int $cy, int $r, int $color) use ($img): void {
            $pts = [];
            $inner = max(1, (int) ($r * 0.42));

            for ($i = 0; $i < 10; $i++) {
                $angle = deg2rad(-90 + $i * 36);
                $radius = ($i % 2 === 0) ? $r : $inner;
                $pts[] = (int) ($cx + $radius * cos($angle));
                $pts[] = (int) ($cy + $radius * sin($angle));
            }

            imagefilledpolygon($img, $pts, $color);
        };

        // Draws the EU circle of stars.
        $draweu = function (
            int $cx,
            int $cy,
            int $orbit,
            int $starr,
            int $color
        ) use (
            $drawstar
        ): void {
            for ($i = 0; $i < 12; $i++) {
                $a = deg2rad($i * 30 - 90);
                $drawstar((int) ($cx + $orbit * cos($a)), (int) ($cy + $orbit * sin($a)), $starr, $color);
            }
        };

        // Writes horizontally centred text using TTF when available.
        $centertext = function (
            string $text,
            int $y,
            int $color,
            ?string $ttf = null,
            int $size = 16,
            int $bitmapfont = 3
        ) use (
            $img,
            $w
        ): void {
            if ($ttf && file_exists($ttf)) {
                $bbox = imagettfbbox($size, 0, $ttf, $text);
                $tw = abs($bbox[2] - $bbox[0]);
                $th = abs($bbox[7] - $bbox[1]);
                imagettftext($img, $size, 0, (int) (($w - $tw) / 2), $y + $th, $color, $ttf, $text);
            } else {
                $tw = strlen($text) * imagefontwidth($bitmapfont);
                imagestring($img, $bitmapfont, (int) (($w - $tw) / 2), $y, $text, $color);
            }
        };

        // Calculates text width for responsive name sizing.
        $textwidth = function (string $text, string $ttf, int $size): int {
            if (!file_exists($ttf)) {
                return strlen($text) * imagefontwidth(3);
            }

            $bbox = imagettfbbox($size, 0, $ttf, $text);
            return abs($bbox[2] - $bbox[0]);
        };

        // Draw the selected background first.
        // If "customcolor" is selected, the full page background uses only the admin colour.
        // Otherwise, one of the bundled background images is used.
        $backgroundkey = (string) ($settings['display_template_background'] ?? 'light');

        if ($backgroundkey === 'customcolor') {
            $backgroundrgb = self::hex_to_rgb(
                (string) ($settings['display_template_background_customcolor'] ?? '#ffffff'),
                [255, 255, 255]
            );

            $custombackground = imagecolorallocate(
                $img,
                $backgroundrgb[0],
                $backgroundrgb[1],
                $backgroundrgb[2]
            );

            imagefill($img, 0, 0, $custombackground);
        } else {
            $background = self::load_bundled_background($backgroundkey);

            if ($background !== null) {
                imagecopyresampled($img, $background, 0, 0, 0, 0, $w, $h, imagesx($background), imagesy($background));
                imagedestroy($background);
            } else {
                imagefill($img, 0, 0, $cwhite);
            }
        }

        // Header area. Custom header must already be validated as 794x160 px.
        $headerh = 160;
        $header = self::load_stored_image_resource('display_template_header');

        if ($header !== null) {
            imagecopyresampled($img, $header, 0, 0, 0, 0, $w, $headerh, imagesx($header), imagesy($header));
            imagedestroy($header);
        } else {
            imagefilledrectangle($img, 0, 0, $w, $headerh, $cdarkgreen);
            imagefilledrectangle($img, 0, $headerh - 6, $w, $headerh, $clightgreen);
            $draweu(76, (int) ($headerh / 2), 30, 6, $clightgreen);
            $centertext('EUROPEAN DIGITAL CREDENTIAL', (int) (($headerh - 18) / 2), $clightgreen, $fsans, 15);
        }

        $bodytop = $headerh;
        $footerh = 120;
        $footertop = $h - $footerh;
        $bodyh = $footertop - $bodytop;
        $logor = 40;
        $blogh = 112;
        $gap = 42;

        $totalh = ($logor * 2) + $gap + 20 + (int) ($gap / 2) + 50 + $gap + $blogh
            + $gap + 70 + $gap + 20 + $gap + 58;
        $y = $bodytop + max((int) (($bodyh - $totalh) / 2), 20);

        $logocx = (int) ($w / 2);
        $logocy = $y + $logor;
        $logomediaobject = self::stored_image_media_object('issuerlogo', 'urn:epass:mediaObject:cover-logo');

        if ($logomediaobject !== null) {
            $logobytes = base64_decode($logomediaobject['content']);
            $logosrc = @imagecreatefromstring($logobytes);

            if ($logosrc !== false) {
                $lw = imagesx($logosrc);
                $lh = imagesy($logosrc);
                $max = $logor * 2;
                $scale = min($max / $lw, $max / $lh);
                $dw = (int) ($lw * $scale);
                $dh = (int) ($lh * $scale);
                $dx = (int) (($w - $dw) / 2);
                $dy = $logocy - (int) ($dh / 2);

                imagecopyresampled($img, $logosrc, $dx, $dy, 0, 0, $dw, $dh, $lw, $lh);
                imagedestroy($logosrc);
            }
        } else {
            imagearc($img, $logocx, $logocy, $logor * 2, $logor * 2, 0, 360, $cborder);
        }

        $y += $logor * 2 + $gap;

        imageline($img, 80, $y, (int) ($w / 2) - 20, $y, $cborder);
        $drawstar((int) ($w / 2), $y, 9, $clightgreen);
        imageline($img, (int) ($w / 2) + 20, $y, $w - 80, $y, $cborder);
        $y += 20 + (int) ($gap / 2);

        $centertext($credentialtitle, $y, $cdarkgreen, $fserif, 36);
        $y += 50 + $gap;

        $blkpad = 68;
        $blktop = $y;
        imagefilledrectangle($img, $blkpad, $blktop, $w - $blkpad, $blktop + $blogh, $cbglight);
        imagefilledrectangle($img, $blkpad, $blktop, $blkpad + 5, $blktop + $blogh, $clightgreen);
        imagefilledrectangle($img, $w - $blkpad - 5, $blktop, $w - $blkpad, $blktop + $blogh, $clightgreen);
        $centertext('AWARDED TO', $blktop + 14, $cgray, $fsans, 13);

        $maxnamew = $w - $blkpad * 2 - 40;
        $namesize = 38;

        while ($namesize >= 20 && file_exists($fserif)) {
            if ($textwidth($studentname, $fserif, $namesize) <= $maxnamew) {
                break;
            }

            $namesize -= 2;
        }

        if (file_exists($fserif)) {
            $bbox = imagettfbbox($namesize, 0, $fserif, $studentname);
            $nameh = abs($bbox[7] - $bbox[1]);
            $namey = $blktop + (int) (($blogh - $nameh) / 2) + 10;
            $centertext($studentname, $namey, $cblue, $fserif, $namesize);
        } else {
            $centertext($studentname, $blktop + 44, $cblue);
        }

        $y += $blogh + $gap;

        $centertext('FOR SUCCESSFULLY COMPLETING', $y, $cgray, $fsans, 13);
        $centertext($claimtitle, $y + 26, $cdarktext, $fserifi, 26);
        $y += 70 + $gap;

        imageline($img, 80, $y, (int) ($w / 2) - 26, $y, $cborder);
        $drawstar((int) ($w / 2) - 13, $y, 6, $clightgreen);
        $drawstar((int) ($w / 2), $y, 4, $cdarkgreen);
        $drawstar((int) ($w / 2) + 13, $y, 6, $clightgreen);
        imageline($img, (int) ($w / 2) + 26, $y, $w - 80, $y, $cborder);
        $y += 20 + $gap;

        $centertext('ISSUED BY', $y, $cgray, $fsans, 13);
        $centertext($issuername, $y + 26, $cdarkgreen, $fserif, 24);

        // Footer area. Custom footer must already be validated as 794x120 px.
        $footer = self::load_stored_image_resource('display_template_footer');

        if ($footer !== null) {
            imagecopyresampled($img, $footer, 0, $footertop, 0, 0, $w, $footerh, imagesx($footer), imagesy($footer));
            imagedestroy($footer);
        } else {
            imagefilledrectangle($img, 0, $footertop, $w, $footertop + 6, $clightgreen);
            imagefilledrectangle($img, 0, $footertop + 6, $w, $h, $cdarkgreen);

            $starn = 14;
            $starsp = 34;
            $sx0 = (int) (($w - ($starn - 1) * $starsp) / 2);

            for ($i = 0; $i < $starn; $i++) {
                $drawstar($sx0 + $i * $starsp, $footertop + 62, 6, $clightgreen);
            }
        }

        ob_start();
        imagejpeg($img, null, 92);
        $jpegbytes = ob_get_clean();
        imagedestroy($img);

        if (empty($jpegbytes)) {
            return null;
        }

        return self::media_object(
            'urn:epass:mediaObject:cover-' . uniqid('', true),
            base64_encode($jpegbytes),
            'JPEG'
        );
    }
}
