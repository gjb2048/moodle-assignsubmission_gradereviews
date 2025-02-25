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

namespace assignsubmission_gradereviews\event;

use mod_assign_test_generator;
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/comment/lib.php');

/**
 * Events tests class.
 *
 * @package    assignsubmission_gradereviews
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class events_test extends \advanced_testcase {

    // Use the generator helper.
    use mod_assign_test_generator;

    /**
     * Test comment_created event.
     */
    public function test_comment_created() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $manager = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($manager);
        $assign = $this->create_instance($course);

        $submission = $assign->get_user_submission($student->id, true);

        $context = $assign->get_context();
        $options = new \stdClass();
        $options->area = 'submission_gradereviews';
        $options->course = $assign->get_course();
        $options->context = $context;
        $options->itemid = $submission->id;
        $options->component = 'assignsubmission_gradereviews';
        $options->showcount = true;
        $options->displaycancel = true;

        $comment = new \comment($options);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $this->setUser($manager);
        $comment->add('New gradereview');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $sink->close();

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\assignsubmission_gradereviews\event\gradereview_created', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/assign/view.php', array('id' => $assign->get_course_module()->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test comment_deleted event.
     */
    public function test_comment_deleted() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $manager = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($manager);
        $assign = $this->create_instance($course);

        $submission = $assign->get_user_submission($student->id, true);

        $context = $assign->get_context();
        $options = new \stdClass();
        $options->area    = 'submission_gradereviews';
        $options->course    = $assign->get_course();
        $options->context = $context;
        $options->itemid  = $submission->id;
        $options->component = 'assignsubmission_gradereviews';
        $options->showcount = true;
        $options->displaycancel = true;
        $gradereview = new \comment($options);
        $this->setUser($manager);
        $newgradereview = $gradereview->add('New comment 1');

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $gradereview->delete($newgradereview->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\assignsubmission_gradereviews\event\gradereview_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/assign/view.php', array('id' => $assign->get_course_module()->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }
}
