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
 * Scheduled task for expired evidence cleanup.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_integrityquiz\task;

defined('MOODLE_INTERNAL') || die();

class cleanup_evidence extends \core\task\scheduled_task {
    public function get_name(): string { return get_string('taskcleanupevidence', 'mod_integrityquiz'); }
    public function execute() {
        global $DB;
        $fs = get_file_storage();
        $instances = $DB->get_records_select('integrityquiz', 'retentiondays > 0', [], '', 'id,retentiondays');
        foreach ($instances as $instance) {
            $cutoff = time() - ($instance->retentiondays * DAYSECS);
            $sql = "SELECT a.id, cm.id AS cmid
                      FROM {integrityquiz_attempts} a
                      JOIN {integrityquiz} iq ON iq.id = a.integrityquizid
                      JOIN {course_modules} cm ON cm.instance = iq.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'integrityquiz'
                     WHERE a.integrityquizid = ? AND a.timestart < ?";
            foreach ($DB->get_records_sql($sql, [$instance->id, $cutoff]) as $attempt) {
                $context = \context_module::instance($attempt->cmid);
                $fs->delete_area_files($context->id, 'mod_integrityquiz', 'evidence', $attempt->id);
            }
        }
    }
}
