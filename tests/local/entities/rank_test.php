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
 * Tests for rank entity display methods
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\local\entities\rank
 */
final class rank_test extends \advanced_testcase {
    /**
     * Helper to create a round with a question and a participant, returning the participant entity.
     *
     * @param string $displayname Display name for the participant
     * @return participant The participant entity
     */
    protected function create_participant(string $displayname = 'Alice'): participant {
        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $round = questions::get_last_round($kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a question so the round has content.
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $user = $this->getDataGenerator()->create_user();
        $participantid = $generator->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $user->id,
            'displayname' => $displayname,
        ]);
        $participant = $round->get_participant_by_id($participantid);
        return $participant;
    }

    /**
     * Helper to create a second participant in the same round as an existing participant.
     *
     * @param participant $existing An existing participant whose round to join
     * @param string $displayname Display name for the new participant
     * @return participant The new participant entity
     */
    protected function create_another_participant(participant $existing, string $displayname = 'Bob'): participant {
        $round = $existing->get_round();

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $user = $this->getDataGenerator()->create_user();
        $participantid = $generator->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $user->id,
            'displayname' => $displayname,
        ]);
        $round->clear_participant_cache();
        return $round->get_participant_by_id($participantid);
    }

    /**
     * Test create_empty returns rank with all zeros
     */
    public function test_create_empty(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = rank::create_empty($participant);

        $this->assertEquals(0, $rank->score);
        $this->assertEquals(0, $rank->minrank);
        $this->assertEquals(0, $rank->maxrank);
        $this->assertEmpty($rank->tiewith);
        $this->assertNull($rank->prevscore);
        $this->assertEmpty($rank->withprevscore);
        $this->assertSame($participant, $rank->participant);
    }

    /**
     * Test get_data_for_revision with zero score shows fail.png
     */
    public function test_revision_zero_score(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 0, 1, 1, [], null, []);

        $data = $rank->get_data_for_revision();

        $this->assertArrayHasKey('rankimage', $data);
        $this->assertStringContainsString('fail.png', $data['rankimage']);
        $this->assertArrayHasKey('rankheader', $data);
        $this->assertNotEmpty($data['rankheader']);
    }

    /**
     * Test get_data_for_revision with first place shows 1.png
     */
    public function test_revision_first_place(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 500, 1, 1, [], null, []);

        $data = $rank->get_data_for_revision();

        $this->assertArrayHasKey('rankimage', $data);
        $this->assertStringContainsString('1.png', $data['rankimage']);
        $this->assertArrayHasKey('rankheader', $data);
    }

    /**
     * Test get_data_for_revision with second place shows 2.png
     */
    public function test_revision_second_place(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 400, 2, 2, [], 500, []);

        $data = $rank->get_data_for_revision();

        $this->assertArrayHasKey('rankimage', $data);
        $this->assertStringContainsString('2.png', $data['rankimage']);
    }

    /**
     * Test get_data_for_revision with rank 5 shows award.png
     */
    public function test_revision_other_rank(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 100, 5, 5, [], 200, []);

        $data = $rank->get_data_for_revision();

        $this->assertArrayHasKey('rankimage', $data);
        $this->assertStringContainsString('award.png', $data['rankimage']);
    }

    /**
     * Test get_data_for_revision with tied participants mentions tie in rank status
     */
    public function test_revision_with_tie(): void {
        $this->resetAfterTest();

        $alice = $this->create_participant('Alice');
        $bob = $this->create_another_participant($alice, 'Bob');

        $rank = new rank($alice, 500, 1, 2, [$bob], null, []);

        $data = $rank->get_data_for_revision();

        $this->assertArrayHasKey('rankstatus', $data);
        $this->assertStringContainsString('Bob', $data['rankstatus']);
    }

    /**
     * Test get_data_for_question_results with zero rank returns empty array
     */
    public function test_question_results_zero_rank(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 0, 0, 0, [], null, []);

        $data = $rank->get_data_for_question_results();

        $this->assertEmpty($data);
    }

    /**
     * Test get_data_for_question_results with zero score returns motivation message
     */
    public function test_question_results_zero_score(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 0, 1, 1, [], null, []);

        $data = $rank->get_data_for_question_results();

        $this->assertArrayHasKey('rankstatus', $data);
        $this->assertNotEmpty($data['rankstatus']);
    }

    /**
     * Test get_data_for_question_results with positive score returns rank message
     */
    public function test_question_results_with_score(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 500, 2, 2, [], 600, []);

        $data = $rank->get_data_for_question_results();

        $this->assertArrayHasKey('rankstatus', $data);
        $this->assertNotEmpty($data['rankstatus']);
    }

    /**
     * Test get_rank_as_range returns single number when minrank equals maxrank
     */
    public function test_rank_as_range_single(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 100, 4, 4, [], null, []);

        $this->assertEquals('4', $rank->get_rank_as_range());
    }

    /**
     * Test get_rank_as_range returns range when minrank differs from maxrank
     */
    public function test_rank_as_range_tie(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 100, 2, 5, [], null, []);

        $this->assertEquals('2-5', $rank->get_rank_as_range());
    }

    /**
     * Test get_rank_movement_status returns negative when rank improved (moved up)
     */
    public function test_rank_movement_up(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();

        // Previous rank was 3rd, current rank is 1st (moved up).
        $prevrank = new rank($participant, 100, 3, 3, [], null, []);
        $currentrank = new rank($participant, 500, 1, 1, [], null, []);
        $currentrank->prevquestionrank = $prevrank;

        $this->assertEquals(-2, $currentrank->get_rank_movement_status());
    }

    /**
     * Test get_rank_movement_status returns positive when rank worsened (moved down)
     */
    public function test_rank_movement_down(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();

        // Previous rank was 1st, current rank is 3rd (moved down).
        $prevrank = new rank($participant, 500, 1, 1, [], null, []);
        $currentrank = new rank($participant, 500, 3, 3, [], null, []);
        $currentrank->prevquestionrank = $prevrank;

        $this->assertEquals(2, $currentrank->get_rank_movement_status());
    }

    /**
     * Test get_rank_movement_status returns 0 when no previous question rank exists
     */
    public function test_rank_movement_none(): void {
        $this->resetAfterTest();

        $participant = $this->create_participant();
        $rank = new rank($participant, 500, 1, 1, [], null, []);

        // Prevquestionrank is null by default.
        $this->assertEquals(0, $rank->get_rank_movement_status());
    }
}
