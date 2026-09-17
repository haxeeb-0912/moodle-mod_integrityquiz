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
 * External function for integrity event logging.
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
 * Record an allow-listed client integrity event.
 */
final class log_event extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Integrity event type'),
            'detail' => new external_value(PARAM_TEXT, 'Event detail', VALUE_DEFAULT, ''),
            'clienttime' => new external_value(PARAM_INT, 'Client timestamp in milliseconds', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute event logging.
     *
     * @param int $attemptid Attempt id.
     * @param string $type Event type.
     * @param string $detail Detail.
     * @param int $clienttime Client time.
     * @return array
     */
    public static function execute(int $attemptid, string $type, string $detail = '', int $clienttime = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'type' => $type,
            'detail' => $detail,
            'clienttime' => $clienttime,
        ]);
        [$attempt, $quiz, $cm, $context] = util::get_attempt_context($params['attemptid']);
        self::validate_context($context);
        require_capability('mod/integrityquiz:attempt', $context);

        $allowed = ['tabswitch', 'cameraended', 'fullscreenexit', 'duplicatepage'];
        if (!in_array($params['type'], $allowed, true)) {
            throw new invalid_parameter_exception(get_string('invalidintegrityevent', 'mod_integrityquiz'));
        }

        engine::log_event($attempt, $params['type'], $params['detail'], $params['clienttime']);
        if ($attempt->state === 'active') {
            $attempt->violations++;
            if (engine::should_suspend($quiz, $attempt, $params['type'])) {
                $attempt->state = 'suspended';
                $attempt->timesuspended = time();
            }
            $attempt->timemodified = time();
            $DB->update_record('integrityquiz_attempts', $attempt);
        }

        return [
            'state' => $attempt->state,
            'violations' => (int)$attempt->violations,
        ];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'state' => new external_value(PARAM_ALPHANUMEXT, 'Attempt state'),
            'violations' => new external_value(PARAM_INT, 'Recorded violation count'),
        ]);
    }
}
