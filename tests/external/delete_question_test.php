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

namespace mod_kahoodle\external;

/**
 * Tests for delete_question web service
 *
 * @covers     \mod_kahoodle\external\delete_question
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class delete_question_test extends \advanced_testcase {
    /**
     * Test deleting a question via web service
     *
     * @return void
     */
    public function test_delete_question(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        // Create a question using the generator.
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $roundquestion = $generator->create_question(['kahoodleid' => $kahoodle->id]);
        $questionid = $roundquestion->get_question_id();

        // Verify question exists.
        $this->assertTrue($DB->record_exists('kahoodle_questions', ['id' => $questionid]));

        // Delete the question via web service.
        $result = delete_question::execute($questionid);
        $result = \core_external\external_api::clean_returnvalue(delete_question::execute_returns(), $result);

        // Verify result structure.
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        // Verify question was deleted.
        $this->assertFalse($DB->record_exists('kahoodle_questions', ['id' => $questionid]));
    }

    /**
     * Test deleting a question without permission
     *
     * @return void
     */
    public function test_delete_question_no_permission(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create question as teacher.
        $this->setUser($teacher);
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $roundquestion = $generator->create_question(['kahoodleid' => $kahoodle->id]);
        $questionid = $roundquestion->get_question_id();

        // Try to delete as student.
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        delete_question::execute($questionid);
    }

    /**
     * Test that sortorder is fixed after deleting a question
     *
     * @return void
     */
    public function test_delete_question_fixes_sortorder(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        // Create three questions.
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $q1 = $generator->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 1']);
        $q2 = $generator->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 2']);
        $q3 = $generator->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 3']);

        // Verify initial sortorder.
        $round = \mod_kahoodle\local\game\questions::get_last_round($kahoodle->id);
        $questions = $DB->get_records('kahoodle_round_questions', ['roundid' => $round->get_id()], 'sortorder ASC');
        $sortorders = array_column($questions, 'sortorder');
        $this->assertEquals([1, 2, 3], array_values($sortorders));

        // Delete the middle question.
        $result = delete_question::execute($q2->get_question_id());
        \core_external\external_api::clean_returnvalue(delete_question::execute_returns(), $result);

        // Verify sortorder was fixed (should be 1, 2 now).
        $questions = $DB->get_records('kahoodle_round_questions', ['roundid' => $round->get_id()], 'sortorder ASC');
        $sortorders = array_column($questions, 'sortorder');
        $this->assertEquals([1, 2], array_values($sortorders));

        // Verify the remaining questions are q1 and q3.
        $questionids = array_column($questions, 'questionversionid');
        $this->assertCount(2, $questionids);
    }
}
