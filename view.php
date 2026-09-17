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
 * Main activity view for Integrity Quiz.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$submitted = optional_param('submitted', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('integrityquiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('integrityquiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/integrityquiz:view', $context);

$canmanagequestions = has_capability('mod/integrityquiz:managequestions', $context);
$canviewreports = has_capability('mod/integrityquiz:viewreports', $context);
$canattempt = has_capability('mod/integrityquiz:attempt', $context);

$PAGE->set_url('/mod/integrityquiz/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($quiz->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('mod-integrityquiz');

// Record the standard activity view event and completion view state.
$event = \mod_integrityquiz\event\course_module_viewed::create([
    'objectid' => $quiz->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('integrityquiz', $quiz);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($quiz->name));

if ($quiz->intro) {
    echo $OUTPUT->box(format_module_intro('integrityquiz', $quiz, $cm->id), 'generalbox');
}

if ($submitted) {
    echo $OUTPUT->notification(get_string('attemptsubmittedreturn', 'mod_integrityquiz'), 'success');
}

if ($canmanagequestions || $canviewreports) {
    echo html_writer::start_div('mb-4 d-flex gap-2 flex-wrap');
    if ($canmanagequestions) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/integrityquiz/managequestions.php', ['id' => $cm->id]),
            get_string('managequestions', 'mod_integrityquiz'),
            'get',
            ['class' => 'btn-primary']
        );
    }
    if ($canviewreports) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/integrityquiz/report.php', ['id' => $cm->id]),
            get_string('attemptreport', 'mod_integrityquiz'),
            'get',
            ['class' => 'btn-secondary']
        );
    }
    echo html_writer::end_div();
}

// Anyone allowed to attempt sees only their own attempt controls and own grades/history.
// Staff keep their management controls above, while students never receive those controls.
// Capability is granted to learners and, by default, teaching roles for test attempts.
$canattempt = has_capability('mod/integrityquiz:attempt', $context);
if ($canattempt) {
    $qcount = $DB->count_records('integrityquiz_questions', ['integrityquizid' => $quiz->id]);
    $used = $DB->count_records('integrityquiz_attempts', [
        'integrityquizid' => $quiz->id,
        'userid' => $USER->id,
    ]);

    // Show all submitted attempts as a compact learner attempt history.
    // Each attempt keeps its own grade. The separate Moodle gradebook value is
    // displayed as the learner's current overall grade for this activity.
    $submittedattempts = $DB->get_records(
        'integrityquiz_attempts',
        [
            'integrityquizid' => $quiz->id,
            'userid' => $USER->id,
            'state' => 'submitted',
        ],
        'attemptno DESC, timefinish DESC, id DESC'
    );

    if ($submittedattempts) {
        $gradeitem = $DB->get_record('grade_items', [
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'integrityquiz',
            'iteminstance' => $quiz->id,
            'itemnumber' => 0,
        ]);

        $finalgrade = null;
        $grademax = (float)$quiz->grade;
        if ($gradeitem) {
            $grademax = (float)$gradeitem->grademax;
            $gradegrade = $DB->get_record('grade_grades', [
                'itemid' => $gradeitem->id,
                'userid' => $USER->id,
            ]);
            if ($gradegrade && $gradegrade->finalgrade !== null) {
                $finalgrade = (float)$gradegrade->finalgrade;
            }
        }

        $attemptcount = count($submittedattempts);
        $attemptgrades = array_filter(array_map(
            static fn($attempt) => $attempt->grade === null ? null : (float)$attempt->grade,
            $submittedattempts
        ), static fn($grade) => $grade !== null);
        $bestattemptgrade = $attemptgrades ? max($attemptgrades) : null;

        echo html_writer::start_div('iq-attempt-history');
        echo html_writer::start_div('iq-attempt-history__heading');
        echo html_writer::start_div();
        echo html_writer::div(get_string('yourresults', 'mod_integrityquiz'), 'iq-attempt-history__eyebrow');
        echo html_writer::tag('h3', get_string('attempthistory', 'mod_integrityquiz'), [
            'class' => 'iq-attempt-history__title',
        ]);
        echo html_writer::div(
            get_string('attempthistorysummary', 'mod_integrityquiz', $attemptcount),
            'iq-attempt-history__subtitle'
        );
        echo html_writer::end_div();

        if ($finalgrade !== null) {
            $overallpercentage = $grademax > 0 ? ($finalgrade / $grademax) * 100 : 0;
            echo html_writer::start_div('iq-attempt-history__overall');
            echo html_writer::span(get_string('currentmoodlegrade', 'mod_integrityquiz'), 'iq-attempt-history__overall-label');
            echo html_writer::span(
                format_float($finalgrade, 2) . ' / ' . format_float($grademax, 2),
                'iq-attempt-history__overall-score'
            );
            echo html_writer::span(
                format_float($overallpercentage, 1) . '%',
                'iq-attempt-history__overall-percent'
            );
            echo html_writer::end_div();
        }
        echo html_writer::end_div();

        echo html_writer::start_div('iq-attempt-history__grid');
        $index = 0;
        foreach ($submittedattempts as $attemptrecord) {
            $index++;
            $islatest = ($index === 1);
            $isbest = $attemptrecord->grade !== null && $bestattemptgrade !== null
                && abs((float)$attemptrecord->grade - $bestattemptgrade) < 0.00001;
            $cardclasses = 'iq-attempt-mini-card';
            if ($islatest) {
                $cardclasses .= ' is-latest';
            }
            if ($isbest) {
                $cardclasses .= ' is-best';
            }

            echo html_writer::start_div($cardclasses);
            echo html_writer::start_div('iq-attempt-mini-card__top');
            echo html_writer::start_div('iq-attempt-mini-card__identity');
            echo html_writer::span((string)$attemptrecord->attemptno, 'iq-attempt-mini-card__number');
            echo html_writer::start_div();
            echo html_writer::div(
                get_string('attemptnumber', 'mod_integrityquiz', $attemptrecord->attemptno),
                'iq-attempt-mini-card__name'
            );
            echo html_writer::div(get_string('completedstatus', 'mod_integrityquiz'), 'iq-attempt-mini-card__status');
            echo html_writer::end_div();
            echo html_writer::end_div();

            echo html_writer::start_div('iq-attempt-mini-card__badges');
            if ($islatest) {
                echo html_writer::span(get_string('latestattempt', 'mod_integrityquiz'), 'iq-attempt-mini-card__badge');
            } else if ($isbest) {
                echo html_writer::span(get_string('bestattempt', 'mod_integrityquiz'), 'iq-attempt-mini-card__badge iq-attempt-mini-card__badge--best');
            }
            echo html_writer::end_div();
            echo html_writer::end_div();

            if ($attemptrecord->grade !== null) {
                $attemptgrade = (float)$attemptrecord->grade;
                $attemptpercentage = $grademax > 0 ? ($attemptgrade / $grademax) * 100 : 0;
                echo html_writer::start_div('iq-attempt-mini-card__result');
                echo html_writer::span(format_float($attemptgrade, 2), 'iq-attempt-mini-card__score');
                echo html_writer::span('/ ' . format_float($grademax, 2), 'iq-attempt-mini-card__max');
                echo html_writer::span(format_float($attemptpercentage, 1) . '%', 'iq-attempt-mini-card__percent');
                echo html_writer::end_div();
            } else {
                echo html_writer::div(get_string('gradependingteacher', 'mod_integrityquiz'), 'iq-attempt-mini-card__pending');
            }

            echo html_writer::start_div('iq-attempt-mini-card__footer');
            echo html_writer::span('✓ ' . get_string('submitted', 'mod_integrityquiz'), 'iq-attempt-mini-card__submitted');
            if (!empty($attemptrecord->timefinish)) {
                echo html_writer::span(userdate($attemptrecord->timefinish, get_string('strftimedatetimeshort', 'langconfig')), 'iq-attempt-mini-card__date');
            }
            echo html_writer::end_div();
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    echo html_writer::div(get_string('questioncountinfo', 'mod_integrityquiz', $qcount), 'alert alert-info');

    if ($quiz->timeopen && time() < $quiz->timeopen) {
        echo $OUTPUT->notification(get_string('notopenyet', 'mod_integrityquiz', userdate($quiz->timeopen)), 'warning');
    } else if ($quiz->timeclose && time() > $quiz->timeclose) {
        echo $OUTPUT->notification(get_string('closed', 'mod_integrityquiz'), 'warning');
    } else if ($used >= $quiz->attemptsallowed) {
        echo $OUTPUT->notification(get_string('nomoreattempts', 'mod_integrityquiz'), 'info');
    } else if ($qcount === 0) {
        echo $OUTPUT->notification(get_string('noquestionsyet', 'mod_integrityquiz'), 'warning');
    } else {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/integrityquiz/attempt.php', ['id' => $cm->id]),
            get_string('startattempt', 'mod_integrityquiz'),
            'post',
            ['class' => 'btn-primary btn-lg']
        );
    }
}

echo $OUTPUT->footer();
