# Integrity Quiz

Integrity Quiz (`mod_integrityquiz`) is a Moodle activity module for monitored online assessments. It combines structured MCQ/True-False authoring with optional camera snapshots, tab-switch monitoring, server-side suspension, per-question timing, Moodle gradebook integration, and teacher review tools.

## Requirements

- Moodle 4.5 or later in the declared support range.
- HTTPS is required by modern browsers when camera access is enabled.
- JavaScript enabled in the learner browser.

## Main features

- Multiple-choice, multiple-select, and True/False questions.
- Structured bulk question import and manual question editing.
- One-question-per-page mode with answer-before-next navigation.
- Overall and optional per-question timers.
- Optional camera pre-check and private still-image evidence.
- Initial, random, event, and final snapshots; no microphone or continuous video recording.
- Tab/window visibility monitoring and configurable attempt suspension.
- Server-authoritative attempt state, response saving, and question timing.
- Multiple attempts and Moodle gradebook synchronisation.
- Teacher reports, evidence review, regrading, manual grades, resume/invalidate/delete actions.
- Moodle Privacy API, scheduled evidence retention cleanup, backup/restore, and capability-based access.
- Responsive UI using active Moodle theme variables.

## Installation

Copy the plugin to `mod/integrityquiz` or install the release ZIP from Moodle Site administration. Visit Site administration > Notifications to complete installation or upgrade.

## Permissions

Learners receive `mod/integrityquiz:view` and `mod/integrityquiz:attempt`. Teaching/management roles receive the relevant question, report, attempt-management, and evidence capabilities. Teachers/managers also receive the attempt capability by default so they can test the assessment. Administrators can override all capabilities using Moodle roles.

## Privacy

When enabled, camera snapshots are stored in Moodle's private File API as assessment evidence. The plugin does not request microphone access and does not continuously record video. The Privacy API declares, exports, and deletes attempt, response, event, review, file, and gradebook-related personal data. Evidence retention is configurable and cleaned by a scheduled task.

## Repository

Canonical repository name for Moodle Marketplace: `moodle-mod_integrityquiz`.

## Licence

GNU GPL v3 or later. See `LICENSE`.

## Maintainer

Muhammad Haseeb — FoneRep Technologies — haseeb.fonerep@gmail.com
