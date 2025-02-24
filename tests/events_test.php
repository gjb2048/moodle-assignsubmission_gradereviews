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
 * Events tests.
 *
 * @package    assignsubmission_gradereviews
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_gradereviews;

use mod_assign_testable_assign;

use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/tests/fixtures/testable_assign.php');

/**
 * Events tests class.
 *
 * @package    assignsubmission_gradereviews
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {

    /** @const Default number of students to create */
    const DEFAULT_STUDENT_COUNT = 3;
    /** @const Default number of teachers to create */
    const DEFAULT_TEACHER_COUNT = 2;
    /** @const Default number of editing teachers to create */
    const DEFAULT_EDITING_TEACHER_COUNT = 2;
    /** @const Optional extra number of students to create */
    const EXTRA_STUDENT_COUNT = 40;
    /** @const Optional number of suspended students */
    const EXTRA_SUSPENDED_COUNT = 10;
    /** @const Optional extra number of teachers to create */
    const EXTRA_TEACHER_COUNT = 5;
    /** @const Optional extra number of editing teachers to create */
    const EXTRA_EDITING_TEACHER_COUNT = 5;
    /** @const Number of groups to create */
    const GROUP_COUNT = 6;

    /** @var \stdClass $course New course created to hold the assignments */
    protected $course = null;

    /** @var array $teachers List of DEFAULT_TEACHER_COUNT teachers in the course*/
    protected $teachers = null;

    /** @var array $editingteachers List of DEFAULT_EDITING_TEACHER_COUNT editing teachers in the course */
    protected $editingteachers = null;

    /** @var array $students List of DEFAULT_STUDENT_COUNT students in the course*/
    protected $students = null;

    /** @var array $extrateachers List of EXTRA_TEACHER_COUNT teachers in the course*/
    protected $extrateachers = null;

    /** @var array $extraeditingteachers List of EXTRA_EDITING_TEACHER_COUNT editing teachers in the course*/
    protected $extraeditingteachers = null;

    /** @var array $extrastudents List of EXTRA_STUDENT_COUNT students in the course*/
    protected $extrastudents = null;

    /** @var array $extrasuspendedstudents List of EXTRA_SUSPENDED_COUNT students in the course*/
    protected $extrasuspendedstudents = null;

    /** @var array $groups List of 10 groups in the course */
    protected $groups = null;

    /**
     * Setup function - we will create a course and add an assign instance to it.
     */
    protected function setUp(): void {
        global $DB;

        $this->resetAfterTest(true);

        $this->course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $this->teachers = array();
        for ($i = 0; $i < self::DEFAULT_TEACHER_COUNT; $i++) {
            array_push($this->teachers, $this->getDataGenerator()->create_user());
        }

        $this->editingteachers = array();
        for ($i = 0; $i < self::DEFAULT_EDITING_TEACHER_COUNT; $i++) {
            array_push($this->editingteachers, $this->getDataGenerator()->create_user());
        }

        $this->students = array();
        for ($i = 0; $i < self::DEFAULT_STUDENT_COUNT; $i++) {
            array_push($this->students, $this->getDataGenerator()->create_user());
        }

        $this->groups = array();
        for ($i = 0; $i < self::GROUP_COUNT; $i++) {
            array_push($this->groups, $this->getDataGenerator()->create_group(array('courseid' => $this->course->id)));
        }

        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        foreach ($this->teachers as $i => $teacher) {
            $this->getDataGenerator()->enrol_user($teacher->id,
                                                  $this->course->id,
                                                  $teacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $teacher);
        }

        $editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        foreach ($this->editingteachers as $i => $editingteacher) {
            $this->getDataGenerator()->enrol_user($editingteacher->id,
                                                  $this->course->id,
                                                  $editingteacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $editingteacher);
        }

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        foreach ($this->students as $i => $student) {
            $this->getDataGenerator()->enrol_user($student->id,
                                                  $this->course->id,
                                                  $studentrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $student);
        }
    }

    /**
     * Test gradereview_created event.
     */
    public function test_gradereview_created() {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        $this->setUser($this->editingteachers[0]);
        $assign = $this->create_instance();
        $submission = $assign->get_user_submission($this->students[0]->id, true);

        $context = $assign->get_context();
        $options = new stdClass();
        $options->area = 'submission_gradereviews';
        $options->course = $assign->get_course();
        $options->context = $context;
        $options->itemid = $submission->id;
        $options->component = 'assignsubmission_gradereviews';
        $options->showcount = true;
        $options->displaycancel = true;

        $gradereview = new \comment($options);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $gradereview->add('New gradereview');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\assignsubmission_gradereviews\event\gradereview_created', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/assign/view.php', ['id' => $assign->get_course_module()->id]);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test gradereview_deleted event.
     */
    public function test_gradereview_deleted() {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        $this->setUser($this->editingteachers[0]);
        $assign = $this->create_instance();
        $submission = $assign->get_user_submission($this->students[0]->id, true);

        $context = $assign->get_context();
        $options = new stdClass();
        $options->area    = 'submission_gradereviews';
        $options->course    = $assign->get_course();
        $options->context = $context;
        $options->itemid  = $submission->id;
        $options->component = 'assignsubmission_gradereviews';
        $options->showcount = true;
        $options->displaycancel = true;
        $gradereview = new \comment($options);
        $newgradereview = $gradereview->add('New gradereview 1');

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $gradereview->delete($newgradereview->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\assignsubmission_gradereviews\event\gradereview_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/assign/view.php', ['id' => $assign->get_course_module()->id]);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Convenience function to create a testable instance of an assignment.
     *
     * @param array $params Array of parameters to pass to the generator
     * @return testable_assign Testable wrapper around the assign class.
     */
    protected function create_instance($params = []) {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        if (!isset($params['course'])) {
            $params['course'] = $this->course->id;
        }
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = \context_module::instance($cm->id);
        return new mod_assign_testable_assign($context, $cm, $this->course);
    }

    public function test_create_instance() {
        $this->assertNotEmpty($this->create_instance());
    }
}

class_alias('mod_assign_testable_assign', 'testable_assign');
