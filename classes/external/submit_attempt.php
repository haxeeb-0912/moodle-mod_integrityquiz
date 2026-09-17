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
 * External function for submitting and grading an attempt.
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
use mod_integrityquiz\local\engine;
use moodle_url;

/**
 * Submit the current attempt and synchronise its grade.
 */
final class submit_attempt extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
        ]);
    }

    /**
     * Submit attempt.
     *
     * @param int $attemptid Attempt id.
     * @return array
     */
    public static function execute(int $attemptid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['attemptid' => $attemptid]);
        [$attempt, $quiz, $cm, $context] = util::get_attempt_context($params['attemptid']);
        self::validate_context($context);
        require_capability('mod/integrityquiz:attempt', $context);

        if ($attempt->state !== 'active') {
            throw new \moodle_exception('attemptnotactive', 'mod_integrityquiz');
        }

        $attempt = engine::submit_attempt($attempt, $quiz);
        engine::log_event($attempt, 'submitted', 'Attempt submitted');
        $redirect = new moodle_url('/mod/integrityquiz/view.php', [
            'id' => $cm->id,
            'submitted' => 1,
        ]);

        return [
            'state' => 'submitted',
            'grade' => (float)$attempt->grade,
            'redirect' => $redirect->out(false),
        ];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'state' => new external_value(PARAM_ALPHANUMEXT, 'Attempt state'),
            'grade' => new external_value(PARAM_FLOAT, 'Attempt grade'),
            'redirect' => new external_value(PARAM_URL, 'Return URL'),
        ]);
    }
}
