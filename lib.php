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
 * Library functions and Moodle callbacks for Integrity Quiz.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function integrityquiz_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        default:
            return null;
    }
}

function integrityquiz_add_instance($data, $mform = null): int {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    $data->requirefullscreen = 0;
    if (!isset($data->questionsperpage)) {
        $data->questionsperpage = 1;
    }
    if (!isset($data->questiontimelimit)) {
        $data->questiontimelimit = 0;
    }
    $id = $DB->insert_record('integrityquiz', $data);
    $data->id = $id;
    integrityquiz_grade_item_update($data);
    return $id;
}

function integrityquiz_update_instance($data, $mform = null): bool {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $data->requirefullscreen = 0;
    if (!isset($data->questiontimelimit)) {
        $data->questiontimelimit = 0;
    }
    $ok = $DB->update_record('integrityquiz', $data);
    integrityquiz_grade_item_update($data);
    integrityquiz_update_grades($data);
    return $ok;
}

function integrityquiz_delete_instance($id): bool {
    global $DB;
    if (!$instance = $DB->get_record('integrityquiz', ['id' => $id])) {
        return false;
    }
    $attemptids = $DB->get_fieldset_select('integrityquiz_attempts', 'id', 'integrityquizid = ?', [$id]);
    if ($attemptids) {
        [$insql, $params] = $DB->get_in_or_equal($attemptids);
        $DB->delete_records_select('integrityquiz_responses', "attemptid $insql", $params);
        $DB->delete_records_select('integrityquiz_events', "attemptid $insql", $params);
        $DB->delete_records_select('integrityquiz_reviews', "attemptid $insql", $params);
    }
    $cm = get_coursemodule_from_instance('integrityquiz', $id, $instance->course, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        get_file_storage()->delete_area_files($context->id, 'mod_integrityquiz', 'evidence');
    }
    $DB->delete_records('integrityquiz_attempts', ['integrityquizid' => $id]);
    $DB->delete_records('integrityquiz_questions', ['integrityquizid' => $id]);
    $DB->delete_records('integrityquiz', ['id' => $id]);
    integrityquiz_grade_item_delete($instance);
    return true;
}

function integrityquiz_grade_item_update($instance, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $params = [
        'itemname' => $instance->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademin' => 0,
        'grademax' => (float)$instance->grade,
    ];
    return grade_update(
        'mod/integrityquiz',
        $instance->course,
        'mod',
        'integrityquiz',
        $instance->id,
        0,
        $grades,
        $params
    );
}

function integrityquiz_grade_item_delete($instance): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update(
        'mod/integrityquiz',
        $instance->course,
        'mod',
        'integrityquiz',
        $instance->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Return grades for the gradebook API. The highest submitted attempt is used.
 *
 * @param object $instance
 * @param int $userid
 * @return array
 */
function integrityquiz_get_user_grades($instance, $userid = 0): array {
    global $DB;

    $params = ['quizid' => $instance->id, 'state' => 'submitted'];
    $usersql = '';
    if ($userid) {
        $usersql = ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    $sql = "SELECT userid, MAX(grade) AS rawgrade
              FROM {integrityquiz_attempts}
             WHERE integrityquizid = :quizid
               AND state = :state
                   $usersql
          GROUP BY userid";
    $records = $DB->get_records_sql($sql, $params);
    $grades = [];
    foreach ($records as $record) {
        $grades[$record->userid] = (object)[
            'userid' => $record->userid,
            'rawgrade' => (float)$record->rawgrade,
        ];
    }
    return $grades;
}

/**
 * Synchronise grades with Moodle gradebook.
 *
 * @param object $instance
 * @param int $userid
 * @param bool $nullifnone
 */
function integrityquiz_update_grades($instance, $userid = 0, $nullifnone = true): void {
    $grades = integrityquiz_get_user_grades($instance, $userid);
    if ($grades) {
        integrityquiz_grade_item_update($instance, $grades);
    } else if ($userid && $nullifnone) {
        integrityquiz_grade_item_update($instance, (object)[
            'userid' => $userid,
            'rawgrade' => null,
        ]);
    } else if (!$userid) {
        integrityquiz_grade_item_update($instance);
    }
}

/**
 * Synchronise one user's best submitted grade with Moodle gradebook.
 *
 * @param object $instance
 * @param int $userid
 * @param float|null $grade Deprecated direct value; the best submitted attempt is recalculated.
 * @return int
 */
function integrityquiz_update_user_grade($instance, int $userid, ?float $grade = null): int {
    $grades = integrityquiz_get_user_grades($instance, $userid);
    if (isset($grades[$userid])) {
        return integrityquiz_grade_item_update($instance, $grades[$userid]);
    }
    return integrityquiz_grade_item_update($instance, (object)[
        'userid' => $userid,
        'rawgrade' => null,
    ]);
}

function integrityquiz_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'evidence') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/integrityquiz:viewevidence', $context);
    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_integrityquiz', 'evidence', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}
