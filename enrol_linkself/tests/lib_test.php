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

namespace enrol_linkself;

use enrol_linkself\local\handout_activity;

/**
 * Tests for the enrol_linkself plugin class.
 *
 * @package    enrol_linkself
 * @copyright  2026 Guido Brombach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_linkself_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_linkself\local\handout::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_linkself\local\handout_activity::class)]
final class lib_test extends \advanced_testcase {
    /**
     * Enable the plugin, course_updated() is only called for enabled enrolment plugins.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $class = \core_plugin_manager::resolve_plugininfo_class('enrol');
        $class::enable_plugin('linkself', true);
    }

    /**
     * With defaultenrol set, a new course gets exactly one ready-to-use instance.
     *
     * @return void
     */
    public function test_default_instance_created_with_defaultenrol(): void {
        global $DB;

        set_config('defaultenrol', 1, 'enrol_linkself');
        $course = $this->getDataGenerator()->create_course();

        $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'linkself']);
        $this->assertCount(1, $instances);

        $instance = reset($instances);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->assertEquals(ENROL_INSTANCE_ENABLED, $instance->status);
        $this->assertEquals($studentroleid, $instance->roleid);
        $this->assertEquals(1, $instance->customint1);
        $this->assertEquals(0, $instance->customint3);
        $this->assertEquals(0, $instance->enrolperiod);
        $this->assertEquals(0, $instance->enrolstartdate);
        $this->assertEquals(0, $instance->enrolenddate);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $instance->customchar1);
    }

    /**
     * Without defaultenrol, a new course gets no instance.
     *
     * @return void
     */
    public function test_no_default_instance_without_defaultenrol(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'linkself']));
    }

    /**
     * Every default instance gets its own token.
     *
     * @return void
     */
    public function test_default_instances_have_distinct_tokens(): void {
        global $DB;

        set_config('defaultenrol', 1, 'enrol_linkself');
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        $token1 = $DB->get_field('enrol', 'customchar1', ['courseid' => $course1->id, 'enrol' => 'linkself']);
        $token2 = $DB->get_field('enrol', 'customchar1', ['courseid' => $course2->id, 'enrol' => 'linkself']);
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotSame($token1, $token2);
    }

    /**
     * add_default_instance() never adds a second instance to a course.
     *
     * @return void
     */
    public function test_add_default_instance_skips_existing(): void {
        global $DB;

        set_config('defaultenrol', 1, 'enrol_linkself');
        $course = $this->getDataGenerator()->create_course();

        $plugin = enrol_get_plugin('linkself');
        $this->assertNull($plugin->add_default_instance($course));
        $this->assertEquals(1, $DB->count_records('enrol', ['courseid' => $course->id, 'enrol' => 'linkself']));
    }

    /**
     * A new instance comes with one hidden file activity in section 0 that holds the handout PDF.
     *
     * @return void
     */
    public function test_handout_activity_created(): void {
        $instance = $this->create_course_with_instance();

        $cms = handout_activity::get_course_modules($instance->courseid);
        $this->assertCount(1, $cms);
        $cm = reset($cms);
        $this->assertEquals(0, $cm->visible);
        $cminfo = get_fast_modinfo($instance->courseid)->get_cm($cm->id);
        $this->assertEquals(0, $cminfo->sectionnum);
        $this->assertEquals('resource', $cminfo->modname);

        $file = $this->get_handout_file($cm->id);
        $this->assertEquals('application/pdf', $file->get_mimetype());
        $this->assertEquals(1, $file->get_sortorder());
        $this->assertStringStartsWith('%PDF', $file->get_content());
    }

    /**
     * A student does not see the handout activity.
     *
     * @return void
     */
    public function test_handout_activity_hidden_from_students(): void {
        $instance = $this->create_course_with_instance();
        $student = $this->getDataGenerator()->create_and_enrol(get_course($instance->courseid), 'student');
        $cms = handout_activity::get_course_modules($instance->courseid);
        $cm = reset($cms);

        $this->setUser($student);
        $this->assertFalse(get_fast_modinfo($instance->courseid)->get_cm($cm->id)->uservisible);
    }

    /**
     * A new token replaces the PDF and bumps the resource revision.
     *
     * @return void
     */
    public function test_regenerate_token_replaces_handout(): void {
        global $DB;

        $instance = $this->create_course_with_instance();
        $cms = handout_activity::get_course_modules($instance->courseid);
        $cm = reset($cms);
        $oldhash = $this->get_handout_file($cm->id)->get_contenthash();
        $oldrevision = $DB->get_field('resource', 'revision', ['id' => $cm->instance]);

        enrol_get_plugin('linkself')->regenerate_token($instance);

        $this->assertCount(1, handout_activity::get_course_modules($instance->courseid));
        $this->assertNotEquals($oldhash, $this->get_handout_file($cm->id)->get_contenthash());
        $this->assertEquals($oldrevision + 1, $DB->get_field('resource', 'revision', ['id' => $cm->instance]));
    }

    /**
     * Deleting the instance also removes the handout activity.
     *
     * @return void
     */
    public function test_delete_instance_removes_handout(): void {
        $instance = $this->create_course_with_instance();

        enrol_get_plugin('linkself')->delete_instance($instance);

        $this->assertEmpty(handout_activity::get_course_modules($instance->courseid));
    }

    /**
     * A restored course gets a new token and a handout with that new token, never the old PDF.
     *
     * @return void
     */
    public function test_restore_replaces_handout(): void {
        global $DB;

        $instance = $this->create_course_with_instance();
        $cms = handout_activity::get_course_modules($instance->courseid);
        $cm = reset($cms);
        $oldhash = $this->get_handout_file($cm->id)->get_contenthash();

        $newcourseid = $this->backup_and_restore($instance->courseid, true);

        $newinstance = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'linkself'], '*', MUST_EXIST);
        $this->assertNotEquals($instance->customchar1, $newinstance->customchar1);
        $this->assertEquals(0, $newinstance->customint1);
        $newcms = handout_activity::get_course_modules($newcourseid);
        $this->assertCount(1, $newcms);
        $this->assertNotEquals($oldhash, $this->get_handout_file(reset($newcms)->id)->get_contenthash());
    }

    /**
     * Restored without enrolment methods, the handout with the foreign link is removed.
     *
     * @return void
     */
    public function test_restore_without_instance_removes_handout(): void {
        $instance = $this->create_course_with_instance();

        $newcourseid = $this->backup_and_restore($instance->courseid, false);

        $this->assertEmpty(handout_activity::get_course_modules($newcourseid));
    }

    /**
     * Create a course with the default link instance.
     *
     * @return \stdClass enrol instance
     */
    protected function create_course_with_instance(): \stdClass {
        global $DB;

        set_config('defaultenrol', 1, 'enrol_linkself');
        $course = $this->getDataGenerator()->create_course();
        return $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'linkself'], '*', MUST_EXIST);
    }

    /**
     * The handout file of an activity.
     *
     * @param int $cmid
     * @return \stored_file
     */
    protected function get_handout_file(int $cmid): \stored_file {
        $files = get_file_storage()->get_area_files(
            \context_module::instance($cmid)->id,
            'mod_resource',
            'content',
            0,
            'sortorder',
            false
        );
        $this->assertCount(1, $files);
        return reset($files);
    }

    /**
     * Back up a course and restore it into a new course.
     *
     * @param int $courseid
     * @param bool $withenrolments restore enrolment methods
     * @return int id of the new course
     */
    protected function backup_and_restore(int $courseid, bool $withenrolments): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $CFG->backup_file_logger_level = \backup::LOG_NONE;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_SAMESITE,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $basepath = $bc->get_plan()->get_basepath();
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        if (!file_exists($basepath . '/moodle_backup.xml')) {
            $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $basepath);
        }

        $course = get_course($courseid);
        $newcourseid = \restore_dbops::create_new_course($course->fullname . ' 2', $course->shortname . '_2', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('enrolments')->set_value($withenrolments ? \backup::ENROL_ALWAYS : \backup::ENROL_NEVER);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }
}
