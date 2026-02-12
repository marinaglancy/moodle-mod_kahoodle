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

namespace mod_kahoodle\external;

use mod_kahoodle\constants;
use mod_kahoodle\questions;

/**
 * Tests for duplicate_question web service
 *
 * @covers     \mod_kahoodle\external\duplicate_question
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class duplicate_question_test extends \advanced_testcase {
    /**
     * Test duplicating a question in the same round via web service
     *
     * @return void
     */
    public function test_duplicate_question(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $roundquestion = $generator->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Test question']);

        // Duplicate the question via web service.
        $result = duplicate_question::execute($roundquestion->get_id());
        $result = \core_external\external_api::clean_returnvalue(duplicate_question::execute_returns(), $result);

        // Verify result structure.
        $this->assertArrayHasKey('roundquestionid', $result);
        $this->assertNotEquals($roundquestion->get_id(), $result['roundquestionid']);

        // Verify the duplicate exists with the same text.
        $newrq = $DB->get_record('kahoodle_round_questions', ['id' => $result['roundquestionid']], '*', MUST_EXIST);
        $newversion = $DB->get_record('kahoodle_question_versions', ['id' => $newrq->questionversionid], '*', MUST_EXIST);
        $this->assertEquals('Test question', $newversion->questiontext);
    }

    /**
     * Test duplicating a question to a different round via web service
     *
     * @return void
     */
    public function test_duplicate_question_cross_round(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $roundquestion = $generator->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Cross-round question']);

        // Start the current round.
        $round1 = questions::get_last_round($kahoodle->id);
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_LOBBY, ['id' => $round1->get_id()]);

        // Create an editable round.
        $newround = new \stdClass();
        $newround->kahoodleid = $kahoodle->id;
        $newround->name = 'Round 2';
        $newround->currentstage = constants::STAGE_PREPARATION;
        $newround->timecreated = time();
        $newround->timemodified = time();
        $targetroundid = $DB->insert_record('kahoodle_rounds', $newround);

        // Duplicate to the target round via web service.
        $result = duplicate_question::execute($roundquestion->get_id(), $targetroundid);
        $result = \core_external\external_api::clean_returnvalue(duplicate_question::execute_returns(), $result);

        // Verify the duplicate is in the target round.
        $newrq = $DB->get_record('kahoodle_round_questions', ['id' => $result['roundquestionid']], '*', MUST_EXIST);
        $this->assertEquals($targetroundid, $newrq->roundid);

        // Original should still be in round 1.
        $this->assertEquals(1, $DB->count_records('kahoodle_round_questions', ['roundid' => $round1->get_id()]));
    }

    /**
     * Test that files are duplicated along with the question
     *
     * @return void
     */
    public function test_duplicate_question_copies_files(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $roundquestion = $generator->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question with image']);

        // Attach a file to the question version.
        $context = \context_module::instance($kahoodle->cmid);
        $fs = get_file_storage();
        $versionid = $roundquestion->get_data()->questionversionid;
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_kahoodle',
            'filearea' => constants::FILEAREA_QUESTION_IMAGE,
            'itemid' => $versionid,
            'filepath' => '/',
            'filename' => 'testimage.png',
        ], 'fake image content');

        // Verify original file exists.
        $origfiles = $fs->get_area_files(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_QUESTION_IMAGE,
            $versionid,
            'filename',
            false
        );
        $this->assertCount(1, $origfiles);

        // Duplicate the question via web service.
        $result = duplicate_question::execute($roundquestion->get_id());
        $result = \core_external\external_api::clean_returnvalue(duplicate_question::execute_returns(), $result);

        // Get the new question version ID.
        $newrq = $DB->get_record('kahoodle_round_questions', ['id' => $result['roundquestionid']], '*', MUST_EXIST);
        $newversionid = $newrq->questionversionid;
        $this->assertNotEquals($versionid, $newversionid);

        // Verify the file was duplicated to the new version.
        $newfiles = $fs->get_area_files(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_QUESTION_IMAGE,
            $newversionid,
            'filename',
            false
        );
        $this->assertCount(1, $newfiles);

        $newfile = reset($newfiles);
        $this->assertEquals('testimage.png', $newfile->get_filename());
        $this->assertEquals('fake image content', $newfile->get_content());
    }

    /**
     * Test duplicating a question without permission
     *
     * @return void
     */
    public function test_duplicate_question_no_permission(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create question as teacher.
        $this->setUser($teacher);
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $roundquestion = $generator->create_question(['kahoodleid' => $kahoodle->id]);

        // Try to duplicate as student.
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        duplicate_question::execute($roundquestion->get_id());
    }
}
