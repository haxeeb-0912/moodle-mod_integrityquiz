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
 * Teacher attempt and integrity report.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$statefilter = optional_param('state', '', PARAM_ALPHA);
$search = trim(optional_param('search', '', PARAM_TEXT));
$deleteattempt = optional_param('deleteattempt', 0, PARAM_INT);
$confirmdelete = optional_param('confirmdelete', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('integrityquiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('integrityquiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/integrityquiz:viewreports', $context);

$PAGE->set_url('/mod/integrityquiz/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('attemptreport', 'mod_integrityquiz'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('mod-integrityquiz integrity-report-page');

$canmanageattempts = has_capability('mod/integrityquiz:manageattempts', $context);
$canmanagequestions = has_capability('mod/integrityquiz:managequestions', $context);

// Safe attempt deletion. Related responses, evidence and integrity records are removed together,
// then the learner's gradebook result is recalculated from their remaining submitted attempts.
if ($deleteattempt) {
    require_capability('mod/integrityquiz:manageattempts', $context);

    $attempttodelete = $DB->get_record('integrityquiz_attempts', [
        'id' => $deleteattempt,
        'integrityquizid' => $quiz->id,
    ], '*', MUST_EXIST);
    $deleteuser = $DB->get_record('user', ['id' => $attempttodelete->userid], '*', MUST_EXIST);

    if ($confirmdelete) {
        require_sesskey();

        $transaction = $DB->start_delegated_transaction();
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_integrityquiz', 'evidence', $attempttodelete->id);
        $DB->delete_records('integrityquiz_reviews', ['attemptid' => $attempttodelete->id]);
        $DB->delete_records('integrityquiz_events', ['attemptid' => $attempttodelete->id]);
        $DB->delete_records('integrityquiz_responses', ['attemptid' => $attempttodelete->id]);
        $DB->delete_records('integrityquiz_attempts', ['id' => $attempttodelete->id]);
        integrityquiz_update_user_grade($quiz, (int)$attempttodelete->userid);
        $transaction->allow_commit();

        redirect(
            new moodle_url('/mod/integrityquiz/report.php', ['id' => $cm->id]),
            get_string('attemptdeletedsuccess', 'mod_integrityquiz'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    echo html_writer::start_div('iq-delete-confirm-wrap');
    echo html_writer::start_div('iq-delete-confirm-card');
    echo html_writer::div('!', 'iq-delete-confirm-icon', ['aria-hidden' => 'true']);
    echo html_writer::tag('h2', get_string('deleteattempttitle', 'mod_integrityquiz'), ['class' => 'iq-delete-confirm-title']);
    echo html_writer::tag(
        'p',
        get_string('deleteattemptconfirm', 'mod_integrityquiz', (object)[
            'attempt' => $attempttodelete->attemptno,
            'student' => fullname($deleteuser),
        ]),
        ['class' => 'iq-delete-confirm-text']
    );
    echo html_writer::div(get_string('deleteattemptwarning', 'mod_integrityquiz'), 'iq-delete-confirm-warning');
    echo html_writer::start_div('iq-delete-confirm-actions');
    echo $OUTPUT->single_button(
        new moodle_url('/mod/integrityquiz/report.php', [
            'id' => $cm->id,
            'deleteattempt' => $attempttodelete->id,
            'confirmdelete' => 1,
            'sesskey' => sesskey(),
        ]),
        get_string('deleteattempt', 'mod_integrityquiz'),
        'post',
        ['class' => 'btn-danger']
    );
    echo html_writer::link(
        new moodle_url('/mod/integrityquiz/report.php', ['id' => $cm->id]),
        get_string('cancel'),
        ['class' => 'btn btn-outline-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$validstates = ['', 'active', 'suspended', 'submitted', 'invalidated'];
if (!in_array($statefilter, $validstates, true)) {
    $statefilter = '';
}

// Dashboard statistics are calculated from the complete activity, independent of filters.
$totalattempts = $DB->count_records('integrityquiz_attempts', ['integrityquizid' => $quiz->id]);
$submittedcount = $DB->count_records('integrityquiz_attempts', ['integrityquizid' => $quiz->id, 'state' => 'submitted']);
$suspendedcount = $DB->count_records('integrityquiz_attempts', ['integrityquizid' => $quiz->id, 'state' => 'suspended']);
$studentcount = (int)$DB->get_field_sql(
    'SELECT COUNT(DISTINCT userid) FROM {integrityquiz_attempts} WHERE integrityquizid = ?',
    [$quiz->id]
);
$averagegrade = $DB->get_field_sql(
    "SELECT AVG(grade)
       FROM {integrityquiz_attempts}
      WHERE integrityquizid = ?
        AND state = ?
        AND grade IS NOT NULL",
    [$quiz->id, 'submitted']
);
$totalviolations = (int)$DB->get_field_sql(
    'SELECT COALESCE(SUM(violations), 0) FROM {integrityquiz_attempts} WHERE integrityquizid = ?',
    [$quiz->id]
);

// Build filterable attempt query.
$where = ['a.integrityquizid = :quizid'];
$params = ['quizid' => $quiz->id];
if ($statefilter !== '') {
    $where[] = 'a.state = :statefilter';
    $params['statefilter'] = $statefilter;
}
if ($search !== '') {
    $like = '%' . $DB->sql_like_escape($search) . '%';
    $where[] = '(' . $DB->sql_like('u.firstname', ':searchfirst', false) .
        ' OR ' . $DB->sql_like('u.lastname', ':searchlast', false) .
        ' OR ' . $DB->sql_like('u.email', ':searchemail', false) . ')';
    $params['searchfirst'] = $like;
    $params['searchlast'] = $like;
    $params['searchemail'] = $like;
}

$sql = "SELECT a.*, u.firstname, u.lastname, u.email
          FROM {integrityquiz_attempts} a
          JOIN {user} u ON u.id = a.userid
         WHERE " . implode(' AND ', $where) . "
      ORDER BY a.id DESC";
$attempts = $DB->get_records_sql($sql, $params);

$stateoptions = [
    '' => get_string('reportallstates', 'mod_integrityquiz'),
    'active' => get_string('statusactive', 'mod_integrityquiz'),
    'suspended' => get_string('statussuspended', 'mod_integrityquiz'),
    'submitted' => get_string('statussubmitted', 'mod_integrityquiz'),
    'invalidated' => get_string('statusinvalidated', 'mod_integrityquiz'),
];

$stateclasses = [
    'active' => 'is-active',
    'suspended' => 'is-suspended',
    'submitted' => 'is-submitted',
    'invalidated' => 'is-invalidated',
];

$statelabels = [
    'active' => get_string('statusactive', 'mod_integrityquiz'),
    'suspended' => get_string('statussuspended', 'mod_integrityquiz'),
    'submitted' => get_string('statussubmitted', 'mod_integrityquiz'),
    'invalidated' => get_string('statusinvalidated', 'mod_integrityquiz'),
];

echo $OUTPUT->header();

// Report hero/header.
echo html_writer::start_div('iq-report-shell');
echo html_writer::start_div('iq-report-hero');
echo html_writer::start_div('iq-report-hero__copy');
echo html_writer::span(get_string('reporteyebrow', 'mod_integrityquiz'), 'iq-report-eyebrow');
echo html_writer::tag('h1', get_string('attemptreport', 'mod_integrityquiz'), ['class' => 'iq-report-title']);
echo html_writer::tag('p', format_string($quiz->name), ['class' => 'iq-report-subtitle']);
echo html_writer::end_div();
echo html_writer::start_div('iq-report-hero__actions');
if ($canmanagequestions) {
    echo html_writer::link(
        new moodle_url('/mod/integrityquiz/managequestions.php', ['id' => $cm->id]),
        get_string('editquestions', 'mod_integrityquiz'),
        ['class' => 'btn btn-light iq-report-topbtn']
    );
}
echo html_writer::link(
    new moodle_url('/grade/report/index.php', ['id' => $course->id]),
    get_string('opencoursegrades', 'mod_integrityquiz'),
    ['class' => 'btn btn-light iq-report-topbtn']
);
echo html_writer::link(
    new moodle_url('/mod/integrityquiz/view.php', ['id' => $cm->id]),
    get_string('backtoquiz', 'mod_integrityquiz'),
    ['class' => 'btn btn-outline-light iq-report-topbtn']
);
echo html_writer::end_div();
echo html_writer::end_div();

// KPI cards.
$avgdisplay = $averagegrade === false || $averagegrade === null
    ? '—'
    : format_float((float)$averagegrade, 2) . ' / ' . format_float((float)$quiz->grade, 2);
$stats = [
    ['value' => $totalattempts, 'label' => get_string('reporttotalattempts', 'mod_integrityquiz'), 'meta' => get_string('reportstudentsmeta', 'mod_integrityquiz', $studentcount), 'class' => 'is-primary'],
    ['value' => $submittedcount, 'label' => get_string('reportsubmitted', 'mod_integrityquiz'), 'meta' => get_string('reportgradedmeta', 'mod_integrityquiz'), 'class' => 'is-success'],
    ['value' => $suspendedcount, 'label' => get_string('reportsuspended', 'mod_integrityquiz'), 'meta' => get_string('reportneedsreview', 'mod_integrityquiz'), 'class' => 'is-warning'],
    ['value' => $avgdisplay, 'label' => get_string('reportaveragegrade', 'mod_integrityquiz'), 'meta' => get_string('reportviolationsmeta', 'mod_integrityquiz', $totalviolations), 'class' => 'is-secondary'],
];

echo html_writer::start_div('iq-report-kpis');
foreach ($stats as $stat) {
    echo html_writer::start_div('iq-report-kpi ' . $stat['class']);
    echo html_writer::span((string)$stat['value'], 'iq-report-kpi__value');
    echo html_writer::span($stat['label'], 'iq-report-kpi__label');
    echo html_writer::span($stat['meta'], 'iq-report-kpi__meta');
    echo html_writer::end_div();
}
echo html_writer::end_div();

// Search and state filters.
echo html_writer::start_div('iq-report-filtercard');
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'iq-report-filters']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::start_div('iq-report-filterfield iq-report-filterfield--search');
echo html_writer::tag('label', get_string('reportsearchstudent', 'mod_integrityquiz'), ['for' => 'iq-report-search']);
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'name' => 'search',
    'id' => 'iq-report-search',
    'value' => $search,
    'class' => 'form-control',
    'placeholder' => get_string('reportsearchplaceholder', 'mod_integrityquiz'),
]);
echo html_writer::end_div();
echo html_writer::start_div('iq-report-filterfield');
echo html_writer::tag('label', get_string('reportfilterstate', 'mod_integrityquiz'), ['for' => 'iq-report-state']);
echo html_writer::select($stateoptions, 'state', $statefilter, false, ['id' => 'iq-report-state', 'class' => 'form-select']);
echo html_writer::end_div();
echo html_writer::start_div('iq-report-filteractions');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('reportapplyfilters', 'mod_integrityquiz'),
    'class' => 'btn btn-primary',
]);
if ($search !== '' || $statefilter !== '') {
    echo html_writer::link(
        new moodle_url('/mod/integrityquiz/report.php', ['id' => $cm->id]),
        get_string('reportclearfilters', 'mod_integrityquiz'),
        ['class' => 'btn btn-outline-secondary']
    );
}
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Attempts table.
echo html_writer::start_div('iq-report-tablecard');
echo html_writer::start_div('iq-report-tablecard__head');
echo html_writer::start_div();
echo html_writer::tag('h2', get_string('reportattemptsheading', 'mod_integrityquiz'), ['class' => 'iq-report-tablecard__title']);
echo html_writer::tag('p', get_string('reportattemptscount', 'mod_integrityquiz', count($attempts)), ['class' => 'iq-report-tablecard__meta']);
echo html_writer::end_div();
echo html_writer::span(get_string('gradebooksyncshort', 'mod_integrityquiz'), 'iq-report-syncbadge');
echo html_writer::end_div();

if (!$attempts) {
    echo html_writer::start_div('iq-report-empty');
    echo html_writer::div('✓', 'iq-report-empty__icon', ['aria-hidden' => 'true']);
    echo html_writer::tag('h3', get_string('reportnoattempts', 'mod_integrityquiz'));
    echo html_writer::tag('p', get_string('reportnoattemptshelp', 'mod_integrityquiz'));
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('table-responsive iq-report-tablewrap');
    echo html_writer::start_tag('table', ['class' => 'table iq-report-table align-middle mb-0']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    foreach ([
        get_string('student', 'mod_integrityquiz'),
        get_string('attempt', 'mod_integrityquiz'),
        get_string('reportstarted', 'mod_integrityquiz'),
        get_string('state', 'mod_integrityquiz'),
        get_string('violations', 'mod_integrityquiz'),
        get_string('grade', 'mod_integrityquiz'),
        get_string('actions', 'mod_integrityquiz'),
    ] as $heading) {
        echo html_writer::tag('th', $heading, ['scope' => 'col']);
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($attempts as $attempt) {
        $reviewurl = new moodle_url('/mod/integrityquiz/review.php', [
            'id' => $cm->id,
            'attemptid' => $attempt->id,
        ]);
        $editresulturl = new moodle_url('/mod/integrityquiz/review.php', [
            'id' => $cm->id,
            'attemptid' => $attempt->id,
        ], 'editresult');
        $deleteurl = new moodle_url('/mod/integrityquiz/report.php', [
            'id' => $cm->id,
            'deleteattempt' => $attempt->id,
        ]);

        $fullname = fullname($attempt);
        $initials = '';
        if (!empty($attempt->firstname)) {
            $initials .= \core_text::strtoupper(\core_text::substr($attempt->firstname, 0, 1));
        }
        if (!empty($attempt->lastname)) {
            $initials .= \core_text::strtoupper(\core_text::substr($attempt->lastname, 0, 1));
        }
        if ($initials === '') {
            $initials = '?';
        }

        $state = $attempt->state;
        $stateclass = $stateclasses[$state] ?? 'is-neutral';
        $statelabel = $statelabels[$state] ?? ucfirst($state);
        $gradetext = get_string('gradependingteacher', 'mod_integrityquiz');
        $gradepercent = null;
        if ($attempt->grade !== null) {
            $gradetext = format_float((float)$attempt->grade, 2) . ' / ' . format_float((float)$quiz->grade, 2);
            if ((float)$quiz->grade > 0) {
                $gradepercent = max(0, min(100, round(((float)$attempt->grade / (float)$quiz->grade) * 100)));
            }
        }

        echo html_writer::start_tag('tr');

        echo html_writer::start_tag('td');
        echo html_writer::start_div('iq-report-student');
        echo html_writer::span($initials, 'iq-report-avatar', ['aria-hidden' => 'true']);
        echo html_writer::start_div('iq-report-student__copy');
        echo html_writer::span($fullname, 'iq-report-student__name');
        echo html_writer::span(s($attempt->email), 'iq-report-student__email');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_tag('td');

        echo html_writer::tag('td', html_writer::span('#' . (int)$attempt->attemptno, 'iq-report-attemptno'));

        echo html_writer::start_tag('td');
        echo html_writer::span(userdate($attempt->timestart, get_string('strftimedatetimeshort', 'langconfig')), 'iq-report-date');
        if (!empty($attempt->timefinish)) {
            echo html_writer::span(get_string('reportfinishedshort', 'mod_integrityquiz', userdate($attempt->timefinish, get_string('strftimedatetimeshort', 'langconfig'))), 'iq-report-date-sub');
        }
        echo html_writer::end_tag('td');

        echo html_writer::tag('td', html_writer::span($statelabel, 'iq-report-status ' . $stateclass));

        $violationclass = (int)$attempt->violations > 0 ? 'has-violations' : 'is-clean';
        echo html_writer::tag(
            'td',
            html_writer::span((string)(int)$attempt->violations, 'iq-report-violations ' . $violationclass)
        );

        echo html_writer::start_tag('td');
        echo html_writer::start_div('iq-report-grade');
        echo html_writer::span($gradetext, 'iq-report-grade__value');
        if ($gradepercent !== null) {
            echo html_writer::start_div('iq-report-grade__bar', ['aria-hidden' => 'true']);
            echo html_writer::div('', 'iq-report-grade__fill', ['style' => 'width:' . $gradepercent . '%']);
            echo html_writer::end_div();
            echo html_writer::span($gradepercent . '%', 'iq-report-grade__percent');
        }
        echo html_writer::end_div();
        echo html_writer::end_tag('td');

        echo html_writer::start_tag('td');
        echo html_writer::start_div('iq-report-rowactions');
        echo html_writer::link(
            $reviewurl,
            get_string('review', 'mod_integrityquiz'),
            ['class' => 'btn btn-sm btn-outline-primary iq-report-action']
        );
        if ($canmanageattempts && $attempt->state === 'submitted') {
            echo html_writer::link(
                $editresulturl,
                get_string('editresultgrade', 'mod_integrityquiz'),
                ['class' => 'btn btn-sm btn-primary iq-report-action']
            );
        }
        if ($canmanageattempts) {
            echo html_writer::link(
                $deleteurl,
                get_string('deleteattempt', 'mod_integrityquiz'),
                ['class' => 'btn btn-sm btn-outline-danger iq-report-action iq-report-action--delete']
            );
        }
        echo html_writer::end_div();
        echo html_writer::end_tag('td');

        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::div(get_string('gradebooksyncinfo', 'mod_integrityquiz'), 'iq-report-info');
echo html_writer::end_div();

echo $OUTPUT->footer();
