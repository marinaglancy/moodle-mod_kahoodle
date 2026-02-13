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
 * Tests for the landing output class
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\output\landing
 */
final class landing_test extends \advanced_testcase {
    /**
     * Helper to create a kahoodle with a course, teacher, and student.
     *
     * @param bool $withquestions Whether to create questions.
     * @return array{kahoodle: \stdClass, cm: \stdClass, teacher: \stdClass, student: \stdClass,
     *     round: round, roundid: int, course: \stdClass}
     */
    protected function setup_kahoodle(bool $withquestions = true): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);

        if ($withquestions) {
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
        }

        // Get the round that was auto-created.
        $rounds = \mod_kahoodle\local\game\instance::get_all_rounds($kahoodle->id);
        $round = reset($rounds);
        $roundid = $round->get_id();

        return [
            'kahoodle' => $kahoodle,
            'cm' => $cm,
            'teacher' => $teacher,
            'student' => $student,
            'round' => $round,
            'roundid' => $roundid,
            'course' => $course,
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
     * Test landing page for a facilitator when round is in preparation stage.
     */
    public function test_preparation_facilitator(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['teacher']);

        // Round is in preparation by default.
        $round = round::create_from_id($data['roundid']);
        $landingoutput = new landing($round);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showcontrolpreparation);
        $this->assertNotEmpty($result->starturl);
        $this->assertFalse($result->startgamebuttondisabled);
    }

    /**
     * Test landing page for a facilitator when kahoodle has no questions.
     */
    public function test_preparation_no_questions(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle(false);
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['teacher']);

        $round = round::create_from_id($data['roundid']);
        $landingoutput = new landing($round);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showcontrolpreparation);
        $this->assertTrue($result->startgamebuttondisabled);
    }

    /**
     * Test landing page for a student when round is in preparation stage.
     */
    public function test_preparation_student(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['student']);

        $round = round::create_from_id($data['roundid']);
        $landingoutput = new landing($round);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showwaitingtostart);
        $this->assertFalse($result->showcontrolpreparation);
    }

    /**
     * Test landing page for a facilitator when round is in progress (lobby).
     */
    public function test_inprogress_facilitator(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['teacher']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_LOBBY);
        $landingoutput = new landing($round);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showfacilitatorcontrols);
    }

    /**
     * Test landing page for a student with join form when round is in progress (lobby).
     */
    public function test_inprogress_join_form(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['student']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_LOBBY);

        $joinform = new \mod_kahoodle\form\join(
            new \moodle_url('/mod/kahoodle/view.php'),
            ['round' => $round]
        );

        $landingoutput = new landing($round, $joinform);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showjoinoption);
        $this->assertNotEmpty($result->joinformhtml);
    }

    /**
     * Test landing page for a facilitator when round is archived.
     */
    public function test_archived(): void {
        $this->resetAfterTest();
        $data = $this->setup_kahoodle();
        $output = $this->setup_page($data['cm']);

        $this->setUser($data['teacher']);

        $round = $this->set_round_stage($data['roundid'], constants::STAGE_ARCHIVED);
        $landingoutput = new landing($round);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showfinished);
        $this->assertNotEmpty($result->resultsurl);
        $this->assertNotEmpty($result->newroundurl);
    }
}
