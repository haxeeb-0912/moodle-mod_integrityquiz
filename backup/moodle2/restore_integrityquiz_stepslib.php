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
 * Restore structure for Integrity Quiz.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Restore structure step for Integrity Quiz.
 */
class restore_integrityquiz_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define restore paths.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('integrityquiz', '/activity/integrityquiz'),
            new restore_path_element('integrityquiz_question', '/activity/integrityquiz/questions/question'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('integrityquiz_attempt', '/activity/integrityquiz/attempts/attempt');
            $paths[] = new restore_path_element('integrityquiz_response', '/activity/integrityquiz/attempts/attempt/responses/response');
            $paths[] = new restore_path_element('integrityquiz_event', '/activity/integrityquiz/attempts/attempt/events/event');
            $paths[] = new restore_path_element('integrityquiz_review', '/activity/integrityquiz/attempts/attempt/reviews/review');
        }
        return $this->prepare_activity_structure($paths);
    }

    /** @param array $data Activity data. */
    protected function process_integrityquiz($data): void {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->questionsperpage = $data->questionsperpage ?? 1;
        $data->questiontimelimit = $data->questiontimelimit ?? 0;
        $data->requirefullscreen = 0;
        $newid = $DB->insert_record('integrityquiz', $data);
        $this->apply_activity_instance($newid);
    }

    /** @param array $data Question data. */
    protected function process_integrityquiz_question($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->integrityquizid = $this->get_new_parentid('integrityquiz');
        $newid = $DB->insert_record('integrityquiz_questions', $data);
        $this->set_mapping('integrityquiz_question', $oldid, $newid);
    }

    /** @param array $data Attempt data. */
    protected function process_integrityquiz_attempt($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->integrityquizid = $this->get_new_parentid('integrityquiz');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $newid = $DB->insert_record('integrityquiz_attempts', $data);
        $this->set_mapping('integrityquiz_attempt', $oldid, $newid, true);
    }

    /** @param array $data Response data. */
    protected function process_integrityquiz_response($data): void {
        global $DB;
        $data = (object)$data;
        $data->attemptid = $this->get_new_parentid('integrityquiz_attempt');
        $data->questionid = $this->get_mappingid('integrityquiz_question', $data->questionid);
        $DB->insert_record('integrityquiz_responses', $data);
    }

    /** @param array $data Event data. */
    protected function process_integrityquiz_event($data): void {
        global $DB;
        $data = (object)$data;
        $data->attemptid = $this->get_new_parentid('integrityquiz_attempt');
        $DB->insert_record('integrityquiz_events', $data);
    }

    /** @param array $data Review data. */
    protected function process_integrityquiz_review($data): void {
        global $DB;
        $data = (object)$data;
        $data->attemptid = $this->get_new_parentid('integrityquiz_attempt');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid, 0);
        $DB->insert_record('integrityquiz_reviews', $data);
    }

    /** Restore related files. */
    protected function after_execute(): void {
        $this->add_related_files('mod_integrityquiz', 'intro', null);
        if ($this->get_setting_value('userinfo')) {
            $this->add_related_files('mod_integrityquiz', 'evidence', 'integrityquiz_attempt');
        }
    }
}
