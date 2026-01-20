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

use mod_kahoodle\local\entities\round_question;
use mod_kahoodle\questions;

/**
 * Tests for Kahoodle
 *
 * @covers     \mod_kahoodle\external\change_question_sortorder
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class change_question_sortorder_test extends \advanced_testcase {
    /**
     * Test deleting a question via web service
     *
     * @return void
     */
    public function test_change_question_sortorder(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        // Create a question using the generator.
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $round = questions::get_last_round($kahoodle->id);
        $questions = [];
        for ($i = 0; $i < 10; $i++) {
            $questions[] = $generator->create_question(['kahoodleid' => $kahoodle->id])->get_id();
        }

        // Change the question sort order via web service.
        change_question_sortorder::execute($questions[7], 2);

        $roundquestions = round_question::get_all_questions_for_round($round);

        // Assert questions are in the following order: 0,7,1,2,3,4,5,6,8,9.
        $expectedorder = [0, 7, 1, 2, 3, 4, 5, 6, 8, 9];
        foreach ($roundquestions as $index => $roundquestion) {
            $this->assertEquals($questions[$expectedorder[$index]], $roundquestion->get_id());
        }

        // Now move question down.
        change_question_sortorder::execute($questions[1], 9);

        $roundquestions = round_question::get_all_questions_for_round($round);

        // Assert questions are in the following order: 0,7,2,3,4,5,6,8,1,9.
        $expectedorder = [0, 7, 2, 3, 4, 5, 6, 8, 1, 9];
        foreach ($roundquestions as $index => $roundquestion) {
            $this->assertEquals($questions[$expectedorder[$index]], $roundquestion->get_id());
        }
    }
}
