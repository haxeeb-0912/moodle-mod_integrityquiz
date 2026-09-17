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
 * Course list of Integrity Quiz activities.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);
$context = context_course::instance($course->id);

$PAGE->set_url('/mod/integrityquiz/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_integrityquiz'));
$PAGE->set_heading(format_string($course->fullname));

$event = \mod_integrityquiz\event\course_module_instance_list_viewed::create([
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->trigger();

echo $OUTPUT->header();

$instances = get_all_instances_in_course('integrityquiz', $course);
$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('integritypolicy', 'mod_integrityquiz'),
];
foreach ($instances as $instance) {
    $table->data[] = [
        html_writer::link(
            new moodle_url('/mod/integrityquiz/view.php', ['id' => $instance->coursemodule]),
            format_string($instance->name)
        ),
        s($instance->integritypolicy),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
