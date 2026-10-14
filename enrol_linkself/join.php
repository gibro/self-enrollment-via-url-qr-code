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
 * Entry page of the secret enrolment link: login, confirmation, enrolment.
 *
 * Opening the link never enrols anybody. Only a confirmed POST with a valid sesskey does.
 * The token alone identifies the instance, the course id does not appear in the URL.
 *
 * @package    enrol_linkself
 * @copyright  2026 Guido Brombach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: in Moodle 5.x the web root is public/. This path assumes the config.php shim in public/.
require_once(__DIR__ . '/../../config.php');

$token = required_param('t', PARAM_ALPHANUM);

$PAGE->set_url(new moodle_url('/enrol/linkself/join.php', ['t' => $token]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('confirmtitle', 'enrol_linkself'));
$PAGE->set_heading(get_string('confirmtitle', 'enrol_linkself'));

// Keep the token out of referrer headers sent to other sites.
@header('Referrer-Policy: no-referrer');

// Not logged in: Moodle remembers this full URL (including the token) and returns here after login.
require_login(null, false);
if (isguestuser()) {
    $SESSION->wantsurl = qualified_me();
    redirect(get_login_url());
}

$plugin = enrol_get_plugin('linkself');
$instance = false;
if ($plugin && enrol_is_enabled('linkself')) {
    $instance = $DB->get_record('enrol', ['enrol' => 'linkself', 'customchar1' => $token]);
}

// Same answer for unknown, revoked or malformed tokens.
if (!$instance) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('linkinvalid', 'enrol_linkself'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->continue_button(new moodle_url('/'));
    echo $OUTPUT->footer();
    exit;
}

$course = get_course($instance->courseid);
$coursecontext = context_course::instance($course->id);

// Already an active participant by any method: just go to the course.
if (is_enrolled($coursecontext, $USER, '', true)) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

// Hidden courses cannot be entered, so enrolment is refused like in the standard enrol page.
if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
    $status = 'enrolmentclosed';
} else {
    $status = $plugin->check_availability($instance);
}

if ($status === true && optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
    $plugin->enrol_current_user($instance);
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

echo $OUTPUT->header();

if ($status !== true) {
    echo $OUTPUT->notification(get_string($status, 'enrol_linkself'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->continue_button(new moodle_url('/'));
} else {
    $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);
    echo $OUTPUT->heading(get_string('confirmquestion', 'enrol_linkself', $coursename), 3);

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out_omit_querystring()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 't', 'value' => $token]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary me-2',
        'value' => get_string('enrolme', 'enrol_linkself'),
    ]);
    echo html_writer::link(new moodle_url('/'), get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
