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
 * Database upgrade steps for Integrity Quiz.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for mod_integrityquiz.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_integrityquiz_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090302) {
        $table = new xmldb_table('integrityquiz');

        $field = new xmldb_field(
            'questionsperpage',
            XMLDB_TYPE_INTEGER,
            '4',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'attemptsallowed'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Fullscreen is no longer required. Keep the legacy field for upgrade compatibility,
        // but force existing and new activities to use the normal Moodle browser page.
        $fullscreenfield = new xmldb_field(
            'requirefullscreen',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'requirecamera'
        );
        if ($dbman->field_exists($table, $fullscreenfield)) {
            $dbman->change_field_default($table, $fullscreenfield);
            $DB->set_field('integrityquiz', 'requirefullscreen', 0);
        }

        upgrade_mod_savepoint(true, 2026090302, 'integrityquiz');
    }

    if ($oldversion < 2026090303) {
        $table = new xmldb_table('integrityquiz');
        $field = new xmldb_field(
            'questiontimelimit',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'timelimit'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $responsetable = new xmldb_table('integrityquiz_responses');
        $openedfield = new xmldb_field(
            'timeopened',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'mark'
        );
        if (!$dbman->field_exists($responsetable, $openedfield)) {
            $dbman->add_field($responsetable, $openedfield);
        }

        $expiredfield = new xmldb_field(
            'timeexpired',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'timeopened'
        );
        if (!$dbman->field_exists($responsetable, $expiredfield)) {
            $dbman->add_field($responsetable, $expiredfield);
        }

        upgrade_mod_savepoint(true, 2026090303, 'integrityquiz');
    }


    if ($oldversion < 2026090304) {
        // Security/capability release. The new staffaccess capability is declared
        // in db/access.php and is installed by Moodle during the plugin upgrade.
        upgrade_mod_savepoint(true, 2026090304, 'integrityquiz');
    }

    if ($oldversion < 2026090305) {
        // Allow teaching/management archetypes to make real Integrity Quiz attempts
        // for testing and course review. Students retain their existing attempt access.
        upgrade_mod_savepoint(true, 2026090305, 'integrityquiz');
    }


    if ($oldversion < 2026091700) {
        // Empty string defaults on required character fields are not valid XMLDB defaults.
        $table = new xmldb_table('integrityquiz');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'course');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        $table = new xmldb_table('integrityquiz_events');
        $field = new xmldb_field('eventhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'prevhash');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        $table = new xmldb_table('integrityquiz_reviews');
        $field = new xmldb_field('oldstate', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null, 'action');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }
        $field = new xmldb_field('newstate', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null, 'oldstate');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        upgrade_mod_savepoint(true, 2026091700, 'integrityquiz');
    }

    return true;
}
