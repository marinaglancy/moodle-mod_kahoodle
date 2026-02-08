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

/**
 * Tests for the results output class
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\output\results
 */
final class results_test extends \advanced_testcase {
    /**
     * Helper to create a kahoodle with a course, teacher, and questions.
     *
     * @return array{kahoodle: \stdClass, cm: \cm_info, kahoodlerecord: \stdClass,
     *     teacher: \stdClass, student: \stdClass, roundid: int, course: \stdClass}
     */
    protected function setup_kahoodle(): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);

        // Create two questions.
        $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'What is 2+2?',
            'questionconfig' => "3\n*4\n5",
        ]);
        $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'What is 3+3?',
            'questionconfig' => "*6\n7\n8",
        ]);

        // Get cm_info object.
        [, $cm] = get_course_and_cm_from_instance($kahoodle->id, 'kahoodle');
        global $DB;
        $kahoodlerecord = $DB->get_record('kahoodle', ['id' => $kahoodle->id]);

        // Get the round that was auto-created.
        $rounds = \mod_kahoodle\api::get_all_rounds($kahoodle->id);
        $round = reset($rounds);
        $roundid = $round->get_id();

        return [
            'kahoodle' => $kahoodle,
            'cm' => $cm,
            'kahoodlerecord' => $kahoodlerecord,
            'teacher' => $teacher,
            'student' => $student,
            'roundid' => $roundid,
            'course' => $course,
        ];
    }

    /**
     * Set up $PAGE and get the renderer.
     *
     * @param \cm_info $cm
     * @return \renderer_base
     */
    protected function setup_page(\cm_info $cm): \renderer_base {
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
     */
    protected function set_round_stage(int $roundid, string $stage, int $currentquestion = 0): void {
        global $DB;
        $updatedata = [
            'id' => $roundid,
            'currentstage' => $stage,
            'currentquestion' => $currentquestion,
            'stagestarttime' => time(),
        ];
        if ($stage !== constants::STAGE_PREPARATION) {
            $updatedata['timestarted'] = time() - 3600;
        }
        if ($stage === constants::STAGE_ARCHIVED) {
            $updatedata['timecompleted'] = time();
        }
        $DB->update_record('kahoodle_rounds', (object)$updatedata);
    }

    /**
     * Test results output with a round in preparation stage.
     */
    public function test_preparation_round(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['teacher']);

        // Round is in preparation by default.
        $resultsoutput = new results($data['kahoodlerecord'], $data['cm']);
        $result = $resultsoutput->export_for_template($output);

        $this->assertNotEmpty($result->rounds);
        $this->assertTrue($result->rounds[0]->ispreparation);
        $this->assertFalse($result->rounds[0]->showdatefields);
    }

    /**
     * Test results output with a completed (archived) round with participants.
     */
    public function test_completed_round(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['teacher']);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Add a participant to the round.
        $generator->create_participant([
            'roundid' => $data['roundid'],
            'userid' => $data['student']->id,
            'displayname' => 'Player One',
            'totalscore' => 500,
        ]);

        // Set round to archived.
        $this->set_round_stage($data['roundid'], constants::STAGE_ARCHIVED);

        $resultsoutput = new results($data['kahoodlerecord'], $data['cm']);
        $result = $resultsoutput->export_for_template($output);

        $this->assertNotEmpty($result->rounds);
        $rounddata = $result->rounds[0];
        $this->assertTrue($rounddata->iscompleted);
        $this->assertTrue($rounddata->showfullstats);
        $this->assertGreaterThanOrEqual(1, $rounddata->participantcount);
        $this->assertIsNumeric($rounddata->averagescore);
        $this->assertIsInt($rounddata->maxscore);
        $this->assertNotEmpty($rounddata->participantsurl);
        $this->assertNotEmpty($rounddata->statisticsurl);
    }

    /**
     * Test results output with a round in progress (lobby).
     */
    public function test_inprogress_round(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['teacher']);

        $this->set_round_stage($data['roundid'], constants::STAGE_LOBBY);

        $resultsoutput = new results($data['kahoodlerecord'], $data['cm']);
        $result = $resultsoutput->export_for_template($output);

        $this->assertNotEmpty($result->rounds);
        $rounddata = $result->rounds[0];
        $this->assertTrue($rounddata->isinprogress);
        $this->assertTrue($rounddata->showdatefields);
    }

    /**
     * Test that allrounds buttons are shown when there are multiple archived rounds.
     */
    public function test_allrounds_buttons_multiple(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['teacher']);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Set first round to archived.
        $this->set_round_stage($data['roundid'], constants::STAGE_ARCHIVED);

        // Create a second archived round.
        $generator->create_round([
            'kahoodleid' => $data['kahoodle']->id,
            'currentstage' => constants::STAGE_ARCHIVED,
            'timestarted' => time() - 7200,
            'timecompleted' => time() - 3600,
            'stagestarttime' => time() - 3600,
        ]);

        $resultsoutput = new results($data['kahoodlerecord'], $data['cm']);
        $result = $resultsoutput->export_for_template($output);

        $this->assertTrue($result->showallroundsbuttons);
        $this->assertNotEmpty($result->allparticipantsurl);
        $this->assertNotEmpty($result->allstatisticsurl);
    }

    /**
     * Test that allrounds buttons are not shown when there is only one archived round.
     */
    public function test_allrounds_buttons_single(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['teacher']);

        // Set the round to archived (only one round).
        $this->set_round_stage($data['roundid'], constants::STAGE_ARCHIVED);

        $resultsoutput = new results($data['kahoodlerecord'], $data['cm']);
        $result = $resultsoutput->export_for_template($output);

        $this->assertFalse($result->showallroundsbuttons);
    }
}
