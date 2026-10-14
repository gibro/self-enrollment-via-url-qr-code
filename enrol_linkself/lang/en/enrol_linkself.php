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
 * English language strings for the enrol_linkself plugin.
 *
 * @package    enrol_linkself
 * @copyright  2026 Guido Brombach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['alreadyenrolled'] = 'There is already an enrolment in this course. If access is suspended, please contact the course team.';
$string['cannotenrol'] = 'This account cannot enrol via this link.';
$string['closeenrolment'] = 'Close enrolment';
$string['confirmquestion'] = 'Enrol in the course "{$a}"?';
$string['confirmtitle'] = 'Confirm enrolment';
$string['enrolenddate'] = 'Enrolment possible until';
$string['enrolenddate_help'] = 'After this time nobody can newly enrol via the link. Users who are already enrolled stay active in the course.';
$string['enrolenddaterror'] = 'The end must not be before the start.';
$string['enrolledcount'] = 'Enrolled via this method: {$a}';
$string['enrolme'] = 'Enrol now';
$string['enrolmentclosed'] = 'Enrolment is closed. Please contact the course team.';
$string['enrolmentclosednow'] = 'Enrolment is closed. Users who are already enrolled stay active in the course.';
$string['enrolmentreopened'] = 'Enrolment is open again.';
$string['enrolmenttooearly'] = 'Enrolment has not started yet.';
$string['enrolmenttoolate'] = 'Enrolment has ended.';
$string['enrolstartdate'] = 'Enrolment possible from';
$string['enrolstartdate_help'] = 'Before this time nobody can enrol via the link.';
$string['linkinvalid'] = 'This link is invalid or has been replaced.';
$string['linklabel'] = 'Enrolment link';
$string['linknotice'] = 'Anyone with the link or the QR code can enrol while enrolment is open. Every person has to enrol themselves and confirm.';
$string['linkpage'] = 'Enrolment link and QR code';
$string['linkregenerated'] = 'There is a new link. The previous link and all printed QR codes are invalid.';
$string['linkself:config'] = 'Configure enrolment via link and manage the link';
$string['linkself:enrolself'] = 'Enrol oneself via the enrolment link';
$string['linkself:manage'] = 'Edit enrolments made via the enrolment link';
$string['linkself:unenrol'] = 'Unenrol users enrolled via link';
$string['linkself:unenrolself'] = 'Unenrol oneself from the course';
$string['maxenrolled'] = 'Maximum number of participants';
$string['maxenrolled_help'] = 'Maximum number of enrolments via this method. 0 means unlimited.';
$string['maxenrollederror'] = 'The number must not be negative.';
$string['maxenrolledreached'] = 'The maximum number of participants has been reached.';
$string['noinstance'] = 'The enrolment method "Enrolment via link" has not been added to this course yet. You can add it under "Enrolment methods".';
$string['noqrlib'] = 'The QR code could not be generated. The link still works.';
$string['openenrolment'] = 'Enrolment open';
$string['openenrolment_help'] = 'If "No", nobody can newly enrol via the link. Users who are already enrolled stay active in the course.';
$string['pluginname'] = 'Enrolment via link';
$string['privacy:metadata'] = 'The "Enrolment via link" plugin does not store any personal data.';
$string['regenerateconfirm'] = 'The previous link and all printed QR codes will become invalid. Users who are already enrolled stay enrolled. Create a new link?';
$string['regeneratelink'] = 'Create new link';
$string['reopenenrolment'] = 'Reopen enrolment';
$string['role'] = 'Role on enrolment';
$string['showlink'] = 'Show link and QR code';
$string['statusclosed'] = 'Enrolment is closed. Users who are already enrolled stay active in the course.';
$string['statusopen'] = 'Enrolment is open.';
