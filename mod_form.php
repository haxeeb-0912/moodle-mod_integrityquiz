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
 * Activity settings form for Integrity Quiz.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_integrityquiz_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('integrityquizname', 'mod_integrityquiz'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'timinghdr', get_string('timing', 'mod_integrityquiz'));
        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'mod_integrityquiz'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'mod_integrityquiz'), ['optional' => true]);
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'mod_integrityquiz'), ['optional' => true]);
        $mform->setDefault('timelimit', 3600);
        $mform->addElement('select', 'attemptsallowed', get_string('attemptsallowed', 'mod_integrityquiz'), array_combine(range(1, 10), range(1, 10)));
        $mform->setDefault('attemptsallowed', 1);

        $mform->addElement('header', 'layouthdr', get_string('layoutsettings', 'mod_integrityquiz'));
        $mform->addElement('select', 'questionsperpage', get_string('questionsperpage', 'mod_integrityquiz'), [
            1 => get_string('onequestionperpage', 'mod_integrityquiz'),
            2 => get_string('questionsperpagex', 'mod_integrityquiz', 2),
            5 => get_string('questionsperpagex', 'mod_integrityquiz', 5),
            10 => get_string('questionsperpagex', 'mod_integrityquiz', 10),
            0 => get_string('allquestionsonepage', 'mod_integrityquiz'),
        ]);
        $mform->setDefault('questionsperpage', 1);
        $mform->addHelpButton('questionsperpage', 'questionsperpage', 'mod_integrityquiz');

        $mform->addElement(
            'duration',
            'questiontimelimit',
            get_string('questiontimelimit', 'mod_integrityquiz'),
            ['optional' => true]
        );
        $mform->setDefault('questiontimelimit', 0);
        $mform->addHelpButton('questiontimelimit', 'questiontimelimit', 'mod_integrityquiz');

        $mform->addElement('text', 'grade', get_string('maximumgrade', 'mod_integrityquiz'));
        $mform->setType('grade', PARAM_FLOAT);
        $mform->setDefault('grade', 100);

        $mform->addElement('header', 'integrityhdr', get_string('integritysettings', 'mod_integrityquiz'));
        $mform->addElement('select', 'integritypolicy', get_string('integritypolicy', 'mod_integrityquiz'), [
            'practice' => get_string('policypractice', 'mod_integrityquiz'),
            'standard' => get_string('policystandard', 'mod_integrityquiz'),
            'strict' => get_string('policystrict', 'mod_integrityquiz'),
        ]);
        $mform->setDefault('integritypolicy', 'strict');
        $mform->addElement('advcheckbox', 'requirecamera', get_string('requirecamera', 'mod_integrityquiz'));
        $mform->setDefault('requirecamera', 1);

        // Kept as a hidden legacy field so old activities upgrade cleanly. Integrity Quiz now
        // always runs in the normal Moodle browser page and never requests browser fullscreen.
        $mform->addElement('hidden', 'requirefullscreen', 0);
        $mform->setType('requirefullscreen', PARAM_INT);
        $mform->setDefault('requirefullscreen', 0);

        $mform->addElement('advcheckbox', 'suspendontabswitch', get_string('suspendontabswitch', 'mod_integrityquiz'));
        $mform->setDefault('suspendontabswitch', 1);
        $mform->addHelpButton('suspendontabswitch', 'suspendontabswitch', 'mod_integrityquiz');
        $mform->addElement('text', 'snapshotmin', get_string('snapshotmin', 'mod_integrityquiz'));
        $mform->setType('snapshotmin', PARAM_INT);
        $mform->setDefault('snapshotmin', 60);
        $mform->addElement('text', 'snapshotmax', get_string('snapshotmax', 'mod_integrityquiz'));
        $mform->setType('snapshotmax', PARAM_INT);
        $mform->setDefault('snapshotmax', 120);
        $mform->addElement('text', 'retentiondays', get_string('retentiondays', 'mod_integrityquiz'));
        $mform->setType('retentiondays', PARAM_INT);
        $mform->setDefault('retentiondays', 30);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((float)$data['grade'] <= 0) {
            $errors['grade'] = get_string('errpositivegrade', 'mod_integrityquiz');
        }
        if ((int)$data['snapshotmin'] < 10) {
            $errors['snapshotmin'] = get_string('errsnapshotmin', 'mod_integrityquiz');
        }
        if ((int)$data['snapshotmax'] < (int)$data['snapshotmin']) {
            $errors['snapshotmax'] = get_string('errsnapshotmax', 'mod_integrityquiz');
        }
        if (!empty($data['questiontimelimit']) && (int)$data['questionsperpage'] !== 1) {
            $errors['questiontimelimit'] = get_string('errquestiontimerlayout', 'mod_integrityquiz');
        }
        if (!empty($data['timeopen']) && !empty($data['timeclose']) && $data['timeclose'] <= $data['timeopen']) {
            $errors['timeclose'] = get_string('errtimeclose', 'mod_integrityquiz');
        }
        return $errors;
    }
}
