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
use mod_kahoodle\local\entities\round;

/**
 * Tests for playback_stages web service
 *
 * @covers     \mod_kahoodle\external\playback_stages
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class playback_stages_test extends \advanced_testcase {
    /**
     * Create a kahoodle with completed round for testing
     *
     * @return array [kahoodle, round, course, teacher]
     */
    protected function create_completed_round(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create questions.
        $q1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Q1',
            'questionconfig' => "A\n*B\nC",
        ]);
        $q2 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Q2',
            'questionconfig' => "*X\nY\nZ",
        ]);

        // Get the round.
        $round = \mod_kahoodle\local\game\questions::get_last_round($kahoodle->id);

        // Create participants.
        $p1 = $generator->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $student1->id,
            'displayname' => 'Sam',
            'totalscore' => 1700,
        ]);
        $p2 = $generator->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $student2->id,
            'displayname' => 'Alex',
            'totalscore' => 600,
        ]);

        // Create responses.
        $generator->create_response([
            'participantid' => $p1,
            'roundquestionid' => $q1->get_id(),
            'iscorrect' => 1,
            'points' => 900,
        ]);
        $generator->create_response([
            'participantid' => $p1,
            'roundquestionid' => $q2->get_id(),
            'iscorrect' => 1,
            'points' => 800,
        ]);
        $generator->create_response([
            'participantid' => $p2,
            'roundquestionid' => $q1->get_id(),
            'iscorrect' => 0,
            'points' => 0,
        ]);
        $generator->create_response([
            'participantid' => $p2,
            'roundquestionid' => $q2->get_id(),
            'iscorrect' => 1,
            'points' => 600,
        ]);

        // Advance round to revision.
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $round->get_id(),
            'currentstage' => constants::STAGE_REVISION,
            'timestarted' => (int)(time() - 3600),
            'timecompleted' => (int)time(),
            'stagestarttime' => (int)time(),
        ]);
        $round = round::create_from_id($round->get_id());

        return [$kahoodle, $round, $course, $teacher];
    }

    /**
     * Test single-round playback returns expected stages
     */
    public function test_single_round_playback(): void {
        $this->resetAfterTest();

        [$kahoodle, $round, $course, $teacher] = $this->create_completed_round();
        $this->setUser($teacher);

        $result = playback_stages::execute($round->get_id(), 0);
        $result = \core_external\external_api::clean_returnvalue(playback_stages::execute_returns(), $result);

        // Verify basic structure.
        $this->assertArrayHasKey('quiztitle', $result);
        $this->assertArrayHasKey('totalquestions', $result);
        $this->assertArrayHasKey('stages', $result);
        $this->assertEquals(2, $result['totalquestions']);

        // Verify we have stages.
        $this->assertNotEmpty($result['stages']);

        // Each stage should have the required keys.
        foreach ($result['stages'] as $stage) {
            $this->assertArrayHasKey('stagesignature', $stage);
            $this->assertArrayHasKey('template', $stage);
            $this->assertArrayHasKey('duration', $stage);
            $this->assertArrayHasKey('templatedata', $stage);

            // Template data should be valid JSON.
            $data = json_decode($stage['templatedata'], true);
            $this->assertNotNull($data, "templatedata should be valid JSON for stage {$stage['stagesignature']}");
            $this->assertTrue($data['isplayback'], "isplayback should be true for stage {$stage['stagesignature']}");
        }

        // Verify lobby is the first stage.
        $this->assertEquals('lobby', $result['stages'][0]['stagesignature']);
        $lobbydata = json_decode($result['stages'][0]['templatedata'], true);
        $this->assertArrayHasKey('participants', $lobbydata);
        $this->assertCount(2, $lobbydata['participants']);
        $this->assertArrayHasKey('qrcodeurl', $lobbydata);

        // Verify we have question stages.
        $signatures = array_column($result['stages'], 'stagesignature');
        $this->assertContains('question-1', $signatures);
        $this->assertContains('question-2', $signatures);
        $this->assertContains('results-1', $signatures);
        $this->assertContains('results-2', $signatures);

        // Verify revision is the last stage.
        $laststage = end($result['stages']);
        $this->assertEquals('revision', $laststage['stagesignature']);
        $revisiondata = json_decode($laststage['templatedata'], true);
        $this->assertTrue($revisiondata['isrevision']);
        $this->assertTrue($revisiondata['skippodium']);
        $this->assertNotEmpty($revisiondata['leaders']);
    }

    /**
     * Test all-rounds playback returns expected stages
     */
    public function test_all_rounds_playback(): void {
        $this->resetAfterTest();

        [$kahoodle, $round, $course, $teacher] = $this->create_completed_round();
        $this->setUser($teacher);

        $result = playback_stages::execute(0, $kahoodle->id);
        $result = \core_external\external_api::clean_returnvalue(playback_stages::execute_returns(), $result);

        // Verify basic structure.
        $this->assertArrayHasKey('quiztitle', $result);
        $this->assertArrayHasKey('totalquestions', $result);
        $this->assertArrayHasKey('stages', $result);
        $this->assertEquals(2, $result['totalquestions']);

        // Verify we have stages.
        $this->assertNotEmpty($result['stages']);

        // All-rounds mode should NOT have a lobby stage.
        $signatures = array_column($result['stages'], 'stagesignature');
        $this->assertNotContains('lobby', $signatures);

        // Should have question stages.
        $this->assertContains('question-1', $signatures);
        $this->assertContains('question-2', $signatures);

        // Should have a revision stage at the end.
        $laststage = end($result['stages']);
        $this->assertEquals('revision', $laststage['stagesignature']);
        $revisiondata = json_decode($laststage['templatedata'], true);
        $this->assertTrue($revisiondata['isrevision']);
        $this->assertTrue($revisiondata['skippodium']);
        $this->assertNotEmpty($revisiondata['leaders']);

        // Each stage should be playback.
        foreach ($result['stages'] as $stage) {
            $data = json_decode($stage['templatedata'], true);
            $this->assertTrue($data['isplayback'], "isplayback should be true for stage {$stage['stagesignature']}");
        }
    }

    /**
     * Test that providing neither roundid nor kahoodleid throws exception
     */
    public function test_no_parameters(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        playback_stages::execute(0, 0);
    }

    /**
     * Test that providing both roundid and kahoodleid throws exception
     */
    public function test_both_parameters(): void {
        $this->resetAfterTest();

        [$kahoodle, $round, $course, $teacher] = $this->create_completed_round();
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        playback_stages::execute($round->get_id(), $kahoodle->id);
    }

    /**
     * Test that students cannot access playback
     */
    public function test_no_permission(): void {
        $this->resetAfterTest();

        [$kahoodle, $round, $course] = $this->create_completed_round();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        playback_stages::execute($round->get_id(), 0);
    }

    /**
     * Test all-rounds playback results stages have correct aggregated response counts
     */
    public function test_all_rounds_response_counts(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a question with 3 options: A, *B (correct), C.
        $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Q1',
            'questionconfig' => "A\n*B\nC",
        ]);

        // Round 1: 3 participants, various answers.
        $round1 = \mod_kahoodle\local\game\questions::get_last_round($kahoodle->id);
        $round1questions = \mod_kahoodle\local\entities\round_question::get_all_questions_for_round($round1);
        $round1q1 = reset($round1questions);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $p1 = $generator->create_participant(['roundid' => $round1->get_id(), 'userid' => $user1->id, 'totalscore' => 100]);
        $p2 = $generator->create_participant(['roundid' => $round1->get_id(), 'userid' => $user2->id, 'totalscore' => 0]);
        $p3 = $generator->create_participant(['roundid' => $round1->get_id(), 'userid' => $user3->id, 'totalscore' => 0]);

        // P1 answers B (option 2, correct), P2 answers A (option 1), P3 answers C (option 3).
        $generator->create_response([
            'participantid' => $p1, 'roundquestionid' => $round1q1->get_id(),
            'response' => '2', 'iscorrect' => 1, 'points' => 100,
        ]);
        $generator->create_response([
            'participantid' => $p2, 'roundquestionid' => $round1q1->get_id(),
            'response' => '1', 'iscorrect' => 0, 'points' => 0,
        ]);
        $generator->create_response([
            'participantid' => $p3, 'roundquestionid' => $round1q1->get_id(),
            'response' => '3', 'iscorrect' => 0, 'points' => 0,
        ]);

        // Complete round 1.
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $round1->get_id(),
            'currentstage' => constants::STAGE_ARCHIVED,
            'timestarted' => (int)(time() - 7200),
            'timecompleted' => (int)(time() - 3600),
            'stagestarttime' => (int)(time() - 3600),
        ]);
        $round1 = round::create_from_id($round1->get_id());

        // Round 2: duplicate from round 1 (copies questions), then complete it.
        $round2 = $round1->duplicate();
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $round2->get_id(),
            'currentstage' => constants::STAGE_REVISION,
            'timestarted' => (int)(time() - 3600),
            'timecompleted' => (int)time(),
            'stagestarttime' => (int)time(),
        ]);
        $round2 = round::create_from_id($round2->get_id());

        // Get round2's round_question for q1.
        $round2questions = \mod_kahoodle\local\entities\round_question::get_all_questions_for_round($round2);
        $round2q1 = reset($round2questions);

        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();
        $p4 = $generator->create_participant(['roundid' => $round2->get_id(), 'userid' => $user4->id, 'totalscore' => 100]);
        $p5 = $generator->create_participant(['roundid' => $round2->get_id(), 'userid' => $user5->id, 'totalscore' => 100]);

        // Both answer B (option 2, correct).
        $generator->create_response([
            'participantid' => $p4, 'roundquestionid' => $round2q1->get_id(),
            'response' => '2', 'iscorrect' => 1, 'points' => 100,
        ]);
        $generator->create_response([
            'participantid' => $p5, 'roundquestionid' => $round2q1->get_id(),
            'response' => '2', 'iscorrect' => 1, 'points' => 100,
        ]);

        $this->setUser($teacher);

        $result = playback_stages::execute(0, $kahoodle->id);
        $result = \core_external\external_api::clean_returnvalue(playback_stages::execute_returns(), $result);

        // Find the results-1 stage.
        $resultsstage = null;
        foreach ($result['stages'] as $stage) {
            if ($stage['stagesignature'] === 'results-1') {
                $resultsstage = $stage;
                break;
            }
        }
        $this->assertNotNull($resultsstage, 'Should have a results-1 stage');

        $templatedata = json_decode($resultsstage['templatedata'], true);
        $typedata = json_decode($templatedata['typedata'], true);

        $this->assertArrayHasKey('options', $typedata, 'typedata should have options key');

        // Expected aggregated counts across both rounds:
        // Option 1 (A): 1 response (from round 1, P2).
        // Option 2 (B): 3 responses (from round 1 P1, round 2 P4, round 2 P5).
        // Option 3 (C): 1 response (from round 1, P3).
        $counts = array_column($typedata['options'], 'count');
        $this->assertEquals([1, 3, 1], $counts, 'Aggregated response counts should be [1, 3, 1]');
    }

    /**
     * Test single-round playback has leaders stage between questions
     */
    public function test_leaders_stages(): void {
        $this->resetAfterTest();

        [$kahoodle, $round, $course, $teacher] = $this->create_completed_round();
        $this->setUser($teacher);

        $result = playback_stages::execute($round->get_id(), 0);
        $result = \core_external\external_api::clean_returnvalue(playback_stages::execute_returns(), $result);
        $signatures = array_column($result['stages'], 'stagesignature');

        // Should have leaders stage after question 1 (but not after last question).
        $this->assertContains('leaders-1', $signatures);

        // Verify leaders data.
        $leadersstage = null;
        foreach ($result['stages'] as $stage) {
            if ($stage['stagesignature'] === 'leaders-1') {
                $leadersstage = $stage;
                break;
            }
        }
        $this->assertNotNull($leadersstage);
        $leadersdata = json_decode($leadersstage['templatedata'], true);
        $this->assertArrayHasKey('leaders', $leadersdata);
        $this->assertNotEmpty($leadersdata['leaders']);
    }
}
