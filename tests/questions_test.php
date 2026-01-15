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

/**
 * Tests for Kahoodle questions class
 *
 * @covers     \mod_kahoodle\questions
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class questions_test extends \advanced_testcase {
    /**
     * Test get_editable_round_id creates a round when none exist
     *
     * @return void
     */
    public function test_get_editable_round_id_creates_round(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        // No rounds should exist yet.
        $this->assertEquals(0, $DB->count_records('kahoodle_rounds', ['kahoodleid' => $kahoodle->id]));

        // Get editable round - should create one.
        $roundid = questions::get_editable_round_id($kahoodle->id);

        $this->assertNotNull($roundid);
        $this->assertEquals(1, $DB->count_records('kahoodle_rounds', ['kahoodleid' => $kahoodle->id]));

        // Verify round properties.
        $round = $DB->get_record('kahoodle_rounds', ['id' => $roundid], '*', MUST_EXIST);
        $this->assertEquals('Round 1', $round->name);
        $this->assertEquals(constants::STAGE_PREPARATION, $round->currentstage);
        $this->assertNull($round->timestarted);
        $this->assertEquals($kahoodle->lobbyduration, $round->lobbyduration);
    }

    /**
     * Test get_editable_round_id returns existing editable round
     *
     * @return void
     */
    public function test_get_editable_round_id_returns_existing(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        // Create first round.
        $roundid1 = questions::get_editable_round_id($kahoodle->id);

        // Getting it again should return the same round.
        $roundid2 = questions::get_editable_round_id($kahoodle->id);

        $this->assertEquals($roundid1, $roundid2);
        $this->assertEquals(1, $DB->count_records('kahoodle_rounds', ['kahoodleid' => $kahoodle->id]));
    }

    /**
     * Test get_editable_round_id returns null when round is started
     *
     * @return void
     */
    public function test_get_editable_round_id_returns_null_when_started(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        // Create a round.
        $roundid = questions::get_editable_round_id($kahoodle->id);

        // Mark it as started.
        $DB->set_field('kahoodle_rounds', 'timestarted', time(), ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_LOBBY, ['id' => $roundid]);

        // Should return null now.
        $result = questions::get_editable_round_id($kahoodle->id);
        $this->assertNull($result);
    }

    /**
     * Test add_question creates question with all components
     *
     * @return void
     */
    public function test_add_question(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        $questiondata = new \stdClass();
        $questiondata->kahoodleid = $kahoodle->id;
        $questiondata->questiontype = constants::QUESTION_TYPE_MULTICHOICE;
        $questiondata->questiontext = 'What is 2+2?';
        $questiondata->questiontextformat = FORMAT_HTML;
        $questiondata->answersconfig = json_encode(['options' => ['3', '4', '5'], 'correct' => 1]);
        $questiondata->maxpoints = 1500;
        $questiondata->minpoints = 750;

        $questionid = questions::add_question($questiondata);

        // Verify question was created.
        $question = $DB->get_record('kahoodle_questions', ['id' => $questionid], '*', MUST_EXIST);
        $this->assertEquals($kahoodle->id, $question->kahoodleid);
        $this->assertEquals(constants::QUESTION_TYPE_MULTICHOICE, $question->questiontype);
        $this->assertEquals(1, $question->sortorder);

        // Verify question version was created.
        $version = $DB->get_record('kahoodle_question_versions', ['questionid' => $questionid], '*', MUST_EXIST);
        $this->assertEquals(1, $version->version);
        $this->assertEquals('What is 2+2?', $version->questiontext);
        $this->assertEquals(FORMAT_HTML, $version->questiontextformat);
        $this->assertEquals($questiondata->answersconfig, $version->answersconfig);

        // Verify round was created and question linked.
        $rounds = $DB->get_records('kahoodle_rounds', ['kahoodleid' => $kahoodle->id]);
        $this->assertCount(1, $rounds);
        $round = reset($rounds);

        $roundquestion = $DB->get_record(
            'kahoodle_round_questions',
            ['roundid' => $round->id, 'questionversionid' => $version->id],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(1, $roundquestion->sortorder);
        $this->assertEquals(1500, $roundquestion->maxpoints);
        $this->assertEquals(750, $roundquestion->minpoints);
    }

    /**
     * Test add_question throws exception when no editable round
     *
     * @return void
     */
    public function test_add_question_no_editable_round(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        // Create a round and mark it as started.
        $roundid = questions::get_editable_round_id($kahoodle->id);
        $DB->set_field('kahoodle_rounds', 'timestarted', time(), ['id' => $roundid]);

        $questiondata = new \stdClass();
        $questiondata->kahoodleid = $kahoodle->id;
        $questiondata->questiontype = constants::QUESTION_TYPE_MULTICHOICE;
        $questiondata->questiontext = 'Test question';

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('No editable round available');
        questions::add_question($questiondata);
    }

    /**
     * Test add_question maintains correct sort order
     *
     * @return void
     */
    public function test_add_question_sort_order(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        // Add three questions.
        $q1 = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 1']);
        $q2 = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 2']);
        $q3 = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 3']);

        // Verify sort order.
        $this->assertEquals(1, $DB->get_field('kahoodle_questions', 'sortorder', ['id' => $q1->id]));
        $this->assertEquals(2, $DB->get_field('kahoodle_questions', 'sortorder', ['id' => $q2->id]));
        $this->assertEquals(3, $DB->get_field('kahoodle_questions', 'sortorder', ['id' => $q3->id]));
    }

    /**
     * Test edit_question updates behavior data
     *
     * @return void
     */
    public function test_edit_question_behavior_data(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id]);

        // Edit behavior data.
        $editdata = new \stdClass();
        $editdata->id = $question->id;
        $editdata->maxpoints = 2000;
        $editdata->minpoints = 800;
        $editdata->questionduration = 45;

        questions::edit_question($editdata);

        // Verify behavior data was updated in round_questions table.
        $round = $DB->get_record('kahoodle_rounds', ['kahoodleid' => $kahoodle->id], '*', MUST_EXIST);
        $version = $DB->get_record('kahoodle_question_versions', ['questionid' => $question->id], '*', MUST_EXIST);
        $roundquestion = $DB->get_record(
            'kahoodle_round_questions',
            ['roundid' => $round->id, 'questionversionid' => $version->id],
            '*',
            MUST_EXIST
        );

        $this->assertEquals(2000, $roundquestion->maxpoints);
        $this->assertEquals(800, $roundquestion->minpoints);
        $this->assertEquals(45, $roundquestion->questionduration);
    }

    /**
     * Test edit_question updates content in place when version not used elsewhere
     *
     * @return void
     */
    public function test_edit_question_content_in_place(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Original text']);

        $versionid = $DB->get_field('kahoodle_question_versions', 'id', ['questionid' => $question->id]);

        // Edit content.
        $editdata = new \stdClass();
        $editdata->id = $question->id;
        $editdata->questiontext = 'Updated text';

        questions::edit_question($editdata);

        // Should update the same version (not create new one).
        $this->assertEquals(1, $DB->count_records('kahoodle_question_versions', ['questionid' => $question->id]));

        $version = $DB->get_record('kahoodle_question_versions', ['id' => $versionid], '*', MUST_EXIST);
        $this->assertEquals('Updated text', $version->questiontext);
        $this->assertEquals(1, $version->version);
    }

    /**
     * Test edit_question creates new version when current version used in started round
     *
     * @return void
     */
    public function test_edit_question_creates_new_version(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Original text']);

        // Mark the round as started.
        $round = $DB->get_record('kahoodle_rounds', ['kahoodleid' => $kahoodle->id], '*', MUST_EXIST);
        $oldtime = time() - 100; // Make sure old round is older.
        $DB->set_field('kahoodle_rounds', 'timestarted', $oldtime, ['id' => $round->id]);
        $DB->set_field('kahoodle_rounds', 'timecreated', $oldtime, ['id' => $round->id]);

        // Create a new editable round with the same question.
        $newround = new \stdClass();
        $newround->kahoodleid = $kahoodle->id;
        $newround->name = 'Round 2';
        $newround->currentstage = constants::STAGE_PREPARATION;
        $newround->lobbyduration = 300;
        $newround->timecreated = time();
        $newround->timemodified = time();
        $newroundid = $DB->insert_record('kahoodle_rounds', $newround);

        // Link the same question version to the new round.
        $oldversion = $DB->get_record('kahoodle_question_versions', ['questionid' => $question->id], '*', MUST_EXIST);
        $roundquestion = new \stdClass();
        $roundquestion->roundid = $newroundid;
        $roundquestion->questionversionid = $oldversion->id;
        $roundquestion->sortorder = 1;
        $roundquestion->timecreated = time();
        $DB->insert_record('kahoodle_round_questions', $roundquestion);

        // Now edit the question content.
        $editdata = new \stdClass();
        $editdata->id = $question->id;
        $editdata->questiontext = 'Updated text';

        questions::edit_question($editdata);

        // Should create a new version.
        $this->assertEquals(2, $DB->count_records('kahoodle_question_versions', ['questionid' => $question->id]));

        // New version should have updated text.
        $newversion = $DB->get_record(
            'kahoodle_question_versions',
            ['questionid' => $question->id, 'version' => 2],
            '*',
            MUST_EXIST
        );
        $this->assertEquals('Updated text', $newversion->questiontext);

        // Old version should still exist with original text.
        $oldversioncheck = $DB->get_record('kahoodle_question_versions', ['id' => $oldversion->id], '*', MUST_EXIST);
        $this->assertEquals('Original text', $oldversioncheck->questiontext);

        // New round should use the new version.
        $newroundq = $DB->get_record(
            'kahoodle_round_questions',
            ['roundid' => $newroundid],
            '*',
            MUST_EXIST
        );
        $this->assertEquals($newversion->id, $newroundq->questionversionid);
    }

    /**
     * Test edit_question throws exception when no editable round
     *
     * @return void
     */
    public function test_edit_question_no_editable_round(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id]);

        // Mark round as started.
        $round = $DB->get_record('kahoodle_rounds', ['kahoodleid' => $kahoodle->id], '*', MUST_EXIST);
        $DB->set_field('kahoodle_rounds', 'timestarted', time(), ['id' => $round->id]);

        $editdata = new \stdClass();
        $editdata->id = $question->id;
        $editdata->questiontext = 'Updated text';

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('No editable round available');
        questions::edit_question($editdata);
    }

    /**
     * Test delete_question removes question and all related data
     *
     * @return void
     */
    public function test_delete_question(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id]);

        questions::delete_question($question->id);

        // Question, version, and round link should all be deleted.
        $this->assertEquals(0, $DB->count_records('kahoodle_questions', ['id' => $question->id]));
        $this->assertEquals(0, $DB->count_records('kahoodle_question_versions', ['questionid' => $question->id]));
        $this->assertEquals(0, $DB->count_records(
            'kahoodle_round_questions',
            ['questionversionid' => $DB->get_field(
                'kahoodle_question_versions',
                'id',
                ['questionid' => $question->id]
            )]
        ));
    }

    /**
     * Test delete_question keeps version when used in started round
     *
     * @return void
     */
    public function test_delete_question_keeps_version_when_used(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id]);

        // Mark the round as started.
        $round = $DB->get_record('kahoodle_rounds', ['kahoodleid' => $kahoodle->id], '*', MUST_EXIST);
        $oldtime = time() - 100; // Make sure old round is older.
        $DB->set_field('kahoodle_rounds', 'timestarted', $oldtime, ['id' => $round->id]);
        $DB->set_field('kahoodle_rounds', 'timecreated', $oldtime, ['id' => $round->id]);

        // Create a new editable round with the same question.
        $newround = new \stdClass();
        $newround->kahoodleid = $kahoodle->id;
        $newround->name = 'Round 2';
        $newround->currentstage = constants::STAGE_PREPARATION;
        $newround->lobbyduration = 300;
        $newround->timecreated = time();
        $newround->timemodified = time();
        $newroundid = $DB->insert_record('kahoodle_rounds', $newround);

        // Link the same question version to the new round.
        $version = $DB->get_record('kahoodle_question_versions', ['questionid' => $question->id], '*', MUST_EXIST);
        $roundquestion = new \stdClass();
        $roundquestion->roundid = $newroundid;
        $roundquestion->questionversionid = $version->id;
        $roundquestion->sortorder = 1;
        $roundquestion->timecreated = time();
        $DB->insert_record('kahoodle_round_questions', $roundquestion);

        // Delete from new round.
        questions::delete_question($question->id);

        // Question and version should still exist (used in started round).
        $this->assertEquals(1, $DB->count_records('kahoodle_questions', ['id' => $question->id]));
        $this->assertEquals(1, $DB->count_records('kahoodle_question_versions', ['questionid' => $question->id]));

        // Link to new round should be deleted, but link to old round should remain.
        $this->assertEquals(0, $DB->count_records('kahoodle_round_questions', ['roundid' => $newroundid]));
        $this->assertEquals(1, $DB->count_records('kahoodle_round_questions', ['roundid' => $round->id]));
    }

    /**
     * Test delete_question throws exception when no editable round
     *
     * @return void
     */
    public function test_delete_question_no_editable_round(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle')
            ->create_question(['kahoodleid' => $kahoodle->id]);

        // Mark round as started.
        $round = $DB->get_record('kahoodle_rounds', ['kahoodleid' => $kahoodle->id], '*', MUST_EXIST);
        $DB->set_field('kahoodle_rounds', 'timestarted', time(), ['id' => $round->id]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('No editable round available');
        questions::delete_question($question->id);
    }
}
