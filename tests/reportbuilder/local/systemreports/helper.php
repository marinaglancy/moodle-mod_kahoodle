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

namespace mod_kahoodle\reportbuilder\local\systemreports;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;

/**
 * Shared helper methods for system report tests
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait helper {
    /**
     * Set up $PAGE for report rendering.
     *
     * @param \stdClass $cm Course module record (must have id).
     */
    protected function setup_page(\stdClass $cm): void {
        global $PAGE;
        $PAGE->set_url('/mod/kahoodle/results.php', ['id' => $cm->id]);
        $PAGE->set_cm($cm);
    }

    /**
     * Create a complete kahoodle test dataset with one archived round.
     *
     * Creates a course, teacher, two students, a kahoodle activity with two questions,
     * an archived round with two participants and their responses.
     *
     * @return array{course: \stdClass, teacher: \stdClass, student1: \stdClass, student2: \stdClass,
     *     kahoodle: \stdClass, cm: \stdClass, q1: \mod_kahoodle\local\entities\round_question,
     *     q2: \mod_kahoodle\local\entities\round_question, round: round,
     *     p1: int, p2: int, generator: \mod_kahoodle_generator}
     */
    protected function create_dataset(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $q1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Q1',
            'questionconfig' => "A\n*B\nC",
        ]);
        $q2 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Q2',
            'questionconfig' => "*X\nY\nZ",
        ]);

        $round = \mod_kahoodle\local\game\questions::get_last_round($kahoodle->id);

        // Create participants.
        $p1 = $generator->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $student1->id,
            'displayname' => 'Sam',
            'totalscore' => 1700,
        ]);
        $p2 = $generator->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $student2->id,
            'displayname' => 'Alex',
            'totalscore' => 600,
        ]);

        // Create responses.
        $generator->create_response([
            'participantid' => $p1,
            'roundquestionid' => $q1->get_id(),
            'iscorrect' => 1,
            'points' => 900,
        ]);
        $generator->create_response([
            'participantid' => $p1,
            'roundquestionid' => $q2->get_id(),
            'iscorrect' => 1,
            'points' => 800,
        ]);
        $generator->create_response([
            'participantid' => $p2,
            'roundquestionid' => $q1->get_id(),
            'iscorrect' => 0,
            'points' => 0,
        ]);
        $generator->create_response([
            'participantid' => $p2,
            'roundquestionid' => $q2->get_id(),
            'iscorrect' => 1,
            'points' => 600,
        ]);

        // Set round to archived.
        $DB->update_record('kahoodle_rounds', (object) [
            'id' => $round->get_id(),
            'currentstage' => constants::STAGE_ARCHIVED,
            'timestarted' => time() - 3600,
            'timecompleted' => time(),
            'stagestarttime' => time(),
        ]);

        // Reload round after update.
        $round = round::create_from_id($round->get_id());

        return [
            'course' => $course,
            'teacher' => $teacher,
            'student1' => $student1,
            'student2' => $student2,
            'kahoodle' => $kahoodle,
            'cm' => $cm,
            'q1' => $q1,
            'q2' => $q2,
            'round' => $round,
            'p1' => $p1,
            'p2' => $p2,
            'generator' => $generator,
        ];
    }

    /**
     * Create a second archived round for the given kahoodle, linked to the same questions.
     *
     * @param array $data The dataset returned by create_dataset().
     * @return int The second round ID.
     */
    protected function create_second_round(array $data): int {
        global $DB;

        $generator = $data['generator'];

        $round2id = $generator->create_round([
            'kahoodleid' => $data['kahoodle']->id,
            'currentstage' => constants::STAGE_ARCHIVED,
            'timestarted' => time() - 7200,
            'timecompleted' => time() - 3600,
            'stagestarttime' => time() - 3600,
        ]);

        // Link questions to round2 by inserting round_questions.
        $qv1id = $DB->get_field('kahoodle_question_versions', 'id', [
            'questionid' => $data['q1']->get_data()->questionid,
            'islast' => 1,
        ]);
        $qv2id = $DB->get_field('kahoodle_question_versions', 'id', [
            'questionid' => $data['q2']->get_data()->questionid,
            'islast' => 1,
        ]);

        $rq1id = $DB->insert_record('kahoodle_round_questions', (object) [
            'roundid' => $round2id,
            'questionversionid' => $qv1id,
            'sortorder' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $rq2id = $DB->insert_record('kahoodle_round_questions', (object) [
            'roundid' => $round2id,
            'questionversionid' => $qv2id,
            'sortorder' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Add participants and responses to round2.
        $p3 = $generator->create_participant([
            'roundid' => $round2id,
            'userid' => $data['student1']->id,
            'displayname' => 'Sam R2',
            'totalscore' => 500,
        ]);
        $generator->create_response([
            'participantid' => $p3,
            'roundquestionid' => $rq1id,
            'iscorrect' => 1,
            'points' => 300,
        ]);
        $generator->create_response([
            'participantid' => $p3,
            'roundquestionid' => $rq2id,
            'iscorrect' => 0,
            'points' => 0,
        ]);

        return $round2id;
    }
}
