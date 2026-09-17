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
 * Assessment engine and integrity state helpers.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_integrityquiz\local;

defined('MOODLE_INTERNAL') || die();

class engine {
    public static function get_attempt(int $attemptid, int $userid) {
        global $DB;
        return $DB->get_record('integrityquiz_attempts', ['id' => $attemptid, 'userid' => $userid], '*', MUST_EXIST);
    }

    /**
     * Validate and normalise a response against the question's available options.
     *
     * @param object $question Question record.
     * @param mixed $response Submitted response.
     * @return mixed Normalised response.
     */
    public static function validate_response($question, $response) {
        $options = json_decode($question->optionsjson ?: '{}', true) ?: [];
        if ($question->qtype === 'multiselect') {
            if (!is_array($response)) {
                throw new \invalid_parameter_exception(get_string('invalidresponse', 'mod_integrityquiz'));
            }
            $response = array_values(array_unique(array_map('strval', $response)));
            foreach ($response as $key) {
                if (!array_key_exists($key, $options)) {
                    throw new \invalid_parameter_exception(get_string('invalidresponse', 'mod_integrityquiz'));
                }
            }
            return $response;
        }

        if (is_array($response)) {
            if (count($response) !== 1) {
                throw new \invalid_parameter_exception(get_string('invalidresponse', 'mod_integrityquiz'));
            }
            $response = reset($response);
        }
        $response = (string)$response;
        if (!array_key_exists($response, $options)) {
            throw new \invalid_parameter_exception(get_string('invalidresponse', 'mod_integrityquiz'));
        }
        return $response;
    }

    public static function mark_question($question, $response): array {
        $correct = json_decode($question->correctjson ?: '[]', true) ?: [];
        $given = is_array($response) ? array_values(array_map('strval', $response)) : [strval($response)];
        $correct = array_values(array_map('strval', $correct));
        sort($given);
        sort($correct);
        $fraction = ($given === $correct) ? 1.0 : 0.0;
        return [$fraction, $fraction * (float)$question->marks];
    }

    /**
     * Start or resume the server-authoritative timer for a question.
     *
     * The timer starts only the first time a student actually opens the question.
     * Returning to the question or refreshing the page does not reset it.
     *
     * @param object $attempt
     * @param object $question
     * @param object $quiz
     * @return array{remaining:int, expired:bool, deadline:int}
     */
    public static function start_question_timer($attempt, $question, $quiz): array {
        global $DB;

        $limit = max(0, (int)($quiz->questiontimelimit ?? 0));
        if ($limit <= 0) {
            return ['remaining' => 0, 'expired' => false, 'deadline' => 0];
        }

        if ($attempt->state !== 'active') {
            throw new \moodle_exception('attemptnotactive', 'mod_integrityquiz');
        }

        $now = time();
        $record = $DB->get_record('integrityquiz_responses', [
            'attemptid' => $attempt->id,
            'questionid' => $question->id,
        ]);

        if (!$record) {
            $record = (object)[
                'attemptid' => $attempt->id,
                'questionid' => $question->id,
                'responsejson' => null,
                'fraction' => 0,
                'mark' => 0,
                'timeopened' => $now,
                'timeexpired' => 0,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('integrityquiz_responses', $record);
        } else if (empty($record->timeopened)) {
            $record->timeopened = $now;
            $record->timemodified = $now;
            $DB->update_record('integrityquiz_responses', (object)[
                'id' => $record->id,
                'timeopened' => $record->timeopened,
                'timemodified' => $record->timemodified,
            ]);
        }

        $deadline = (int)$record->timeopened + $limit;
        $expired = !empty($record->timeexpired) || $now >= $deadline;

        if ($expired && empty($record->timeexpired)) {
            $record->timeexpired = $now;
            $record->timemodified = $now;
            $DB->update_record('integrityquiz_responses', (object)[
                'id' => $record->id,
                'timeexpired' => $record->timeexpired,
                'timemodified' => $record->timemodified,
            ]);
            self::log_event($attempt, 'questiontimeexpired', 'Question ' . $question->id . ' time expired');
        }

        return [
            'remaining' => $expired ? 0 : max(0, $deadline - $now),
            'expired' => $expired,
            'deadline' => $deadline,
        ];
    }

    public static function save_response($attempt, $question, $response): void {
        global $DB;
        if ($attempt->state !== 'active') {
            throw new \moodle_exception('attemptnotactive', 'mod_integrityquiz');
        }

        $quiz = $DB->get_record('integrityquiz', ['id' => $attempt->integrityquizid], '*', MUST_EXIST);
        $limit = max(0, (int)($quiz->questiontimelimit ?? 0));
        $record = $DB->get_record('integrityquiz_responses', [
            'attemptid' => $attempt->id,
            'questionid' => $question->id,
        ]);
        $now = time();

        if ($limit > 0) {
            if (!$record || empty($record->timeopened)) {
                $timer = self::start_question_timer($attempt, $question, $quiz);
                if ($timer['expired']) {
                    throw new \moodle_exception('questiontimeexpired', 'mod_integrityquiz');
                }
                $record = $DB->get_record('integrityquiz_responses', [
                    'attemptid' => $attempt->id,
                    'questionid' => $question->id,
                ], '*', MUST_EXIST);
            }

            $deadline = (int)$record->timeopened + $limit;
            if (!empty($record->timeexpired) || $now >= $deadline) {
                if (empty($record->timeexpired)) {
                    $DB->update_record('integrityquiz_responses', (object)[
                        'id' => $record->id,
                        'timeexpired' => $now,
                        'timemodified' => $now,
                    ]);
                }
                throw new \moodle_exception('questiontimeexpired', 'mod_integrityquiz');
            }
        }

        [$fraction, $mark] = self::mark_question($question, $response);
        $data = (object)[
            'attemptid' => $attempt->id,
            'questionid' => $question->id,
            'responsejson' => json_encode($response),
            'fraction' => $fraction,
            'mark' => $mark,
            'timemodified' => $now,
        ];
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('integrityquiz_responses', $data);
        } else {
            $data->timeopened = 0;
            $data->timeexpired = 0;
            $DB->insert_record('integrityquiz_responses', $data);
        }
    }

    public static function log_event($attempt, string $type, string $detail = '', int $clienttime = 0): string {
        global $DB;
        $last = $DB->get_record_sql(
            'SELECT * FROM {integrityquiz_events} WHERE attemptid = ? ORDER BY id DESC',
            [$attempt->id],
            IGNORE_MULTIPLE
        );
        $prevhash = $last ? $last->eventhash : '';
        $servertime = time();
        $severitymap = [
            'tabswitch' => 3,
            'cameraended' => 3,
            'duplicatepage' => 4,
            'questiontimeexpired' => 1,
            'snapshot' => 0,
            'heartbeat' => 0,
        ];
        $severity = $severitymap[$type] ?? 1;
        $payload = implode('|', [$attempt->id, $type, $detail, $clienttime, $servertime, $prevhash]);
        $hash = hash('sha256', $payload);
        $DB->insert_record('integrityquiz_events', (object)[
            'attemptid' => $attempt->id,
            'eventtype' => $type,
            'severity' => $severity,
            'detail' => $detail,
            'clienttime' => $clienttime,
            'servertime' => $servertime,
            'prevhash' => $prevhash,
            'eventhash' => $hash,
        ]);
        return $hash;
    }

    public static function should_suspend($quiz, $attempt, string $type): bool {
        if ($quiz->integritypolicy === 'practice') {
            return false;
        }

        if ($type === 'tabswitch') {
            return !empty($quiz->suspendontabswitch);
        }

        $serious = in_array($type, ['cameraended', 'duplicatepage'], true);
        if ($quiz->integritypolicy === 'strict') {
            return $serious;
        }
        return $serious && ((int)$attempt->violations + 1 >= 2);
    }

    public static function regrade_attempt($attempt, $quiz, bool $pushgradebook = true): object {
        global $DB;

        $questions = $DB->get_records('integrityquiz_questions', ['integrityquizid' => $quiz->id]);
        $responses = $DB->get_records('integrityquiz_responses', ['attemptid' => $attempt->id], '', 'questionid,responsejson,id');
        $max = 0.0;
        $raw = 0.0;

        foreach ($questions as $question) {
            $max += (float)$question->marks;
            if (!isset($responses[$question->id]) || $responses[$question->id]->responsejson === null || $responses[$question->id]->responsejson === '') {
                continue;
            }
            $response = json_decode($responses[$question->id]->responsejson, true);
            [$fraction, $mark] = self::mark_question($question, $response);
            $DB->update_record('integrityquiz_responses', (object)[
                'id' => $responses[$question->id]->id,
                'fraction' => $fraction,
                'mark' => $mark,
                'timemodified' => time(),
            ]);
            $raw += $mark;
        }

        $attempt->rawscore = $raw;
        $attempt->grade = $max > 0 ? ($raw / $max) * (float)$quiz->grade : 0.0;
        $attempt->timemodified = time();
        $DB->update_record('integrityquiz_attempts', $attempt);

        if ($pushgradebook && $attempt->state === 'submitted') {
            \integrityquiz_update_user_grade($quiz, $attempt->userid);
        }

        return $attempt;
    }

    public static function submit_attempt($attempt, $quiz): object {
        global $DB;

        $attempt = self::regrade_attempt($attempt, $quiz, false);
        $attempt->state = 'submitted';
        $attempt->timefinish = time();
        $attempt->timemodified = time();
        $DB->update_record('integrityquiz_attempts', $attempt);

        \integrityquiz_update_user_grade($quiz, $attempt->userid);
        return $attempt;
    }
}
