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
 * This file contains the moodle hooks for the submission gradereviews plugin
 *
 * @package   assignsubmission_gradereviews
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 *
 * Callback method for data validation---- required method for AJAXmoodle based gradereview API
 *
 * @param stdClass $options
 * @return bool
 */
function assignsubmission_gradereviews_comment_validate(stdClass $options) {
    global $USER, $CFG, $DB;

    if ($options->commentarea != 'submission_gradereviews' &&
            $options->commentarea != 'submission_gradereviews_upgrade') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$submission = $DB->get_record('assign_submission', array('id' => $options->itemid))) {
        throw new comment_exception('invalidgradereviewitemid');
    }
    $context = $options->context;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    static $assignment = null;
    if (is_null($assignment) || $assignment->get_context() != $context) {
        $assignment = new assign($context, null, null);
    }

    if ($assignment->get_instance()->id != $submission->assignment) {
        throw new comment_exception('invalidcontext');
    }
    $canview = false;
    if ($submission->userid) {
        $canview = $assignment->can_view_submission($submission->userid);
    } else {
        $canview = $assignment->can_view_group_submission($submission->groupid);
    }
    if (!$canview) {
        throw new comment_exception('nopermissiontogradereview');
    }

    return true;
}

/**
 * Permission control method for submission plugin ---- required method for AJAXmoodle based gradereview API
 *
 * @param stdClass $options
 * @return array
 */
function assignsubmission_gradereviews_comment_permissions(stdClass $options) {
    global $USER, $CFG, $DB;

    if ($options->commentarea != 'submission_gradereviews' &&
            $options->commentarea != 'submission_gradereviews_upgrade') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$submission = $DB->get_record('assign_submission', array('id' => $options->itemid))) {
        throw new comment_exception('invalidgradereviewitemid');
    }
    $context = $options->context;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    static $assignment = null;
    if (is_null($assignment) || $assignment->get_context() != $context) {
        $assignment = new assign($context, null, null);
    }

    if ($assignment->get_instance()->id != $submission->assignment) {
        throw new comment_exception('invalidcontext');
    }

    if ($assignment->get_instance()->teamsubmission &&
        !$assignment->can_view_group_submission($submission->groupid)) {
        return array('post' => false, 'view' => false);
    }

    if (!$assignment->get_instance()->teamsubmission &&
        !$assignment->can_view_submission($submission->userid)) {
        return array('post' => false, 'view' => false);
    }

    return array('post' => true, 'view' => true);
}

/**
 * Callback called by gradereview::get_gradereviews() and gradereview::add(). Gives an opportunity to enforce blind-marking.
 *
 * @param array $gradereviews
 * @param stdClass $options
 * @return array
 * @throws comment_exception
 */
function assignsubmission_gradereviews_comment_display($gradereviews, $options) {
    global $CFG, $DB, $USER, $COURSE;

    if ($options->commentarea != 'submission_gradereviews' &&
        $options->commentarea != 'submission_gradereviews_upgrade') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$submission = $DB->get_record('assign_submission', array('id' => $options->itemid))) {
        throw new comment_exception('invalidgradereviewitemid');
    }
    $context = $options->context;
    $cm = $options->cm;
    $course = $options->courseid;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    $assignment = new assign($context, $cm, $course);

    if ($assignment->get_instance()->id != $submission->assignment) {
        throw new comment_exception('invalidcontext');
    }

    if ($assignment->is_blind_marking() && !empty($gradereviews)) {
        // Blind marking is being used, may need to map unique anonymous ids to the comments.
        $usermappings = [];
        $guestuser = guest_user();

        // Check group users first.
        $userinteam = false;
        if ($assignment->get_instance()->teamsubmission && has_capability('mod/assign:submit', $context)) {
            $assignment->set_course(get_course($course));
            $userinteam = $assignment->can_edit_group_submission($submission->groupid);
        }

        foreach ($gradereviews as $gradereview) {

            if (has_capability('mod/assign:viewblinddetails', $context) && $USER->id != $gradereview->userid) {
                $anonid = $assignment->get_uniqueid_for_user($gradereview->userid);
                // Show participant information and the user's full name to users with the view blind details capability.
                $a = new stdClass();
                $a->participantnumber = $anonid;
                $a->participantfullname = $gradereview->fullname;
                $gradereview->fullname = get_string('blindmarkingviewfullname', 'assignsubmission_gradereviews', $a);
            } else if ($USER->id == $gradereview->userid || $submission->userid == $USER->id || $userinteam) { // phpcs:ignore
                // Do not anonymize the user details for this gradereview.
            } else {
                // Anonymize the comments.
                if (empty($usermappings[$gradereview->userid])) {
                    $anonid = $assignment->get_uniqueid_for_user($gradereview->userid);
                    // The blind-marking information for this gradereviewer has not been generated; do so now.
                    $gradereviewer = new stdClass();
                    $gradereviewer->firstname = get_string('blindmarkingname', 'assignsubmission_gradereviews', $anonid);
                    $gradereviewer->lastname = '';
                    $gradereviewer->firstnamephonetic = '';
                    $gradereviewer->lastnamephonetic = '';
                    $gradereviewer->middlename = '';
                    $gradereviewer->alternatename = '';
                    $gradereviewer->picture = 0;
                    $gradereviewer->id = $guestuser->id;
                    $gradereviewer->email = $guestuser->email;
                    $gradereviewer->imagealt = $guestuser->imagealt;

                    // Temporarily store blind-marking information for use in later comments if necessary.
                    $usermappings[$gradereview->userid] = new stdClass();
                    $usermappings[$gradereview->userid]->fullname = fullname($gradereviewer);
                    $usermappings[$gradereview->userid]->avatar = $assignment->get_renderer()->user_picture($gradereviewer,
                            array('size' => 18, 'link' => false));
                }

                // Commenting these three next lines, the grade reviewer name should not be hidden even in blind marking.
                // Set blind-marking information for this comment.
                //$gradereview->fullname = $usermappings[$comment->userid]->fullname;
                //$gradereview->avatar = $usermappings[$comment->userid]->avatar;
                //$gradereview->profileurl = null;
            }
        }
    }

    // Do not display delete option if the user is not the creator.
    foreach ($gradereviews as &$gradereview) {
        if ($gradereview->userid != $USER->id) {
            // Check if the user is manager.
            if (!has_capability('assign/submission:caneditreviewgrade', context_user::instance($USER->id)) &&
                !has_capability('assign/submission:caneditreviewgrade', context_course::instance($COURSE->id))) {
                $gradereview->delete = 0;
            }
        }
    }

    return $gradereviews;
}

/**
 * Callback to force the userid for all gradereviews to be the userid of the submission and NOT the global $USER->id. This
 * is required by the upgrade code. Note the gradereview area is used to identify upgrades.
 *
 * @param stdClass $gradereview
 * @param stdClass $param
 */
function assignsubmission_gradereviews_comment_add(stdClass $gradereview, stdClass $param) {

    global $DB;
    if ($gradereview->commentarea == 'submission_gradereviews_upgrade') {
        $submissionid = $gradereview->itemid;
        $submission = $DB->get_record('assign_submission', array('id' => $submissionid));

        $gradereview->userid = $submission->userid;
        $gradereview->commentarea = 'submission_gradereviews';
    }
}

