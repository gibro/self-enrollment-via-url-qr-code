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
 * Hidden file activity (mod_resource) in section 0 that holds the handout PDF.
 *
 * The activity is recognised by its ID number, so it survives backup and restore. It is created
 * without capability checks on purpose: courses are also created by CLI and upload tools where no
 * teacher is logged in.
 *
 * @package    enrol_linkself
 * @copyright  2026 Guido Brombach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class handout_activity {
    /** @var string ID number of the course module. */
    const IDNUMBER = 'enrol_linkself_handout';

    /**
     * Create the hidden file activity with the handout in section 0.
     *
     * @param stdClass $instance enrol instance with token
     * @return int|null course module id, null if mod_resource is not available
     */
    public static function create(stdClass $instance): ?int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        $module = $DB->get_record('modules', ['name' => 'resource', 'visible' => 1]);
        if (!$module) {
            return null;
        }
        $course = get_course($instance->courseid);
        $lang = handout::get_language($course);

        $moduleinfo = (object) [
            'modulename' => 'resource',
            'module' => $module->id,
            'course' => $course->id,
            'section' => 0,
            'visible' => 0,
            'visibleoncoursepage' => 1,
            'cmidnumber' => self::IDNUMBER,
            'name' => handout::str('handoutactivityname', $lang),
            'intro' => handout::str('handoutactivityintro', $lang),
            'introformat' => FORMAT_HTML,
            'showdescription' => 0,
            'display' => RESOURCELIB_DISPLAY_AUTO,
            'printintro' => 0,
            'files' => 0,
        ];
        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        self::store_file($moduleinfo->coursemodule, $instance, $course);
        return (int) $moduleinfo->coursemodule;
    }

    /**
     * Replace the PDF in all handout activities of the course, e.g. after a new token was generated.
     *
     * @param stdClass $instance enrol instance with the current token
     * @return void
     */
    public static function refresh(stdClass $instance): void {
        global $DB;

        $course = get_course($instance->courseid);
        foreach (self::get_course_modules($course->id) as $cm) {
            self::store_file($cm->id, $instance, $course);
            // New revision, so browsers and the app do not serve the old PDF from their cache.
            $DB->execute('UPDATE {resource} SET revision = revision + 1 WHERE id = ?', [$cm->instance]);
            \course_modinfo::purge_course_module_cache($course->id, $cm->id);
        }
        rebuild_course_cache($course->id, true);
    }

    /**
     * Delete all handout activities of a course.
     *
     * @param int $courseid
     * @return void
     */
    public static function delete(int $courseid): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        foreach (self::get_course_modules($courseid) as $cm) {
            if (method_exists(\core_courseformat\local\cmactions::class, 'delete')) {
                \core_courseformat\formatactions::cm($courseid)->delete($cm->id);
            } else {
                // Before Moodle 5.2.
                course_delete_module($cm->id);
            }
        }
    }

    /**
     * After a restore the handout still holds the token of the source course. Replace it with the
     * token of the restored instance, or remove the activity if the course has no link instance.
     *
     * @param \core\event\course_restored $event
     * @return void
     */
    public static function course_restored(\core\event\course_restored $event): void {
        global $DB;

        if (!self::get_course_modules($event->courseid)) {
            return;
        }
        $instance = $DB->get_record('enrol', ['courseid' => $event->courseid, 'enrol' => 'linkself'], '*', IGNORE_MULTIPLE);
        if ($instance) {
            self::refresh($instance);
        } else {
            self::delete($event->courseid);
        }
    }

    /**
     * Handout activities of a course.
     *
     * @param int $courseid
     * @return stdClass[] course_modules records
     */
    public static function get_course_modules(int $courseid): array {
        global $DB;

        $sql = "SELECT cm.*
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name = 'resource' AND cm.course = :courseid AND cm.idnumber = :idnumber";
        return $DB->get_records_sql($sql, ['courseid' => $courseid, 'idnumber' => self::IDNUMBER]);
    }

    /**
     * Put a freshly rendered PDF as the only (and main) file into the activity.
     *
     * @param int $cmid
     * @param stdClass $instance
     * @param stdClass $course
     * @return void
     */
    protected static function store_file(int $cmid, stdClass $instance, stdClass $course): void {
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_resource', 'content', 0);
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => handout::get_filename($course),
            'sortorder' => 1,
        ], handout::render($instance, $course));
    }
}
