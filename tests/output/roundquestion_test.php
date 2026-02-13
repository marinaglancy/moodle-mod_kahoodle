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
use mod_kahoodle\local\entities\round_question;

/**
 * Tests for the roundquestion output class
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\output\roundquestion
 */
final class roundquestion_test extends \advanced_testcase {
    /**
     * Helper to create a kahoodle with questions and return the first round question.
     *
     * @return array{kahoodle: \stdClass, cm: \stdClass, round: round, roundid: int,
     *     roundquestion: round_question, roundquestions: round_question[]}
     */
    protected function setup_kahoodle_with_questions(): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);

        // Create a question.
        $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'What is 2+2?',
            'questionconfig' => "3\n*4\n5",
        ]);

        // Get the round that was auto-created.
        $rounds = \mod_kahoodle\local\game\instance::get_all_rounds($kahoodle->id);
        $round = reset($rounds);
        $roundid = $round->get_id();

        // Get round questions.
        $roundquestions = round_question::get_all_questions_for_round($round);

        return [
            'kahoodle' => $kahoodle,
            'cm' => $cm,
            'round' => $round,
            'roundid' => $roundid,
            'roundquestion' => $roundquestions[0],
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
     * Test roundquestion output in preview stage.
     */
    public function test_preview_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_questions();
        $output = $this->setup_page($data['cm']);

        $rqoutput = new roundquestion($data['roundquestion'], constants::STAGE_QUESTION_PREVIEW, false);
        $result = $rqoutput->export_for_template($output);

        $this->assertNotEmpty($result->questiontext);
        $this->assertObjectHasProperty('sortorder', $result);
        $this->assertGreaterThanOrEqual(1, $result->sortorder);
        $this->assertObjectHasProperty('stage', $result);
        $this->assertEquals(constants::STAGE_QUESTION_PREVIEW, $result->stage);
    }

    /**
     * Test roundquestion output in question stage.
     */
    public function test_question_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_questions();
        $output = $this->setup_page($data['cm']);

        $rqoutput = new roundquestion($data['roundquestion'], constants::STAGE_QUESTION, false);
        $result = $rqoutput->export_for_template($output);

        $this->assertObjectHasProperty('typedata', $result);
        $typedata = json_decode($result->typedata, true);
        $this->assertIsArray($typedata);
        $this->assertArrayHasKey('options', $typedata);
        $this->assertNotEmpty($typedata['options']);
        $this->assertArrayHasKey('optioncount', $typedata);
        $this->assertEquals(3, $typedata['optioncount']);
    }

    /**
     * Test roundquestion output in results stage with real data.
     */
    public function test_results_stage(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle_with_questions();
        $output = $this->setup_page($data['cm']);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Set round to lobby first to allow participant creation to make sense.
        global $DB;
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $data['roundid'],
            'currentstage' => constants::STAGE_QUESTION_RESULTS,
            'currentquestion' => 1,
            'stagestarttime' => time(),
            'timestarted' => time(),
        ]);

        // Create a participant and response so results have real data.
        $participantid = $generator->create_participant([
            'roundid' => $data['roundid'],
            'userid' => $data['student']->id,
            'displayname' => 'Tester',
        ]);
        $generator->create_response([
            'participantid' => $participantid,
            'roundquestionid' => $data['roundquestion']->get_id(),
            'response' => '2',
            'iscorrect' => 1,
            'points' => 900,
        ]);

        $rqoutput = new roundquestion($data['roundquestion'], constants::STAGE_QUESTION_RESULTS, false);
        $result = $rqoutput->export_for_template($output);

        $this->assertObjectHasProperty('typedata', $result);
        $typedata = json_decode($result->typedata, true);
        $this->assertIsArray($typedata);
        $this->assertArrayHasKey('options', $typedata);
        $this->assertNotEmpty($typedata['options']);
        // In results stage, options should have iscorrect info.
        $this->assertArrayHasKey('iscorrect', $typedata['options'][0]);
    }
}
