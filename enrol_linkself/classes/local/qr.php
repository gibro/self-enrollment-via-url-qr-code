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

namespace enrol_linkself\local;

/**
 * Tiny QR code helper on top of the TCPDF 2D barcode class bundled with Moodle.
 *
 * Returns null if the generator is not available, so the page can still show the plain link.
 *
 * @package    enrol_linkself
 * @copyright  2026 Guido Brombach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qr {

    /**
     * Render a text as inline SVG QR code.
     *
     * @param string $text text to encode, here the join URL
     * @return string|null SVG markup without XML prolog, or null if no generator is available
     */
    public static function svg(string $text): ?string {
        global $CFG;

        $file = $CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php';
        if (!is_readable($file)) {
            return null;
        }

        try {
            require_once($file);
            if (!class_exists('\TCPDF2DBarcode')) {
                return null;
            }
            $barcode = new \TCPDF2DBarcode($text, 'QRCODE,M');
            $svg = $barcode->getBarcodeSVGcode(6, 6, 'black');
        } catch (\Throwable $e) {
            debugging('enrol_linkself: QR code generation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        if (!is_string($svg) || $svg === '') {
            return null;
        }

        // Strip XML prolog and DOCTYPE (both are invalid inside an HTML page), make the SVG scale with its container.
        $svg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
        $svg = preg_replace('/^<!DOCTYPE[^>]*>\s*/i', '', $svg);
        if (preg_match('/<svg[^>]*\swidth="([\d.]+)"[^>]*\sheight="([\d.]+)"/', $svg, $m)) {
            $svg = preg_replace(
                '/<svg\s/',
                '<svg viewBox="0 0 ' . $m[1] . ' ' . $m[2] . '" style="width:100%;max-width:320px;height:auto" ',
                $svg,
                1
            );
        }
        return $svg;
    }
}
