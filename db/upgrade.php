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
 * Upgrade steps for Kahoodle
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    mod_kahoodle
 * @category   upgrade
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_kahoodle_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();


    if ($oldversion < 2025090204 ) {

        // Define table kahoodle_questions to be created.
        $table = new xmldb_table('kahoodle_questions');

        // Adding fields to table kahoodle_questions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('kahoodle_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('started_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('question', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '60');
        $table->add_field('order', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table kahoodle_questions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('kahoodle_id', XMLDB_KEY_FOREIGN, ['kahoodle_id'], 'kahoodle', ['id']);

        // Conditionally launch create table for kahoodle_questions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table kahoodle_players to be created.
        $table = new xmldb_table('kahoodle_players');

        // Adding fields to table kahoodle_players.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('session_id', XMLDB_TYPE_CHAR, '100', null, null, null, null);

        // Adding keys to table kahoodle_players.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user_id', XMLDB_KEY_FOREIGN, ['user_id'], 'user', ['id']);

        // Conditionally launch create table for kahoodle_players.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

	// Adding fields to table kahoodle_answers.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('question_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('player_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('answer', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table kahoodle_answers.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('player_id', XMLDB_KEY_FOREIGN, ['player_id'], 'kahoodle_players', ['id']);
        $table->add_key('question_id', XMLDB_KEY_FOREIGN, ['question_id'], 'kahoodle_questions', ['id']);

        // Conditionally launch create table for kahoodle_answers.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define field state to be added to kahoodle.
        $table = new xmldb_table('kahoodle');
        $field = new xmldb_field('state', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'PREPARATION', 'timemodified');

        // Conditionally launch add field state.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
	}
        $field = new xmldb_field('configuration', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'state');

        // Conditionally launch add field configuration.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('question_state', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'configuration');

        // Conditionally launch add field question_state.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
	}
        $field = new xmldb_field('question_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'question_state');

        // Conditionally launch add field question_id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kahoodle savepoint reached.
        upgrade_mod_savepoint(true, 2025090204, 'kahoodle');
    }


    return true;
}
