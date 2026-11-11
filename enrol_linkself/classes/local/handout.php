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

use stdClass;

/**
 * Printable A4 handout (landscape) with enrolment link and QR code, built on the IG Metall template
 * "In Moodle-Kurs einschreiben".
 *
 * Four steps. The background pix/handout-background.png is the image of the Word template reduced to
 * header, logo, step numbers 1 to 4, the QR code frame and the laptop illustration; tiles 1 to 3 form the left
 * column, tile 4 is the whole right column. Texts, QR code, link, course name and button label are drawn on
 * top. All positions are measured in pixels of that image (3508 x 2480 px, 300 dpi) and converted with px().
 *
 * @package    enrol_linkself
 * @copyright  2026 Guido Brombach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class handout {
    /** @var int[] Body text, as in the template. */
    const TEXT = [0, 0, 0];

    /** @var int[] IG Metall Rot, final step and button. */
    const RED = [227, 5, 27];

    /** @var int[] White, text on the button. */
    const WHITE = [255, 255, 255];

    /** @var int[] Grey of the link in tile 1. */
    const LINKGREY = [112, 112, 111];

    /** @var int[] Fill of the grey pills. */
    const PILLFILL = [228, 228, 227];

    /** @var int[] Text in the grey pills (template placeholders, 50 % black). */
    const PILLTEXT = [127, 127, 127];

    /** @var string Language of the handout. The background image carries German text. */
    const LANG = 'de';

    /**
     * Render the handout.
     *
     * @param stdClass $instance enrol instance, must carry the token in customchar1
     * @param stdClass $course
     * @return string PDF file content
     */
    public static function render(stdClass $instance, stdClass $course): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $lang = get_string_manager()->translation_exists(self::LANG) ? self::LANG : self::get_language($course);
        $joinurl = enrol_get_plugin('linkself')->get_join_url($instance)->out(false);
        $context = \context_course::instance($course->id);

        $pdf = new \pdf('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->SetTitle(self::str('handoutheadline', $lang));
        $pdf->SetCreator('Moodle enrol_linkself');
        $fonts = self::load_fonts($pdf);
        $pdf->AddPage();

        $pdf->Image(
            $CFG->dirroot . '/enrol/linkself/pix/handout-background.png',
            0,
            0,
            $pdf->getPageWidth(),
            $pdf->getPageHeight(),
            'PNG'
        );

        // Text columns and tile tops, taken from the template.
        $leftx = self::px(300);
        $rightx = self::px(2055);
        $tiletop = [1 => self::px(654), 2 => self::px(1194), 3 => self::px(1738), 4 => self::px(654)];
        $textoffset = 7.2;

        // Step texts in the light condensed headline font at 18 pt with 7.6 mm line pitch, like the template.
        $pdf->setCellHeightRatio(1.2);
        $widths = [1 => self::px(1180) - $leftx, 2 => 118, 3 => 118, 4 => 100];
        $bottoms = [];
        for ($i = 1; $i <= 4; $i++) {
            $pdf->SetTextColor(...($i === 4 ? self::RED : self::TEXT));
            $pdf->SetFont($fonts['headlight'], '', 18);
            $pdf->SetXY($i === 4 ? $rightx : $leftx, $tiletop[$i] + $textoffset);
            $pdf->MultiCell($widths[$i], 0, self::str('handoutstep' . $i, $lang), 0, 'L');
            $bottoms[$i] = $pdf->GetY();
        }

        // Tile 1: link below the text, grey and smaller, may wrap. QR code inside the frame of the template.
        $pdf->SetTextColor(...self::LINKGREY);
        $pdf->SetFont($fonts['headlight'], '', 11);
        $pdf->setCellHeightRatio(1.15);
        $pdf->SetXY($leftx, $bottoms[1] + 1.5);
        $pdf->MultiCell($widths[1], 0, $joinurl, 0, 'L');

        $qrsize = self::px(1555 - 1245);
        $pdf->write2DBarcode($joinurl, 'QRCODE,M', self::px(1245), self::px(913.5) - $qrsize / 2, $qrsize, $qrsize, [
            'border' => false,
            'padding' => 0,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
        ], 'N');

        // Tile 3: course name in a grey pill like the template placeholders, below it the confirm button in red.
        $coursename = format_string($course->fullname, true, ['context' => $context]);
        $y = self::pill($pdf, $fonts['headblack'], $leftx, $bottoms[3] + 3.5, 118, $coursename, self::PILLFILL, self::PILLTEXT);
        self::pill($pdf, $fonts['headblack'], $leftx, $y + 3, 118, self::str('enrolme', $lang), self::RED, self::WHITE);

        return $pdf->Output('', 'S');
    }

    /**
     * Rounded pill with text, styled like the placeholders of the template. Wraps if the text is too long.
     *
     * @param \pdf $pdf
     * @param string $font
     * @param float $x
     * @param float $y
     * @param float $maxwidth
     * @param string $text
     * @param int[] $fill
     * @param int[] $textcolor
     * @return float bottom of the pill
     */
    protected static function pill(
        \pdf $pdf,
        string $font,
        float $x,
        float $y,
        float $maxwidth,
        string $text,
        array $fill,
        array $textcolor
    ): float {
        $padding = 5;
        $height = self::px(105);
        $pdf->SetFont($font, '', 13);
        $pdf->setCellHeightRatio(1.15);
        // One millimetre slack, otherwise MultiCell may wrap a text that just fits.
        $width = min($pdf->GetStringWidth($text) + 2 * $padding + 1, $maxwidth);
        $minwidth = self::px(410);
        $width = max($width, $minwidth);
        $lines = $pdf->getNumLines($text, $width - 2 * $padding);
        $lineheight = 13 * 0.3528 * 1.15;
        $boxheight = max($height, $lines * $lineheight + 4);

        $pdf->SetFillColor(...$fill);
        $pdf->RoundedRect($x, $y, $width, $boxheight, min($height, $boxheight) / 2, '1111', 'F');
        $pdf->SetTextColor(...$textcolor);
        $pdf->SetXY($x + $padding, $y + ($boxheight - $lines * $lineheight) / 2);
        $pdf->MultiCell($width - 2 * $padding, 0, $text, 0, 'C');
        return $y + $boxheight;
    }

    /**
     * Convert a position in pixels of the background image (300 dpi) to mm.
     *
     * @param float $px
     * @return float
     */
    protected static function px(float $px): float {
        return $px * 297 / 3508;
    }

    /**
     * File name of the handout in the language of the course.
     *
     * @param stdClass $course
     * @return string
     */
    public static function get_filename(stdClass $course): string {
        return self::str('handoutfilename', self::get_language($course));
    }

    /**
     * Language for texts that end up in the course: forced course language, otherwise the site default.
     *
     * @param stdClass $course
     * @return string
     */
    public static function get_language(stdClass $course): string {
        global $CFG;

        $lang = !empty($course->lang) ? $course->lang : $CFG->lang;
        return get_string_manager()->translation_exists($lang) ? $lang : 'en';
    }

    /**
     * A plugin string in the given language.
     *
     * @param string $identifier
     * @param string $lang
     * @return string
     */
    public static function str(string $identifier, string $lang): string {
        return get_string_manager()->get_string($identifier, 'enrol_linkself', null, $lang);
    }

    /**
     * Make the Meta IGM fonts available to TCPDF.
     *
     * TCPDF cannot use TTF files directly. They are converted once into the local cache and loaded
     * from there; if the cache is purged they are simply converted again. Fonts are embedded as subsets.
     *
     * @param \pdf $pdf
     * @return string[] role => TCPDF font family; falls back to helvetica if a font cannot be converted
     */
    protected static function load_fonts(\pdf $pdf): array {
        global $CFG;

        $files = [
            'headblack' => 'MetaHeadIGM-CondBlack.ttf',
            'headlight' => 'MetaHeadIGM-CondLight.ttf',
            'regular' => 'MetaIGM.ttf',
        ];
        $cachedir = make_localcache_directory('enrol_linkself/fonts') . '/';
        $fonts = [];
        foreach ($files as $role => $file) {
            $fonts[$role] = 'helvetica';
            $family = \TCPDF_FONTS::addTTFfont(
                $CFG->dirroot . '/enrol/linkself/fonts/' . $file,
                'TrueTypeUnicode',
                '',
                32,
                $cachedir
            );
            if ($family) {
                $pdf->AddFont($family, '', $cachedir . $family . '.php');
                $fonts[$role] = $family;
            } else {
                debugging('enrol_linkself: could not convert font ' . $file, DEBUG_DEVELOPER);
            }
        }
        return $fonts;
    }
}
