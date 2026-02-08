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
use mod_kahoodle\questions;
use mod_kahoodle\local\entities\round;

/**
 * Tests for game progress manager
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\local\game\progress
 */
final class progress_test extends \advanced_testcase {
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
     * Test that start_game transitions a round from preparation to lobby
     */
    public function test_start_game(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);
        $this->assertEquals(constants::STAGE_PREPARATION, $round->get_current_stage_name());

        progress::start_game($round);

        $this->assertEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());
    }

    /**
     * Test that start_game is a no-op if the round is already in lobby (race condition guard)
     */
    public function test_start_game_already_started(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);

        // Start the game first to move to lobby.
        progress::start_game($round);
        $this->assertEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());

        // Starting again should be a no-op.
        progress::start_game($round);
        $this->assertEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());
    }

    /**
     * Test that advance_to_next_stage moves from lobby to the next stage
     */
    public function test_advance_to_next_stage(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);
        progress::start_game($round);
        $this->assertEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());

        $signature = $round->get_current_stage()->get_stage_signature();
        $nextstage = progress::advance_to_next_stage($round, $signature);

        // After lobby, the next stage should be a question-related stage (preview or question).
        $this->assertNotEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());
        $this->assertContains($round->get_current_stage_name(), [
            constants::STAGE_QUESTION_PREVIEW,
            constants::STAGE_QUESTION,
        ]);
    }

    /**
     * Test advancing through all stages from lobby to revision
     */
    public function test_advance_through_all_stages(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);
        progress::start_game($round);

        // Get all stages for reference.
        $allstages = $round->get_all_stages();

        // Advance through all stages until we reach revision or archived.
        $maxiterations = count($allstages) + 5; // Safety limit.
        $iterations = 0;
        $visitedstages = [$round->get_current_stage()->get_stage_signature()];

        while (
            $round->get_current_stage_name() !== constants::STAGE_REVISION
                && $round->get_current_stage_name() !== constants::STAGE_ARCHIVED
                && $iterations < $maxiterations
        ) {
            $signature = $round->get_current_stage()->get_stage_signature();
            progress::advance_to_next_stage($round, $signature);
            $visitedstages[] = $round->get_current_stage()->get_stage_signature();
            $iterations++;
        }

        // Verify we reached the revision stage (or archived if revision is skipped).
        $this->assertContains($round->get_current_stage_name(), [
            constants::STAGE_REVISION,
            constants::STAGE_ARCHIVED,
        ]);

        // Verify we visited more than just the lobby.
        $this->assertGreaterThan(2, count($visitedstages));
    }

    /**
     * Test that advance_to_next_stage returns current stage when signature does not match (stale signature)
     */
    public function test_advance_stale_signature(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);
        progress::start_game($round);

        $this->assertEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());

        // Pass a wrong/stale signature.
        $nextstage = progress::advance_to_next_stage($round, 'wrong-signature');

        // Stage should remain unchanged.
        $this->assertEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());
        $this->assertEquals(constants::STAGE_LOBBY, $nextstage->get_stage_name());
    }

    /**
     * Test that finish_game transitions an in-progress round to archived
     */
    public function test_finish_game(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);

        // Start the game so the round is in progress.
        progress::start_game($round);
        $this->assertTrue($round->is_in_progress());

        progress::finish_game($round);

        $this->assertEquals(constants::STAGE_ARCHIVED, $round->get_current_stage_name());
        $this->assertFalse($round->is_in_progress());
    }

    /**
     * Test that finish_game is a no-op when the round is still in preparation
     */
    public function test_finish_game_not_in_progress(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);
        $this->assertEquals(constants::STAGE_PREPARATION, $round->get_current_stage_name());

        // Finish game should be a no-op when not in progress.
        progress::finish_game($round);

        $this->assertEquals(constants::STAGE_PREPARATION, $round->get_current_stage_name());
    }
}
