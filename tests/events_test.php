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

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\round_question;
use mod_kahoodle\local\game\participants;
use mod_kahoodle\local\game\progress;
use mod_kahoodle\local\game\responses;

/**
 * Tests for all Kahoodle event classes
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\event\course_module_viewed
 * @covers     \mod_kahoodle\event\course_module_instance_list_viewed
 * @covers     \mod_kahoodle\event\question_created
 * @covers     \mod_kahoodle\event\question_updated
 * @covers     \mod_kahoodle\event\question_removed
 * @covers     \mod_kahoodle\event\round_created
 * @covers     \mod_kahoodle\event\round_updated
 * @covers     \mod_kahoodle\event\participant_joined
 * @covers     \mod_kahoodle\event\participant_left
 * @covers     \mod_kahoodle\event\response_submitted
 */
final class events_test extends \advanced_testcase {
    /**
     * Get the Kahoodle plugin generator
     *
     * @return \mod_kahoodle_generator
     */
    protected function get_generator(): \mod_kahoodle_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
    }

    /**
     * Test course_module_viewed event.
     */
    public function test_course_module_viewed(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        [, $cm] = get_course_and_cm_from_instance($kahoodle->id, 'kahoodle');

        $event = event\course_module_viewed::create_from_record($kahoodle, $cm, $course);
        $event->trigger();

        $this->assertStringContainsString('viewed', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test course_module_instance_list_viewed event.
     */
    public function test_course_module_instance_list_viewed(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $event = event\course_module_instance_list_viewed::create_from_course($course);
        $event->trigger();

        $this->assertStringContainsString('viewed', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test question_created event is triggered when adding a question.
     */
    public function test_question_created(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        $sink = $this->redirectEvents();

        $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Event test question',
        ]);

        $events = $sink->get_events();
        $sink->close();

        $event = null;
        foreach ($events as $e) {
            if ($e instanceof event\question_created) {
                $event = $e;
            }
        }

        $this->assertNotNull($event);
        $this->assertGreaterThan(0, $event->objectid);
        $this->assertEquals($kahoodle->id, $event->other['kahoodleid']);
        $this->assertStringContainsString('created question', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test question_updated event is triggered when editing a question.
     */
    public function test_question_updated(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $roundquestion = $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Original text',
        ]);

        $sink = $this->redirectEvents();

        $editdata = new \stdClass();
        $editdata->questiontext = 'Updated text';
        questions::edit_question($roundquestion, $editdata);

        $events = $sink->get_events();
        $sink->close();

        $event = null;
        foreach ($events as $e) {
            if ($e instanceof event\question_updated) {
                $event = $e;
            }
        }

        $this->assertNotNull($event);
        $this->assertEquals($roundquestion->get_question_id(), $event->objectid);
        $this->assertStringContainsString('updated question', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test question_removed event is triggered when deleting a question.
     */
    public function test_question_removed(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $roundquestion = $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'To be removed',
        ]);

        $sink = $this->redirectEvents();

        questions::delete_question($roundquestion);

        $events = $sink->get_events();
        $sink->close();

        $event = null;
        foreach ($events as $e) {
            if ($e instanceof event\question_removed) {
                $event = $e;
            }
        }

        $this->assertNotNull($event);
        $this->assertEquals($roundquestion->get_question_id(), $event->objectid);
        $this->assertStringContainsString('removed question', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test round_created event is triggered when a round is created.
     */
    public function test_round_created(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        // Create the first round by getting it (auto-creates).
        $round = questions::get_last_round($kahoodle->id);

        // Now duplicate to trigger a round_created event we can capture.
        $sink = $this->redirectEvents();

        $newround = $round->duplicate();

        $events = $sink->get_events();
        $sink->close();

        $event = null;
        foreach ($events as $e) {
            if ($e instanceof event\round_created) {
                $event = $e;
            }
        }

        $this->assertNotNull($event);
        $this->assertEquals($newround->get_id(), $event->objectid);
        $this->assertEquals($kahoodle->id, $event->other['kahoodleid']);
        $this->assertStringContainsString('created round', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test round_updated event is triggered when advancing stages.
     */
    public function test_round_updated(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Test question',
        ]);
        $round = questions::get_last_round($kahoodle->id);

        $sink = $this->redirectEvents();

        // Start game transitions from preparation to lobby, which calls set_current_stage.
        progress::start_game($round);

        $events = $sink->get_events();
        $sink->close();

        $event = null;
        foreach ($events as $e) {
            if ($e instanceof event\round_updated) {
                $event = $e;
            }
        }

        $this->assertNotNull($event);
        $this->assertEquals($round->get_id(), $event->objectid);
        $this->assertEquals($kahoodle->id, $event->other['kahoodleid']);
        $this->assertArrayHasKey('stage', $event->other);
        $this->assertStringContainsString('updated round', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test participant_joined event is triggered when a user joins a round.
     */
    public function test_participant_joined(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Test question',
        ]);
        $round = questions::get_last_round($kahoodle->id);

        // Start the game to move to lobby.
        progress::start_game($round);
        $round = round::create_from_id($round->get_id());

        // Create a user and set them as the current user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Enrol the user so they have the participate capability.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $sink = $this->redirectEvents();

        participants::join_round($round);

        $events = $sink->get_events();
        $sink->close();

        $event = null;
        foreach ($events as $e) {
            if ($e instanceof event\participant_joined) {
                $event = $e;
            }
        }

        $this->assertNotNull($event);
        $this->assertGreaterThan(0, $event->objectid);
        $this->assertEquals($round->get_id(), $event->other['roundid']);
        $this->assertEquals($kahoodle->id, $event->other['kahoodleid']);
        $this->assertStringContainsString('joined round', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test participant_left event is triggered when a user leaves a round.
     */
    public function test_participant_left(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Test question',
        ]);
        $round = questions::get_last_round($kahoodle->id);

        // Start the game to move to lobby.
        progress::start_game($round);
        $round = round::create_from_id($round->get_id());

        // Create a user with both facilitate and participate capabilities, set as current user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Enrol the user so they have participant capability.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // First join, then leave.
        participants::join_round($round);
        $round = round::create_from_id($round->get_id());

        $sink = $this->redirectEvents();

        participants::leave_round($round);

        $events = $sink->get_events();
        $sink->close();

        $event = null;
        foreach ($events as $e) {
            if ($e instanceof event\participant_left) {
                $event = $e;
            }
        }

        $this->assertNotNull($event);
        $this->assertGreaterThan(0, $event->objectid);
        $this->assertEquals($round->get_id(), $event->other['roundid']);
        $this->assertEquals($kahoodle->id, $event->other['kahoodleid']);
        $this->assertStringContainsString('left round', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test response_submitted event is triggered when recording an answer.
     */
    public function test_response_submitted(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $generator = $this->get_generator();

        // Create a question with default multichoice config.
        $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Event test question',
        ]);

        $round = questions::get_last_round($kahoodle->id);

        // Create a user and participant via the generator.
        $user = $this->getDataGenerator()->create_user();
        $participantid = $generator->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $user->id,
        ]);

        // Advance round to question-1 stage.
        progress::start_game($round);
        $sig = $round->get_current_stage()->get_stage_signature();
        progress::advance_to_next_stage($round, $sig);
        $sig = $round->get_current_stage()->get_stage_signature();
        progress::advance_to_next_stage($round, $sig);

        // Set stage start time so response time is deterministic.
        $DB->set_field('kahoodle_rounds', 'stagestarttime', (int)(microtime(true) - 5.0), ['id' => $round->get_id()]);

        // Reload round to pick up stage changes.
        $round = round::create_from_id($round->get_id());
        $participant = $round->get_participant_by_id($participantid);
        $stagesig = $round->get_current_stage()->get_stage_signature();

        $sink = $this->redirectEvents();

        // Record a correct answer (response '2' is correct for default multichoice).
        responses::record_answer($participant, '2', $stagesig);

        $events = $sink->get_events();
        $sink->close();

        $event = null;
        foreach ($events as $e) {
            if ($e instanceof event\response_submitted) {
                $event = $e;
            }
        }

        $this->assertNotNull($event);
        $this->assertGreaterThan(0, $event->objectid);
        $this->assertEquals($round->get_id(), $event->other['roundid']);
        $this->assertArrayHasKey('iscorrect', $event->other);
        $this->assertArrayHasKey('points', $event->other);
        $this->assertStringContainsString('response', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }
}
