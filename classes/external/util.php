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
 * Shared validation helpers for Integrity Quiz external functions.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_integrityquiz\external;

use context_module;
use mod_integrityquiz\local\engine;

/**
 * Shared validation helpers for AJAX external functions.
 */
final class util {
    /**
     * Resolve an attempt owned by the current user and its module context.
     *
     * @param int $attemptid Attempt id.
     * @return array{0:object,1:object,2:object,3:context_module}
     */
    public static function get_attempt_context(int $attemptid): array {
        global $DB, $USER;

        $attempt = engine::get_attempt($attemptid, (int)$USER->id);
        $quiz = $DB->get_record('integrityquiz', ['id' => $attempt->integrityquizid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance(
            'integrityquiz',
            $quiz->id,
            $quiz->course,
            false,
            MUST_EXIST
        );
        $context = context_module::instance($cm->id);
        $course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
        require_login($course, true, $cm);

        return [$attempt, $quiz, $cm, $context];
    }
}
