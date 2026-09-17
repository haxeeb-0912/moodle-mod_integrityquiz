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
 * Privacy API provider for Integrity Quiz.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_integrityquiz\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for all personal data stored by Integrity Quiz.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe stored personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('integrityquiz_attempts', [
            'userid' => 'privacy:metadata:attempts:userid',
            'attemptno' => 'privacy:metadata:attempts:attemptno',
            'state' => 'privacy:metadata:attempts:state',
            'timestart' => 'privacy:metadata:attempts:timestart',
            'timefinish' => 'privacy:metadata:attempts:timefinish',
            'timesuspended' => 'privacy:metadata:attempts:timesuspended',
            'lastheartbeat' => 'privacy:metadata:attempts:lastheartbeat',
            'pageid' => 'privacy:metadata:attempts:pageid',
            'violations' => 'privacy:metadata:attempts:violations',
            'rawscore' => 'privacy:metadata:attempts:rawscore',
            'grade' => 'privacy:metadata:attempts:grade',
            'timemodified' => 'privacy:metadata:attempts:timemodified',
        ], 'privacy:metadata:attempts');
        $collection->add_database_table('integrityquiz_responses', [
            'questionid' => 'privacy:metadata:responses:questionid',
            'responsejson' => 'privacy:metadata:responses:response',
            'fraction' => 'privacy:metadata:responses:fraction',
            'mark' => 'privacy:metadata:responses:mark',
            'timeopened' => 'privacy:metadata:responses:timeopened',
            'timeexpired' => 'privacy:metadata:responses:timeexpired',
            'timemodified' => 'privacy:metadata:responses:timemodified',
        ], 'privacy:metadata:responses');
        $collection->add_database_table('integrityquiz_events', [
            'eventtype' => 'privacy:metadata:events:eventtype',
            'severity' => 'privacy:metadata:events:severity',
            'detail' => 'privacy:metadata:events:detail',
            'clienttime' => 'privacy:metadata:events:clienttime',
            'servertime' => 'privacy:metadata:events:servertime',
            'prevhash' => 'privacy:metadata:events:prevhash',
            'eventhash' => 'privacy:metadata:events:eventhash',
        ], 'privacy:metadata:events');
        $collection->add_database_table('integrityquiz_reviews', [
            'reviewerid' => 'privacy:metadata:reviews:reviewerid',
            'action' => 'privacy:metadata:reviews:action',
            'oldstate' => 'privacy:metadata:reviews:oldstate',
            'newstate' => 'privacy:metadata:reviews:newstate',
            'note' => 'privacy:metadata:reviews:note',
            'timecreated' => 'privacy:metadata:reviews:timecreated',
        ], 'privacy:metadata:reviews');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        $collection->add_subsystem_link('core_grades', [], 'privacy:metadata:gradebook');
        return $collection;
    }

    /**
     * Find module contexts containing personal data for a user.
     *
     * This includes users who own attempts and staff who authored review records.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm
                    ON cm.id = ctx.instanceid
                   AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {integrityquiz_attempts} a
                    ON a.integrityquizid = cm.instance
             LEFT JOIN {integrityquiz_reviews} r
                    ON r.attemptid = a.id
                 WHERE a.userid = :attemptuserid
                    OR r.reviewerid = :reviewerid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'integrityquiz',
            'attemptuserid' => $userid,
            'reviewerid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export personal data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('integrityquiz', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $attempts = $DB->get_records('integrityquiz_attempts', [
                'integrityquizid' => $cm->instance,
                'userid' => $userid,
            ], 'attemptno ASC');
            foreach ($attempts as $attempt) {
                $subcontext = [
                    get_string('pluginname', 'mod_integrityquiz'),
                    get_string('attemptnumber', 'mod_integrityquiz', $attempt->attemptno),
                ];
                $data = (object)[
                    'attemptno' => (int)$attempt->attemptno,
                    'state' => $attempt->state,
                    'timestart' => transform::datetime($attempt->timestart),
                    'timefinish' => $attempt->timefinish ? transform::datetime($attempt->timefinish) : null,
                    'timesuspended' => $attempt->timesuspended ? transform::datetime($attempt->timesuspended) : null,
                    'lastheartbeat' => $attempt->lastheartbeat ? transform::datetime($attempt->lastheartbeat) : null,
                    'pageid' => $attempt->pageid,
                    'violations' => (int)$attempt->violations,
                    'rawscore' => $attempt->rawscore,
                    'grade' => $attempt->grade,
                    'timemodified' => transform::datetime($attempt->timemodified),
                    'responses' => array_values($DB->get_records(
                        'integrityquiz_responses',
                        ['attemptid' => $attempt->id],
                        'id ASC'
                    )),
                    'events' => array_values($DB->get_records(
                        'integrityquiz_events',
                        ['attemptid' => $attempt->id],
                        'id ASC'
                    )),
                    'reviews' => array_values($DB->get_records(
                        'integrityquiz_reviews',
                        ['attemptid' => $attempt->id],
                        'id ASC'
                    )),
                ];
                writer::with_context($context)->export_data($subcontext, $data);
                writer::with_context($context)->export_area_files(
                    $subcontext,
                    'mod_integrityquiz',
                    'evidence',
                    $attempt->id
                );
            }

            // Review records authored by this user may concern another learner's attempt.
            $sql = "SELECT r.*, a.attemptno, a.userid AS attemptuserid
                      FROM {integrityquiz_reviews} r
                      JOIN {integrityquiz_attempts} a ON a.id = r.attemptid
                     WHERE a.integrityquizid = :quizid
                       AND r.reviewerid = :reviewerid
                  ORDER BY r.id ASC";
            $reviews = $DB->get_records_sql($sql, [
                'quizid' => $cm->instance,
                'reviewerid' => $userid,
            ]);
            if ($reviews) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'mod_integrityquiz'), get_string('privacy:reviewsauthored', 'mod_integrityquiz')],
                    (object)['reviews' => array_values($reviews)]
                );
            }
        }
    }

    /**
     * Delete all personal data in a module context.
     *
     * @param context $context Context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('integrityquiz', $context->instanceid);
        if (!$cm) {
            return;
        }
        $attemptids = $DB->get_fieldset_select(
            'integrityquiz_attempts',
            'id',
            'integrityquizid = ?',
            [$cm->instance]
        );
        self::delete_attempt_data($context, $attemptids);
        $DB->delete_records('integrityquiz_attempts', ['integrityquizid' => $cm->instance]);
    }

    /**
     * Delete personal data for one user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('integrityquiz', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_users_in_activity($context, $cm->instance, [$userid]);
        }
    }

    /**
     * Add all users with personal data in this module context.
     *
     * @param userlist $userlist User list.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('integrityquiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        $sql = "SELECT a.userid
                  FROM {integrityquiz_attempts} a
                 WHERE a.integrityquizid = :quizid1
                 UNION
                SELECT r.reviewerid AS userid
                  FROM {integrityquiz_reviews} r
                  JOIN {integrityquiz_attempts} a ON a.id = r.attemptid
                 WHERE a.integrityquizid = :quizid2";
        $userlist->add_from_sql('userid', $sql, [
            'quizid1' => $cm->instance,
            'quizid2' => $cm->instance,
        ]);
    }

    /**
     * Delete personal data for an approved list of users in a context.
     *
     * @param approved_userlist $userlist Approved user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('integrityquiz', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_users_in_activity($context, $cm->instance, $userlist->get_userids());
    }

    /**
     * Delete attempt-owned and reviewer-authored personal data for users.
     *
     * @param context $context Module context.
     * @param int $quizid Activity instance id.
     * @param int[] $userids User ids.
     */
    private static function delete_users_in_activity(context $context, int $quizid, array $userids): void {
        global $DB;

        if (!$userids) {
            return;
        }
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'privacyuser');

        // Delete review rows authored by these users, including reviews of other learners.
        $params = ['quizid' => $quizid] + $userparams;
        $reviewids = $DB->get_fieldset_sql(
            "SELECT r.id
               FROM {integrityquiz_reviews} r
               JOIN {integrityquiz_attempts} a ON a.id = r.attemptid
              WHERE a.integrityquizid = :quizid
                AND r.reviewerid $usersql",
            $params
        );
        if ($reviewids) {
            $DB->delete_records_list('integrityquiz_reviews', 'id', $reviewids);
        }

        $params = ['quizid2' => $quizid] + $userparams;
        $attemptids = $DB->get_fieldset_sql(
            "SELECT id
               FROM {integrityquiz_attempts}
              WHERE integrityquizid = :quizid2
                AND userid $usersql",
            $params
        );
        self::delete_attempt_data($context, $attemptids);
        if ($attemptids) {
            $DB->delete_records_list('integrityquiz_attempts', 'id', $attemptids);
        }
    }

    /**
     * Delete child records and evidence files for attempt ids.
     *
     * @param context $context Module context.
     * @param int[] $attemptids Attempt ids.
     */
    private static function delete_attempt_data(context $context, array $attemptids): void {
        global $DB;

        if (!$attemptids) {
            return;
        }
        $fs = get_file_storage();
        foreach ($attemptids as $attemptid) {
            $DB->delete_records('integrityquiz_responses', ['attemptid' => $attemptid]);
            $DB->delete_records('integrityquiz_events', ['attemptid' => $attemptid]);
            $DB->delete_records('integrityquiz_reviews', ['attemptid' => $attemptid]);
            $fs->delete_area_files($context->id, 'mod_integrityquiz', 'evidence', $attemptid);
        }
    }
}
