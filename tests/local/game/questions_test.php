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

namespace mod_kahoodle\local\game;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round_question;

/**
 * Tests for Kahoodle questions class
 *
 * @covers     \mod_kahoodle\local\game\questions
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class questions_test extends \advanced_testcase {
    /**
     * Get the Kahoodle plugin generator
     *
     * @return \mod_kahoodle_generator
     */
    protected function get_generator(): \mod_kahoodle_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
    }

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
        $questiondata->questiontext = 'What is 2+2?';
        $questiondata->questionconfig = "3\n*4\n5";
        $questiondata->maxpoints = 1500;
        $questiondata->minpoints = 750;

        $questionid = questions::add_question($questiondata, null)->get_question_id();

        // Verify question was created.
        $question = $DB->get_record('kahoodle_questions', ['id' => $questionid], '*', MUST_EXIST);
        $this->assertEquals($kahoodle->id, $question->kahoodleid);
        $this->assertEquals('multichoice', $question->questiontype);

        // Verify question version was created.
        $version = $DB->get_record('kahoodle_question_versions', ['questionid' => $questionid], '*', MUST_EXIST);
        $this->assertEquals(1, $version->version);
        $this->assertEquals('What is 2+2?', $version->questiontext);
        $this->assertEquals($questiondata->questionconfig, $version->questionconfig);

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
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_LOBBY, ['id' => $roundid]);

        $questiondata = new \stdClass();
        $questiondata->kahoodleid = $kahoodle->id;
        $questiondata->questiontext = 'Test question';

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('No editable round available');
        questions::add_question($questiondata, null);
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
        $q1 = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 1']);
        $q2 = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 2']);
        $q3 = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 3']);

        // Verify sort order.
        $this->assertEquals(1, $DB->get_field('kahoodle_round_questions', 'sortorder', ['id' => $q1->get_id()]));
        $this->assertEquals(2, $DB->get_field('kahoodle_round_questions', 'sortorder', ['id' => $q2->get_id()]));
        $this->assertEquals(3, $DB->get_field('kahoodle_round_questions', 'sortorder', ['id' => $q3->get_id()]));
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
        $roundquestion = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id]);

        // Edit behavior data.
        $editdata = new \stdClass();
        $editdata->maxpoints = 2000;
        $editdata->minpoints = 800;
        $editdata->questionduration = 45;

        questions::edit_question($roundquestion, $editdata);

        // Verify behavior data was updated in round_questions table.
        $round = $DB->get_record('kahoodle_rounds', ['kahoodleid' => $kahoodle->id], '*', MUST_EXIST);
        $version = $DB->get_record(
            'kahoodle_question_versions',
            ['questionid' => $roundquestion->get_data()->questionid],
            '*',
            MUST_EXIST
        );
        $roundquestionrecord = $DB->get_record(
            'kahoodle_round_questions',
            ['roundid' => $round->id, 'questionversionid' => $version->id],
            '*',
            MUST_EXIST
        );

        $this->assertEquals(2000, $roundquestionrecord->maxpoints);
        $this->assertEquals(800, $roundquestionrecord->minpoints);
        $this->assertEquals(45, $roundquestionrecord->questionduration);
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
        $roundquestion = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Original text']);

        $versionid = $roundquestion->get_data()->questionversionid;

        // Edit content.
        $editdata = new \stdClass();
        $editdata->questiontext = 'Updated text';

        questions::edit_question($roundquestion, $editdata);

        // Should update the same version (not create new one).
        $this->assertEquals(1, $DB->count_records(
            'kahoodle_question_versions',
            ['questionid' => $roundquestion->get_data()->questionid]
        ));

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
        $roundquestion = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Original text']);

        // Mark the round as started.
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_LOBBY, ['id' => $roundquestion->get_data()->roundid]);

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
        $oldversion = $DB->get_record(
            'kahoodle_question_versions',
            ['questionid' => $roundquestion->get_data()->questionid],
            '*',
            MUST_EXIST
        );
        $roundquestionrec = new \stdClass();
        $roundquestionrec->roundid = $newroundid;
        $roundquestionrec->questionversionid = $oldversion->id;
        $roundquestionrec->sortorder = 1;
        $roundquestionrec->timecreated = time();
        $DB->insert_record('kahoodle_round_questions', $roundquestionrec);

        // Now edit the question content.
        $editdata = new \stdClass();
        $editdata->questiontext = 'Updated text';

        $questionid = $roundquestion->get_data()->questionid;
        $roundquestion2 = round_question::create_from_question_id($questionid);
        questions::edit_question($roundquestion2, $editdata);

        // Should create a new version.
        $this->assertEquals(2, $DB->count_records('kahoodle_question_versions', ['questionid' => $questionid]));

        // New version should have updated text.
        $newversion = $DB->get_record(
            'kahoodle_question_versions',
            ['questionid' => $questionid, 'version' => 2],
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
     * Test edit_question works in a non-editable (started) round
     *
     * @return void
     */
    public function test_edit_question_in_started_round(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $roundquestion = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Original text']);

        // Mark round as started.
        $round = $DB->get_record('kahoodle_rounds', ['kahoodleid' => $kahoodle->id], '*', MUST_EXIST);
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_LOBBY, ['id' => $round->id]);

        $editdata = new \stdClass();
        $editdata->questiontext = 'Updated text';

        // Function edit_question should work even in started rounds (updates version in place
        // since no other round references this version).
        questions::edit_question($roundquestion, $editdata);

        $version = $DB->get_record(
            'kahoodle_question_versions',
            ['questionid' => $roundquestion->get_data()->questionid],
            '*',
            MUST_EXIST
        );
        $this->assertEquals('Updated text', $version->questiontext);
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
        $roundquestion = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id]);
        $questionid = $roundquestion->get_question_id();

        questions::delete_question($roundquestion);

        // Question, version, and round link should all be deleted.
        $this->assertEquals(0, $DB->count_records('kahoodle_questions', ['id' => $questionid]));
        $this->assertEquals(0, $DB->count_records('kahoodle_question_versions', ['questionid' => $questionid]));
        $this->assertEquals(0, $DB->count_records(
            'kahoodle_round_questions',
            ['questionversionid' => $DB->get_field(
                'kahoodle_question_versions',
                'id',
                ['questionid' => $questionid]
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
        $roundquestion = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id]);
        $questionid = $roundquestion->get_question_id();

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
        $version = $DB->get_record(
            'kahoodle_question_versions',
            ['questionid' => $questionid],
            '*',
            MUST_EXIST
        );
        $newroundquestion = new \stdClass();
        $newroundquestion->roundid = $newroundid;
        $newroundquestion->questionversionid = $version->id;
        $newroundquestion->sortorder = 1;
        $newroundquestion->timecreated = time();
        $newid = $DB->insert_record('kahoodle_round_questions', $newroundquestion);

        // Delete from new round.
        questions::delete_question(round_question::create_from_round_question_id($newid));

        // Question and version should still exist (used in started round).
        $this->assertEquals(1, $DB->count_records('kahoodle_questions', ['id' => $questionid]));
        $this->assertEquals(1, $DB->count_records('kahoodle_question_versions', ['questionid' => $questionid]));
        // Link to new round should be deleted, but link to old round should remain.
        $this->assertEquals(0, $DB->count_records('kahoodle_round_questions', ['roundid' => $newroundid]));
        $this->assertEquals(1, $DB->count_records('kahoodle_round_questions', ['roundid' => $round->id]));
    }

    /**
     * Test duplicate_question within the same round
     *
     * @return void
     */
    public function test_duplicate_question_same_round(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        // Create two questions.
        $q1 = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 1']);
        $q2 = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Question 2']);

        // Duplicate q1.
        $duplicate = questions::duplicate_question($q1);

        // Should be a new question with its own ID.
        $this->assertNotEquals($q1->get_question_id(), $duplicate->get_question_id());
        $this->assertNotEquals($q1->get_id(), $duplicate->get_id());

        // Verify text was copied.
        $this->assertEquals('Question 1', $duplicate->get_data()->questiontext);

        // Verify sortorder: q1=1, duplicate=2, q2=3.
        $round = questions::get_last_round($kahoodle->id);
        $rqs = $DB->get_records('kahoodle_round_questions', ['roundid' => $round->get_id()], 'sortorder ASC');
        $sortorders = array_values(array_column($rqs, 'sortorder'));
        $this->assertEquals(['1', '2', '3'], $sortorders);

        // The duplicate should be right after q1, pushing q2 to sortorder 3.
        $this->assertEquals(2, $DB->get_field('kahoodle_round_questions', 'sortorder', ['id' => $duplicate->get_id()]));
        $this->assertEquals(3, $DB->get_field('kahoodle_round_questions', 'sortorder', ['id' => $q2->get_id()]));
    }

    /**
     * Test duplicate_question to a different round
     *
     * @return void
     */
    public function test_duplicate_question_cross_round(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        // Create a question in the first round.
        $q1 = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id, 'questiontext' => 'Original question']);

        // Start the first round.
        $round1 = questions::get_last_round($kahoodle->id);
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_LOBBY, ['id' => $round1->get_id()]);

        // Create a new editable round.
        $newround = new \stdClass();
        $newround->kahoodleid = $kahoodle->id;
        $newround->name = 'Round 2';
        $newround->currentstage = constants::STAGE_PREPARATION;
        $newround->timecreated = time();
        $newround->timemodified = time();
        $newroundid = $DB->insert_record('kahoodle_rounds', $newround);
        $targetround = \mod_kahoodle\local\entities\round::create_from_id($newroundid);

        // Duplicate q1 into the target round.
        $duplicate = questions::duplicate_question($q1, $targetround);

        // Should be in the target round.
        $this->assertEquals($newroundid, $duplicate->get_data()->roundid);
        $this->assertEquals('Original question', $duplicate->get_data()->questiontext);

        // Should be appended at sortorder 1 (only question in the new round).
        $this->assertEquals(1, $duplicate->get_data()->sortorder);

        // Original round should still have the original question.
        $this->assertEquals(1, $DB->count_records('kahoodle_round_questions', ['roundid' => $round1->get_id()]));
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
        $roundquestion = $this->get_generator()
            ->create_question(['kahoodleid' => $kahoodle->id]);
        $id = $roundquestion->get_id();

        // Mark round as started.
        $round = $DB->get_record('kahoodle_rounds', ['kahoodleid' => $kahoodle->id], '*', MUST_EXIST);
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_LOBBY, ['id' => $round->id]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('No editable round available');
        questions::delete_question(round_question::create_from_round_question_id($id));
    }

    /**
     * Test validate_question_data returns empty array for valid data
     */
    public function test_validate_question_data_valid(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $rq = $this->get_generator()->create_question(['kahoodleid' => $kahoodle->id]);

        $data = (object) [
            'maxpoints' => 1000,
            'minpoints' => 500,
            'questionduration' => 30,
            'questionpreviewduration' => 5,
            'questionresultsduration' => 10,
        ];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertEmpty($errors);
    }

    /**
     * Test validate_question_data returns empty for null fields (use defaults)
     */
    public function test_validate_question_data_null_fields(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $rq = $this->get_generator()->create_question(['kahoodleid' => $kahoodle->id]);

        $data = (object) [
            'maxpoints' => null,
            'minpoints' => null,
            'questionduration' => null,
        ];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertEmpty($errors);
    }

    /**
     * Test validate_question_data detects negative field
     */
    public function test_validate_question_data_negative(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $rq = $this->get_generator()->create_question(['kahoodleid' => $kahoodle->id]);

        $data = (object) ['questionduration' => -10];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayHasKey('questionduration', $errors);
        $this->assertEquals(get_string('error_nonnegative', 'mod_kahoodle'), $errors['questionduration']);
    }

    /**
     * Test validate_question_data detects maxpoints less than minpoints
     */
    public function test_validate_question_data_max_less_than_min(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $rq = $this->get_generator()->create_question(['kahoodleid' => $kahoodle->id]);

        $data = (object) ['maxpoints' => 100, 'minpoints' => 500];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayHasKey('maxpoints', $errors);
        $this->assertEquals(get_string('error_maxpoints_less_than_minpoints', 'mod_kahoodle'), $errors['maxpoints']);
    }

    /**
     * Test validate_question_data uses defaults for maxpoints/minpoints comparison
     */
    public function test_validate_question_data_uses_defaults(): void {
        $this->resetAfterTest();

        // Create kahoodle with maxpoints=1000, minpoints=500 (defaults).
        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', [
            'course' => $course->id,
            'maxpoints' => 1000,
            'minpoints' => 500,
        ]);
        $rq = $this->get_generator()->create_question(['kahoodleid' => $kahoodle->id]);

        // Setting only maxpoints=100 should fail because default minpoints=500. Error on maxpoints.
        $data = (object) ['maxpoints' => 100];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayHasKey('maxpoints', $errors);

        // Setting only minpoints=2000 should fail because default maxpoints=1000. Error on minpoints.
        $data = (object) ['minpoints' => 2000];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayHasKey('minpoints', $errors);
        $this->assertArrayNotHasKey('maxpoints', $errors);

        // Setting only maxpoints=600 should succeed because default minpoints=500.
        $data = (object) ['maxpoints' => 600];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertEmpty($errors);
    }

    /**
     * Test validate_question_data allows zero values (not an error)
     */
    public function test_validate_question_data_zero_is_valid(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $rq = $this->get_generator()->create_question(['kahoodleid' => $kahoodle->id]);

        // Zero is non-negative, so it should be valid.
        $data = (object) ['questionduration' => 0, 'questionpreviewduration' => 0];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertEmpty($errors);
    }

    /**
     * Test validate_question_data validates rich text questiontext requires h3 tag
     */
    public function test_validate_question_data_richtext_h3_required(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', [
            'course' => $course->id,
            'questionformat' => constants::QUESTIONFORMAT_RICHTEXT,
        ]);
        $rq = $this->get_generator()->create_question(['kahoodleid' => $kahoodle->id]);

        // No h3 tag — error.
        $data = (object) ['questiontext' => '<p>Some question without heading</p>'];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayHasKey('questiontext', $errors);

        // Empty h3 tag — error.
        $data = (object) ['questiontext' => '<h3></h3><p>Details here</p>'];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayHasKey('questiontext', $errors);

        // H3 with only whitespace/tags — error.
        $data = (object) ['questiontext' => '<h3> <br> </h3>'];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayHasKey('questiontext', $errors);

        // Valid h3 with content — no error.
        $data = (object) ['questiontext' => '<h3>What is 2+2?</h3><p>Choose the correct answer.</p>'];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayNotHasKey('questiontext', $errors);

        // H3 with attributes — still valid.
        $data = (object) ['questiontext' => '<h3 class="foo">Question?</h3>'];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayNotHasKey('questiontext', $errors);
    }

    /**
     * Test validate_question_data validates plain text questiontext with PARAM_TEXT
     */
    public function test_validate_question_data_plaintext_clean(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', [
            'course' => $course->id,
            'questionformat' => constants::QUESTIONFORMAT_PLAIN,
        ]);
        $rq = $this->get_generator()->create_question(['kahoodleid' => $kahoodle->id]);

        // Plain text — valid.
        $data = (object) ['questiontext' => 'What is 2+2?'];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayNotHasKey('questiontext', $errors);

        // HTML tags — invalid for plain text.
        $data = (object) ['questiontext' => '<script>alert("xss")</script>'];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayHasKey('questiontext', $errors);

        // Null or empty questiontext — skipped, no error.
        $data = (object) ['questiontext' => null];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayNotHasKey('questiontext', $errors);

        $data = (object) ['questiontext' => ''];
        $errors = questions::validate_question_data($data, $rq);
        $this->assertArrayNotHasKey('questiontext', $errors);
    }
}
