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

use core\exception\coding_exception;
use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\statistics;
use mod_kahoodle\local\game\progress;

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
     * @return array{statistics: statistics, cm: \cm_info, teacher: \stdClass, student: \stdClass}
     */
    protected function setup_kahoodle(bool $withquestions = true): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);

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
        $statistics = statistics::create_from_kahoodle_id(0, $kahoodle);

        return [
            'statistics' => $statistics,
            'cm' => $statistics->get_cm(),
            'teacher' => $teacher,
            'student' => $student,
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
     * @param round $round
     * @param string $stagesignature desired stage
     * @return round
     */
    protected function set_round_stage(round $round, string $stagesignature): round {
        if ($round->get_current_stage_name() === constants::STAGE_PREPARATION) {
            progress::start_game($round);
        }

        while ($round->get_current_stage()->get_stage_signature() !== $stagesignature) {
            if ($round->get_current_stage()->get_stage_signature() === constants::STAGE_ARCHIVED) {
                throw new coding_exception('Stage not reached');
            }
            progress::advance_to_next_stage($round, $round->get_current_stage()->get_stage_signature());
        }
        return $round;
    }

    /**
     * Test landing page for a facilitator when round is in preparation stage.
     */
    public function test_preparation_facilitator(): void {
        $this->resetAfterTest();
        ['cm' => $cm, 'teacher' => $teacher, 'statistics' => $statistics] = $this->setup_kahoodle();
        $output = $this->setup_page($cm);

        $this->setUser($teacher);

        // Round is in preparation by default.
        $landingoutput = new landing($statistics);
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
        ['cm' => $cm, 'teacher' => $teacher, 'statistics' => $statistics] = $this->setup_kahoodle(false);
        $output = $this->setup_page($cm);

        $this->setUser($teacher);

        $landingoutput = new landing($statistics);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showcontrolpreparation);
        $this->assertTrue($result->startgamebuttondisabled);
    }

    /**
     * Test landing page for a student when round is in preparation stage.
     */
    public function test_preparation_student(): void {
        $this->resetAfterTest();
        ['cm' => $cm, 'student' => $student, 'statistics' => $statistics] = $this->setup_kahoodle();
        $output = $this->setup_page($cm);

        $this->setUser($student);

        $landingoutput = new landing($statistics);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showwaitingtostart);
        $this->assertFalse($result->showcontrolpreparation);
    }

    /**
     * Test landing page for a facilitator when round is in progress (lobby).
     */
    public function test_inprogress_facilitator(): void {
        $this->resetAfterTest();
        ['cm' => $cm, 'teacher' => $teacher, 'statistics' => $statistics] = $this->setup_kahoodle();
        $output = $this->setup_page($cm);

        $this->setUser($teacher);

        $this->set_round_stage($statistics->get_last_round(), constants::STAGE_LOBBY);
        $landingoutput = new landing($statistics);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showfacilitatorcontrols);
    }

    /**
     * Test landing page for a student with join form when round is in progress (lobby).
     */
    public function test_inprogress_join_form(): void {
        $this->resetAfterTest();
        ['cm' => $cm, 'student' => $student, 'statistics' => $statistics] = $this->setup_kahoodle();
        $output = $this->setup_page($cm);

        $this->setUser($student);

        $round = $this->set_round_stage($statistics->get_last_round(), constants::STAGE_LOBBY);

        $joinform = new \mod_kahoodle\form\join(
            new \moodle_url('/mod/kahoodle/view.php'),
            ['round' => $round]
        );

        $landingoutput = new landing($statistics, $joinform);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showjoinoption);
        $this->assertNotEmpty($result->joinformhtml);
    }

    /**
     * Test landing page for a facilitator when round is archived.
     */
    public function test_archived(): void {
        $this->resetAfterTest();
        ['cm' => $cm, 'teacher' => $teacher, 'statistics' => $statistics] = $this->setup_kahoodle();
        $output = $this->setup_page($cm);

        $this->setUser($teacher);

        $this->set_round_stage($statistics->get_last_round(), constants::STAGE_ARCHIVED);
        $landingoutput = new landing($statistics);
        $result = $landingoutput->export_for_template($output);

        $this->assertTrue($result->showfinished);
        $this->assertNotEmpty($result->resultsurl);
        $this->assertNotEmpty($result->newroundurl);
    }
}
