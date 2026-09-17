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
 * English language strings for Integrity Quiz.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Integrity Quiz';
$string['modulename'] = 'Integrity Quiz';
$string['modulenameplural'] = 'Integrity Quizzes';
$string['integrityquizname'] = 'Integrity Quiz name';
$string['pluginadministration'] = 'Integrity Quiz administration';
$string['integritysettings'] = 'Integrity monitoring';
$string['integritypolicy'] = 'Integrity policy';
$string['policypractice'] = 'Practice — record events only';
$string['policystandard'] = 'Standard — suspend after repeated serious events';
$string['policystrict'] = 'Strict — suspend immediately on serious events';
$string['requirecamera'] = 'Require camera';
$string['requirefullscreen'] = 'Require fullscreen';
$string['suspendontabswitch'] = 'Suspend when the assessment tab loses visibility';
$string['suspendontabswitch_help'] = 'When enabled, leaving the assessment tab after the attempt begins suspends the attempt. Integrity Quiz runs in the normal Moodle browser page and does not request fullscreen.';
$string['snapshotmin'] = 'Minimum random snapshot interval (seconds)';
$string['snapshotmax'] = 'Maximum random snapshot interval (seconds)';
$string['retentiondays'] = 'Evidence retention (days)';
$string['timing'] = 'Timing';
$string['timeopen'] = 'Open the assessment';
$string['timeclose'] = 'Close the assessment';
$string['timelimit'] = 'Time limit';
$string['attemptsallowed'] = 'Attempts allowed';
$string['layoutsettings'] = 'Question layout';
$string['questionsperpage'] = 'Questions per page';
$string['questionsperpage_help'] = 'Choose 1 to show one question at a time with Previous and Next navigation. The student stays on the same secure assessment page, so camera monitoring and tab-switch detection continue while moving between questions.';
$string['onequestionperpage'] = '1 — one question per page';
$string['questionsperpagex'] = '{$a} questions per page';
$string['allquestionsonepage'] = 'All questions on one page';
$string['maximumgrade'] = 'Maximum grade';
$string['managequestions'] = 'Manage questions';
$string['editquestions'] = 'Edit questions';
$string['attemptreport'] = 'Attempts and integrity report';
$string['questioncountinfo'] = '{$a} question(s) are configured.';
$string['notopenyet'] = 'This assessment opens {$a}.';
$string['closed'] = 'This assessment is closed.';
$string['nomoreattempts'] = 'No more attempts are available.';
$string['noquestionsyet'] = 'The teacher has not added questions yet.';
$string['startattempt'] = 'Start secure assessment';
$string['bulkhelp'] = 'Paste structured questions below. The parser supports MCQ, MULTISELECT, and TRUEFALSE. Correct answers are explicit, for example CORRECT: B.';
$string['bulkcontent'] = 'Bulk question content';
$string['bulkexample'] = "[QUESTION 001]\nTYPE: MCQ\nTEXT: What is 2 + 2?\nA: 3\nB: 4\nC: 5\nCORRECT: B\nMARKS: 1\n---\n[QUESTION 002]\nTYPE: TRUEFALSE\nTEXT: Moodle is an LMS.\nCORRECT: TRUE\nMARKS: 1";
$string['splitandimport'] = 'Split, validate and import';
$string['questionsimported'] = '{$a} question(s) imported successfully.';
$string['parsefailed'] = 'No valid questions were found. Check the required TEXT, options and CORRECT fields.';
$string['questiondeleted'] = 'Question deleted.';
$string['questionupdated'] = 'Question updated successfully.';
$string['questioneditwarning'] = 'Editing a question changes how future attempts are marked. Completed attempts keep their current result until you open the attempt report and use Regrade attempt.';
$string['editquestion'] = 'Edit question';
$string['qtypemcq'] = 'Multiple choice — one answer';
$string['qtypemultiselect'] = 'Multiple choice — multiple answers';
$string['qtypetruefalse'] = 'True / False';
$string['answeroptions'] = 'Answer options';
$string['answeroptionshelp'] = 'Enter one option per line using KEY: Answer, for example A: Islamabad. True/False questions can use TRUE: True and FALSE: False.';
$string['correctanswerkeys'] = 'Correct answer key(s)';
$string['invalidquestiontype'] = 'Invalid question type.';
$string['invalidoptionsformat'] = 'Each answer option must use the format KEY: Answer.';
$string['invalidquestionedit'] = 'Enter question text, at least two answer options, a valid correct answer and marks greater than zero.';
$string['correctkeymissing'] = 'Correct answer key {$a} does not exist in the answer options.';
$string['singleanswerrequired'] = 'This question type requires exactly one correct answer.';
$string['type'] = 'Type';
$string['marks'] = 'Marks';
$string['feedback'] = 'Feedback';
$string['backtoquiz'] = 'Back to Integrity Quiz';
$string['notavailable'] = 'This assessment is not available at this time.';
$string['attemptsuspended'] = 'Assessment suspended';
$string['suspendedmessage'] = 'Your current responses were saved. An authorised teacher must review this attempt before you can continue.';
$string['secureassessment'] = 'Secure assessment';
$string['systemcheck'] = 'Secure assessment system check';
$string['systemcheckdesc'] = 'Camera permission is used for snapshots only. Audio is not requested. The assessment remains in the normal Moodle browser window. After you begin, changing to another tab can suspend the attempt.';
$string['entersecuremode'] = 'Begin assessment';
$string['beginassessment'] = 'Begin assessment';
$string['questionx'] = 'Question {$a}';
$string['questionprogress'] = 'Question {current} of {total}';
$string['submitattempt'] = 'Submit assessment';
$string['submitconfirm'] = 'Submit this assessment? You will not be able to change your answers after submission.';
$string['attempt'] = 'Attempt';
$string['attemptnotactive'] = 'This attempt is no longer active.';
$string['snapshotfailed'] = 'The snapshot could not be uploaded.';
$string['invalidsnapshot'] = 'Invalid snapshot file type.';
$string['snapshottoolarge'] = 'The snapshot file is too large.';
$string['student'] = 'Student';
$string['grade'] = 'Grade';
$string['state'] = 'State';
$string['violations'] = 'Violations';
$string['review'] = 'Review';
$string['reviewandeditresult'] = 'Review / edit result';
$string['reviewattempt'] = 'Review attempt {$a}';
$string['actionresume'] = 'Resume attempt';
$string['actionsubmit'] = 'Submit current answers';
$string['actionregrade'] = 'Regrade attempt';
$string['actioninvalidate'] = 'Invalidate attempt';
$string['reviewupdated'] = 'Attempt review updated.';
$string['editresultgrade'] = 'Edit result / grade';
$string['manualgradehelp'] = 'A saved manual grade is immediately synchronised with Moodle Grades. If multiple attempts are allowed, the highest submitted attempt is shown in the gradebook.';
$string['savegrade'] = 'Save grade';
$string['invalidmanualgrade'] = 'The manual grade must be between 0 and {$a}.';
$string['manualgradeonlysubmitted'] = 'A manual grade can only be saved for a submitted attempt.';
$string['attemptresponses'] = 'Question responses';
$string['studentanswer'] = 'Student answer';
$string['notanswered'] = 'Not answered';
$string['regradeafteredithelp'] = 'If you edit a question or its correct answer, return to this attempt and click Regrade attempt to recalculate the result and update Moodle Grades.';
$string['opencoursegrades'] = 'Open course grades';
$string['gradebooksyncinfo'] = 'Submitted Integrity Quiz results are stored in Moodle gradebook. Manual grade edits and regrades from this report are synchronised back to Moodle Grades.';
$string['eventtype'] = 'Event';
$string['detail'] = 'Detail';
$string['eventhash'] = 'Evidence hash';
$string['evidence'] = 'Camera evidence';
$string['taskcleanupevidence'] = 'Delete expired Integrity Quiz camera evidence';
$string['errpositivegrade'] = 'Maximum grade must be greater than zero.';
$string['errsnapshotmin'] = 'Use at least 10 seconds for the minimum snapshot interval.';
$string['errsnapshotmax'] = 'The maximum interval must be equal to or greater than the minimum interval.';
$string['errtimeclose'] = 'The close time must be after the open time.';
$string['privacy:metadata:integrityquiz_attempts'] = 'Stores secure assessment attempts.';
$string['integrityquiz:addinstance'] = 'Add a new Integrity Quiz';
$string['integrityquiz:view'] = 'View Integrity Quiz';
$string['integrityquiz:attempt'] = 'Attempt Integrity Quiz';
$string['integrityquiz:managequestions'] = 'Manage Integrity Quiz questions';
$string['integrityquiz:manageattempts'] = 'Manage Integrity Quiz attempts';
$string['integrityquiz:viewreports'] = 'View Integrity Quiz reports';
$string['integrityquiz:viewevidence'] = 'View Integrity Quiz camera evidence';
$string['privacy:metadata:attempts'] = 'Secure assessment attempt records.';
$string['privacy:metadata:attempts:userid'] = 'The user who made the attempt.';
$string['privacy:metadata:attempts:state'] = 'The current attempt state.';
$string['privacy:metadata:attempts:timestart'] = 'The time the attempt started.';
$string['privacy:metadata:attempts:timefinish'] = 'The time the attempt finished.';
$string['privacy:metadata:attempts:violations'] = 'The number of recorded integrity violations.';
$string['privacy:metadata:attempts:grade'] = 'The calculated attempt grade.';
$string['privacy:metadata:responses'] = 'Responses saved during a secure assessment attempt.';
$string['privacy:metadata:responses:response'] = 'The response supplied by the user.';
$string['privacy:metadata:responses:mark'] = 'The mark calculated for the response.';
$string['privacy:metadata:events'] = 'Integrity events recorded during an attempt.';
$string['privacy:metadata:events:eventtype'] = 'The type of integrity event.';
$string['privacy:metadata:events:detail'] = 'Additional detail recorded for the event.';
$string['privacy:metadata:events:time'] = 'The server time at which the event was recorded.';
$string['privacy:metadata:files'] = 'Camera snapshots are stored in Moodle file storage as private assessment evidence.';
$string['assessmentprogress'] = 'Progress';
$string['timeleft'] = 'Time left';
$string['camera'] = 'Camera';
$string['cameraactive'] = 'Camera active';
$string['cameraoptional'] = 'Camera optional';
$string['camerarequired'] = 'Camera permission is required to continue.';
$string['checkingcamera'] = 'Checking…';
$string['camerachecklabel'] = 'Live camera check';
$string['donotswitchtab'] = 'Do not switch tab or window';
$string['tabswitchwarning'] = 'Your assessment may be suspended.';
$string['questionprogressstatic'] = 'Question {$a->current} of {$a->total}';
$string['questionmarks'] = '{$a} mark(s)';
$string['selectbestanswer'] = 'Select the best answer.';
$string['questionnavigation'] = 'Question navigation';
$string['questiontimelimit'] = 'Time limit per question';
$string['questiontimelimit_help'] = 'Optional. When enabled, each question gets its own countdown starting the first time the student opens it. Refreshing the page or returning to the question does not reset the timer. When time expires, the question is locked and the attempt automatically moves to the next question. This setting requires one question per page.';
$string['errquestiontimerlayout'] = 'The per-question timer can only be used when Questions per page is set to 1.';
$string['questiontimerlabel'] = 'Question time';
$string['questiontimerpending'] = 'Question timer';
$string['questiontimeexpired'] = 'Time expired';
$string['answerbeforecontinue'] = 'Select an answer before continuing.';

// End-of-attempt and learner result screen.
$string['attemptsubmittedreturn'] = 'Your assessment has been submitted successfully. Camera monitoring has ended.';
$string['yourresult'] = 'Your result';
$string['attemptcompleted'] = 'Assessment completed';
$string['gradepercentage'] = '{$a}%';
$string['gradedsynced'] = 'This result is stored in Moodle Grades.';
$string['gradependingteacher'] = 'Grade pending';
$string['gradependingteacher_help'] = 'Your assessment has been submitted. Your grade will appear here when it is available or after your teacher updates it.';
$string['submittedon'] = 'Submitted {$a}';

// Compact learner attempt history.
$string['yourresults'] = 'Your results';
$string['attempthistory'] = 'Attempt history';
$string['attempthistorysummary'] = '{$a} completed attempt(s)';
$string['currentmoodlegrade'] = 'Current Moodle grade';
$string['attemptnumber'] = 'Attempt {$a}';
$string['completedstatus'] = 'Completed';
$string['latestattempt'] = 'Latest';
$string['bestattempt'] = 'Best';

// Professional teacher attempt report.
$string['reporteyebrow'] = 'Assessment analytics';
$string['reporttotalattempts'] = 'Total attempts';
$string['reportstudentsmeta'] = '{$a} student(s)';
$string['reportsubmitted'] = 'Submitted';
$string['reportgradedmeta'] = 'Completed attempts';
$string['reportsuspended'] = 'Suspended';
$string['reportneedsreview'] = 'Needs teacher review';
$string['reportaveragegrade'] = 'Average grade';
$string['reportviolationsmeta'] = '{$a} integrity event(s)';
$string['reportsearchstudent'] = 'Search student';
$string['reportsearchplaceholder'] = 'Name or email';
$string['reportfilterstate'] = 'Attempt status';
$string['reportallstates'] = 'All statuses';
$string['reportapplyfilters'] = 'Apply filters';
$string['reportclearfilters'] = 'Clear';
$string['reportattemptsheading'] = 'Student attempts';
$string['reportattemptscount'] = '{$a} attempt(s) shown';
$string['gradebooksyncshort'] = 'Moodle Grades synced';
$string['reportnoattempts'] = 'No attempts found';
$string['reportnoattemptshelp'] = 'No attempts match the current filters yet.';
$string['reportstarted'] = 'Started / finished';
$string['reportfinishedshort'] = 'Finished {$a}';
$string['statusactive'] = 'Active';
$string['statussuspended'] = 'Suspended';
$string['statussubmitted'] = 'Submitted';
$string['statusinvalidated'] = 'Invalidated';
$string['deleteattempt'] = 'Delete';
$string['deleteattempttitle'] = 'Delete attempt?';
$string['deleteattemptconfirm'] = 'You are about to permanently delete attempt {$a->attempt} for {$a->student}.';
$string['deleteattemptwarning'] = 'This removes the attempt responses, integrity events, review history and stored camera evidence. The student\'s Moodle Gradebook grade will then be recalculated from their remaining submitted attempts.';
$string['attemptdeletedsuccess'] = 'The attempt and its associated evidence were deleted. Moodle Grades have been recalculated.';

$string['submitted'] = 'Submitted';
$string['grades'] = 'Grades';
$string['studentattempts'] = 'Student attempts';
$string['startedfinished'] = 'Started / Finished';
$string['actions'] = 'Actions';
$string['gradepending'] = 'Grade pending';
$string['moodlegradessynced'] = 'Moodle Grades synced';

$string['invalidresponse'] = 'The submitted answer is not valid for this question.';
$string['invalidintegrityevent'] = 'The integrity event type is not valid.';
$string['invalidsnapshotkind'] = 'The snapshot type is not valid.';
$string['privacy:metadata:attempts:attemptno'] = 'The sequential number of the user attempt.';
$string['privacy:metadata:attempts:timesuspended'] = 'The time the attempt was suspended.';
$string['privacy:metadata:attempts:lastheartbeat'] = 'The last server heartbeat time recorded for the attempt.';
$string['privacy:metadata:attempts:pageid'] = 'A temporary browser-page identifier used to detect duplicate active pages.';
$string['privacy:metadata:attempts:rawscore'] = 'The raw score calculated for the attempt.';
$string['privacy:metadata:attempts:timemodified'] = 'The time the attempt record was last modified.';
$string['privacy:metadata:responses:questionid'] = 'The question to which the response belongs.';
$string['privacy:metadata:responses:fraction'] = 'The calculated correctness fraction for the response.';
$string['privacy:metadata:responses:timeopened'] = 'The time the timed question was first opened.';
$string['privacy:metadata:responses:timeexpired'] = 'The time the timed question expired.';
$string['privacy:metadata:responses:timemodified'] = 'The time the response was last modified.';
$string['privacy:metadata:events:severity'] = 'The severity value assigned to the integrity event.';
$string['privacy:metadata:events:clienttime'] = 'The browser timestamp supplied for the event.';
$string['privacy:metadata:events:servertime'] = 'The server timestamp recorded for the event.';
$string['privacy:metadata:events:prevhash'] = 'The previous event hash used in the evidence chain.';
$string['privacy:metadata:events:eventhash'] = 'The hash calculated for the integrity event.';
$string['privacy:metadata:reviews'] = 'Teacher review records associated with assessment attempts.';
$string['privacy:metadata:reviews:reviewerid'] = 'The user who performed the review action.';
$string['privacy:metadata:reviews:action'] = 'The review action that was performed.';
$string['privacy:metadata:reviews:oldstate'] = 'The attempt state before the review action.';
$string['privacy:metadata:reviews:newstate'] = 'The attempt state after the review action.';
$string['privacy:metadata:reviews:note'] = 'The review note entered by the reviewer.';
$string['privacy:metadata:reviews:timecreated'] = 'The time the review action was recorded.';
$string['privacy:metadata:gradebook'] = 'Submitted results are sent to the Moodle gradebook.';
$string['privacy:reviewsauthored'] = 'Reviews authored by you';
