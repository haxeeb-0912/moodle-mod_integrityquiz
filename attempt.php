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
 * Secure assessment attempt page.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('integrityquiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('integrityquiz', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/integrityquiz:attempt', $context);
require_sesskey();

if ($quiz->timeopen && time() < $quiz->timeopen) {
    throw new moodle_exception('notavailable', 'mod_integrityquiz');
}
if ($quiz->timeclose && time() > $quiz->timeclose) {
    throw new moodle_exception('notavailable', 'mod_integrityquiz');
}

$attempt = $DB->get_record_select(
    'integrityquiz_attempts',
    'integrityquizid = ? AND userid = ? AND state IN (?, ?)',
    [$quiz->id, $USER->id, 'active', 'suspended']
);

if (!$attempt) {
    $used = $DB->count_records('integrityquiz_attempts', [
        'integrityquizid' => $quiz->id,
        'userid' => $USER->id,
    ]);
    if ($used >= $quiz->attemptsallowed) {
        throw new moodle_exception('nomoreattempts', 'mod_integrityquiz');
    }
    $maxattemptno = (int)$DB->get_field_sql(
        'SELECT COALESCE(MAX(attemptno), 0) FROM {integrityquiz_attempts} WHERE integrityquizid = ? AND userid = ?',
        [$quiz->id, $USER->id]
    );
    $attempt = (object)[
        'integrityquizid' => $quiz->id,
        'userid' => $USER->id,
        'attemptno' => $maxattemptno + 1,
        'state' => 'active',
        'timestart' => time(),
        'timefinish' => 0,
        'timesuspended' => 0,
        'lastheartbeat' => time(),
        'pageid' => null,
        'violations' => 0,
        'rawscore' => null,
        'grade' => null,
        'timemodified' => time(),
    ];
    $attempt->id = $DB->insert_record('integrityquiz_attempts', $attempt);
    \mod_integrityquiz\local\engine::log_event($attempt, 'attemptstarted', 'Attempt started');
}

$questions = $DB->get_records('integrityquiz_questions', ['integrityquizid' => $quiz->id], 'sortorder ASC');
if (!$questions) {
    throw new moodle_exception('noquestionsyet', 'mod_integrityquiz');
}
$responses = $DB->get_records('integrityquiz_responses', ['attemptid' => $attempt->id], '', 'questionid,responsejson');
$totalquestions = count($questions);

$PAGE->set_url('/mod/integrityquiz/attempt.php', ['id' => $cm->id, 'sesskey' => sesskey()]);
$PAGE->set_title(format_string($quiz->name));
$PAGE->set_heading(format_string($quiz->name));
$PAGE->add_body_class('mod-integrityquiz secure-attempt');
$PAGE->requires->js_call_amd('mod_integrityquiz/secure', 'init', [[
    'attemptid' => $attempt->id,
    'cmid' => $cm->id,
    'requirecamera' => (bool)$quiz->requirecamera,
    'suspendontabswitch' => (bool)$quiz->suspendontabswitch,
    'snapshotmin' => (int)$quiz->snapshotmin,
    'snapshotmax' => (int)$quiz->snapshotmax,
    'timelimit' => (int)$quiz->timelimit,
    'questiontimelimit' => isset($quiz->questiontimelimit) ? (int)$quiz->questiontimelimit : 0,
    'timestart' => (int)$attempt->timestart,
    'questionsperpage' => isset($quiz->questionsperpage) ? (int)$quiz->questionsperpage : 1,
    'previouslabel' => get_string('previous'),
    'nextlabel' => get_string('next'),
    'questionprogress' => get_string('questionprogress', 'mod_integrityquiz'),
    'submitconfirm' => get_string('submitconfirm', 'mod_integrityquiz'),
    'cameraactive' => get_string('cameraactive', 'mod_integrityquiz'),
    'cameraoptional' => get_string('cameraoptional', 'mod_integrityquiz'),
    'camerarequired' => get_string('camerarequired', 'mod_integrityquiz'),
    'questiontimerlabel' => get_string('questiontimerlabel', 'mod_integrityquiz'),
    'questiontimeexpired' => get_string('questiontimeexpired', 'mod_integrityquiz'),
    'answerbeforecontinue' => get_string('answerbeforecontinue', 'mod_integrityquiz'),
]]);

echo $OUTPUT->header();

if ($attempt->state === 'suspended') {
    echo html_writer::start_div('iq-suspended-card');
    echo html_writer::div('!', 'iq-suspended-card__icon', ['aria-hidden' => 'true']);
    echo html_writer::tag('h2', get_string('attemptsuspended', 'mod_integrityquiz'));
    echo html_writer::tag('p', get_string('suspendedmessage', 'mod_integrityquiz'));
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('integrity-attempt-app iq-fixed-attempt-shell');

// Brand area.
echo html_writer::start_div('iq-attempt-brand');
echo html_writer::div('✓', 'iq-attempt-brand__shield', ['aria-hidden' => 'true']);
echo html_writer::start_div('iq-attempt-brand__copy');
echo html_writer::tag('div', get_string('pluginname', 'mod_integrityquiz'), ['class' => 'iq-attempt-brand__name']);
echo html_writer::tag('div', format_string($quiz->name), ['class' => 'iq-attempt-brand__subtitle']);
echo html_writer::end_div();
echo html_writer::end_div();

// Camera/system precheck.
echo html_writer::start_div('integrity-precheck iq-precheck-card', ['id' => 'integrity-precheck']);
echo html_writer::start_div('iq-precheck-card__intro');
echo html_writer::div('●', 'iq-precheck-card__eyebrow-dot', ['aria-hidden' => 'true']);
echo html_writer::start_div();
echo html_writer::tag('h2', get_string('systemcheck', 'mod_integrityquiz'), ['class' => 'iq-precheck-card__title']);
echo html_writer::tag('p', get_string('systemcheckdesc', 'mod_integrityquiz'), ['class' => 'iq-precheck-card__description']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::start_div('iq-precheck-card__body');
echo html_writer::start_div('iq-camera-preview-wrap');
echo html_writer::tag('video', '', [
    'id' => 'integrity-camera',
    'autoplay' => 'autoplay',
    'muted' => 'muted',
    'playsinline' => 'playsinline',
]);
echo html_writer::div(get_string('camerachecklabel', 'mod_integrityquiz'), 'iq-camera-preview-label');
echo html_writer::end_div();
echo html_writer::start_div('iq-precheck-card__actions');
echo html_writer::div('', 'iq-precheck-status', ['id' => 'integrity-status']);
echo html_writer::tag('button', get_string('beginassessment', 'mod_integrityquiz'), [
    'type' => 'button',
    'id' => 'integrity-enter',
    'class' => 'btn btn-primary iq-primary-action',
]);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Secure question experience.
echo html_writer::start_div('integrity-questions iq-assessment-stage iq-fixed-attempt-stage', [
    'id' => 'integrity-questions',
    'hidden' => 'hidden',
]);

// Floating status cards from Design 4.
echo html_writer::start_div('iq-status-grid');

echo html_writer::start_div('iq-status-card iq-status-card--progress');
echo html_writer::div('◔', 'iq-status-card__icon', ['aria-hidden' => 'true']);
echo html_writer::start_div('iq-status-card__content');
echo html_writer::tag('span', get_string('assessmentprogress', 'mod_integrityquiz'), ['class' => 'iq-status-card__label']);
echo html_writer::tag('strong', '0%', ['class' => 'iq-status-card__value', 'id' => 'integrity-progress-text']);
echo html_writer::div(html_writer::div('', 'iq-status-card__progress-fill', ['id' => 'integrity-progress-bar']), 'iq-status-card__progress');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('iq-status-card iq-status-card--time');
echo html_writer::div('◷', 'iq-status-card__icon', ['aria-hidden' => 'true']);
echo html_writer::start_div('iq-status-card__content');
echo html_writer::tag('span', get_string('timeleft', 'mod_integrityquiz'), ['class' => 'iq-status-card__label']);
echo html_writer::tag('strong', '--:--:--', ['class' => 'iq-status-card__value iq-status-card__value--timer', 'id' => 'integrity-timer']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('iq-status-card iq-status-card--camera');
echo html_writer::div('▣', 'iq-status-card__icon', ['aria-hidden' => 'true']);
echo html_writer::start_div('iq-status-card__content');
echo html_writer::tag('span', get_string('camera', 'mod_integrityquiz'), ['class' => 'iq-status-card__label']);
echo html_writer::tag('strong', get_string('checkingcamera', 'mod_integrityquiz'), [
    'class' => 'iq-status-card__value iq-camera-state',
    'id' => 'integrity-camera-state',
]);
echo html_writer::end_div();
echo html_writer::span('', 'iq-live-dot', ['aria-hidden' => 'true']);
echo html_writer::end_div();

echo html_writer::start_div('iq-status-card iq-status-card--warning');
echo html_writer::div('!', 'iq-status-card__icon', ['aria-hidden' => 'true']);
echo html_writer::start_div('iq-status-card__content');
echo html_writer::tag('strong', get_string('donotswitchtab', 'mod_integrityquiz'), ['class' => 'iq-status-card__warning-title']);
echo html_writer::tag('span', get_string('tabswitchwarning', 'mod_integrityquiz'), ['class' => 'iq-status-card__warning-text']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();

// Question cards.
echo html_writer::start_div('iq-question-canvas');
$n = 0;
foreach ($questions as $q) {
    $n++;
    $options = json_decode($q->optionsjson, true) ?: [];
    $saved = isset($responses[$q->id]) ? json_decode($responses[$q->id]->responsejson, true) : null;

    echo html_writer::start_div('integrity-question iq-question-card', [
        'data-questionid' => $q->id,
        'data-questionindex' => $n - 1,
        'data-questionnumber' => $n,
    ]);

    echo html_writer::start_div('iq-question-card__meta');
    echo html_writer::tag(
        'span',
        get_string('questionprogressstatic', 'mod_integrityquiz', (object)['current' => $n, 'total' => $totalquestions]),
        ['class' => 'iq-question-card__counter']
    );
    if (!empty($quiz->questiontimelimit)) {
        echo html_writer::tag(
            'span',
            get_string('questiontimerpending', 'mod_integrityquiz'),
            [
                'class' => 'iq-question-card__timer',
                'data-questiontimer' => $q->id,
                'aria-live' => 'polite',
            ]
        );
    }
    echo html_writer::tag(
        'span',
        get_string('questionmarks', 'mod_integrityquiz', format_float($q->marks, 2)),
        ['class' => 'iq-question-card__marks']
    );
    echo html_writer::end_div();

    echo html_writer::div(format_text($q->questiontext, FORMAT_PLAIN), 'iq-question-card__text');
    echo html_writer::tag('p', get_string('selectbestanswer', 'mod_integrityquiz'), ['class' => 'iq-question-card__instruction']);
    echo html_writer::start_div('iq-option-list');

    $inputtype = ($q->qtype === 'multiselect') ? 'checkbox' : 'radio';
    foreach ($options as $key => $label) {
        $checked = is_array($saved)
            ? in_array((string)$key, array_map('strval', $saved), true)
            : ((string)$saved === (string)$key);
        $inputid = 'iq-answer-' . $q->id . '-' . clean_param((string)$key, PARAM_ALPHANUMEXT);
        $attrs = [
            'type' => $inputtype,
            'id' => $inputid,
            'name' => 'q' . $q->id . ($inputtype === 'checkbox' ? '[]' : ''),
            'value' => $key,
            'class' => 'integrity-answer iq-option-card__input',
            'data-questionid' => $q->id,
        ];
        if ($checked) {
            $attrs['checked'] = 'checked';
        }

        $cardclasses = 'iq-option-card' . ($checked ? ' is-selected' : '');
        echo html_writer::start_tag('label', ['class' => $cardclasses, 'for' => $inputid]);
        echo html_writer::empty_tag('input', $attrs);
        echo html_writer::span(s((string)$key), 'iq-option-card__key', ['aria-hidden' => 'true']);
        echo html_writer::span(s($label), 'iq-option-card__label');
        echo html_writer::span('✓', 'iq-option-card__check', ['aria-hidden' => 'true']);
        echo html_writer::end_tag('label');
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo html_writer::end_div();

// Bottom navigation tray.
echo html_writer::start_div('integrity-pagination iq-bottom-nav', ['id' => 'integrity-pagination']);
echo html_writer::tag('button', '← ' . get_string('previous'), [
    'type' => 'button',
    'id' => 'integrity-prev',
    'class' => 'btn iq-nav-button iq-nav-button--previous',
]);

echo html_writer::start_div('iq-question-nav-wrap');
echo html_writer::div('', 'integrity-page-status iq-page-status', ['id' => 'integrity-page-status']);
echo html_writer::start_div('iq-question-nav', ['id' => 'integrity-question-nav', 'role' => 'navigation', 'aria-label' => get_string('questionnavigation', 'mod_integrityquiz')]);
$n = 0;
foreach ($questions as $q) {
    $n++;
    $saved = isset($responses[$q->id]) ? json_decode($responses[$q->id]->responsejson, true) : null;
    $answered = is_array($saved) ? !empty($saved) : ($saved !== null && $saved !== '');
    $navclasses = 'iq-question-nav__item' . ($answered ? ' is-answered' : '');
    echo html_writer::tag('button', (string)$n, [
        'type' => 'button',
        'class' => $navclasses,
        'data-questionindex' => $n - 1,
        'data-questionid' => $q->id,
        'aria-label' => get_string('questionx', 'mod_integrityquiz', $n),
    ]);
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('iq-bottom-nav__actions');
echo html_writer::tag('button', get_string('next') . ' →', [
    'type' => 'button',
    'id' => 'integrity-next',
    'class' => 'btn btn-primary iq-nav-button iq-nav-button--next',
]);
echo html_writer::tag('button', get_string('submitattempt', 'mod_integrityquiz') . ' ✓', [
    'type' => 'button',
    'id' => 'integrity-submit',
    'class' => 'btn btn-primary iq-nav-button iq-nav-button--submit',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // #integrity-questions.

echo html_writer::div(
    fullname($USER) . ' • ' . get_string('attempt', 'mod_integrityquiz') . ' ' . $attempt->attemptno,
    'integrity-watermark',
    ['aria-hidden' => 'true']
);
echo html_writer::end_div(); // .integrity-attempt-app.

echo $OUTPUT->footer();
