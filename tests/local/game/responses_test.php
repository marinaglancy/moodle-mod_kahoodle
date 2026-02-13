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

namespace mod_kahoodle\local\game;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\participant;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\game\questions;

/**
 * Tests for response management in Kahoodle rounds
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\local\game\responses
 */
final class responses_test extends \advanced_testcase {
    /**
     * Get the Kahoodle plugin generator
     *
     * @return \mod_kahoodle_generator
     */
    protected function get_generator(): \mod_kahoodle_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
    }

    /**
     * Helper to create a kahoodle activity with a question and advance the round to the question stage.
     *
     * Returns an array with the round (reloaded), participant entity, and the stage signature at question-1.
     *
     * @return array{round, participant, string} [$round, $participant, $stagesignature]
     */
    protected function setup_round_at_question_stage(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $generator = $this->get_generator();

        // Create a question (default multichoice: "Option 1\n*Option 2\nOption 3").
        $generator->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Test question']);

        $round = questions::get_last_round($kahoodle->id);

        // Create a user and a participant via the generator.
        $user = $this->getDataGenerator()->create_user();
        $participantid = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user->id]);

        // Advance the round to the question-1 stage.
        progress::start_game($round);

        // Advance from lobby to questionpreview-1.
        $sig = $round->get_current_stage()->get_stage_signature();
        progress::advance_to_next_stage($round, $sig);

        // Advance from questionpreview-1 to question-1.
        $sig = $round->get_current_stage()->get_stage_signature();
        progress::advance_to_next_stage($round, $sig);

        // Set the stage start time to 5 seconds ago so responsetime is about 5s.
        $DB->set_field('kahoodle_rounds', 'stagestarttime', (int)(microtime(true) - 5.0), ['id' => $round->get_id()]);

        // Reload the round to pick up stage changes.
        $round = round::create_from_id($round->get_id());

        // Get the participant entity from the round.
        $participant = $round->get_participant_by_id($participantid);

        // Get the current stage signature at question-1.
        $stagesignature = $round->get_current_stage()->get_stage_signature();

        return [$round, $participant, $stagesignature];
    }

    /**
     * Test recording a correct answer awards points and marks as correct.
     */
    public function test_record_answer_correct(): void {
        global $DB;
        $this->resetAfterTest();

        [$round, $participant, $sig] = $this->setup_round_at_question_stage();

        // Response '2' is correct (second option is marked with * in default config).
        responses::record_answer($participant, '2', $sig);

        $record = $DB->get_record('kahoodle_responses', [
            'participantid' => $participant->get_id(),
        ]);
        $this->assertNotEmpty($record);
        $this->assertEquals(1, (int)$record->iscorrect);
        $this->assertGreaterThan(0, (int)$record->points);
    }

    /**
     * Test recording an incorrect answer yields zero points and marks as incorrect.
     */
    public function test_record_answer_incorrect(): void {
        global $DB;
        $this->resetAfterTest();

        [$round, $participant, $sig] = $this->setup_round_at_question_stage();

        // Response '1' is incorrect.
        responses::record_answer($participant, '1', $sig);

        $record = $DB->get_record('kahoodle_responses', [
            'participantid' => $participant->get_id(),
        ]);
        $this->assertNotEmpty($record);
        $this->assertEquals(0, (int)$record->iscorrect);
        $this->assertEquals(0, (int)$record->points);
    }

    /**
     * Test recording an empty response is silently ignored.
     */
    public function test_record_answer_empty(): void {
        global $DB;
        $this->resetAfterTest();

        [$round, $participant, $sig] = $this->setup_round_at_question_stage();

        // Empty response should be silently ignored.
        responses::record_answer($participant, '', $sig);

        $count = $DB->count_records('kahoodle_responses', [
            'participantid' => $participant->get_id(),
        ]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test recording an answer when the round is in the wrong stage is silently ignored.
     */
    public function test_record_answer_wrong_stage(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $generator = $this->get_generator();

        $generator->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Test question']);
        $round = questions::get_last_round($kahoodle->id);

        $user = $this->getDataGenerator()->create_user();
        $participantid = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user->id]);

        // Start the game (moves to lobby stage), but do NOT advance further.
        progress::start_game($round);
        $round = round::create_from_id($round->get_id());

        $participant = $round->get_participant_by_id($participantid);
        $sig = $round->get_current_stage()->get_stage_signature();

        // Lobby stage, not question stage - should be silently ignored.
        responses::record_answer($participant, '2', $sig);

        $count = $DB->count_records('kahoodle_responses', [
            'participantid' => $participant->get_id(),
        ]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test recording an answer with a wrong stage signature is silently ignored.
     */
    public function test_record_answer_wrong_signature(): void {
        global $DB;
        $this->resetAfterTest();

        [$round, $participant, $sig] = $this->setup_round_at_question_stage();

        // Pass an invalid signature.
        responses::record_answer($participant, '2', 'invalid-signature');

        $count = $DB->count_records('kahoodle_responses', [
            'participantid' => $participant->get_id(),
        ]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test that duplicate submissions are silently ignored.
     */
    public function test_record_answer_duplicate(): void {
        global $DB;
        $this->resetAfterTest();

        [$round, $participant, $sig] = $this->setup_round_at_question_stage();

        // Record the first answer.
        responses::record_answer($participant, '2', $sig);
        // Record a second answer (should be ignored as duplicate).
        responses::record_answer($participant, '1', $sig);

        $count = $DB->count_records('kahoodle_responses', [
            'participantid' => $participant->get_id(),
        ]);
        $this->assertEquals(1, $count);

        // The stored response should be the first one ('2', correct).
        $record = $DB->get_record('kahoodle_responses', [
            'participantid' => $participant->get_id(),
        ]);
        $this->assertEquals('2', $record->response);
    }

    /**
     * Test recording an invalid option number is silently ignored.
     */
    public function test_record_answer_invalid_option(): void {
        global $DB;
        $this->resetAfterTest();

        [$round, $participant, $sig] = $this->setup_round_at_question_stage();

        // 99 is not a valid option for a 3-option multichoice question.
        responses::record_answer($participant, '99', $sig);

        $count = $DB->count_records('kahoodle_responses', [
            'participantid' => $participant->get_id(),
        ]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test has_answered returns false before and true after recording an answer.
     */
    public function test_has_answered(): void {
        $this->resetAfterTest();

        [$round, $participant, $sig] = $this->setup_round_at_question_stage();

        $roundquestion = $round->get_current_stage()->get_round_question();

        $this->assertFalse(responses::has_answered($participant, $roundquestion));

        responses::record_answer($participant, '2', $sig);

        $this->assertTrue(responses::has_answered($participant, $roundquestion));
    }

    /**
     * Test get_response returns null before and an object after recording an answer.
     */
    public function test_get_response(): void {
        $this->resetAfterTest();

        [$round, $participant, $sig] = $this->setup_round_at_question_stage();

        $roundquestion = $round->get_current_stage()->get_round_question();

        $this->assertNull(responses::get_response($participant, $roundquestion));

        responses::record_answer($participant, '2', $sig);

        $response = responses::get_response($participant, $roundquestion);
        $this->assertNotNull($response);
        $this->assertEquals('2', $response->response);
        $this->assertEquals(1, (int)$response->iscorrect);
        $this->assertGreaterThan(0, (int)$response->points);
    }
}
