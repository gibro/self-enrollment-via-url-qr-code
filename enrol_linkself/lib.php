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
 * Enrolment via secret link (QR code friendly).
 *
 * Each instance owns a random token (customchar1). Whoever opens /enrol/linkself/join.php?t=<token>
 * and confirms may enrol themselves. Nobody can be enrolled by someone else: allow_enrol() is false,
 * there is no batch path. "Closing" enrolment only flips customint1, the instance status stays
 * enabled, so everybody already enrolled keeps access.
 *
 * Field usage in the enrol table:
 *   customchar1 = secret token
 *   customint1  = enrolment open (1) or closed (0)
 *   customint3  = maximum number of enrolled users (0 = unlimited)
 *   enrolstartdate / enrolenddate = optional enrolment window
 *
 * @package    enrol_linkself
 * @copyright  2026 Guido Brombach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Link based self enrolment plugin.
 */
class enrol_linkself_plugin extends enrol_plugin {

    /**
     * Create a new random token (256 bit, hex encoded).
     *
     * @return string
     */
    public static function generate_token(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Localised name of the instance.
     *
     * @param stdClass $instance
     * @return string
     */
    public function get_instance_name($instance) {
        if (!empty($instance->name)) {
            return format_string($instance->name, true, ['context' => context_course::instance($instance->courseid)]);
        }
        return get_string('pluginname', 'enrol_linkself');
    }

    /**
     * Teachers may adjust roles later, like in self enrolment.
     *
     * @return bool
     */
    public function roles_protected() {
        return false;
    }

    /**
     * Nobody may enrol other users through this method, not even teachers.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_enrol(stdClass $instance) {
        return false;
    }

    /**
     * Unenrolling stays possible for users with the capability.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Teachers may edit period and status of single enrolments.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    /**
     * One link per course.
     *
     * @param int $courseid
     * @return bool
     */
    public function can_add_instance($courseid) {
        global $DB;

        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) || !has_capability('enrol/linkself:config', $context)) {
            return false;
        }
        return !$DB->record_exists('enrol', ['courseid' => $courseid, 'enrol' => 'linkself']);
    }

    /**
     * Hiding would suspend the access of everybody enrolled via this method, so it is never offered.
     * Enrolment is stopped with the "close" switch on the link page instead.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        return false;
    }

    /**
     * Deleting an instance unenrols everybody, so it is only allowed while nobody is enrolled through it.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        global $DB;

        $context = context_course::instance($instance->courseid);
        if (!has_capability('enrol/linkself:config', $context)) {
            return false;
        }
        return !$DB->record_exists('user_enrolments', ['enrolid' => $instance->id]);
    }

    /**
     * Use the standard add/edit instance UI.
     *
     * @return bool
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Icons for the list of enrolment methods: edit and link page.
     *
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance): array {
        global $OUTPUT;

        $context = context_course::instance($instance->courseid);
        $icons = [];
        if (has_capability('enrol/linkself:config', $context)) {
            $editurl = new moodle_url('/enrol/editinstance.php', [
                'courseid' => $instance->courseid,
                'id' => $instance->id,
                'type' => $instance->enrol,
            ]);
            $icons[] = $OUTPUT->action_icon(
                $editurl,
                new pix_icon('t/edit', get_string('edit'), 'core', ['class' => 'iconsmall'])
            );

            $linkurl = new moodle_url('/enrol/linkself/link.php', ['courseid' => $instance->courseid]);
            $icons[] = $OUTPUT->action_icon(
                $linkurl,
                new pix_icon('e/insert_edit_link', get_string('showlink', 'enrol_linkself'), 'core', ['class' => 'iconsmall'])
            );
        }
        return $icons;
    }

    /**
     * Defaults for new instances.
     *
     * @return array
     */
    public function get_instance_defaults() {
        global $DB;

        $roleid = $this->get_config('roleid');
        if (!$roleid) {
            $roleid = $DB->get_field('role', 'id', ['archetype' => 'student'], IGNORE_MULTIPLE);
        }

        return [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $roleid ?: 0,
            'customint1' => 1,
            'customint3' => 0,
            'enrolperiod' => 0,
        ];
    }

    /**
     * Add elements to the edit instance form.
     *
     * There is deliberately no status field and no enrolment duration: a disabled instance or an
     * expiring enrolment would lock participants out.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return void
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        if (!has_capability('enrol/linkself:config', $context)) {
            return;
        }

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'), ['size' => '20', 'maxlength' => '255']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $mform->addElement('select', 'customint1', get_string('openenrolment', 'enrol_linkself'), [
            1 => get_string('yes'),
            0 => get_string('no'),
        ]);
        $mform->addHelpButton('customint1', 'openenrolment', 'enrol_linkself');

        $roleid = $instance->roleid ?? 0;
        $mform->addElement('select', 'roleid', get_string('role', 'enrol_linkself'), $this->extend_assignable_roles($context, $roleid));

        $mform->addElement('text', 'customint3', get_string('maxenrolled', 'enrol_linkself'));
        $mform->setType('customint3', PARAM_INT);
        $mform->addHelpButton('customint3', 'maxenrolled', 'enrol_linkself');

        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_linkself'), ['optional' => true]);
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_linkself');

        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_linkself'), ['optional' => true]);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_linkself');

        if (!empty($instance->id)) {
            $linkurl = new moodle_url('/enrol/linkself/link.php', ['courseid' => $instance->courseid]);
            $mform->addElement('static', 'linkpageinfo', get_string('linkpage', 'enrol_linkself'),
                html_writer::link($linkurl, get_string('showlink', 'enrol_linkself')));
        }
    }

    /**
     * Validate the edit instance form.
     *
     * @param array $data
     * @param array $files
     * @param object $instance
     * @param context $context
     * @return array
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];

        if (!empty($data['enrolenddate']) && $data['enrolenddate'] < ($data['enrolstartdate'] ?? 0)) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_linkself');
        }
        if (isset($data['customint3']) && $data['customint3'] < 0) {
            $errors['customint3'] = get_string('maxenrollederror', 'enrol_linkself');
        }

        $validroles = array_keys($this->extend_assignable_roles($context, $instance->roleid ?? 0));
        $typeerrors = $this->validate_param_types($data, [
            'name' => PARAM_TEXT,
            'customint1' => [0, 1],
            'customint3' => PARAM_INT,
            'enrolstartdate' => PARAM_INT,
            'enrolenddate' => PARAM_INT,
            'roleid' => $validroles,
        ]);

        return array_merge($errors, $typeerrors);
    }

    /**
     * Add a new instance. A fresh token is always generated, whatever the caller passes.
     *
     * @param stdClass $course
     * @param array|null $fields
     * @return int|null id of the new instance
     */
    public function add_instance($course, ?array $fields = null) {
        $fields = $fields ?? [];
        $fields['customchar1'] = self::generate_token();
        $fields['status'] = ENROL_INSTANCE_ENABLED;
        $fields['enrolperiod'] = 0;
        return parent::add_instance($course, $fields);
    }

    /**
     * Update an instance. Status stays enabled and the token cannot be changed through this path.
     *
     * @param stdClass $instance
     * @param stdClass $data
     * @return bool
     */
    public function update_instance($instance, $data) {
        $data->status = ENROL_INSTANCE_ENABLED;
        $data->enrolperiod = 0;
        unset($data->customchar1);
        return parent::update_instance($instance, $data);
    }

    /**
     * Open or close enrolment. Only the open flag changes, enrolled users are untouched.
     *
     * @param stdClass $instance
     * @param bool $open
     * @return void
     */
    public function set_open(stdClass $instance, bool $open): void {
        $this->update_instance($instance, (object) ['customint1' => $open ? 1 : 0]);
    }

    /**
     * Replace the token. The old link and all printed QR codes stop working at once.
     *
     * @param stdClass $instance
     * @return string the new token
     */
    public function regenerate_token(stdClass $instance): string {
        global $DB;

        $token = self::generate_token();
        $DB->update_record('enrol', (object) [
            'id' => $instance->id,
            'customchar1' => $token,
            'timemodified' => time(),
        ]);
        $instance->customchar1 = $token;
        \core\event\enrol_instance_updated::create_from_record($instance)->trigger();
        return $token;
    }

    /**
     * URL that enrols (after login and confirmation) via this instance.
     *
     * @param stdClass $instance
     * @return moodle_url
     */
    public function get_join_url(stdClass $instance): moodle_url {
        return new moodle_url('/enrol/linkself/join.php', ['t' => $instance->customchar1]);
    }

    /**
     * Check whether the current user may enrol via this instance right now.
     *
     * @param stdClass $instance
     * @return true|string true, or the identifier of an enrol_linkself language string with the reason
     */
    public function check_availability(stdClass $instance) {
        global $DB, $USER;

        if ($instance->status != ENROL_INSTANCE_ENABLED || empty($instance->customint1)) {
            return 'enrolmentclosed';
        }

        $now = time();
        if (!empty($instance->enrolstartdate) && $instance->enrolstartdate > $now) {
            return 'enrolmenttooearly';
        }
        if (!empty($instance->enrolenddate) && $instance->enrolenddate < $now) {
            return 'enrolmenttoolate';
        }

        if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $USER->id])) {
            return 'alreadyenrolled';
        }

        if ((int) $instance->customint3 > 0) {
            $count = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);
            if ($count >= (int) $instance->customint3) {
                return 'maxenrolledreached';
            }
        }

        if (!has_capability('enrol/linkself:enrolself', context_course::instance($instance->courseid))) {
            return 'cannotenrol';
        }

        return true;
    }

    /**
     * Enrol the current user. Re-checks everything, so it is safe to call from a confirmed request only.
     *
     * @param stdClass $instance
     * @return void
     */
    public function enrol_current_user(stdClass $instance): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            throw new moodle_exception('noguestaccess', 'enrol');
        }
        $available = $this->check_availability($instance);
        if ($available !== true) {
            throw new moodle_exception($available, 'enrol_linkself');
        }

        // No enrolment end: the enrolment must not expire on its own.
        $this->enrol_user($instance, $USER->id, $instance->roleid, time(), 0);
        \core\notification::success(get_string('youenrolledincourse', 'enrol'));
    }

    /**
     * Roles that may be assigned on enrolment, including the current one.
     *
     * @param context $context
     * @param int $defaultrole
     * @return array role id => name
     */
    protected function extend_assignable_roles($context, $defaultrole) {
        global $DB;

        $roles = get_assignable_roles($context, ROLENAME_BOTH);
        if ($defaultrole && !isset($roles[$defaultrole])) {
            if ($role = $DB->get_record('role', ['id' => $defaultrole])) {
                $roles[$defaultrole] = role_get_name($role, $context, ROLENAME_BOTH);
            }
        }
        return $roles;
    }

    /**
     * Restore an instance from a backup. The token is never copied: the restored instance gets a
     * new token and starts closed, so a course copy cannot be entered through the old link.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        $fields = (array) $data;
        unset($fields['customchar1']);
        $fields['customint1'] = 0;
        $fields['status'] = ENROL_INSTANCE_ENABLED;
        $instanceid = $this->add_instance($course, $fields);
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore a user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $userid
     * @param int $oldinstancestatus
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Restore a role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        role_assign($roleid, $userid, $contextid, '', 0);
    }
}
