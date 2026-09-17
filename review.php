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
 * Teacher review interface for an assessment attempt.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$note = optional_param('note', '', PARAM_TEXT);

$cm = get_coursemodule_from_id('integrityquiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('integrityquiz', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/integrityquiz:viewreports', $context);
$canmanageattempts = has_capability('mod/integrityquiz:manageattempts', $context);
$canmanagequestions = has_capability('mod/integrityquiz:managequestions', $context);
$canviewevidence = has_capability('mod/integrityquiz:viewevidence', $context);
$attempt = $DB->get_record('integrityquiz_attempts', [
    'id' => $attemptid,
    'integrityquizid' => $quiz->id,
], '*', MUST_EXIST);

if ($action && confirm_sesskey()) {
    require_capability('mod/integrityquiz:manageattempts', $context);
    $oldstate = $attempt->state;

    if ($action === 'resume') {
        $attempt->state = 'active';
        $attempt->pageid = null;
        $attempt->timesuspended = 0;
    } else if ($action === 'invalidate') {
        $attempt->state = 'invalidated';
        $attempt->timefinish = time();
    } else if ($action === 'submit' && $attempt->state !== 'submitted') {
        $attempt = \mod_integrityquiz\local\engine::submit_attempt($attempt, $quiz);
    } else if ($action === 'regrade' && $attempt->state === 'submitted') {
        $attempt = \mod_integrityquiz\local\engine::regrade_attempt($attempt, $quiz, true);
    } else if ($action === 'savegrade') {
        $manualgrade = required_param('manualgrade', PARAM_FLOAT);
        if ($manualgrade < 0 || $manualgrade > (float)$quiz->grade) {
            redirect(
                new moodle_url('/mod/integrityquiz/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]),
                get_string('invalidmanualgrade', 'mod_integrityquiz', format_float($quiz->grade, 2)),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        if ($attempt->state !== 'submitted') {
            redirect(
                new moodle_url('/mod/integrityquiz/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]),
                get_string('manualgradeonlysubmitted', 'mod_integrityquiz'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        $attempt->grade = $manualgrade;
    }

    $attempt->timemodified = time();
    $DB->update_record('integrityquiz_attempts', $attempt);

    if (in_array($action, ['invalidate', 'savegrade'], true)) {
        integrityquiz_update_user_grade($quiz, $attempt->userid);
    }

    $DB->insert_record('integrityquiz_reviews', (object)[
        'attemptid' => $attempt->id,
        'reviewerid' => $USER->id,
        'action' => $action,
        'oldstate' => $oldstate,
        'newstate' => $attempt->state,
        'note' => $note,
        'timecreated' => time(),
    ]);

    redirect(
        new moodle_url('/mod/integrityquiz/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]),
        get_string('reviewupdated', 'mod_integrityquiz')
    );
}

$PAGE->set_url('/mod/integrityquiz/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]);
$PAGE->set_title(get_string('review', 'mod_integrityquiz'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('mod-integrityquiz');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewattempt', 'mod_integrityquiz', $attempt->attemptno));
echo html_writer::div(html_writer::link(new moodle_url('/mod/integrityquiz/report.php', ['id' => $cm->id]), '← ' . get_string('attemptreport', 'mod_integrityquiz'), ['class' => 'btn btn-sm btn-outline-secondary mb-3']), 'iq-review-back');
$user = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);
echo html_writer::div(fullname($user) . ' — ' . s($attempt->state), 'alert alert-info');

echo html_writer::start_div('mb-3 d-flex gap-2 flex-wrap');
if ($canmanageattempts && $attempt->state === 'suspended') {
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['action' => 'resume', 'sesskey' => sesskey()]),
        get_string('actionresume', 'mod_integrityquiz'),
        'post'
    );
}
if ($canmanageattempts && $attempt->state !== 'submitted' && $attempt->state !== 'invalidated') {
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['action' => 'submit', 'sesskey' => sesskey()]),
        get_string('actionsubmit', 'mod_integrityquiz'),
        'post'
    );
}
if ($canmanageattempts && $attempt->state === 'submitted') {
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['action' => 'regrade', 'sesskey' => sesskey()]),
        get_string('actionregrade', 'mod_integrityquiz'),
        'post'
    );
}
if ($canmanageattempts && $attempt->state !== 'invalidated') {
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['action' => 'invalidate', 'sesskey' => sesskey()]),
        get_string('actioninvalidate', 'mod_integrityquiz'),
        'post'
    );
}
echo html_writer::end_div();

if ($canmanageattempts && $attempt->state === 'submitted') {
    echo html_writer::start_div('card shadow-sm p-3 mb-4 integrity-grade-editor', ['id' => 'editresult']);
    echo html_writer::tag('h3', get_string('editresultgrade', 'mod_integrityquiz'));
    echo html_writer::div(get_string('manualgradehelp', 'mod_integrityquiz'), 'text-muted mb-3');
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-flex align-items-end gap-2 flex-wrap']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savegrade']);
    echo html_writer::start_div();
    echo html_writer::tag('label', get_string('grade', 'mod_integrityquiz'), ['for' => 'manualgrade', 'class' => 'form-label fw-bold']);
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'manualgrade',
        'id' => 'manualgrade',
        'min' => '0',
        'max' => (float)$quiz->grade,
        'step' => '0.01',
        'value' => $attempt->grade === null ? '' : format_float($attempt->grade, 2, true, false),
        'class' => 'form-control',
        'required' => 'required',
    ]);
    echo html_writer::end_div();
    echo html_writer::div('/ ' . format_float($quiz->grade, 2), 'pb-2 fw-bold');
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('savegrade', 'mod_integrityquiz'),
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

$questions = $DB->get_records('integrityquiz_questions', ['integrityquizid' => $quiz->id], 'sortorder ASC');
$responses = $DB->get_records('integrityquiz_responses', ['attemptid' => $attempt->id], '', 'questionid,responsejson,mark');

if ($questions) {
    echo $OUTPUT->heading(get_string('attemptresponses', 'mod_integrityquiz'), 3);
    $responsetable = new html_table();
    $responsetable->head = [
        '#',
        get_string('question'),
        get_string('studentanswer', 'mod_integrityquiz'),
        get_string('grade', 'mod_integrityquiz'),
        get_string('actions', 'mod_integrityquiz'),
    ];
    $number = 0;
    foreach ($questions as $question) {
        $number++;
        $options = json_decode($question->optionsjson, true) ?: [];
        $answertext = get_string('notanswered', 'mod_integrityquiz');
        $mark = 0.0;
        if (isset($responses[$question->id])) {
            $rawanswer = json_decode($responses[$question->id]->responsejson, true);
            $keys = is_array($rawanswer) ? $rawanswer : [$rawanswer];
            $labels = [];
            foreach ($keys as $key) {
                if ($key === null || $key === '') {
                    continue;
                }
                $key = (string)$key;
                $labels[] = isset($options[$key]) ? $key . ': ' . $options[$key] : $key;
            }
            if ($labels) {
                $answertext = implode(', ', $labels);
            }
            $mark = (float)$responses[$question->id]->mark;
        }
        $editurl = new moodle_url('/mod/integrityquiz/managequestions.php', [
            'id' => $cm->id,
            'edit' => $question->id,
        ], 'editquestion');
        $responsetable->data[] = [
            $number,
            format_text($question->questiontext, FORMAT_PLAIN),
            s($answertext),
            format_float($mark, 2) . ' / ' . format_float($question->marks, 2),
            $canmanagequestions
                ? html_writer::link($editurl, get_string('editquestion', 'mod_integrityquiz'))
                : '-',
        ];
    }
    echo html_writer::table($responsetable);
    if ($attempt->state === 'submitted') {
        echo html_writer::div(get_string('regradeafteredithelp', 'mod_integrityquiz'), 'alert alert-warning');
    }
}

$events = $DB->get_records('integrityquiz_events', ['attemptid' => $attempt->id], 'id ASC');
$table = new html_table();
$table->head = [
    get_string('time'),
    get_string('eventtype', 'mod_integrityquiz'),
    get_string('detail', 'mod_integrityquiz'),
    get_string('eventhash', 'mod_integrityquiz'),
];
foreach ($events as $event) {
    $table->data[] = [
        userdate($event->servertime),
        s($event->eventtype),
        s($event->detail),
        html_writer::tag('code', substr($event->eventhash, 0, 16) . '…'),
    ];
}
echo html_writer::table($table);

if ($canviewevidence) {
    $files = get_file_storage()->get_area_files(
        $context->id,
        'mod_integrityquiz',
        'evidence',
        $attempt->id,
        'timemodified DESC',
        false
    );
    if ($files) {
        echo $OUTPUT->heading(get_string('evidence', 'mod_integrityquiz'), 3);
        echo html_writer::start_div('integrity-evidence-grid');
        foreach ($files as $file) {
            $url = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_integrityquiz',
                'evidence',
                $attempt->id,
                '/',
                $file->get_filename()
            );
            echo html_writer::link(
                $url,
                html_writer::empty_tag('img', [
                    'src' => $url,
                    'alt' => get_string('evidence', 'mod_integrityquiz'),
                    'class' => 'integrity-evidence-img',
                ])
            );
        }
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
