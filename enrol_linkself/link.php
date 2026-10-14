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
 * Trainer page: shows the enrolment link and QR code, closes or reopens enrolment, creates a new link.
 *
 * Closing enrolment only stops new enrolments. Everybody already enrolled stays active.
 *
 * @package    enrol_linkself
 * @copyright  2026 Guido Brombach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: in Moodle 5.x the web root is public/. This path assumes the config.php shim in public/.
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('enrol/linkself:config', $context);

$PAGE->set_url(new moodle_url('/enrol/linkself/link.php', ['courseid' => $course->id]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('linkpage', 'enrol_linkself'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$plugin = enrol_get_plugin('linkself');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'linkself'], '*', IGNORE_MULTIPLE);

if (!$plugin || !$instance) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('noinstance', 'enrol_linkself'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->continue_button(new moodle_url('/enrol/instances.php', ['id' => $course->id]));
    echo $OUTPUT->footer();
    exit;
}

// Switches. All of them change data, so all of them need a valid sesskey.
if ($action === 'open' || $action === 'close') {
    require_sesskey();
    $plugin->set_open($instance, $action === 'open');
    redirect($PAGE->url, get_string($action === 'open' ? 'enrolmentreopened' : 'enrolmentclosednow', 'enrol_linkself'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'regenerate') {
    if ($confirm && confirm_sesskey()) {
        $plugin->regenerate_token($instance);
        redirect($PAGE->url, get_string('linkregenerated', 'enrol_linkself'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('regenerateconfirm', 'enrol_linkself'),
        new moodle_url($PAGE->url, ['action' => 'regenerate', 'confirm' => 1, 'sesskey' => sesskey()]),
        $PAGE->url
    );
    echo $OUTPUT->footer();
    exit;
}

$isopen = !empty($instance->customint1);
$joinurl = $plugin->get_join_url($instance)->out(false);
$qr = \enrol_linkself\local\qr::svg($joinurl);
$enrolledcount = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('linkpage', 'enrol_linkself'));

if ($isopen) {
    echo $OUTPUT->notification(get_string('statusopen', 'enrol_linkself'), \core\output\notification::NOTIFY_SUCCESS);
} else {
    echo $OUTPUT->notification(get_string('statusclosed', 'enrol_linkself'), \core\output\notification::NOTIFY_WARNING);
}

echo html_writer::tag('p', get_string('enrolledcount', 'enrol_linkself', $enrolledcount));

echo html_writer::tag('label', get_string('linklabel', 'enrol_linkself'), ['for' => 'linkself-url', 'class' => 'fw-bold']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'linkself-url',
    'class' => 'form-control mb-3',
    'readonly' => 'readonly',
    'value' => $joinurl,
    'onfocus' => 'this.select()',
]);

if ($qr !== null) {
    echo html_writer::div($qr, 'mb-3', ['style' => 'background:#fff;padding:16px;display:inline-block;border:1px solid #ccc']);
} else {
    echo $OUTPUT->notification(get_string('noqrlib', 'enrol_linkself'), \core\output\notification::NOTIFY_INFO);
}

echo html_writer::tag('p', get_string('linknotice', 'enrol_linkself'), ['class' => 'text-muted']);

// Buttons as POST forms.
$buttons = [
    [$isopen ? 'close' : 'open', $isopen ? 'closeenrolment' : 'reopenenrolment', $isopen ? 'btn-primary' : 'btn-success'],
    ['regenerate', 'regeneratelink', 'btn-secondary'],
];
echo html_writer::start_div('d-flex flex-wrap gap-2');
foreach ($buttons as [$buttonaction, $stringid, $class]) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out_omit_querystring()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $course->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $buttonaction]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn ' . $class,
        'value' => get_string($stringid, 'enrol_linkself'),
    ]);
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();

echo $OUTPUT->footer();
