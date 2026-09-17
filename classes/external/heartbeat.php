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
 * External function for attempt heartbeat updates.
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

/**
 * Update the heartbeat and detect duplicate active pages.
 */
final class heartbeat extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'pageid' => new external_value(PARAM_ALPHANUMEXT, 'Client page identifier'),
        ]);
    }

    /**
     * Execute heartbeat update.
     *
     * @param int $attemptid Attempt id.
     * @param string $pageid Client page id.
     * @return array
     */
    public static function execute(int $attemptid, string $pageid): array {
        global $DB;

        ['attemptid' => $attemptid, 'pageid' => $pageid] = self::validate_parameters(
            self::execute_parameters(),
            ['attemptid' => $attemptid, 'pageid' => $pageid]
        );
        [$attempt, $quiz, $cm, $context] = util::get_attempt_context($attemptid);
        self::validate_context($context);
        require_capability('mod/integrityquiz:attempt', $context);

        if ($attempt->state === 'active'
                && !empty($attempt->pageid)
                && $attempt->pageid !== $pageid
                && (time() - (int)$attempt->lastheartbeat) < 20) {
            engine::log_event($attempt, 'duplicatepage', 'A second active page was detected');
            $attempt->violations++;
            if (engine::should_suspend($quiz, $attempt, 'duplicatepage')) {
                $attempt->state = 'suspended';
                $attempt->timesuspended = time();
            }
        }

        if (empty($attempt->pageid)) {
            $attempt->pageid = $pageid;
        }
        $attempt->lastheartbeat = time();
        $attempt->timemodified = time();
        $DB->update_record('integrityquiz_attempts', $attempt);

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
