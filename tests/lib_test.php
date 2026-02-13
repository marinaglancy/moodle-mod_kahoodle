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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/kahoodle/lib.php');

/**
 * Tests for Kahoodle lib.php callback functions
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::kahoodle_supports
 * @covers     ::kahoodle_delete_instance
 * @covers     ::mod_kahoodle_inplace_editable
 */
final class lib_test extends \advanced_testcase {
    /**
     * Test kahoodle_supports returns expected values for known and unknown features.
     */
    public function test_supports(): void {
        $this->resetAfterTest();

        // Supported features.
        $this->assertTrue(kahoodle_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(kahoodle_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertTrue(kahoodle_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertEquals(MOD_PURPOSE_ASSESSMENT, kahoodle_supports(FEATURE_MOD_PURPOSE));

        // Unknown/unsupported features should return null.
        $this->assertNull(kahoodle_supports(FEATURE_GROUPS));
        $this->assertNull(kahoodle_supports(FEATURE_GROUPINGS));
        $this->assertNull(kahoodle_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
    }

    /**
     * Test kahoodle_delete_instance cascading delete removes all related records.
     */
    public function test_delete_instance_cascading(): void {
        global $DB;
        $this->resetAfterTest();

        // Disable recycle bin so we test actual deletion.
        set_config('coursebinenable', 0, 'tool_recyclebin');

        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        // Create questions.
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
            'displayname' => 'Student1',
            'totalscore' => 500,
        ]);
        $p2 = $generator->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $student2->id,
            'displayname' => 'Student2',
            'totalscore' => 300,
        ]);

        // Create responses.
        $generator->create_response([
            'participantid' => $p1,
            'roundquestionid' => $q1->get_id(),
            'iscorrect' => 1,
            'points' => 500,
        ]);
        $generator->create_response([
            'participantid' => $p2,
            'roundquestionid' => $q2->get_id(),
            'iscorrect' => 0,
            'points' => 0,
        ]);

        // Verify records exist before deletion.
        $this->assertGreaterThan(0, $DB->count_records('kahoodle', ['id' => $kahoodle->id]));
        $this->assertGreaterThan(0, $DB->count_records('kahoodle_rounds', ['kahoodleid' => $kahoodle->id]));
        $this->assertGreaterThan(0, $DB->count_records('kahoodle_questions', ['kahoodleid' => $kahoodle->id]));

        // Delete the instance.
        $result = kahoodle_delete_instance($kahoodle->id);
        $this->assertTrue($result);

        // Verify all related records are deleted.
        $this->assertEquals(0, $DB->count_records('kahoodle', ['id' => $kahoodle->id]));
        $this->assertEquals(0, $DB->count_records('kahoodle_rounds', ['kahoodleid' => $kahoodle->id]));
        $this->assertEquals(0, $DB->count_records('kahoodle_questions', ['kahoodleid' => $kahoodle->id]));
        $this->assertEquals(0, $DB->count_records('kahoodle_question_versions', [
            'questionid' => $q1->get_data()->questionid,
        ]));
        $this->assertEquals(0, $DB->count_records('kahoodle_question_versions', [
            'questionid' => $q2->get_data()->questionid,
        ]));
        $this->assertEquals(0, $DB->count_records('kahoodle_participants', [
            'roundid' => $round->get_id(),
        ]));
        $this->assertEquals(0, $DB->count_records('kahoodle_responses', [
            'participantid' => $p1,
        ]));
        $this->assertEquals(0, $DB->count_records('kahoodle_responses', [
            'participantid' => $p2,
        ]));
    }

    /**
     * Test mod_kahoodle_inplace_editable updates the round name for 'roundname' item type.
     */
    public function test_inplace_editable_roundname(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Q1',
            'questionconfig' => "A\n*B\nC",
        ]);

        $round = \mod_kahoodle\local\game\questions::get_last_round($kahoodle->id);

        // Call the inplace editable function.
        $result = mod_kahoodle_inplace_editable('roundname', $round->get_id(), 'New Name');

        $this->assertInstanceOf(\core\output\inplace_editable::class, $result);

        // Export the result and verify the value was updated.
        $PAGE->set_url('/mod/kahoodle/results.php', ['id' => $cm->id]);
        $PAGE->set_context(\context_module::instance($cm->id));
        $exported = $result->export_for_template($PAGE->get_renderer('core'));
        $this->assertEquals('New Name', $exported['value']);

        // Verify the name was persisted in the database.
        $roundrecord = $DB->get_record('kahoodle_rounds', ['id' => $round->get_id()]);
        $this->assertEquals('New Name', $roundrecord->name);
    }

    /**
     * Test mod_kahoodle_inplace_editable throws coding_exception for unknown item type.
     */
    public function test_inplace_editable_unknown_type(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('Unknown item type');
        mod_kahoodle_inplace_editable('unknowntype', 1, 'value');
    }
}
