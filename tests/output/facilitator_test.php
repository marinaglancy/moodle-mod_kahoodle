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

/**
 * Tests for the facilitator output class
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\output\facilitator
 */
final class facilitator_test extends \advanced_testcase {
    /**
     * Helper to set up a kahoodle with questions, participants, and responses.
     *
     * @return array{kahoodle: \stdClass, round: round, cm: \stdClass, roundid: int,
     *     participants: int[], roundquestions: \mod_kahoodle\local\entities\round_question[]}
     */
    protected function setup_kahoodle_with_data(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');

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
        $rounds = \mod_kahoodle\local\game\instance::get_all_rounds($kahoodle->id);
        $round = reset($rounds);
        $roundid = $round->get_id();

        // Create participants.
        $p1id = $generator->create_participant([
            'roundid' => $roundid,
            'userid' => $student1->id,
            'displayname' => 'Player One',
        ]);
        $p2id = $generator->create_participant([
            'roundid' => $roundid,
            'userid' => $student2->id,
            'displayname' => 'Player Two',
        ]);

        // Get round questions.
        $roundquestions = \mod_kahoodle\local\entities\round_question::get_all_questions_for_round($round);

        // Create responses for question 1.
        $generator->create_response([
            'participantid' => $p1id,
            'roundquestionid' => $roundquestions[0]->get_id(),
            'response' => '2',
            'iscorrect' => 1,
            'points' => 800,
        ]);
        $generator->create_response([
            'participantid' => $p2id,
            'roundquestionid' => $roundquestions[0]->get_id(),
            'response' => '1',
            'iscorrect' => 0,
            'points' => 0,
        ]);

        return [
            'kahoodle' => $kahoodle,
            'round' => $round,
            'cm' => $cm,
            'roundid' => $roundid,
            'participants' => [$p1id, $p2id],
            'roundquestions' => $roundquestions,
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
     * Move a round to a specific stage.
     *
     * @param int $roundid
     * @param string $stage
     * @param int $currentquestion
     * @return round
     */
    protected function set_round_stage(int $roundid, string $stage, int $currentquestion = 0): round {
        global $DB;
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $roundid,
            'currentstage' => $stage,
            'currentquestion' => $currentquestion,
            'stagestarttime' => time(),
            'timestarted' => time(),
        ]);
        return round::create_from_id($roundid);
    }

    /**
     * Test facilitator output in lobby stage.
     */
    public function test_lobby_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_LOBBY);

        $facilitator = new facilitator($round);
        $result = $facilitator->export_for_template($output);

        $this->assertArrayHasKey('stagesignature', $result);
        $this->assertArrayHasKey('template', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('templatedata', $result);

        $this->assertStringContainsString('facilitator/lobby', $result['template']);

        $td = $result['templatedata'];
        $this->assertArrayHasKey('participants', $td);
        $this->assertIsArray($td['participants']);
        $this->assertCount(2, $td['participants']);
        $this->assertArrayHasKey('participantcount', $td);
        $this->assertEquals(2, $td['participantcount']);
        $this->assertArrayHasKey('qrcodeurl', $td);
        $this->assertNotEmpty($td['qrcodeurl']);
    }

    /**
     * Test facilitator output in question preview stage.
     */
    public function test_question_preview_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_QUESTION_PREVIEW, 1);

        $facilitator = new facilitator($round);
        $result = $facilitator->export_for_template($output);

        $this->assertNotNull($result['template']);
        $this->assertStringContainsString('preview', $result['stagesignature']);
    }

    /**
     * Test facilitator output in question stage.
     */
    public function test_question_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_QUESTION, 1);

        $facilitator = new facilitator($round);
        $result = $facilitator->export_for_template($output);

        $this->assertNotNull($result['template']);
        $td = $result['templatedata'];
        // Question data should contain typedata with options info.
        $this->assertArrayHasKey('typedata', $td);
        $typedata = json_decode($td['typedata'], true);
        $this->assertIsArray($typedata);
        $this->assertArrayHasKey('options', $typedata);
    }

    /**
     * Test facilitator output in question results stage.
     */
    public function test_question_results_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_QUESTION_RESULTS, 1);

        $facilitator = new facilitator($round);
        $result = $facilitator->export_for_template($output);

        $this->assertNotNull($result['template']);
        $td = $result['templatedata'];
        $this->assertArrayHasKey('typedata', $td);
        $typedata = json_decode($td['typedata'], true);
        $this->assertIsArray($typedata);
        // Results should have iscorrect info in options.
        $this->assertArrayHasKey('options', $typedata);
        $this->assertNotEmpty($typedata['options']);
        $this->assertArrayHasKey('iscorrect', $typedata['options'][0]);
    }

    /**
     * Test facilitator output in leaders stage.
     */
    public function test_leaders_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_LEADERS, 1);

        $facilitator = new facilitator($round);
        $result = $facilitator->export_for_template($output);

        $td = $result['templatedata'];
        $this->assertArrayHasKey('leaders', $td);
        $this->assertIsArray($td['leaders']);
        $this->assertNotEmpty($td['leaders']);
    }

    /**
     * Test facilitator output in revision stage.
     */
    public function test_revision_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_REVISION);

        $facilitator = new facilitator($round);
        $result = $facilitator->export_for_template($output);

        $td = $result['templatedata'];
        $this->assertArrayHasKey('isrevision', $td);
        $this->assertTrue($td['isrevision']);
    }

    /**
     * Test facilitator output in archived stage.
     */
    public function test_archived_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_data();
        $output = $this->setup_page($data['cm']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_ARCHIVED);

        $facilitator = new facilitator($round);
        $result = $facilitator->export_for_template($output);

        $this->assertNull($result['template']);
    }
}
