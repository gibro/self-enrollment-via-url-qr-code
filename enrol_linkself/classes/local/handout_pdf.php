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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Moodle PDF with marked content carrying a replacement text (PDF /ActualText).
 *
 * The enrolment link in the handout is too long for one line and wraps. Viewers then copy it with line
 * breaks. Wrapping its lines in a marked-content sequence with /ActualText makes viewers that support it
 * copy the link in one piece.
 *
 * @package    enrol_linkself
 * @copyright  2026 Guido Brombach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class handout_pdf extends \pdf {
    /**
     * Start a marked-content sequence whose text, when copied or extracted, is $text.
     *
     * Must be closed with end_actual_text() on the same page.
     *
     * @param string $text UTF-8
     */
    public function begin_actual_text(string $text): void {
        $utf16 = "\xFE\xFF" . mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
        $this->_out('/Span <</ActualText (' . \TCPDF_STATIC::_escape($utf16) . ')>> BDC');
    }

    /**
     * Close the marked-content sequence started by begin_actual_text().
     */
    public function end_actual_text(): void {
        $this->_out('EMC');
    }
}
