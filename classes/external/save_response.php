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
 * External function for saving assessment responses.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_integrityquiz\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use mod_integrityquiz\local\engine;

/**
 * Save a response for the current user's attempt.
 */
final class save_response extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'questionid' => new external_value(PARAM_INT, 'Question id'),
            'response' => new external_value(PARAM_RAW, 'JSON encoded response'),
        ]);
    }

    /**
     * Save the response.
     *
     * @param int $attemptid Attempt id.
     * @param int $questionid Question id.
     * @param string $response JSON response.
     * @return array
     */
    public static function execute(int $attemptid, int $questionid, string $response): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'questionid' => $questionid,
            'response' => $response,
        ]);
        [$attempt, $quiz, $cm, $context] = util::get_attempt_context($params['attemptid']);
        self::validate_context($context);
        require_capability('mod/integrityquiz:attempt', $context);

        $question = $DB->get_record('integrityquiz_questions', [
            'id' => $params['questionid'],
            'integrityquizid' => $quiz->id,
        ], '*', MUST_EXIST);

        $decoded = json_decode($params['response'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new invalid_parameter_exception(get_string('invalidresponse', 'mod_integrityquiz'));
        }
        $decoded = engine::validate_response($question, $decoded);
        engine::save_response($attempt, $question, $decoded);

        return ['state' => $attempt->state];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'state' => new external_value(PARAM_ALPHANUMEXT, 'Attempt state'),
        ]);
    }
}
