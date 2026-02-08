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

namespace mod_kahoodle\output;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\participant as participant_entity;

/**
 * Tests for the participant output class
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\output\participant
 */
final class participant_test extends \advanced_testcase {
    /**
     * Helper to set up a kahoodle with questions, a participant, and optional responses.
     *
     * @return array{kahoodle: \stdClass, round: round, cm: \stdClass, roundid: int,
     *     participantid: int, roundquestions: \mod_kahoodle\local\entities\round_question[],
     *     student: \stdClass}
     */
    protected function setup_kahoodle_with_data(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);

        // Create two questions.
        $q1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'What is 2+2?',
            'questionconfig' => "3\n*4\n5",
        ]);
        $q2 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'What is 3+3?',
            'questionconfig' => "*6\n7\n8",
        ]);

        // Get the round that was auto-created.
        $rounds = \mod_kahoodle\api::get_all_rounds($kahoodle->id);
        $round = reset($rounds);
        $roundid = $round->get_id();

        // Create a participant.
        $participantid = $generator->create_participant([
            'roundid' => $roundid,
            'userid' => $student->id,
            'displayname' => 'Player One',
        ]);

        // Get round questions.
        $roundquestions = \mod_kahoodle\local\entities\round_question::get_all_questions_for_round($round);

        return [
            'kahoodle' => $kahoodle,
            'round' => $round,
            'cm' => $cm,
            'roundid' => $roundid,
            'participantid' => $participantid,
            'roundquestions' => $roundquestions,
            'student' => $student,
        ];
    }

    /**
     * Set up $PAGE and get the renderer.
     *
     * @param \stdClass $cm
     * @return \renderer_base
     */
    protected function setup_page(\stdClass $cm): \renderer_base {
        global $PAGE;
        $PAGE->set_url('/mod/kahoodle/view.php', ['id' => $cm->id]);
        $PAGE->set_context(\context_module::instance($cm->id));
        return $PAGE->get_renderer('mod_kahoodle');
    }

    /**
     * Move a round to a specific stage and reload participant entity.
     *
     * @param int $roundid
     * @param string $stage
     * @param int $currentquestion
     * @param int $participantid
     * @return participant_entity
     */
    protected function set_round_and_get_participant(
        int $roundid,
        string $stage,
        int $currentquestion,
        int $participantid
    ): participant_entity {
        global $DB;
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $roundid,
            'currentstage' => $stage,
            'currentquestion' => $currentquestion,
            'stagestarttime' => time(),
            'timestarted' => time(),
        ]);
        $round = round::create_from_id($roundid);
        return $round->get_participant_by_id($participantid);
    }

    /**
     * Test participant output in lobby stage.
     */
    public function test_lobby_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $participant = $this->set_round_and_get_participant(
            $data['roundid'],
            constants::STAGE_LOBBY,
            0,
            $data['participantid']
        );

        $participantoutput = new participant($participant);
        $result = $participantoutput->export_for_template($output);

        $this->assertArrayHasKey('stagesignature', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertEquals(0, $result['duration']);
        $this->assertArrayHasKey('template', $result);
        $this->assertArrayHasKey('templatedata', $result);

        $td = $result['templatedata'];
        $this->assertArrayHasKey('avatarurl', $td);
        $this->assertNotEmpty($td['avatarurl']);
        $this->assertArrayHasKey('displayname', $td);
        $this->assertNotEmpty($td['displayname']);
        $this->assertArrayHasKey('totalscore', $td);
        $this->assertArrayHasKey('caneditavatar', $td);
    }

    /**
     * Test participant output in question preview stage.
     */
    public function test_question_preview(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $participant = $this->set_round_and_get_participant(
            $data['roundid'],
            constants::STAGE_QUESTION_PREVIEW,
            1,
            $data['participantid']
        );

        $participantoutput = new participant($participant);
        $result = $participantoutput->export_for_template($output);

        $td = $result['templatedata'];
        $this->assertArrayHasKey('sortorder', $td);
        $this->assertEquals(1, $td['sortorder']);
        $this->assertArrayHasKey('questiontype', $td);
        $this->assertArrayHasKey('typedata', $td);
    }

    /**
     * Test participant output in question stage when no answer has been submitted.
     */
    public function test_question_not_answered(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $participant = $this->set_round_and_get_participant(
            $data['roundid'],
            constants::STAGE_QUESTION,
            1,
            $data['participantid']
        );

        $participantoutput = new participant($participant);
        $result = $participantoutput->export_for_template($output);

        $td = $result['templatedata'];
        $this->assertArrayHasKey('answered', $td);
        $this->assertFalse($td['answered']);
    }

    /**
     * Test participant output in question stage when answer has been submitted.
     */
    public function test_question_answered(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a response for this participant on question 1.
        $generator->create_response([
            'participantid' => $data['participantid'],
            'roundquestionid' => $data['roundquestions'][0]->get_id(),
            'response' => '2',
            'iscorrect' => 1,
            'points' => 800,
        ]);

        $participant = $this->set_round_and_get_participant(
            $data['roundid'],
            constants::STAGE_QUESTION,
            1,
            $data['participantid']
        );

        $participantoutput = new participant($participant);
        $result = $participantoutput->export_for_template($output);

        $td = $result['templatedata'];
        $this->assertTrue($td['answered']);
        $this->assertArrayHasKey('waitingmessage', $td);
        $this->assertNotEmpty($td['waitingmessage']);
    }

    /**
     * Test participant output in question results stage with a correct response.
     */
    public function test_results_correct(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a correct response for this participant on question 1.
        $generator->create_response([
            'participantid' => $data['participantid'],
            'roundquestionid' => $data['roundquestions'][0]->get_id(),
            'response' => '2',
            'iscorrect' => 1,
            'points' => 800,
        ]);

        $participant = $this->set_round_and_get_participant(
            $data['roundid'],
            constants::STAGE_QUESTION_RESULTS,
            1,
            $data['participantid']
        );

        $participantoutput = new participant($participant);
        $result = $participantoutput->export_for_template($output);

        $td = $result['templatedata'];
        $this->assertArrayHasKey('iscorrect', $td);
        $this->assertTrue($td['iscorrect']);
        $this->assertArrayHasKey('points', $td);
        $this->assertGreaterThan(0, $td['points']);
    }

    /**
     * Test participant output in question results stage with no response (time up).
     */
    public function test_results_no_answer(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        // No response created for this participant.
        $participant = $this->set_round_and_get_participant(
            $data['roundid'],
            constants::STAGE_QUESTION_RESULTS,
            1,
            $data['participantid']
        );

        $participantoutput = new participant($participant);
        $result = $participantoutput->export_for_template($output);

        $td = $result['templatedata'];
        $this->assertArrayHasKey('timeup', $td);
        $this->assertTrue($td['timeup']);
    }

    /**
     * Test participant output in revision stage.
     */
    public function test_revision_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create a response so the participant has score/rank data.
        $generator->create_response([
            'participantid' => $data['participantid'],
            'roundquestionid' => $data['roundquestions'][0]->get_id(),
            'response' => '2',
            'iscorrect' => 1,
            'points' => 800,
        ]);

        $participant = $this->set_round_and_get_participant(
            $data['roundid'],
            constants::STAGE_REVISION,
            0,
            $data['participantid']
        );

        $participantoutput = new participant($participant);
        $result = $participantoutput->export_for_template($output);

        $td = $result['templatedata'];
        // Revision data includes rank-related fields from get_data_for_revision().
        $this->assertArrayHasKey('rankimage', $td);
        $this->assertArrayHasKey('rankheader', $td);
        $this->assertArrayHasKey('rankstatus', $td);
    }
}
