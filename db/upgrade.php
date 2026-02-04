<?php
// This file is part of mod_kahoodle plugin
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

    if ($oldversion < 2026011901) {
        // Rename field defaultmaxpoints on table kahoodle to maxpoints.
        $table = new xmldb_table('kahoodle');
        $field = new xmldb_field(
            'defaultmaxpoints',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '1000',
            'questionresultsduration'
        );

        // Launch rename field defaultmaxpoints.
        $dbman->rename_field($table, $field, 'maxpoints');

        // Rename field defaultminpoints on table kahoodle to minpoints.
        $table = new xmldb_table('kahoodle');
        $field = new xmldb_field('defaultminpoints', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '500', 'maxpoints');

        // Launch rename field defaultminpoints.
        $dbman->rename_field($table, $field, 'minpoints');

        // Kahoodle savepoint reached.
        upgrade_mod_savepoint(true, 2026011901, 'kahoodle');
    }

    if ($oldversion < 2026012801) {
        // Define field lobbyduration to be dropped from kahoodle_rounds.
        $table = new xmldb_table('kahoodle_rounds');
        $field = new xmldb_field('lobbyduration');

        // Conditionally launch drop field lobbyduration.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Kahoodle savepoint reached.
        upgrade_mod_savepoint(true, 2026012801, 'kahoodle');
    }

    if ($oldversion < 2026020400) {
        // Define field islast to be added to kahoodle_question_versions.
        $table = new xmldb_table('kahoodle_question_versions');
        $field = new xmldb_field('islast', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field islast.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Populate islast for existing questions - set to 1 for the highest version of each question.
        $sql = "UPDATE {kahoodle_question_versions}
                   SET islast = 1
                 WHERE id IN (
                       SELECT maxid FROM (
                           SELECT MAX(id) AS maxid
                             FROM {kahoodle_question_versions}
                            GROUP BY questionid
                       ) subquery
                 )";
        $DB->execute($sql);

        // Kahoodle savepoint reached.
        upgrade_mod_savepoint(true, 2026020400, 'kahoodle');
    }

    return true;
}
