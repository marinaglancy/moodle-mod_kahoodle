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

namespace mod_kahoodle;

/**
 * Tests for constants class field lists.
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\constants
 */
final class constants_test extends \advanced_testcase {
    /**
     * Test that FIELDS_QUESTION_VERSION matches kahoodle_question_versions table columns
     * minus primary/foreign keys, version, and timestamps.
     */
    public function test_fields_question_version(): void {
        global $DB;

        $columns = array_keys($DB->get_columns('kahoodle_question_versions'));

        // Excluded: primary key (id), foreign key (questionid), version, timestamps (timecreated, timemodified), islast.
        $excluded = ['id', 'questionid', 'version', 'timecreated', 'timemodified', 'islast'];
        $expected = array_values(array_diff($columns, $excluded));

        $actual = array_values(constants::FIELDS_QUESTION_VERSION);

        sort($expected);
        sort($actual);

        $this->assertEquals(
            $expected,
            $actual,
            'FIELDS_QUESTION_VERSION does not match kahoodle_question_versions table columns ' .
            '(minus id, questionid, version, timecreated, timemodified, islast)'
        );
    }

    /**
     * Test that FIELDS_ROUND_QUESTION matches kahoodle_round_questions table columns
     * minus primary/foreign keys, sortorder, and timestamps.
     */
    public function test_fields_round_question(): void {
        global $DB;

        $columns = array_keys($DB->get_columns('kahoodle_round_questions'));

        // Excluded: primary key (id), foreign keys (roundid, questionversionid), sortorder,
        // timestamps (timecreated, timemodified).
        $excluded = [
            'id', 'roundid', 'questionversionid', 'sortorder',
            'timecreated', 'timemodified',
        ];
        $expected = array_values(array_diff($columns, $excluded));

        $actual = array_values(constants::FIELDS_ROUND_QUESTION);

        sort($expected);
        sort($actual);

        $this->assertEquals(
            $expected,
            $actual,
            'FIELDS_ROUND_QUESTION does not match kahoodle_round_questions table columns ' .
            '(minus id, roundid, questionversionid, sortorder, timecreated, timemodified)'
        );
    }
}
