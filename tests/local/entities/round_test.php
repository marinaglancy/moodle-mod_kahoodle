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

namespace mod_kahoodle\local\entities;

use mod_kahoodle\constants;
use mod_kahoodle\questions;

/**
 * Tests for round entity
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\local\entities\round
 */
final class round_test extends \advanced_testcase {
    /**
     * Helper to set round stage in the database
     *
     * @param round $round
     * @param string $stage
     * @param int $currentquestion
     */
    protected function set_round_stage(round $round, string $stage, int $currentquestion = 0): void {
        global $DB;
        $DB->update_record('kahoodle_rounds', [
            'id' => $round->get_id(),
            'currentstage' => $stage,
            'currentquestion' => $currentquestion,
        ]);
    }

    /**
     * Test get_rankings with no participants
     *
     * @covers \mod_kahoodle\local\entities\round::get_rankings
     */
    public function test_get_rankings_empty(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $round = questions::get_last_round($kahoodle->id);

        // Set round to revision stage (game finished).
        $this->set_round_stage($round, constants::STAGE_REVISION);

        // Re-fetch round to get updated data.
        $round = round::create_from_id($round->get_id());

        $rankings = $round->get_rankings();

        $this->assertIsArray($rankings);
        $this->assertEmpty($rankings);
    }

    /**
     * Helper to get participant IDs from array of participant objects
     *
     * @param participant[] $participants
     * @return int[]
     */
    protected function get_participant_ids(array $participants): array {
        return array_map(fn($p) => $p->get_id(), $participants);
    }

    /**
     * Test get_rankings with single participant
     *
     * @covers \mod_kahoodle\local\entities\round::get_rankings
     */
    public function test_get_rankings_single_participant(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $round = questions::get_last_round($kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a question.
        $question = $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $p1id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user1->id]);

        // Create response with 100 points.
        $generator->create_response([
            'participantid' => $p1id,
            'roundquestionid' => $question->get_id(),
            'points' => 100,
        ]);

        // Set round to revision stage.
        $this->set_round_stage($round, constants::STAGE_REVISION);
        $round = round::create_from_id($round->get_id());

        $rankings = $round->get_rankings();

        $this->assertCount(1, $rankings);
        $this->assertArrayHasKey($p1id, $rankings);
        $this->assertInstanceOf(rank::class, $rankings[$p1id]);
        $this->assertEquals(1, $rankings[$p1id]->minrank);
        $this->assertEquals(1, $rankings[$p1id]->maxrank);
        $this->assertEmpty($rankings[$p1id]->tiewith);
        $this->assertNull($rankings[$p1id]->prevscore);
        $this->assertEmpty($rankings[$p1id]->withprevscore);
    }

    /**
     * Test get_rankings with distinct scores
     *
     * @covers \mod_kahoodle\local\entities\round::get_rankings
     */
    public function test_get_rankings_distinct_scores(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $round = questions::get_last_round($kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a question.
        $question = $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Create participants.
        $p1id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user1->id]);
        $p2id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user2->id]);
        $p3id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user3->id]);

        // Create responses with different scores.
        $generator->create_response(['participantid' => $p1id, 'roundquestionid' => $question->get_id(), 'points' => 100]);
        $generator->create_response(['participantid' => $p2id, 'roundquestionid' => $question->get_id(), 'points' => 80]);
        $generator->create_response(['participantid' => $p3id, 'roundquestionid' => $question->get_id(), 'points' => 50]);

        // Set round to revision stage.
        $this->set_round_stage($round, constants::STAGE_REVISION);
        $round = round::create_from_id($round->get_id());

        $rankings = $round->get_rankings();

        $this->assertCount(3, $rankings);

        // First place: 100 points.
        $this->assertEquals(1, $rankings[$p1id]->minrank);
        $this->assertEquals(1, $rankings[$p1id]->maxrank);
        $this->assertEmpty($rankings[$p1id]->tiewith);
        $this->assertNull($rankings[$p1id]->prevscore);

        // Second place: 80 points.
        $this->assertEquals(2, $rankings[$p2id]->minrank);
        $this->assertEquals(2, $rankings[$p2id]->maxrank);
        $this->assertEmpty($rankings[$p2id]->tiewith);
        $this->assertEquals(100, $rankings[$p2id]->prevscore);
        $this->assertContains($p1id, $this->get_participant_ids($rankings[$p2id]->withprevscore));

        // Third place: 50 points.
        $this->assertEquals(3, $rankings[$p3id]->minrank);
        $this->assertEquals(3, $rankings[$p3id]->maxrank);
        $this->assertEmpty($rankings[$p3id]->tiewith);
        $this->assertEquals(80, $rankings[$p3id]->prevscore);
        $this->assertContains($p2id, $this->get_participant_ids($rankings[$p3id]->withprevscore));
    }

    /**
     * Test get_rankings with tied scores
     *
     * @covers \mod_kahoodle\local\entities\round::get_rankings
     */
    public function test_get_rankings_tied_scores(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $round = questions::get_last_round($kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a question.
        $question = $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        // Create participants.
        $p1id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user1->id]);
        $p2id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user2->id]);
        $p3id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user3->id]);
        $p4id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user4->id]);

        // Create responses: A (100pts), B (80pts), C (80pts), D (50pts).
        $generator->create_response(['participantid' => $p1id, 'roundquestionid' => $question->get_id(), 'points' => 100]);
        $generator->create_response(['participantid' => $p2id, 'roundquestionid' => $question->get_id(), 'points' => 80]);
        $generator->create_response(['participantid' => $p3id, 'roundquestionid' => $question->get_id(), 'points' => 80]);
        $generator->create_response(['participantid' => $p4id, 'roundquestionid' => $question->get_id(), 'points' => 50]);

        // Set round to revision stage.
        $this->set_round_stage($round, constants::STAGE_REVISION);
        $round = round::create_from_id($round->get_id());

        $rankings = $round->get_rankings();

        $this->assertCount(4, $rankings);

        // First place: 100 points - no tie.
        $this->assertEquals(1, $rankings[$p1id]->minrank);
        $this->assertEquals(1, $rankings[$p1id]->maxrank);
        $this->assertEmpty($rankings[$p1id]->tiewith);

        // Tied for 2nd-3rd place: 80 points.
        $this->assertEquals(2, $rankings[$p2id]->minrank);
        $this->assertEquals(3, $rankings[$p2id]->maxrank);
        $this->assertContains($p3id, $this->get_participant_ids($rankings[$p2id]->tiewith));
        $this->assertNotContains($p2id, $this->get_participant_ids($rankings[$p2id]->tiewith));
        $this->assertEquals(100, $rankings[$p2id]->prevscore);

        $this->assertEquals(2, $rankings[$p3id]->minrank);
        $this->assertEquals(3, $rankings[$p3id]->maxrank);
        $this->assertContains($p2id, $this->get_participant_ids($rankings[$p3id]->tiewith));
        $this->assertNotContains($p3id, $this->get_participant_ids($rankings[$p3id]->tiewith));
        $this->assertEquals(100, $rankings[$p3id]->prevscore);

        // Fourth place: 50 points (rank 4, not 3).
        $this->assertEquals(4, $rankings[$p4id]->minrank);
        $this->assertEquals(4, $rankings[$p4id]->maxrank);
        $this->assertEmpty($rankings[$p4id]->tiewith);
        $this->assertEquals(80, $rankings[$p4id]->prevscore);
        // Both tied participants are at previous rank.
        $this->assertContains($p2id, $this->get_participant_ids($rankings[$p4id]->withprevscore));
        $this->assertContains($p3id, $this->get_participant_ids($rankings[$p4id]->withprevscore));
    }

    /**
     * Test get_rankings with all participants tied
     *
     * @covers \mod_kahoodle\local\entities\round::get_rankings
     */
    public function test_get_rankings_all_tied(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $round = questions::get_last_round($kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a question.
        $question = $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Create participants.
        $p1id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user1->id]);
        $p2id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user2->id]);
        $p3id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user3->id]);

        // All participants have the same score.
        $generator->create_response(['participantid' => $p1id, 'roundquestionid' => $question->get_id(), 'points' => 100]);
        $generator->create_response(['participantid' => $p2id, 'roundquestionid' => $question->get_id(), 'points' => 100]);
        $generator->create_response(['participantid' => $p3id, 'roundquestionid' => $question->get_id(), 'points' => 100]);

        // Set round to revision stage.
        $this->set_round_stage($round, constants::STAGE_REVISION);
        $round = round::create_from_id($round->get_id());

        $rankings = $round->get_rankings();

        $this->assertCount(3, $rankings);

        // All tied for 1st-3rd place.
        foreach ([$p1id, $p2id, $p3id] as $pid) {
            $this->assertEquals(1, $rankings[$pid]->minrank);
            $this->assertEquals(3, $rankings[$pid]->maxrank);
            $this->assertCount(2, $rankings[$pid]->tiewith);
            $this->assertNotContains($pid, $this->get_participant_ids($rankings[$pid]->tiewith));
            $this->assertNull($rankings[$pid]->prevscore);
        }
    }

    /**
     * Test get_rankings with zero scores
     *
     * @covers \mod_kahoodle\local\entities\round::get_rankings
     */
    public function test_get_rankings_zero_scores(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $round = questions::get_last_round($kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a question.
        $question = $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Create participants.
        $p1id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user1->id]);
        $p2id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user2->id]);
        $p3id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user3->id]);

        // Mix of scores including zero.
        $generator->create_response(['participantid' => $p1id, 'roundquestionid' => $question->get_id(), 'points' => 100]);
        $generator->create_response(['participantid' => $p2id, 'roundquestionid' => $question->get_id(), 'points' => 0]);
        $generator->create_response(['participantid' => $p3id, 'roundquestionid' => $question->get_id(), 'points' => 0]);

        // Set round to revision stage.
        $this->set_round_stage($round, constants::STAGE_REVISION);
        $round = round::create_from_id($round->get_id());

        $rankings = $round->get_rankings();

        $this->assertCount(3, $rankings);

        // First place: 100 points.
        $this->assertEquals(1, $rankings[$p1id]->minrank);
        $this->assertEquals(1, $rankings[$p1id]->maxrank);

        // Tied for 2nd-3rd with 0 points.
        $this->assertEquals(2, $rankings[$p2id]->minrank);
        $this->assertEquals(3, $rankings[$p2id]->maxrank);
        $this->assertEquals(100, $rankings[$p2id]->prevscore);

        $this->assertEquals(2, $rankings[$p3id]->minrank);
        $this->assertEquals(3, $rankings[$p3id]->maxrank);
    }

    /**
     * Test that withprevscore is empty when previous score is 0
     *
     * @covers \mod_kahoodle\local\entities\round::get_rankings
     */
    public function test_get_rankings_withprevscore_empty_for_zero(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $round = questions::get_last_round($kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a question.
        $question = $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Create participants.
        $p1id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user1->id]);
        $p2id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user2->id]);

        // Both have 0 score.
        $generator->create_response(['participantid' => $p1id, 'roundquestionid' => $question->get_id(), 'points' => 0]);
        $generator->create_response(['participantid' => $p2id, 'roundquestionid' => $question->get_id(), 'points' => 0]);

        // Set round to revision stage.
        $this->set_round_stage($round, constants::STAGE_REVISION);
        $round = round::create_from_id($round->get_id());

        $rankings = $round->get_rankings();

        // Both should have null prevscore and empty withprevscore.
        $this->assertNull($rankings[$p1id]->prevscore);
        $this->assertEmpty($rankings[$p1id]->withprevscore);
        $this->assertNull($rankings[$p2id]->prevscore);
        $this->assertEmpty($rankings[$p2id]->withprevscore);
    }

    /**
     * Test get_prev_question_rankings returns different values than get_rankings
     *
     * @covers \mod_kahoodle\local\entities\round::get_prev_question_rankings
     */
    public function test_get_prev_question_rankings(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $round = questions::get_last_round($kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create two questions.
        $question1 = $generator->create_question(['kahoodleid' => $kahoodle->id]);
        $question2 = $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Create participants.
        $p1id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user1->id]);
        $p2id = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user2->id]);

        // Question 1: P1 gets 100, P2 gets 50 - P1 leads.
        $generator->create_response(['participantid' => $p1id, 'roundquestionid' => $question1->get_id(), 'points' => 100]);
        $generator->create_response(['participantid' => $p2id, 'roundquestionid' => $question1->get_id(), 'points' => 50]);

        // Question 2: P1 gets 0, P2 gets 100 - P2 catches up and overtakes.
        $generator->create_response(['participantid' => $p1id, 'roundquestionid' => $question2->get_id(), 'points' => 0]);
        $generator->create_response(['participantid' => $p2id, 'roundquestionid' => $question2->get_id(), 'points' => 100]);

        // Set round to results stage for question 2.
        $this->set_round_stage($round, constants::STAGE_QUESTION_RESULTS, 2);
        $round = round::create_from_id($round->get_id());

        // Current rankings (after Q2): P1: 100+0=100, P2: 50+100=150 - P2 is now ahead.
        $currentrankings = $round->get_rankings();
        $this->assertCount(2, $currentrankings);
        $this->assertEquals(1, $currentrankings[$p2id]->minrank);
        $this->assertEquals(2, $currentrankings[$p1id]->minrank);

        // Previous rankings (after Q1): P1 had 100, P2 had 50 - P1 was ahead.
        $prevrankings = $round->get_prev_question_rankings();
        $this->assertCount(2, $prevrankings);
        $this->assertEquals(1, $prevrankings[$p1id]->minrank);
        $this->assertEquals(2, $prevrankings[$p2id]->minrank);

        // Verify rankings changed between questions.
        $this->assertNotEquals(
            $currentrankings[$p1id]->minrank,
            $prevrankings[$p1id]->minrank,
            'P1 rank should have changed between questions'
        );
        $this->assertNotEquals(
            $currentrankings[$p2id]->minrank,
            $prevrankings[$p2id]->minrank,
            'P2 rank should have changed between questions'
        );
    }

    /**
     * Test round duplicate creates new round with copied questions
     *
     * @covers \mod_kahoodle\local\entities\round::duplicate
     */
    public function test_duplicate(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create questions with custom settings.
        $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Question 1',
            'maxpoints' => 1500,
            'minpoints' => 750,
            'questionduration' => 45,
        ]);
        $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Question 2',
            'maxpoints' => 2000,
        ]);

        // Get the original round and mark it as archived.
        $round = questions::get_last_round($kahoodle->id);
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_ARCHIVED, ['id' => $round->get_id()]);
        $DB->set_field('kahoodle_rounds', 'timestarted', time() - 3600, ['id' => $round->get_id()]);
        $DB->set_field('kahoodle_rounds', 'timecompleted', time() - 3000, ['id' => $round->get_id()]);

        // Add statistics to original round questions (should not be copied).
        $DB->execute(
            "UPDATE {kahoodle_round_questions} SET totalresponses = 10, answerdistribution = ? WHERE roundid = ?",
            ['{"A":5,"B":3,"C":2}', $round->get_id()]
        );

        // Reload round to get fresh data.
        $round = round::create_from_id($round->get_id());

        // Duplicate the round.
        $newround = $round->duplicate();

        // Verify new round was created.
        $this->assertNotEquals($round->get_id(), $newround->get_id());
        $this->assertEquals(2, $DB->count_records('kahoodle_rounds', ['kahoodleid' => $kahoodle->id]));

        // Verify new round properties.
        $newroundrecord = $DB->get_record('kahoodle_rounds', ['id' => $newround->get_id()], '*', MUST_EXIST);
        $this->assertEquals('Round 2', $newroundrecord->name);
        $this->assertEquals(constants::STAGE_PREPARATION, $newroundrecord->currentstage);
        $this->assertNull($newroundrecord->timestarted);
        $this->assertNull($newroundrecord->timecompleted);

        // Verify questions were copied.
        $newquestions = $DB->get_records('kahoodle_round_questions', ['roundid' => $newround->get_id()], 'sortorder ASC');
        $this->assertCount(2, $newquestions);

        // Verify question settings were copied.
        $newq1 = array_shift($newquestions);
        $this->assertEquals(1, $newq1->sortorder);
        $this->assertEquals(1500, $newq1->maxpoints);
        $this->assertEquals(750, $newq1->minpoints);
        $this->assertEquals(45, $newq1->questionduration);

        $newq2 = array_shift($newquestions);
        $this->assertEquals(2, $newq2->sortorder);
        $this->assertEquals(2000, $newq2->maxpoints);

        // Verify statistics were NOT copied.
        $this->assertNull($newq1->totalresponses);
        $this->assertNull($newq1->answerdistribution);
        $this->assertNull($newq2->totalresponses);
        $this->assertNull($newq2->answerdistribution);
    }
}
