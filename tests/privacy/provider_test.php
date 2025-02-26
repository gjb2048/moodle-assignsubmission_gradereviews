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

namespace assignsubmission_gradereviews\privacy;

use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use mod_assign\privacy\assign_plugin_request_data;
use mod_assign\privacy\useridlist;

/**
 * Unit tests for mod/assign/submission/comments/classes/privacy/
 *
 * @package    assignsubmission_gradereviews
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @copyright  2025 Church of England
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \assignsubmission_comments\privacy\provider
 */
final class provider_test extends \mod_assign\tests\provider_testcase {

    /**
     * Convenience function for creating feedback data.
     *
     * @param  object   $assign         assign object
     * @param  stdClass $student        user object
     * @param  string   $submissiontext Submission text
     * @return array   Submission plugin object and the submission object and the comment object.
     */
    protected function create_gradereview_submission($assign, $student, $submissiontext) {

        $submission = $assign->get_user_submission($student->id, true);

        $plugin = $assign->get_submission_plugin_by_type('comments');

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
        $comment->set_post_permission(true);

        $this->setUser($student);

        $comment->add($submissiontext);

        return [$plugin, $submission, $comment];
    }

    /**
     * Convenience method for creating comments.
     *
     * Note, you must set the current user prior to calling this.
     *
     * @param assign $assign The assignment.
     * @param object $submission The submission.
     * @param string $message The message.
     * @return array With plugin, submission and comment.
     */
    protected function create_comment($assign, $submission, $message) {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        $plugin = $assign->get_submission_plugin_by_type('comments');

        $options = new \stdClass();
        $options->area = 'submission_gradereviews';
        $options->course = $assign->get_course();
        $options->context = $assign->get_context();
        $options->itemid = $submission->id;
        $options->component = 'assignsubmission_gradereviews';
        $options->showcount = true;
        $options->displaycancel = true;

        $comment = new \comment($options);
        $comment->set_post_permission(true);
        $comment->add($message);

        return $comment;
    }

    /**
     * Quick test to make sure that get_metadata returns something.
     */
    public function test_get_metadata() {
        $collection = new \core_privacy\local\metadata\collection('assignsubmission_gradereviews');
        $collection = \assignsubmission_gradereviews\privacy\provider::get_metadata($collection);
        $this->assertNotEmpty($collection);
    }

    /**
     * Test returning the context for a user who has made a comment in an assignment.
     */
    public function test_get_context_for_userid_within_submission() {
        $this->resetAfterTest();
        // Create course, assignment, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        // Manager.
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'manager');
        $this->setUser($user2);
        $assign = $this->create_instance(['course' => $course]);

        $context = $assign->get_context();

        $studentcomment = 'Comment from user 1';
        list($plugin, $submission, $comment) = $this->create_gradereview_submission($assign, $user1, $studentcomment);
        $managercomment = 'From the manager';
        $this->setUser($user2);
        $comment->add($managercomment);

        $contextlist = new \core_privacy\local\request\contextlist();
        \assignsubmission_gradereviews\privacy\provider::get_context_for_userid_within_submission($user2->id, $contextlist);
        $this->assertEquals($context->id, $contextlist->get_contextids()[0]);
    }

    /**
     * Test returning the context for a user who has made a comment in an assignment.
     */
    public function test_get_context_for_userid_within_submission_two() {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        $c1 = $dg->create_course();
        $c2 = $dg->create_course();

        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();
        $u5 = $dg->create_user();

        $dg->enrol_user($u1->id, $c1->id, 'student');
        $dg->enrol_user($u1->id, $c2->id, 'student');
        $dg->enrol_user($u2->id, $c1->id, 'student');
        $dg->enrol_user($u3->id, $c1->id, 'editingteacher');
        $dg->enrol_user($u3->id, $c2->id, 'editingteacher');
        $dg->enrol_user($u4->id, $c1->id, 'editingteacher');
        $dg->enrol_user($u4->id, $c2->id, 'editingteacher');
        $dg->enrol_user($u5->id, $c2->id, 'editingteacher');

        $this->setAdminUser();

        $assign1 = $this->create_instance(['course' => $c1]);
        $assign2 = $this->create_instance(['course' => $c1]);
        $assign3 = $this->create_instance(['course' => $c2]);

        $sub1a = $this->create_submission($assign1, $u1, 'Abc');
        $sub1b = $this->create_submission($assign1, $u2, 'Abc');
        $sub2a = $this->create_submission($assign2, $u1, 'Abc');
        $sub3a = $this->create_submission($assign3, $u1, 'Abc');

        $this->setUser($u3);
        $this->create_comment($assign1, $sub1a, 'Test 1');
        $this->create_comment($assign3, $sub3a, 'Test 2');

        $this->setUser($u4);
        $this->create_comment($assign1, $sub1a, 'Test 3');
        $this->create_comment($assign1, $sub1b, 'Test 4 on u2');

        // User 1 has a submission in each assignment, but no comments.
        $this->setUser($u1);
        $contextlist = new contextlist();
        provider::get_context_for_userid_within_submission($u1->id, $contextlist);
        $contextids = $contextlist->get_contextids();
        $this->assertCount(0, $contextids);

        // User 2 has a submission in one assignment, but no comments.
        $this->setUser($u2);
        $contextlist = new contextlist();
        provider::get_context_for_userid_within_submission($u2->id, $contextlist);
        $contextids = $contextlist->get_contextids();
        $this->assertCount(0, $contextids);

        // User 3 has commented, in two assignments.
        $this->setUser($u3);
        $contextlist = new contextlist();
        provider::get_context_for_userid_within_submission($u3->id, $contextlist);
        $contextids = $contextlist->get_contextids();
        $this->assertCount(2, $contextids);
        $this->assertContains((string) $assign1->get_context()->id, $contextids);
        $this->assertContains((string) $assign3->get_context()->id, $contextids);

        // User 4 has commented, in one assignment.
        $this->setUser($u4);
        $contextlist = new contextlist();
        provider::get_context_for_userid_within_submission($u4->id, $contextlist);
        $contextids = $contextlist->get_contextids();
        $this->assertCount(1, $contextids);
        $this->assertContains((string) $assign1->get_context()->id, $contextids);

        // User 5 did not comment.
        $this->setUser($u5);
        $contextlist = new contextlist();
        provider::get_context_for_userid_within_submission($u5->id, $contextlist);
        $contextids = $contextlist->get_contextids();
        $this->assertCount(0, $contextids);
    }

    /**
     * Test returning student ids given a user ID.
     */
    public function test_get_student_user_ids() {
        $this->resetAfterTest();
        // Create course, assignment, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        // Manager.
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'manager');
        $this->setUser($user2);
        $assign = $this->create_instance(['course' => $course]);

        $context = $assign->get_context();

        $studentcomment = 'Comment from user 1';
        list($plugin, $submission, $comment) = $this->create_gradereview_submission($assign, $user1, $studentcomment);
        $managercomment = 'From the manager';
        $this->setUser($user2);
        $comment->add($managercomment);

        $useridlist = new useridlist($user2->id, $assign->get_instance()->id);
        \assignsubmission_gradereviews\privacy\provider::get_student_user_ids($useridlist);
        $this->assertEquals($user1->id, $useridlist->get_userids()[0]->id);
    }

    /**
     * Test get student user IDs.
     */
    public function test_get_student_user_ids_orig() {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        $c1 = $dg->create_course();
        $c2 = $dg->create_course();

        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();
        $u5 = $dg->create_user();

        $dg->enrol_user($u1->id, $c1->id, 'student');
        $dg->enrol_user($u1->id, $c2->id, 'student');
        $dg->enrol_user($u2->id, $c1->id, 'student');
        $dg->enrol_user($u3->id, $c1->id, 'editingteacher');
        $dg->enrol_user($u3->id, $c2->id, 'editingteacher');
        $dg->enrol_user($u4->id, $c1->id, 'editingteacher');
        $dg->enrol_user($u4->id, $c2->id, 'editingteacher');
        $dg->enrol_user($u5->id, $c2->id, 'editingteacher');

        $this->setAdminUser();

        $assign1 = $this->create_instance(['course' => $c1]);
        $assign2 = $this->create_instance(['course' => $c1]);
        $assign3 = $this->create_instance(['course' => $c2]);

        $sub1a = $this->create_submission($assign1, $u1, 'Abc');
        $sub1b = $this->create_submission($assign1, $u2, 'Abc');
        $sub2a = $this->create_submission($assign2, $u1, 'Abc');
        $sub3a = $this->create_submission($assign3, $u1, 'Abc');

        $this->setUser($u3);
        $this->create_comment($assign1, $sub1a, 'Test 1');
        $this->create_comment($assign3, $sub3a, 'Test 2');

        $this->setUser($u4);
        $this->create_comment($assign1, $sub1a, 'Test 3');
        $this->create_comment($assign1, $sub1b, 'Test 4 on u2');

        // User 1 and 2 are students, and could not comment.
        $this->assert_student_user_ids($assign1, $u1, []);
        $this->assert_student_user_ids($assign2, $u1, []);
        $this->assert_student_user_ids($assign3, $u1, []);
        $this->assert_student_user_ids($assign1, $u2, []);
        $this->assert_student_user_ids($assign2, $u2, []);
        $this->assert_student_user_ids($assign3, $u2, []);

        // User 3 commented for 1 user on two assignments.
        $this->assert_student_user_ids($assign1, $u3, [$u1->id]);
        $this->assert_student_user_ids($assign2, $u3, []);
        $this->assert_student_user_ids($assign3, $u3, [$u1->id]);

        // User 4 commented for 2 users on one assignment.
        $this->assert_student_user_ids($assign1, $u4, [$u1->id, $u2->id]);
        $this->assert_student_user_ids($assign2, $u4, []);
        $this->assert_student_user_ids($assign3, $u4, []);

        // User 5 is a slacker, and did nothing!
        $this->assert_student_user_ids($assign1, $u5, []);
        $this->assert_student_user_ids($assign2, $u5, []);
        $this->assert_student_user_ids($assign3, $u5, []);
    }

    /**
     * Test exporting data.
     */
    public function test_export_submission_user_data() {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $c1 = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();

        $dg->enrol_user($u1->id, $c1->id, 'student');
        $dg->enrol_user($u2->id, $c1->id, 'student');
        $dg->enrol_user($u3->id, $c1->id, 'editingteacher');
        $dg->enrol_user($u4->id, $c1->id, 'editingteacher');

        $this->setAdminUser();

        $assign1 = $this->create_instance(['course' => $c1]);
        $assign2 = $this->create_instance(['course' => $c1]);

        $sub1a = $this->create_submission($assign1, $u1, 'Abc');
        $sub1b = $this->create_submission($assign1, $u2, 'Abc');
        $sub2a = $this->create_submission($assign2, $u1, 'Abc');

        $this->setUser($u3);
        $this->create_comment($assign1, $sub1a, 'Test 1');
        $this->create_comment($assign1, $sub1a, 'Test 1b');
        $this->create_comment($assign2, $sub2a, 'Test 2');

        $this->setUser($u4);
        $this->create_comment($assign1, $sub1a, 'Test 3');
        $this->create_comment($assign1, $sub1b, 'Test 4 on u2');

        // Check export all in context, typically if a user exported their content.
        $this->assert_export_comments($assign1, $sub1a, null, [[$u4, 'Test 3'], [$u3, 'Test 1b'], [$u3, 'Test 1']]);
        $this->assert_export_comments($assign1, $sub1b, null, [[$u4, 'Test 4 on u2']]);
        $this->assert_export_comments($assign2, $sub2a, null, [[$u3, 'Test 2']]);

        // Check export if user 1 was a teacher.
        $this->assert_export_comments($assign1, $sub1a, $u1, []);
        $this->assert_export_comments($assign1, $sub1b, $u1, []);
        $this->assert_export_comments($assign2, $sub2a, $u1, []);

        // Check export if user 2 was a teacher.
        $this->assert_export_comments($assign1, $sub1a, $u2, []);
        $this->assert_export_comments($assign1, $sub1b, $u2, []);
        $this->assert_export_comments($assign2, $sub2a, $u2, []);

        // Check export with user 3 as teacher.
        $this->assert_export_comments($assign1, $sub1a, $u3, [[$u3, 'Test 1b'], [$u3, 'Test 1']]);
        $this->assert_export_comments($assign1, $sub1b, $u3, []);
        $this->assert_export_comments($assign2, $sub2a, $u3, [[$u3, 'Test 2']]);

        // Check export with user 4 as teacher.
        $this->assert_export_comments($assign1, $sub1a, $u4, [[$u4, 'Test 3']]);
        $this->assert_export_comments($assign1, $sub1b, $u4, [[$u4, 'Test 4 on u2']]);
        $this->assert_export_comments($assign2, $sub2a, $u4, []);
    }

    /**
     * Test deleting submission for context.
     */
    public function test_delete_submission_for_context() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $c1 = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();

        $dg->enrol_user($u1->id, $c1->id, 'student');
        $dg->enrol_user($u2->id, $c1->id, 'student');
        $dg->enrol_user($u3->id, $c1->id, 'editingteacher');
        $dg->enrol_user($u4->id, $c1->id, 'editingteacher');

        $this->setAdminUser();

        $assign1 = $this->create_instance(['course' => $c1]);
        $assign2 = $this->create_instance(['course' => $c1]);

        $sub1a = $this->create_submission($assign1, $u1, 'Abc');
        $sub1b = $this->create_submission($assign1, $u2, 'Abc');
        $sub2a = $this->create_submission($assign2, $u1, 'Abc');

        $this->setUser($u3);
        $this->create_comment($assign1, $sub1a, 'Test 1');
        $this->create_comment($assign1, $sub1a, 'Test 1b');
        $this->create_comment($assign2, $sub2a, 'Test 2');

        $this->setUser($u4);
        $this->create_comment($assign1, $sub1a, 'Test 3');
        $this->create_comment($assign1, $sub1b, 'Test 4 on u2');

        $this->assertEquals(4, $DB->count_records('comments', ['contextid' => $assign1->get_context()->id]));
        $this->assertEquals(1, $DB->count_records('comments', ['contextid' => $assign2->get_context()->id]));

        $this->setGuestUser();
        $requestdata = new assign_plugin_request_data($assign1->get_context(), $assign1);
        \assignsubmission_gradereviews\privacy\provider::delete_submission_for_context($requestdata);
        $this->assertEquals(0, $DB->count_records('comments', ['contextid' => $assign1->get_context()->id]));
        $this->assertEquals(1, $DB->count_records('comments', ['contextid' => $assign2->get_context()->id]));
    }

    /**
     * Test deleting submission for user ID.
     */
    public function test_delete_submission_for_userid() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $c1 = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();

        $dg->enrol_user($u1->id, $c1->id, 'student');
        $dg->enrol_user($u2->id, $c1->id, 'student');
        $dg->enrol_user($u3->id, $c1->id, 'editingteacher');
        $dg->enrol_user($u4->id, $c1->id, 'editingteacher');

        $this->setAdminUser();

        $assign1 = $this->create_instance(['course' => $c1]);
        $assign2 = $this->create_instance(['course' => $c1]);
        $a1ctx = $assign1->get_context();
        $a2ctx = $assign2->get_context();

        $sub1a = $this->create_submission($assign1, $u1, 'Abc');
        $sub1b = $this->create_submission($assign1, $u2, 'Abc');
        $sub2a = $this->create_submission($assign2, $u1, 'Abc');

        $this->setUser($u3);
        $this->create_comment($assign1, $sub1a, 'Test 1');
        $this->create_comment($assign1, $sub1a, 'Test 1b');
        $this->create_comment($assign2, $sub2a, 'Test 2');

        $this->setUser($u4);
        $this->create_comment($assign1, $sub1a, 'Test 3');
        $this->create_comment($assign1, $sub1b, 'Test 4 on u2');

        $this->assertEquals(3, $DB->count_records('comments', ['contextid' => $a1ctx->id, 'itemid' => $sub1a->id]));
        $this->assertEquals(1, $DB->count_records('comments', ['contextid' => $a1ctx->id, 'itemid' => $sub1b->id]));
        $this->assertEquals(1, $DB->count_records('comments', ['contextid' => $a2ctx->id, 'itemid' => $sub2a->id]));

        $this->setGuestUser();

        $requestdata = new assign_plugin_request_data($a1ctx, $assign1, $sub1a, [], $u3);
        \assignsubmission_gradereviews\privacy\provider::delete_submission_for_userid($requestdata);
        $this->assertEquals(1, $DB->count_records('comments', ['contextid' => $a1ctx->id, 'itemid' => $sub1a->id]));
        $this->assertEquals(1, $DB->count_records('comments', ['contextid' => $a1ctx->id, 'itemid' => $sub1b->id]));
        $this->assertEquals(1, $DB->count_records('comments', ['contextid' => $a2ctx->id, 'itemid' => $sub2a->id]));

        $requestdata = new assign_plugin_request_data($a2ctx, $assign2, $sub2a, [], $u3);
        \assignsubmission_gradereviews\privacy\provider::delete_submission_for_userid($requestdata);
        $this->assertEquals(1, $DB->count_records('comments', ['contextid' => $a1ctx->id, 'itemid' => $sub1a->id]));
        $this->assertEquals(1, $DB->count_records('comments', ['contextid' => $a1ctx->id, 'itemid' => $sub1b->id]));
        $this->assertEquals(0, $DB->count_records('comments', ['contextid' => $a2ctx->id, 'itemid' => $sub2a->id]));
    }

    /**
     * Assert the exported comments.
     *
     * @param object $assign The assignment.
     * @param object $submission The submission.
     * @param object|null $teacher The teacher to export for, e.g. the reviewer.
     * @param bool $asteacher Whether we export as a teacher.
     * @param array $expected Contains [[$user, 'Comment made'], ...]
     */
    protected function assert_export_comments($assign, $submission, $teacher, $expected) {
        if (!empty($teacher)) {
            // We need to ensure that the current user is the teacher.
            $this->setUser($teacher);
        } else {
            // It shouldn't matter which user we are, but just to make sure we set the guest one.
            $this->setGuestUser();
        }
        writer::reset();

        $context = $assign->get_context();
        $requestdata = new assign_plugin_request_data($context, $assign, $submission, [], $teacher ? $teacher : null);
        \assignsubmission_gradereviews\privacy\provider::export_submission_user_data($requestdata);
        $stuff = writer::with_context($context)->get_data([get_string('commentsubcontext', 'core_comment')]);
        $comments = !empty($stuff) ? $stuff->comments : [];

        $this->assertCount(count($expected), $comments);

        // The order of the comments is random.
        foreach ($comments as $i => $comment) {
            $found = false;
            foreach ($expected as $key => $data) {
                $found = $comment->userid == $data[0]->id && strip_tags($comment->content) == $data[1];
                if ($found) {
                    unset($expected[$key]);
                    break;
                }
            }
            $this->assertTrue($found);
        }
        $this->assertEmpty($expected);
    }

    /**
     * Convenience method to assert the result of 'get_user_student_ids'.
     *
     * @param object $assign The assignment.
     * @param object $user The user.
     * @param array $expectedids The expected IDs.
     */
    protected function assert_student_user_ids($assign, $user, $expectedids) {
        $this->setUser($user);

        $useridlist = new useridlist($user->id, $assign->get_instance()->id);
        \assignsubmission_gradereviews\privacy\provider::get_student_user_ids($useridlist);
        $userids = $useridlist->get_userids();

        $this->assertCount(count($expectedids), $userids);
        foreach ($expectedids as $id) {
            $this->assertContains($id, $expectedids);
        }
    }
}
