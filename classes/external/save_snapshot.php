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
 * External function for storing private camera evidence.
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
 * Store a camera snapshot in the Moodle File API.
 */
final class save_snapshot extends external_api {
    /** Maximum decoded snapshot size (2 MiB). */
    private const MAX_BYTES = 2097152;

    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'kind' => new external_value(PARAM_ALPHANUMEXT, 'Snapshot kind', VALUE_DEFAULT, 'random'),
            'data' => new external_value(PARAM_RAW, 'Base64 encoded image data'),
        ]);
    }

    /**
     * Store snapshot evidence.
     *
     * @param int $attemptid Attempt id.
     * @param string $kind Snapshot kind.
     * @param string $data Base64 data.
     * @return array
     */
    public static function execute(int $attemptid, string $kind, string $data): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'kind' => $kind,
            'data' => $data,
        ]);
        [$attempt, $quiz, $cm, $context] = util::get_attempt_context($params['attemptid']);
        self::validate_context($context);
        require_capability('mod/integrityquiz:attempt', $context);

        $allowedkinds = ['start', 'random', 'event', 'final'];
        if (!in_array($params['kind'], $allowedkinds, true)) {
            throw new invalid_parameter_exception(get_string('invalidsnapshotkind', 'mod_integrityquiz'));
        }
        if ($attempt->state !== 'active' && $params['kind'] !== 'final') {
            throw new \moodle_exception('attemptnotactive', 'mod_integrityquiz');
        }

        // Reject unreasonable encoded payloads before decoding.
        if (strlen($params['data']) > (self::MAX_BYTES * 2)) {
            throw new \moodle_exception('snapshottoolarge', 'mod_integrityquiz');
        }
        $binary = base64_decode($params['data'], true);
        if ($binary === false || $binary === '') {
            throw new \moodle_exception('invalidsnapshot', 'mod_integrityquiz');
        }
        if (strlen($binary) > self::MAX_BYTES) {
            throw new \moodle_exception('snapshottoolarge', 'mod_integrityquiz');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);
        $extensions = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
        ];
        if (!isset($extensions[$mime])) {
            throw new \moodle_exception('invalidsnapshot', 'mod_integrityquiz');
        }

        $filename = $params['kind'] . '-' . time() . '-' . bin2hex(random_bytes(3)) . $extensions[$mime];
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_integrityquiz',
            'filearea' => 'evidence',
            'itemid' => $attempt->id,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $USER->id,
        ];
        get_file_storage()->create_file_from_string($filerecord, $binary);
        engine::log_event($attempt, 'snapshot', $params['kind']);

        return ['saved' => true];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'saved' => new external_value(PARAM_BOOL, 'Whether the snapshot was stored'),
        ]);
    }
}
