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
 * Backup structure for Integrity Quiz.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Backup structure step for Integrity Quiz.
 */
class backup_integrityquiz_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the activity backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $integrityquiz = new backup_nested_element('integrityquiz', ['id'], [
            'name', 'intro', 'introformat', 'timeopen', 'timeclose', 'timelimit', 'questiontimelimit',
            'attemptsallowed', 'questionsperpage', 'grade', 'integritypolicy', 'requirecamera',
            'requirefullscreen', 'suspendontabswitch', 'snapshotmin', 'snapshotmax', 'retentiondays',
            'timecreated', 'timemodified',
        ]);
        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'qtype', 'questiontext', 'optionsjson', 'correctjson', 'feedback', 'marks', 'sortorder', 'timecreated',
        ]);
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid', 'attemptno', 'state', 'timestart', 'timefinish', 'timesuspended', 'lastheartbeat', 'pageid',
            'violations', 'rawscore', 'grade', 'timemodified',
        ]);
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], [
            'questionid', 'responsejson', 'fraction', 'mark', 'timeopened', 'timeexpired', 'timemodified',
        ]);
        $events = new backup_nested_element('events');
        $event = new backup_nested_element('event', ['id'], [
            'eventtype', 'severity', 'detail', 'clienttime', 'servertime', 'prevhash', 'eventhash',
        ]);
        $reviews = new backup_nested_element('reviews');
        $review = new backup_nested_element('review', ['id'], [
            'reviewerid', 'action', 'oldstate', 'newstate', 'note', 'timecreated',
        ]);

        $integrityquiz->add_child($questions);
        $questions->add_child($question);
        $integrityquiz->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($responses);
        $responses->add_child($response);
        $attempt->add_child($events);
        $events->add_child($event);
        $attempt->add_child($reviews);
        $reviews->add_child($review);

        $integrityquiz->set_source_table('integrityquiz', ['id' => backup::VAR_ACTIVITYID]);
        $question->set_source_table('integrityquiz_questions', ['integrityquizid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $attempt->set_source_table('integrityquiz_attempts', ['integrityquizid' => backup::VAR_PARENTID]);
            $response->set_source_table('integrityquiz_responses', ['attemptid' => backup::VAR_PARENTID]);
            $event->set_source_table('integrityquiz_events', ['attemptid' => backup::VAR_PARENTID]);
            $review->set_source_table('integrityquiz_reviews', ['attemptid' => backup::VAR_PARENTID]);
            $attempt->annotate_ids('user', 'userid');
            $review->annotate_ids('user', 'reviewerid');
            $attempt->annotate_files('mod_integrityquiz', 'evidence', 'id');
        }

        $integrityquiz->annotate_files('mod_integrityquiz', 'intro', null);
        return $this->prepare_activity_structure($integrityquiz);
    }
}
