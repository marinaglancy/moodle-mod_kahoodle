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

use advanced_testcase;
use backup;
use backup_controller;
use backup_setting;
use restore_controller;
use restore_dbops;

/**
 * Backup and restore tests for mod_kahoodle.
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_kahoodle_activity_structure_step
 * @covers     \restore_kahoodle_activity_structure_step
 * @covers     \restore_kahoodle_activity_task
 */
final class backup_restore_test extends advanced_testcase {
    /**
     * Load backup/restore libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        parent::setUpBeforeClass();
    }

    /**
     * Test backup and restore with user data.
     */
    public function test_backup_restore_with_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance([
            'course' => $course->id,
            'name' => 'Test Kahoodle',
            'lobbyduration' => 120,
            'questionduration' => 20,
            'maxpoints' => 800,
            'minpoints' => 400,
        ]);

        // Create questions in the first round (preparation stage).
        $rq1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Question 1',
            'questionconfig' => "Apple\n*Banana\nCherry",
        ]);
        $rq2 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Question 2',
            'questionconfig' => "*Red\nBlue\nGreen",
        ]);

        // Start the first round (move out of preparation).
        $roundid = $rq1->get_round()->get_id();
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_REVISION, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'timestarted', time() - 3600, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'timecompleted', time() - 3000, ['id' => $roundid]);

        // Add participants and responses to round 1.
        $p1 = $generator->create_participant([
            'roundid' => $roundid,
            'userid' => $user1->id,
            'displayname' => 'Player One',
            'totalscore' => 1500,
            'finalrank' => 1,
        ]);
        $p2 = $generator->create_participant([
            'roundid' => $roundid,
            'userid' => $user2->id,
            'displayname' => 'Player Two',
            'totalscore' => 1000,
            'finalrank' => 2,
        ]);

        $generator->create_response([
            'participantid' => $p1,
            'roundquestionid' => $rq1->get_id(),
            'response' => '1',
            'iscorrect' => 1,
            'points' => 800,
        ]);
        $generator->create_response([
            'participantid' => $p2,
            'roundquestionid' => $rq1->get_id(),
            'response' => '0',
            'iscorrect' => 0,
            'points' => 0,
        ]);

        // Create a second round (preparation).
        $round2 = new \stdClass();
        $round2->kahoodleid = $kahoodle->id;
        $round2->name = 'Round 2';
        $round2->currentstage = constants::STAGE_PREPARATION;
        $round2->timecreated = time();
        $round2->timemodified = time();
        $round2id = $DB->insert_record('kahoodle_rounds', $round2);

        // Add a question to round 2 referencing same question versions.
        $rq1data = $rq1->get_data();
        $DB->insert_record('kahoodle_round_questions', [
            'roundid' => $round2id,
            'questionversionid' => $rq1data->questionversionid,
            'sortorder' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Count original records.
        $origquestions = $DB->count_records('kahoodle_questions', ['kahoodleid' => $kahoodle->id]);
        $origversions = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {kahoodle_question_versions} qv
               JOIN {kahoodle_questions} q ON q.id = qv.questionid
              WHERE q.kahoodleid = ?",
            [$kahoodle->id]
        );
        $origrounds = $DB->count_records('kahoodle_rounds', ['kahoodleid' => $kahoodle->id]);
        $origparticipants = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {kahoodle_participants} p
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?",
            [$kahoodle->id]
        );
        $origresponses = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {kahoodle_responses} resp
               JOIN {kahoodle_participants} p ON p.id = resp.participantid
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?",
            [$kahoodle->id]
        );

        $this->assertEquals(2, $origquestions);
        $this->assertEquals(2, $origversions);
        $this->assertEquals(2, $origrounds);
        $this->assertEquals(2, $origparticipants);
        $this->assertEquals(2, $origresponses);

        // Backup and restore with user data.
        $newcourseid = $this->backup_and_restore($course, true);

        // Verify restored activity.
        $newkahoodle = $DB->get_record('kahoodle', ['course' => $newcourseid]);
        $this->assertNotEmpty($newkahoodle);
        $this->assertEquals('Test Kahoodle', $newkahoodle->name);
        $this->assertEquals(120, $newkahoodle->lobbyduration);
        $this->assertEquals(20, $newkahoodle->questionduration);
        $this->assertEquals(800, $newkahoodle->maxpoints);
        $this->assertEquals(400, $newkahoodle->minpoints);

        // Verify all rounds are restored.
        $newrounds = $DB->count_records('kahoodle_rounds', ['kahoodleid' => $newkahoodle->id]);
        $this->assertEquals(2, $newrounds);

        // Verify all questions are restored.
        $newquestions = $DB->count_records('kahoodle_questions', ['kahoodleid' => $newkahoodle->id]);
        $this->assertEquals(2, $newquestions);

        // Verify question versions.
        $newversions = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {kahoodle_question_versions} qv
               JOIN {kahoodle_questions} q ON q.id = qv.questionid
              WHERE q.kahoodleid = ?",
            [$newkahoodle->id]
        );
        $this->assertEquals(2, $newversions);

        // Verify participants are restored.
        $newparticipants = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {kahoodle_participants} p
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?",
            [$newkahoodle->id]
        );
        $this->assertEquals(2, $newparticipants);

        // Verify responses are restored.
        $newresponses = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {kahoodle_responses} resp
               JOIN {kahoodle_participants} p ON p.id = resp.participantid
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?",
            [$newkahoodle->id]
        );
        $this->assertEquals(2, $newresponses);

        // Verify round question cross-references are valid.
        $newroundquestions = $DB->get_records_sql(
            "SELECT rq.* FROM {kahoodle_round_questions} rq
               JOIN {kahoodle_rounds} r ON r.id = rq.roundid
              WHERE r.kahoodleid = ?",
            [$newkahoodle->id]
        );
        foreach ($newroundquestions as $rq) {
            // Each round question should reference a valid question version.
            $this->assertTrue($DB->record_exists('kahoodle_question_versions', ['id' => $rq->questionversionid]));
        }

        // Verify participant data is correct.
        $newparticipantrecords = $DB->get_records_sql(
            "SELECT p.* FROM {kahoodle_participants} p
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?
              ORDER BY p.finalrank ASC",
            [$newkahoodle->id]
        );
        $firstparticipant = reset($newparticipantrecords);
        $this->assertEquals('Player One', $firstparticipant->displayname);
        $this->assertEquals(1500, $firstparticipant->totalscore);
        $this->assertEquals(1, $firstparticipant->finalrank);
    }

    /**
     * Test backup and restore without user data.
     */
    public function test_backup_restore_without_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance([
            'course' => $course->id,
            'name' => 'Test Kahoodle No User Data',
        ]);

        // Create questions.
        $rq1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Q1 for no userdata',
            'questionconfig' => "A\n*B\nC",
        ]);
        $rq2 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Q2 for no userdata',
            'questionconfig' => "*X\nY\nZ",
        ]);

        // Start the first round.
        $roundid = $rq1->get_round()->get_id();
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_REVISION, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'timestarted', time() - 3600, ['id' => $roundid]);

        // Add participants.
        $p1 = $generator->create_participant([
            'roundid' => $roundid,
            'userid' => $user1->id,
            'displayname' => 'Player',
            'totalscore' => 500,
        ]);
        $generator->create_response([
            'participantid' => $p1,
            'roundquestionid' => $rq1->get_id(),
        ]);

        // Create second round (preparation).
        $round2 = new \stdClass();
        $round2->kahoodleid = $kahoodle->id;
        $round2->name = 'Round 2';
        $round2->currentstage = constants::STAGE_PREPARATION;
        $round2->timecreated = time();
        $round2->timemodified = time();
        $round2id = $DB->insert_record('kahoodle_rounds', $round2);

        // Add both questions to round 2.
        $rq1data = $rq1->get_data();
        $rq2data = $rq2->get_data();
        $DB->insert_record('kahoodle_round_questions', [
            'roundid' => $round2id,
            'questionversionid' => $rq1data->questionversionid,
            'sortorder' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('kahoodle_round_questions', [
            'roundid' => $round2id,
            'questionversionid' => $rq2data->questionversionid,
            'sortorder' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Backup and restore without user data.
        $newcourseid = $this->backup_and_restore($course, false);

        $newkahoodle = $DB->get_record('kahoodle', ['course' => $newcourseid]);
        $this->assertNotEmpty($newkahoodle);
        $this->assertEquals('Test Kahoodle No User Data', $newkahoodle->name);

        // Only the last round (round 2 in preparation) should be restored.
        $newrounds = $DB->get_records('kahoodle_rounds', ['kahoodleid' => $newkahoodle->id]);
        $this->assertCount(1, $newrounds);
        $newround = reset($newrounds);
        $this->assertEquals('Round 2', $newround->name);
        $this->assertEquals(constants::STAGE_PREPARATION, $newround->currentstage);

        // Both questions should be restored (they are in the last round).
        $newquestions = $DB->count_records('kahoodle_questions', ['kahoodleid' => $newkahoodle->id]);
        $this->assertEquals(2, $newquestions);

        // Round questions should be restored.
        $newroundquestions = $DB->count_records('kahoodle_round_questions', ['roundid' => $newround->id]);
        $this->assertEquals(2, $newroundquestions);

        // No participants.
        $newparticipants = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {kahoodle_participants} p
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?",
            [$newkahoodle->id]
        );
        $this->assertEquals(0, $newparticipants);

        // No responses.
        $newresponses = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {kahoodle_responses} resp
               JOIN {kahoodle_participants} p ON p.id = resp.participantid
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?",
            [$newkahoodle->id]
        );
        $this->assertEquals(0, $newresponses);
    }

    /**
     * Test backup with user data but restore without user data.
     *
     * When backup includes all rounds (with user data) but restore strips user data,
     * all rounds and their question configuration should still be restored,
     * but no participants or responses.
     */
    public function test_backup_with_userdata_restore_without(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance([
            'course' => $course->id,
            'name' => 'Mixed backup test',
        ]);

        // Create questions.
        $rq1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Mixed Q1',
            'questionconfig' => "A\n*B\nC",
        ]);

        // Start the round.
        $roundid = $rq1->get_round()->get_id();
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_ARCHIVED, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'timestarted', time() - 7200, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'timecompleted', time() - 3600, ['id' => $roundid]);

        // Add participant.
        $p1 = $generator->create_participant([
            'roundid' => $roundid,
            'userid' => $user1->id,
            'displayname' => 'Test Player',
            'totalscore' => 700,
        ]);
        $generator->create_response([
            'participantid' => $p1,
            'roundquestionid' => $rq1->get_id(),
        ]);

        // Create a second round in preparation.
        $round2 = new \stdClass();
        $round2->kahoodleid = $kahoodle->id;
        $round2->name = 'Prep Round';
        $round2->currentstage = constants::STAGE_PREPARATION;
        $round2->timecreated = time();
        $round2->timemodified = time();
        $round2id = $DB->insert_record('kahoodle_rounds', $round2);

        $rq1data = $rq1->get_data();
        $DB->insert_record('kahoodle_round_questions', [
            'roundid' => $round2id,
            'questionversionid' => $rq1data->questionversionid,
            'sortorder' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Backup with user data, restore without.
        $newcourseid = $this->backup_and_restore_mixed($course);

        $newkahoodle = $DB->get_record('kahoodle', ['course' => $newcourseid]);
        $this->assertNotEmpty($newkahoodle);

        // Both rounds should be in the backup (backed up with userdata),
        // but on restore without userdata, both rounds are still restored (structure is preserved).
        $newrounds = $DB->get_records('kahoodle_rounds', ['kahoodleid' => $newkahoodle->id], 'timecreated ASC');
        $this->assertCount(2, $newrounds);

        // Questions should be restored.
        $newquestions = $DB->count_records('kahoodle_questions', ['kahoodleid' => $newkahoodle->id]);
        $this->assertEquals(1, $newquestions);

        // No participants (user data not restored).
        $newparticipants = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {kahoodle_participants} p
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?",
            [$newkahoodle->id]
        );
        $this->assertEquals(0, $newparticipants);
    }

    /**
     * Test backup and restore with question files.
     */
    public function test_backup_restore_question_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);

        // Create a question.
        $rq1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Question with image',
        ]);

        // Add a file to the question version.
        $context = \context_module::instance($kahoodle->cmid);
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_kahoodle',
            'filearea' => constants::FILEAREA_QUESTION_IMAGE,
            'itemid' => $rq1->get_data()->questionversionid,
            'filepath' => '/',
            'filename' => 'testimage.png',
        ];
        $fs->create_file_from_string($filerecord, 'fake image content');

        // Verify the file exists.
        $files = $fs->get_area_files(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_QUESTION_IMAGE,
            $rq1->get_data()->questionversionid,
            'filename',
            false
        );
        $this->assertCount(1, $files);

        // Backup and restore.
        $newcourseid = $this->backup_and_restore($course, false);

        // Find the restored activity.
        $newkahoodle = $DB->get_record('kahoodle', ['course' => $newcourseid]);
        $newcm = get_coursemodule_from_instance('kahoodle', $newkahoodle->id);
        $newcontext = \context_module::instance($newcm->id);

        // Find the restored question version.
        $newversions = $DB->get_records_sql(
            "SELECT qv.* FROM {kahoodle_question_versions} qv
               JOIN {kahoodle_questions} q ON q.id = qv.questionid
              WHERE q.kahoodleid = ?",
            [$newkahoodle->id]
        );
        $this->assertCount(1, $newversions);
        $newversion = reset($newversions);

        // Verify the file was restored.
        $newfiles = $fs->get_area_files(
            $newcontext->id,
            'mod_kahoodle',
            constants::FILEAREA_QUESTION_IMAGE,
            $newversion->id,
            'filename',
            false
        );
        $this->assertCount(1, $newfiles);
        $newfile = reset($newfiles);
        $this->assertEquals('testimage.png', $newfile->get_filename());
        $this->assertEquals('fake image content', $newfile->get_content());
    }

    /**
     * Test backup and restore with participant avatar files.
     */
    public function test_backup_restore_avatar_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);

        $rq1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Q for avatar test',
        ]);

        $roundid = $rq1->get_round()->get_id();
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_REVISION, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'timestarted', time(), ['id' => $roundid]);

        $p1 = $generator->create_participant([
            'roundid' => $roundid,
            'userid' => $user1->id,
            'displayname' => 'Avatar Player',
            'avatar' => 'avatar.png',
        ]);

        // Create avatar file for the participant.
        $context = \context_module::instance($kahoodle->cmid);
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_kahoodle',
            'filearea' => constants::FILEAREA_AVATAR,
            'itemid' => $p1,
            'filepath' => '/',
            'filename' => 'avatar.png',
        ];
        $fs->create_file_from_string($filerecord, 'fake avatar content');

        // Backup and restore with user data.
        $newcourseid = $this->backup_and_restore($course, true);

        $newkahoodle = $DB->get_record('kahoodle', ['course' => $newcourseid]);
        $newcm = get_coursemodule_from_instance('kahoodle', $newkahoodle->id);
        $newcontext = \context_module::instance($newcm->id);

        // Find restored participant.
        $newparticipant = $DB->get_record_sql(
            "SELECT p.* FROM {kahoodle_participants} p
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?",
            [$newkahoodle->id]
        );
        $this->assertNotEmpty($newparticipant);
        $this->assertEquals('Avatar Player', $newparticipant->displayname);

        // Verify avatar file was restored.
        $newfiles = $fs->get_area_files(
            $newcontext->id,
            'mod_kahoodle',
            constants::FILEAREA_AVATAR,
            $newparticipant->id,
            'filename',
            false
        );
        $this->assertCount(1, $newfiles);
        $newfile = reset($newfiles);
        $this->assertEquals('avatar.png', $newfile->get_filename());
        $this->assertEquals('fake avatar content', $newfile->get_content());
    }

    /**
     * Test that a single round with no user data backs up correctly.
     */
    public function test_backup_restore_single_round_no_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $rq1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Only question',
            'questionconfig' => "Yes\n*No",
        ]);

        // Backup and restore without user data.
        $newcourseid = $this->backup_and_restore($course, false);

        $newkahoodle = $DB->get_record('kahoodle', ['course' => $newcourseid]);
        $this->assertNotEmpty($newkahoodle);

        // One round restored.
        $newrounds = $DB->get_records('kahoodle_rounds', ['kahoodleid' => $newkahoodle->id]);
        $this->assertCount(1, $newrounds);

        // One question.
        $newquestions = $DB->count_records('kahoodle_questions', ['kahoodleid' => $newkahoodle->id]);
        $this->assertEquals(1, $newquestions);

        // One round question.
        $newround = reset($newrounds);
        $newroundquestions = $DB->count_records('kahoodle_round_questions', ['roundid' => $newround->id]);
        $this->assertEquals(1, $newroundquestions);

        // Verify question content.
        $newversion = $DB->get_record_sql(
            "SELECT qv.* FROM {kahoodle_question_versions} qv
               JOIN {kahoodle_questions} q ON q.id = qv.questionid
              WHERE q.kahoodleid = ?",
            [$newkahoodle->id]
        );
        $this->assertEquals('Only question', $newversion->questiontext);
        $this->assertEquals("Yes\n*No", $newversion->questionconfig);
    }

    /**
     * Test that restoring without user data resets the round stage to preparation.
     */
    public function test_backup_restore_without_userdata_resets_stage(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);

        // Create questions.
        $rq1 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Archived Q1',
            'questionconfig' => "A\n*B\nC",
        ]);
        $rq2 = $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Archived Q2',
            'questionconfig' => "*X\nY",
        ]);

        // Progress the round to archived.
        $roundid = $rq1->get_round()->get_id();
        $DB->set_field('kahoodle_rounds', 'currentstage', constants::STAGE_ARCHIVED, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'timestarted', time() - 7200, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'timecompleted', time() - 3600, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'currentquestion', 2, ['id' => $roundid]);
        $DB->set_field('kahoodle_rounds', 'stagestarttime', time() - 3600, ['id' => $roundid]);

        // Backup and restore without user data.
        $newcourseid = $this->backup_and_restore($course, false);

        $newkahoodle = $DB->get_record('kahoodle', ['course' => $newcourseid]);
        $this->assertNotEmpty($newkahoodle);

        // The restored round should be in preparation stage.
        $newrounds = $DB->get_records('kahoodle_rounds', ['kahoodleid' => $newkahoodle->id]);
        $this->assertCount(1, $newrounds);
        $newround = reset($newrounds);
        $this->assertEquals(constants::STAGE_PREPARATION, $newround->currentstage);
        $this->assertNull($newround->currentquestion);
        $this->assertNull($newround->stagestarttime);
        $this->assertNull($newround->timestarted);
        $this->assertNull($newround->timecompleted);

        // Questions should still be restored.
        $newquestions = $DB->count_records('kahoodle_questions', ['kahoodleid' => $newkahoodle->id]);
        $this->assertEquals(2, $newquestions);

        $newroundquestions = $DB->count_records('kahoodle_round_questions', ['roundid' => $newround->id]);
        $this->assertEquals(2, $newroundquestions);
    }

    /**
     * Test that restore log rules correctly remap IDs in legacy log URLs.
     */
    public function test_restore_log_rules(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/kahoodle/backup/moodle2/restore_kahoodle_activity_task.class.php');

        // Test activity-level log rules.
        $rules = \restore_kahoodle_activity_task::define_restore_log_rules();
        $this->assertCount(3, $rules);

        $fixedvalues = ['course_module' => 100, 'kahoodle' => 200];

        // Test 'add' rule.
        $rules[0]->set_fixed_values($fixedvalues);
        $log = (object) ['module' => 'kahoodle', 'action' => 'add', 'url' => 'view.php?id=42', 'info' => '99'];
        $result = $rules[0]->process($log);
        $this->assertNotFalse($result);
        $this->assertEquals('view.php?id=100', $result->url);
        $this->assertEquals('200', $result->info);

        // Test 'update' rule.
        $rules[1]->set_fixed_values($fixedvalues);
        $log = (object) ['module' => 'kahoodle', 'action' => 'update', 'url' => 'view.php?id=55', 'info' => '77'];
        $result = $rules[1]->process($log);
        $this->assertNotFalse($result);
        $this->assertEquals('view.php?id=100', $result->url);
        $this->assertEquals('200', $result->info);

        // Test 'view' rule.
        $rules[2]->set_fixed_values($fixedvalues);
        $log = (object) ['module' => 'kahoodle', 'action' => 'view', 'url' => 'view.php?id=10', 'info' => '5'];
        $result = $rules[2]->process($log);
        $this->assertNotFalse($result);
        $this->assertEquals('view.php?id=100', $result->url);
        $this->assertEquals('200', $result->info);

        // Test that a non-matching URL returns false.
        $log = (object) ['module' => 'kahoodle', 'action' => 'add', 'url' => 'edit.php?id=42', 'info' => '99'];
        $result = $rules[0]->process($log);
        $this->assertFalse($result);

        // Test course-level log rules.
        $courserules = \restore_kahoodle_activity_task::define_restore_log_rules_for_course();
        $this->assertCount(1, $courserules);

        // Test 'view all' rule.
        $courserules[0]->set_fixed_values(['course' => 300]);
        $log = (object) ['module' => 'kahoodle', 'action' => 'view all', 'url' => 'index.php?id=50', 'info' => ''];
        $result = $courserules[0]->process($log);
        $this->assertNotFalse($result);
        $this->assertEquals('index.php?id=300', $result->url);
    }

    /**
     * Backs up a course and restores it.
     *
     * @param \stdClass $srccourse Course object to backup
     * @param bool $userdata Whether to include user data
     * @return int ID of newly restored course
     */
    private function backup_and_restore(\stdClass $srccourse, bool $userdata): int {
        global $USER, $CFG;

        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        // MODE_IMPORT creates the directory without zipping.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $srccourse->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );

        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userdata);

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore to a new course.
        $newcourseid = restore_dbops::create_new_course(
            $srccourse->fullname,
            $srccourse->shortname . '_2',
            $srccourse->category
        );
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );

        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value($userdata);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Backs up with user data and restores without user data.
     *
     * @param \stdClass $srccourse Course object to backup
     * @return int ID of newly restored course
     */
    private function backup_and_restore_mixed(\stdClass $srccourse): int {
        global $USER, $CFG;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        // Backup WITH user data.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $srccourse->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );

        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore WITHOUT user data.
        $newcourseid = restore_dbops::create_new_course(
            $srccourse->fullname,
            $srccourse->shortname . '_2',
            $srccourse->category
        );
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );

        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(false);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
