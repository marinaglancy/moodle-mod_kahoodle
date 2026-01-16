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

use mod_kahoodle\constants;

/**
 * Tests for add_questions web service
 *
 * @covers     \mod_kahoodle\external\add_questions
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class add_questions_test extends \advanced_testcase {
    /**
     * Test adding a single question via web service
     *
     * @return void
     */
    public function test_add_single_question(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        $questions = [
            [
                'kahoodleid' => $kahoodle->id,
                'questiontype' => constants::QUESTION_TYPE_MULTICHOICE,
                'questiontext' => 'What is 2+2?',
                'questiontextformat' => FORMAT_HTML,
                'answersconfig' => json_encode(['options' => ['3', '4', '5'], 'correct' => 1]),
                'maxpoints' => 1500,
                'minpoints' => 750,
            ],
        ];

        $result = add_questions::execute($questions);

        // Verify the result structure.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('questionids', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertCount(1, $result['questionids']);
        $this->assertCount(0, $result['warnings']);

        // Verify question was created.
        $questionid = $result['questionids'][0]['questionid'];
        $this->assertEquals(0, $result['questionids'][0]['index']);

        $question = $DB->get_record('kahoodle_questions', ['id' => $questionid], '*', MUST_EXIST);
        $this->assertEquals($kahoodle->id, $question->kahoodleid);
        $this->assertEquals(constants::QUESTION_TYPE_MULTICHOICE, $question->questiontype);
    }

    /**
     * Test adding multiple questions via web service
     *
     * @return void
     */
    public function test_add_multiple_questions(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        $questions = [
            [
                'kahoodleid' => $kahoodle->id,
                'questiontext' => 'Question 1',
            ],
            [
                'kahoodleid' => $kahoodle->id,
                'questiontext' => 'Question 2',
            ],
            [
                'kahoodleid' => $kahoodle->id,
                'questiontext' => 'Question 3',
            ],
        ];

        $result = add_questions::execute($questions);

        // Verify all questions were created.
        $this->assertCount(3, $result['questionids']);
        $this->assertCount(0, $result['warnings']);

        // Verify questions exist in database.
        $this->assertEquals(3, $DB->count_records('kahoodle_questions', ['kahoodleid' => $kahoodle->id]));
    }

    /**
     * Test adding question without permission
     *
     * @return void
     */
    public function test_add_question_no_permission(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($user);

        $questions = [
            [
                'kahoodleid' => $kahoodle->id,
                'questiontext' => 'Test question',
            ],
        ];

        $result = add_questions::execute($questions);

        // Should have a warning about permission.
        $this->assertCount(0, $result['questionids']);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('question', $result['warnings'][0]['item']);
        $this->assertEquals(0, $result['warnings'][0]['itemid']);
    }

    /**
     * Test adding question to non-existent kahoodle
     *
     * @return void
     */
    public function test_add_question_invalid_kahoodleid(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        $questions = [
            [
                'kahoodleid' => 99999,
                'questiontext' => 'Test question',
            ],
        ];

        $result = add_questions::execute($questions);

        // Should have a warning, not an exception.
        $this->assertCount(0, $result['questionids']);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('question', $result['warnings'][0]['item']);
        $this->assertEquals(0, $result['warnings'][0]['itemid']);
    }

    /**
     * Test adding question with no editable round
     *
     * @return void
     */
    public function test_add_question_no_editable_round(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        // Create and start a round.
        $roundid = \mod_kahoodle\questions::get_editable_round_id($kahoodle->id);
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_LOBBY, ['id' => $roundid]);

        $questions = [
            [
                'kahoodleid' => $kahoodle->id,
                'questiontext' => 'Test question',
            ],
        ];

        $result = add_questions::execute($questions);

        // Should have a warning.
        $this->assertCount(0, $result['questionids']);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('noeditableround', $result['warnings'][0]['warningcode']);
    }

    /**
     * Test adding questions with mixed success and failure
     *
     * @return void
     */
    public function test_add_questions_mixed_results(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        $questions = [
            [
                'kahoodleid' => $kahoodle->id,
                'questiontext' => 'Valid question',
            ],
            [
                'kahoodleid' => 99999,
                'questiontext' => 'Invalid kahoodleid',
            ],
            [
                'kahoodleid' => $kahoodle->id,
                'questiontext' => 'Another valid question',
            ],
        ];

        $result = add_questions::execute($questions);

        // Should have 2 successes and 1 warning.
        $this->assertCount(2, $result['questionids']);
        $this->assertCount(1, $result['warnings']);

        // Verify the successful questions were created.
        $this->assertEquals(0, $result['questionids'][0]['index']);
        $this->assertEquals(2, $result['questionids'][1]['index']);

        // Verify the failed question.
        $this->assertEquals(1, $result['warnings'][0]['itemid']);
    }

    /**
     * Test adding question with all optional parameters
     *
     * @return void
     */
    public function test_add_question_with_all_parameters(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        $questions = [
            [
                'kahoodleid' => $kahoodle->id,
                'questiontype' => constants::QUESTION_TYPE_MULTICHOICE,
                'questiontext' => 'Complete question',
                'questiontextformat' => FORMAT_PLAIN,
                'questionconfig' => json_encode(['setting' => 'value']),
                'answersconfig' => json_encode(['options' => ['A', 'B', 'C'], 'correct' => 0]),
                'questionpreviewduration' => 15,
                'questionduration' => 45,
                'questionresultsduration' => 20,
                'maxpoints' => 2000,
                'minpoints' => 1000,
            ],
        ];

        $result = add_questions::execute($questions);

        $this->assertCount(1, $result['questionids']);
        $this->assertCount(0, $result['warnings']);

        // Verify all parameters were saved.
        $questionid = $result['questionids'][0]['questionid'];
        $version = $DB->get_record('kahoodle_question_versions', ['questionid' => $questionid], '*', MUST_EXIST);

        $this->assertEquals('Complete question', $version->questiontext);
        $this->assertEquals(FORMAT_PLAIN, $version->questiontextformat);
        $this->assertEquals('{"setting":"value"}', $version->questionconfig);
        $this->assertEquals('{"options":["A","B","C"],"correct":0}', $version->answersconfig);

        // Verify behavior data.
        $round = $DB->get_record('kahoodle_rounds', ['kahoodleid' => $kahoodle->id], '*', MUST_EXIST);
        $roundquestion = $DB->get_record(
            'kahoodle_round_questions',
            ['roundid' => $round->id, 'questionversionid' => $version->id],
            '*',
            MUST_EXIST
        );

        $this->assertEquals(15, $roundquestion->questionpreviewduration);
        $this->assertEquals(45, $roundquestion->questionduration);
        $this->assertEquals(20, $roundquestion->questionresultsduration);
        $this->assertEquals(2000, $roundquestion->maxpoints);
        $this->assertEquals(1000, $roundquestion->minpoints);
    }

    /**
     * Test adding question with default values
     *
     * @return void
     */
    public function test_add_question_with_defaults(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        $questions = [
            [
                'kahoodleid' => $kahoodle->id,
                'questiontext' => 'Minimal question',
            ],
        ];

        $result = add_questions::execute($questions);

        $this->assertCount(1, $result['questionids']);

        // Verify defaults were applied.
        $questionid = $result['questionids'][0]['questionid'];
        $question = $DB->get_record('kahoodle_questions', ['id' => $questionid], '*', MUST_EXIST);
        $version = $DB->get_record('kahoodle_question_versions', ['questionid' => $questionid], '*', MUST_EXIST);

        $this->assertEquals(constants::QUESTION_TYPE_MULTICHOICE, $question->questiontype);
        $this->assertEquals(FORMAT_HTML, $version->questiontextformat);
        $this->assertNull($version->questionconfig);
        $this->assertNull($version->answersconfig);
    }
}
