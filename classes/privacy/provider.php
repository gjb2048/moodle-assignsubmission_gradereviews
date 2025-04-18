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
 * Privacy API.
 *
 * @package    assignsubmission_gradereviews
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @copyright  2018 Church of England
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_gradereviews\privacy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadataprovider;
use core_comment\privacy\provider as comments_provider;
use core_privacy\local\request\contextlist;
use mod_assign\privacy\assign_plugin_request_data;

/**
 * Privacy class for requesting user data.
 *
 * @package    assignsubmission_gradereviews
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @copyright  2018 Church of England
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadataprovider,
        \mod_assign\privacy\assignsubmission_provider,
        \mod_assign\privacy\assignsubmission_user_provider {

    /**
     * Return meta data about this plugin.
     *
     * @param  collection $collection A list of information to add to.
     * @return collection Return the collection after adding to it.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->link_subsystem('core_comment', 'privacy:metadata:commentpurpose');
        return $collection;
    }

    /**
     * It is possible to make a comment as a teacher without creating an entry in the submission table, so this is required
     * to find those entries.
     *
     * @param  int $userid The user ID that we are finding contexts for.
     * @param  contextlist $contextlist A context list to add sql and params to for contexts.
     */
    public static function get_context_for_userid_within_submission(int $userid, contextlist $contextlist) {
        $sql = "SELECT contextid
                  FROM {comments}
                 WHERE component = :component
                       AND commentarea = :commentarea
                       AND userid = :userid";
        $params = ['userid' => $userid, 'component' => 'assignsubmission_gradereviews', 'commentarea' => 'submission_gradereviews'];
        $contextlist->add_from_sql($sql, $params);
        // No need to add the contexts where the student is the person commented about, because
        // students must have a submisison for comments to be enabled, and submissions context
        // are already taken care of by the assign provider.
    }

    /**
     * Due to the fact that we can't rely on the queries in the mod_assign provider we have to add some additional sql.
     *
     * @param  \mod_assign\privacy\useridlist $useridlist An object for obtaining user IDs of students.
     */
    public static function get_student_user_ids(\mod_assign\privacy\useridlist $useridlist) {
        $params = [
            'assignid' => $useridlist->get_assignid(),
            'teacherid' => $useridlist->get_teacherid(),
            'component' => 'assignsubmission_gradereviews',
            'commentarea' => 'submission_gradereviews',
        ];
        $sql = "SELECT DISTINCT asub.userid AS id
                  FROM {assign_submission} asub
                  JOIN {comments} c
                    ON c.itemid = asub.id
                   AND c.component = :component
                   AND c.commentarea = :commentarea
                 WHERE c.userid = :teacherid
                   AND asub.assignment = :assignid";
        $useridlist->add_from_sql($sql, $params);
    }

    /**
     * If you have tables that contain userids and you can generate entries in your tables without creating an
     * entry in the assign_submission table then please fill in this method.
     *
     * @param  \core_privacy\local\request\userlist $userlist The userlist object
     */
    public static function get_userids_from_context(\core_privacy\local\request\userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        comments_provider::get_users_in_context_from_sql($userlist, 'c', 'assignsubmission_gradereviews', 'submission_gradereviews',
                $context->id);
    }

    /**
     * Export all user data for this plugin.
     *
     * TODO: Should the user see these comments about them from their educators?
     *
     * @param  assign_plugin_request_data $exportdata Data used to determine which context and user to export and other useful
     * information to help with exporting.
     */
    public static function export_submission_user_data(assign_plugin_request_data $exportdata) {
        $component = 'assignsubmission_gradereviews';
        $commentarea = 'submission_gradereviews';

        /* When a user is passed, that is because we're exporting the reviewer's data, and in this
           case we will only export the comments made by this person. If the user isn't provided,
           we need to export all comments made about the submission, because it was requested by
           the author of the submission. */
        $userid = ($exportdata->get_user() != null);
        $submission = $exportdata->get_pluginobject();

        // For the moment we are only showing the comments made by this user.
        comments_provider::export_comments($exportdata->get_context(), $component, $commentarea, $submission->id,
                $exportdata->get_subcontext(), $userid);
    }

    /**
     * Delete all the comments made for this context.
     *
     * @param  assign_plugin_request_data $requestdata Data to fulfill the deletion request.
     */
    public static function delete_submission_for_context(assign_plugin_request_data $requestdata) {
        comments_provider::delete_comments_for_all_users(
            $requestdata->get_context(),
            'assignsubmission_gradereviews',
            'submission_gradereviews'
        );
    }

    /**
     * A call to this method should delete user data (where practical) using the userid and submission.
     *
     * @param  assign_plugin_request_data $exportdata Details about the user and context to focus the deletion.
     */
    public static function delete_submission_for_userid(assign_plugin_request_data $exportdata) {
        // Create an approved context list to delete the comments.
        $contextlist = new \core_privacy\local\request\approved_contextlist($exportdata->get_user(), 'assignsubmission_gradereviews',
            [$exportdata->get_context()->id]);
        comments_provider::delete_comments_for_user($contextlist, 'assignsubmission_gradereviews', 'submission_gradereviews');
    }

    /**
     * Deletes all submissions for the submission ids / userids provided in a context.
     * assign_plugin_request_data contains:
     * - context
     * - assign object
     * - submission ids (pluginids)
     * - user ids
     * @param  assign_plugin_request_data $deletedata A class that contains the relevant information required for deletion.
     */
    public static function delete_submissions(assign_plugin_request_data $deletedata) {
        $userlist = new \core_privacy\local\request\approved_userlist($deletedata->get_context(), 'assignsubmission_comments',
                $deletedata->get_userids());
        comments_provider::delete_comments_for_users($userlist, 'assignsubmission_gradereviews', 'submission_gradereviews');
    }

}
