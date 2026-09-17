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
 * External service definitions for Integrity Quiz AJAX calls.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_integrityquiz_heartbeat' => [
        'classname' => 'mod_integrityquiz\external\heartbeat',
        'methodname' => 'execute',
        'description' => 'Update the heartbeat for the current Integrity Quiz attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/integrityquiz:attempt',
    ],
    'mod_integrityquiz_log_event' => [
        'classname' => 'mod_integrityquiz\external\log_event',
        'methodname' => 'execute',
        'description' => 'Record an allow-listed integrity event for an attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/integrityquiz:attempt',
    ],
    'mod_integrityquiz_question_timer' => [
        'classname' => 'mod_integrityquiz\external\question_timer',
        'methodname' => 'execute',
        'description' => 'Start or query the authoritative per-question timer.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/integrityquiz:attempt',
    ],
    'mod_integrityquiz_save_response' => [
        'classname' => 'mod_integrityquiz\external\save_response',
        'methodname' => 'execute',
        'description' => 'Save a response for the current attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/integrityquiz:attempt',
    ],
    'mod_integrityquiz_save_snapshot' => [
        'classname' => 'mod_integrityquiz\external\save_snapshot',
        'methodname' => 'execute',
        'description' => 'Store a camera snapshot as private Integrity Quiz evidence.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/integrityquiz:attempt',
    ],
    'mod_integrityquiz_submit_attempt' => [
        'classname' => 'mod_integrityquiz\external\submit_attempt',
        'methodname' => 'execute',
        'description' => 'Submit and grade the current Integrity Quiz attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/integrityquiz:attempt',
    ],
];
