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

namespace mod_kahoodle\task;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\game\progress;
use mod_kahoodle\local\game\questions;

/**
 * Tests for the auto_archive_round ad-hoc task
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\task\auto_archive_round
 */
final class auto_archive_round_test extends \advanced_testcase {
    /**
     * Helper to create a kahoodle with one question and return the round in lobby stage
     *
     * @return round
     */
    protected function create_started_round(): round {
        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);
        progress::start_game($round);

        return $round;
    }

    /**
     * Helper to set round fields directly in the database
     *
     * @param round $round
     * @param array $fields
     */
    protected function update_round_fields(round $round, array $fields): void {
        global $DB;
        $DB->update_record('kahoodle_rounds', ['id' => $round->get_id()] + $fields);
    }

    /**
     * Test that execute archives a round when the auto-archive time has passed
     */
    public function test_execute_archives_expired_round(): void {
        $this->resetAfterTest();

        $round = $this->create_started_round();
        $this->assertEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());

        // Set timestarted to well in the past so auto-archive time has passed.
        $this->update_round_fields($round, [
            'timestarted' => time() - constants::MAX_ROUND_DURATION - 100,
            'stagestarttime' => time() - constants::MAX_ROUND_DURATION - 100,
        ]);

        // Create and execute the task.
        $task = new auto_archive_round();
        $task->set_custom_data((object)['roundid' => $round->get_id()]);
        $task->execute();

        // Reload and verify the round is archived.
        $round = round::create_from_id($round->get_id());
        $this->assertEquals(constants::STAGE_ARCHIVED, $round->get_current_stage_name());
    }

    /**
     * Test that execute does not archive when the auto-archive time has not passed yet
     */
    public function test_execute_does_not_archive_before_deadline(): void {
        $this->resetAfterTest();

        $round = $this->create_started_round();
        $this->assertEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());

        // Timestarted is just now (set by start_game), so auto-archive time is in the future.
        $task = new auto_archive_round();
        $task->set_custom_data((object)['roundid' => $round->get_id()]);
        $task->execute();

        // Round should still be in lobby.
        $round = round::create_from_id($round->get_id());
        $this->assertEquals(constants::STAGE_LOBBY, $round->get_current_stage_name());
    }

    /**
     * Test that execute is a no-op when the round is already archived
     */
    public function test_execute_noop_when_already_archived(): void {
        $this->resetAfterTest();

        $round = $this->create_started_round();
        progress::finish_game($round);
        $this->assertEquals(constants::STAGE_ARCHIVED, $round->get_current_stage_name());
        $timecompleted = $round->get_timecompleted();

        $task = new auto_archive_round();
        $task->set_custom_data((object)['roundid' => $round->get_id()]);
        $task->execute();

        // Verify it's still archived with the same completion time.
        $round = round::create_from_id($round->get_id());
        $this->assertEquals(constants::STAGE_ARCHIVED, $round->get_current_stage_name());
        $this->assertEquals($timecompleted, $round->get_timecompleted());
    }

    /**
     * Test that execute is a no-op when the round has been deleted
     */
    public function test_execute_noop_when_round_deleted(): void {
        global $DB;
        $this->resetAfterTest();

        $round = $this->create_started_round();
        $roundid = $round->get_id();
        $DB->delete_records('kahoodle_rounds', ['id' => $roundid]);

        // Should not throw an exception.
        $task = new auto_archive_round();
        $task->set_custom_data((object)['roundid' => $roundid]);
        $task->execute();
    }

    /**
     * Test that execute archives a round in revision stage when revision deadline has passed
     */
    public function test_execute_archives_expired_revision(): void {
        $this->resetAfterTest();

        $round = $this->create_started_round();

        // Simulate revision stage with stagestarttime in the past.
        $this->update_round_fields($round, [
            'currentstage' => constants::STAGE_REVISION,
            'stagestarttime' => time() - constants::MAX_REVISION_DURATION - 100,
        ]);

        $task = new auto_archive_round();
        $task->set_custom_data((object)['roundid' => $round->get_id()]);
        $task->execute();

        $round = round::create_from_id($round->get_id());
        $this->assertEquals(constants::STAGE_ARCHIVED, $round->get_current_stage_name());
    }

    /**
     * Test that execute does not archive a round in revision stage when revision deadline has not passed
     */
    public function test_execute_does_not_archive_fresh_revision(): void {
        $this->resetAfterTest();

        $round = $this->create_started_round();

        // Simulate revision stage with stagestarttime just now.
        $this->update_round_fields($round, [
            'currentstage' => constants::STAGE_REVISION,
            'stagestarttime' => time(),
        ]);

        $task = new auto_archive_round();
        $task->set_custom_data((object)['roundid' => $round->get_id()]);
        $task->execute();

        $round = round::create_from_id($round->get_id());
        $this->assertEquals(constants::STAGE_REVISION, $round->get_current_stage_name());
    }

    /**
     * Test that schedule queues an ad-hoc task when the round is in progress
     */
    public function test_schedule_queues_task(): void {
        global $DB;
        $this->resetAfterTest();

        // Clear any tasks queued by start_game.
        $DB->delete_records('task_adhoc', ['classname' => '\\' . auto_archive_round::class]);

        $round = $this->create_started_round();

        auto_archive_round::schedule($round);

        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\' . auto_archive_round::class]);
        // One from start_game + one from our explicit call, but start_game's was deleted above,
        // so there should be exactly one.
        $this->assertCount(1, $tasks);

        $task = reset($tasks);
        $data = json_decode($task->customdata);
        $this->assertEquals($round->get_id(), $data->roundid);

        // Next run time should be auto-archive time + 1.
        $expectedtime = $round->get_auto_archive_time() + 1;
        $this->assertEquals($expectedtime, (int)$task->nextruntime);
    }

    /**
     * Test that schedule does not queue a task when the round is in preparation
     */
    public function test_schedule_noop_for_preparation(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);
        $this->assertEquals(constants::STAGE_PREPARATION, $round->get_current_stage_name());

        auto_archive_round::schedule($round);

        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\' . auto_archive_round::class]);
        $this->assertCount(0, $tasks);
    }

    /**
     * Test that start_game schedules an auto-archive task
     */
    public function test_start_game_schedules_task(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);

        // No tasks before starting.
        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\' . auto_archive_round::class]);
        $this->assertCount(0, $tasks);

        progress::start_game($round);

        // Task should be scheduled after starting.
        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\' . auto_archive_round::class]);
        $this->assertCount(1, $tasks);

        $task = reset($tasks);
        $data = json_decode($task->customdata);
        $this->assertEquals($round->get_id(), $data->roundid);
    }

    /**
     * Test get_auto_archive_time returns null for preparation stage
     */
    public function test_get_auto_archive_time_preparation(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $generator->create_question(['kahoodleid' => $kahoodle->id]);

        $round = questions::get_last_round($kahoodle->id);
        $this->assertNull($round->get_auto_archive_time());
    }

    /**
     * Test get_auto_archive_time returns null for archived stage
     */
    public function test_get_auto_archive_time_archived(): void {
        $this->resetAfterTest();

        $round = $this->create_started_round();
        progress::finish_game($round);
        $this->assertNull($round->get_auto_archive_time());
    }

    /**
     * Test get_auto_archive_time returns timestarted + MAX_ROUND_DURATION for lobby
     */
    public function test_get_auto_archive_time_lobby(): void {
        $this->resetAfterTest();

        $round = $this->create_started_round();

        $expected = $round->get_timestarted() + constants::MAX_ROUND_DURATION;
        $this->assertEquals($expected, $round->get_auto_archive_time());
    }

    /**
     * Test get_auto_archive_time returns the minimum of overall and revision deadlines
     */
    public function test_get_auto_archive_time_revision(): void {
        $this->resetAfterTest();

        $round = $this->create_started_round();
        $now = time();

        // Simulate revision stage with known times.
        $this->update_round_fields($round, [
            'currentstage' => constants::STAGE_REVISION,
            'stagestarttime' => $now,
        ]);

        $round = round::create_from_id($round->get_id());

        // Revision deadline should be stagestarttime + MAX_REVISION_DURATION.
        $revisiondeadline = $now + constants::MAX_REVISION_DURATION;
        // Overall deadline is timestarted + MAX_ROUND_DURATION (should be much later since round just started).
        $overalldeadline = $round->get_timestarted() + constants::MAX_ROUND_DURATION;

        // Revision deadline should be sooner than the overall deadline for a freshly started round.
        $this->assertLessThan($overalldeadline, $revisiondeadline);
        $this->assertEquals($revisiondeadline, $round->get_auto_archive_time());
    }

    /**
     * Test get_auto_archive_time uses overall deadline when it is sooner than revision deadline
     */
    public function test_get_auto_archive_time_revision_overall_sooner(): void {
        $this->resetAfterTest();

        $round = $this->create_started_round();
        $now = time();

        // Set timestarted far in the past so the overall deadline is very soon.
        $timestarted = $now - constants::MAX_ROUND_DURATION + 60;
        $this->update_round_fields($round, [
            'timestarted' => $timestarted,
            'currentstage' => constants::STAGE_REVISION,
            'stagestarttime' => $now,
        ]);

        $round = round::create_from_id($round->get_id());

        $overalldeadline = $timestarted + constants::MAX_ROUND_DURATION;
        $revisiondeadline = $now + constants::MAX_REVISION_DURATION;

        // Overall deadline should be sooner.
        $this->assertLessThan($revisiondeadline, $overalldeadline);
        $this->assertEquals($overalldeadline, $round->get_auto_archive_time());
    }
}
