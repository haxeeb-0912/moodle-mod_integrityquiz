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
 * Question management interface.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);
$formaction = optional_param('formaction', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('integrityquiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('integrityquiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/integrityquiz:managequestions', $context);

$PAGE->set_url('/mod/integrityquiz/managequestions.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('managequestions', 'mod_integrityquiz'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('mod-integrityquiz');

if ($delete && confirm_sesskey()) {
    $DB->delete_records('integrityquiz_questions', [
        'id' => $delete,
        'integrityquizid' => $quiz->id,
    ]);
    redirect($PAGE->url, get_string('questiondeleted', 'mod_integrityquiz'));
}

if ($formaction === 'bulkimport' && data_submitted() && confirm_sesskey()) {
    $bulk = required_param('bulkcontent', PARAM_RAW);
    $questions = \mod_integrityquiz\local\parser::parse($bulk);

    if (!$questions) {
        redirect(
            $PAGE->url,
            get_string('parsefailed', 'mod_integrityquiz'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $sort = (int) $DB->get_field_sql(
        'SELECT COALESCE(MAX(sortorder), 0)
           FROM {integrityquiz_questions}
          WHERE integrityquizid = ?',
        [$quiz->id]
    );

    foreach ($questions as $question) {
        $sort++;
        $DB->insert_record('integrityquiz_questions', (object) [
            'integrityquizid' => $quiz->id,
            'qtype' => $question['qtype'],
            'questiontext' => $question['questiontext'],
            'optionsjson' => json_encode($question['options']),
            'correctjson' => json_encode($question['correct']),
            'feedback' => $question['feedback'],
            'marks' => $question['marks'],
            'sortorder' => $sort,
            'timecreated' => time(),
        ]);
    }

    redirect($PAGE->url, get_string('questionsimported', 'mod_integrityquiz', count($questions)));
}

if ($formaction === 'savequestion' && data_submitted() && confirm_sesskey()) {
    $questionid = required_param('questionid', PARAM_INT);
    $existing = $DB->get_record('integrityquiz_questions', [
        'id' => $questionid,
        'integrityquizid' => $quiz->id,
    ], '*', MUST_EXIST);

    $qtype = required_param('qtype', PARAM_ALPHANUMEXT);
    if (!in_array($qtype, ['multichoice', 'multiselect', 'truefalse'], true)) {
        throw new moodle_exception('invalidquestiontype', 'mod_integrityquiz');
    }

    $questiontext = trim(required_param('questiontext', PARAM_RAW));
    $optionsraw = trim(required_param('optionsraw', PARAM_RAW));
    $correctraw = trim(required_param('correctraw', PARAM_RAW));
    $feedback = trim(optional_param('feedback', '', PARAM_RAW));
    $marks = required_param('marks', PARAM_FLOAT);

    $options = [];
    foreach (preg_split('/\R/', $optionsraw) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(.+)$/u', $line, $matches)) {
            redirect(
                $PAGE->url,
                get_string('invalidoptionsformat', 'mod_integrityquiz'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        $key = strtoupper(trim($matches[1]));
        $options[$key] = clean_param(trim($matches[2]), PARAM_TEXT);
    }

    $correct = array_values(array_unique(array_filter(array_map(
        static fn($value) => strtoupper(trim($value)),
        preg_split('/[,;]+/', $correctraw)
    ))));

    if ($questiontext === '' || count($options) < 2 || !$correct || $marks <= 0) {
        redirect(
            $PAGE->url,
            get_string('invalidquestionedit', 'mod_integrityquiz'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    foreach ($correct as $key) {
        if (!array_key_exists($key, $options)) {
            redirect(
                $PAGE->url,
                get_string('correctkeymissing', 'mod_integrityquiz', $key),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    if ($qtype !== 'multiselect' && count($correct) !== 1) {
        redirect(
            $PAGE->url,
            get_string('singleanswerrequired', 'mod_integrityquiz'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $existing->qtype = $qtype;
    $existing->questiontext = $questiontext;
    $existing->optionsjson = json_encode($options);
    $existing->correctjson = json_encode($correct);
    $existing->feedback = $feedback;
    $existing->marks = $marks;
    $DB->update_record('integrityquiz_questions', $existing);

    redirect($PAGE->url, get_string('questionupdated', 'mod_integrityquiz'));
}

$questions = $DB->get_records(
    'integrityquiz_questions',
    ['integrityquizid' => $quiz->id],
    'sortorder ASC'
);
$questioncount = count($questions);
$totalmarks = 0.0;
foreach ($questions as $question) {
    $totalmarks += (float) $question->marks;
}

if (!empty($quiz->questionsperpage)) {
    if ((int) $quiz->questionsperpage === 1) {
        $layoutvalue = get_string('onequestionperpage', 'mod_integrityquiz');
    } else {
        $layoutvalue = get_string('questionsperpagex', 'mod_integrityquiz', (int) $quiz->questionsperpage);
    }
} else {
    $layoutvalue = get_string('allquestionsonepage', 'mod_integrityquiz');
}

$viewurl = new moodle_url('/mod/integrityquiz/view.php', ['id' => $cm->id]);

echo $OUTPUT->header();

echo html_writer::start_div('integrity-qm-page');

// Hero header.
echo html_writer::start_div('integrity-qm-hero');
echo html_writer::start_div('integrity-qm-hero__content');
echo html_writer::div(get_string('pluginname', 'mod_integrityquiz'), 'integrity-qm-eyebrow');
echo html_writer::tag('h2', get_string('managequestions', 'mod_integrityquiz'), ['class' => 'integrity-qm-title']);
echo html_writer::div(format_string($quiz->name), 'integrity-qm-subtitle');
echo html_writer::end_div();
echo html_writer::start_div('integrity-qm-hero__actions');
echo html_writer::link(
    $viewurl,
    get_string('backtoquiz', 'mod_integrityquiz'),
    ['class' => 'btn btn-light integrity-qm-hero-button']
);
echo html_writer::end_div();
echo html_writer::end_div();

// Overview cards.
echo html_writer::start_div('integrity-qm-stats');

echo html_writer::start_div('integrity-qm-stat');
echo html_writer::div((string) $questioncount, 'integrity-qm-stat__value');
echo html_writer::div(get_string('questioncountinfo', 'mod_integrityquiz', $questioncount), 'integrity-qm-stat__label');
echo html_writer::end_div();

echo html_writer::start_div('integrity-qm-stat');
echo html_writer::div(format_float($totalmarks, 2), 'integrity-qm-stat__value');
echo html_writer::div(get_string('marks', 'mod_integrityquiz'), 'integrity-qm-stat__label');
echo html_writer::end_div();

echo html_writer::start_div('integrity-qm-stat integrity-qm-stat--wide');
echo html_writer::div(get_string('questionsperpage', 'mod_integrityquiz'), 'integrity-qm-stat__kicker');
echo html_writer::div(s($layoutvalue), 'integrity-qm-stat__layout');
echo html_writer::end_div();

echo html_writer::end_div();

// Edit existing question.
if ($edit) {
    $question = $DB->get_record('integrityquiz_questions', [
        'id' => $edit,
        'integrityquizid' => $quiz->id,
    ], '*', MUST_EXIST);
    $options = json_decode($question->optionsjson, true) ?: [];
    $correct = json_decode($question->correctjson, true) ?: [];
    $optionsraw = [];
    foreach ($options as $key => $label) {
        $optionsraw[] = $key . ': ' . $label;
    }

    echo html_writer::start_div('integrity-qm-panel integrity-qm-editor', ['id' => 'editquestion']);
    echo html_writer::start_div('integrity-qm-panel__header');
    echo html_writer::start_div('integrity-qm-panel__heading');
    echo html_writer::div(get_string('questionx', 'mod_integrityquiz', $question->sortorder), 'integrity-qm-panel__eyebrow');
    echo html_writer::tag('h3', get_string('editquestion', 'mod_integrityquiz'), ['class' => 'integrity-qm-panel__title']);
    echo html_writer::end_div();
    echo html_writer::link($PAGE->url, get_string('cancel'), ['class' => 'btn btn-outline-secondary']);
    echo html_writer::end_div();

    echo html_writer::div(get_string('questioneditwarning', 'mod_integrityquiz'), 'integrity-qm-notice integrity-qm-notice--warning');

    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'integrity-qm-form']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'formaction', 'value' => 'savequestion']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $question->id]);

    echo html_writer::start_div('integrity-qm-form-grid');

    echo html_writer::start_div('integrity-qm-field');
    echo html_writer::tag('label', get_string('type', 'mod_integrityquiz'), [
        'for' => 'qtype',
        'class' => 'form-label integrity-qm-label',
    ]);
    echo html_writer::select([
        'multichoice' => get_string('qtypemcq', 'mod_integrityquiz'),
        'multiselect' => get_string('qtypemultiselect', 'mod_integrityquiz'),
        'truefalse' => get_string('qtypetruefalse', 'mod_integrityquiz'),
    ], 'qtype', $question->qtype, false, [
        'id' => 'qtype',
        'class' => 'form-select',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('integrity-qm-field');
    echo html_writer::tag('label', get_string('marks', 'mod_integrityquiz'), [
        'for' => 'marks',
        'class' => 'form-label integrity-qm-label',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'marks',
        'id' => 'marks',
        'value' => format_float($question->marks, 2, true, false),
        'step' => '0.01',
        'min' => '0.01',
        'class' => 'form-control',
        'required' => 'required',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('integrity-qm-field integrity-qm-field--full');
    echo html_writer::tag('label', get_string('question'), [
        'for' => 'questiontext',
        'class' => 'form-label integrity-qm-label',
    ]);
    echo html_writer::tag('textarea', s($question->questiontext), [
        'name' => 'questiontext',
        'id' => 'questiontext',
        'rows' => 4,
        'class' => 'form-control',
        'required' => 'required',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('integrity-qm-field integrity-qm-field--full');
    echo html_writer::tag('label', get_string('answeroptions', 'mod_integrityquiz'), [
        'for' => 'optionsraw',
        'class' => 'form-label integrity-qm-label',
    ]);
    echo html_writer::tag('textarea', s(implode("\n", $optionsraw)), [
        'name' => 'optionsraw',
        'id' => 'optionsraw',
        'rows' => 7,
        'class' => 'form-control font-monospace',
        'required' => 'required',
    ]);
    echo html_writer::div(get_string('answeroptionshelp', 'mod_integrityquiz'), 'form-text integrity-qm-help');
    echo html_writer::end_div();

    echo html_writer::start_div('integrity-qm-field');
    echo html_writer::tag('label', get_string('correctanswerkeys', 'mod_integrityquiz'), [
        'for' => 'correctraw',
        'class' => 'form-label integrity-qm-label',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'correctraw',
        'id' => 'correctraw',
        'value' => implode(',', $correct),
        'class' => 'form-control',
        'required' => 'required',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('integrity-qm-field');
    echo html_writer::tag('label', get_string('feedback', 'mod_integrityquiz'), [
        'for' => 'feedback',
        'class' => 'form-label integrity-qm-label',
    ]);
    echo html_writer::tag('textarea', s($question->feedback), [
        'name' => 'feedback',
        'id' => 'feedback',
        'rows' => 3,
        'class' => 'form-control',
    ]);
    echo html_writer::end_div();

    echo html_writer::end_div();

    echo html_writer::start_div('integrity-qm-form-actions');
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('savechanges'),
        'class' => 'btn btn-primary btn-lg',
    ]);
    echo html_writer::link($PAGE->url, get_string('cancel'), ['class' => 'btn btn-outline-secondary btn-lg']);
    echo html_writer::end_div();

    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

// Bulk import workspace.
echo html_writer::start_div('integrity-qm-workspace');

echo html_writer::start_div('integrity-qm-panel integrity-qm-import');
echo html_writer::start_div('integrity-qm-panel__header integrity-qm-panel__header--stack');
echo html_writer::start_div('integrity-qm-panel__heading');
echo html_writer::div(get_string('editquestions', 'mod_integrityquiz'), 'integrity-qm-panel__eyebrow');
echo html_writer::tag('h3', get_string('bulkcontent', 'mod_integrityquiz'), ['class' => 'integrity-qm-panel__title']);
echo html_writer::end_div();
echo html_writer::div(get_string('bulkhelp', 'mod_integrityquiz'), 'integrity-qm-panel__description');
echo html_writer::end_div();

echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'integrity-qm-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'formaction', 'value' => 'bulkimport']);
echo html_writer::tag('label', get_string('bulkcontent', 'mod_integrityquiz'), [
    'for' => 'bulkcontent',
    'class' => 'form-label integrity-qm-label',
]);
echo html_writer::tag('textarea', '', [
    'name' => 'bulkcontent',
    'id' => 'bulkcontent',
    'rows' => 18,
    'class' => 'form-control font-monospace integrity-qm-bulktextarea',
    'required' => 'required',
    'placeholder' => get_string('bulkexample', 'mod_integrityquiz'),
    'spellcheck' => 'false',
]);
echo html_writer::start_div('integrity-qm-import-actions');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('splitandimport', 'mod_integrityquiz'),
    'class' => 'btn btn-primary btn-lg',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Format helper.
echo html_writer::start_div('integrity-qm-panel integrity-qm-guide');
echo html_writer::div(get_string('qtypemcq', 'mod_integrityquiz'), 'integrity-qm-guide__badge');
echo html_writer::tag('h3', get_string('bulkexample', 'mod_integrityquiz'), [
    'class' => 'sr-only visually-hidden',
]);
echo html_writer::tag('div', get_string('bulkhelp', 'mod_integrityquiz'), ['class' => 'integrity-qm-guide__text']);
echo html_writer::tag('pre', s(get_string('bulkexample', 'mod_integrityquiz')), ['class' => 'integrity-qm-code']);
echo html_writer::start_div('integrity-qm-guide__chips');
echo html_writer::span('MCQ', 'integrity-qm-chip');
echo html_writer::span('MULTISELECT', 'integrity-qm-chip');
echo html_writer::span('TRUEFALSE', 'integrity-qm-chip');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();

// Question list.
echo html_writer::start_div('integrity-qm-list-section');
echo html_writer::start_div('integrity-qm-list-header');
echo html_writer::start_div('integrity-qm-list-heading');
echo html_writer::tag('h3', get_string('editquestions', 'mod_integrityquiz'), ['class' => 'integrity-qm-list-title']);
echo html_writer::div(get_string('questioncountinfo', 'mod_integrityquiz', $questioncount), 'integrity-qm-list-subtitle');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::div(get_string('questioneditwarning', 'mod_integrityquiz'), 'integrity-qm-notice integrity-qm-notice--warning');

if (!$questions) {
    echo html_writer::start_div('integrity-qm-empty');
    echo html_writer::div('?', 'integrity-qm-empty__icon', ['aria-hidden' => 'true']);
    echo html_writer::tag('h4', get_string('noquestionsyet', 'mod_integrityquiz'), ['class' => 'integrity-qm-empty__title']);
    echo html_writer::div(get_string('bulkhelp', 'mod_integrityquiz'), 'integrity-qm-empty__text');
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('integrity-qm-question-list');

    foreach ($questions as $question) {
        $editurl = new moodle_url($PAGE->url, ['edit' => $question->id]);
        $deleteurl = new moodle_url($PAGE->url, [
            'delete' => $question->id,
            'sesskey' => sesskey(),
        ]);
        $options = json_decode($question->optionsjson, true) ?: [];
        $correct = json_decode($question->correctjson, true) ?: [];

        $typelabels = [
            'multichoice' => get_string('qtypemcq', 'mod_integrityquiz'),
            'multiselect' => get_string('qtypemultiselect', 'mod_integrityquiz'),
            'truefalse' => get_string('qtypetruefalse', 'mod_integrityquiz'),
        ];
        $typelabel = $typelabels[$question->qtype] ?? s($question->qtype);

        echo html_writer::start_div('integrity-qm-question-card');
        echo html_writer::start_div('integrity-qm-question-card__top');
        echo html_writer::start_div('integrity-qm-question-card__number', ['aria-label' => get_string('questionx', 'mod_integrityquiz', $question->sortorder)]);
        echo html_writer::span((string) $question->sortorder, 'integrity-qm-question-card__number-value');
        echo html_writer::end_div();

        echo html_writer::start_div('integrity-qm-question-card__main');
        echo html_writer::start_div('integrity-qm-question-card__meta');
        echo html_writer::span(s($typelabel), 'integrity-qm-type-badge');
        echo html_writer::span(
            format_float($question->marks, 2) . ' ' . get_string('marks', 'mod_integrityquiz'),
            'integrity-qm-marks-badge'
        );
        echo html_writer::end_div();
        echo html_writer::div(
            format_text($question->questiontext, FORMAT_PLAIN),
            'integrity-qm-question-card__text'
        );
        echo html_writer::end_div();

        echo html_writer::start_div('integrity-qm-question-card__actions');
        echo html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-outline-primary btn-sm']);
        echo html_writer::link($deleteurl, get_string('delete'), ['class' => 'btn btn-outline-danger btn-sm']);
        echo html_writer::end_div();
        echo html_writer::end_div();

        if ($options) {
            echo html_writer::start_div('integrity-qm-options');
            foreach ($options as $key => $label) {
                $iscorrect = in_array((string) $key, array_map('strval', $correct), true);
                $classes = 'integrity-qm-option';
                if ($iscorrect) {
                    $classes .= ' integrity-qm-option--correct';
                }
                echo html_writer::start_div($classes);
                echo html_writer::span(s((string) $key), 'integrity-qm-option__key');
                echo html_writer::span(s((string) $label), 'integrity-qm-option__label');
                if ($iscorrect) {
                    echo html_writer::span(get_string('correctanswerkeys', 'mod_integrityquiz'), 'integrity-qm-option__correct');
                }
                echo html_writer::end_div();
            }
            echo html_writer::end_div();
        }

        echo html_writer::end_div();
    }

    echo html_writer::end_div();
}

echo html_writer::end_div();

echo html_writer::start_div('integrity-qm-footer-actions');
echo html_writer::link($viewurl, get_string('backtoquiz', 'mod_integrityquiz'), ['class' => 'btn btn-secondary btn-lg']);
echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
