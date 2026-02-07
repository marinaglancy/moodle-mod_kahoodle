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

namespace mod_kahoodle\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Privacy provider tests for mod_kahoodle.
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Test that the module's metadata provider reports the correct items.
     */
    public function test_get_metadata(): void {
        $collection = new collection('mod_kahoodle');
        $newcollection = provider::get_metadata($collection);
        $itemtypes = array_map(fn($item) => $item->get_name(), $newcollection->get_collection());
        $this->assertContains('kahoodle_participants', $itemtypes);
        $this->assertContains('kahoodle_responses', $itemtypes);
        $this->assertContains('core_files', $itemtypes);
    }

    /**
     * Test getting contexts for a user who has participated.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);
        $context = \context_module::instance($cm->id);

        // No data yet — no contexts.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);

        // Add a participant.
        $this->create_participation($generator, $kahoodle->id, $user->id);

        // Now we should get the context.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context->id, $contextlist->get_contextids()[0]);
    }

    /**
     * Test getting users in a context.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);
        $context = \context_module::instance($cm->id);

        // No users initially.
        $userlist = new userlist($context, 'mod_kahoodle');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        // Add two participants.
        $this->create_participation($generator, $kahoodle->id, $user1->id);
        $this->create_participation($generator, $kahoodle->id, $user2->id);

        $userlist = new userlist($context, 'mod_kahoodle');
        provider::get_users_in_context($userlist);
        $this->assertCount(2, $userlist);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $userlist->get_userids());
    }

    /**
     * Test that get_users_in_context returns nothing for non-module contexts.
     */
    public function test_get_users_in_context_invalid_context(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $userlist = new userlist($context, 'mod_kahoodle');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * Test export of user data.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);
        $context = \context_module::instance($cm->id);

        $this->create_participation($generator, $kahoodle->id, $user->id);

        // Export data.
        $this->export_context_data_for_user($user->id, $context, 'mod_kahoodle');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data([]);
        $this->assertNotEmpty($data->participations);
        $this->assertCount(1, $data->participations);

        $participation = $data->participations[0];
        $this->assertEquals('User ' . $user->id, $participation['displayname']);
        $this->assertNotEmpty($participation['responses']);
        $this->assertCount(1, $participation['responses']);
        $this->assertEquals(100, $participation['responses'][0]['points']);
    }

    /**
     * Test deleting all data for all users in a context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);
        $context = \context_module::instance($cm->id);

        $this->create_participation($generator, $kahoodle->id, $user1->id);
        $this->create_participation($generator, $kahoodle->id, $user2->id);

        // Verify data exists.
        $this->assertTrue($DB->record_exists('kahoodle_participants', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('kahoodle_participants', ['userid' => $user2->id]));
        $this->assertGreaterThan(0, $DB->count_records('kahoodle_responses'));

        // Delete all data in context.
        provider::delete_data_for_all_users_in_context($context);

        // Verify all data is gone.
        $roundids = $DB->get_fieldset_select('kahoodle_rounds', 'id', 'kahoodleid = ?', [$kahoodle->id]);
        foreach ($roundids as $roundid) {
            $this->assertFalse($DB->record_exists('kahoodle_participants', ['roundid' => $roundid]));
        }
        $this->assertEquals(0, $DB->count_records('kahoodle_responses'));
    }

    /**
     * Test deleting data for a specific user.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);
        $context = \context_module::instance($cm->id);

        $this->create_participation($generator, $kahoodle->id, $user1->id);
        $this->create_participation($generator, $kahoodle->id, $user2->id);

        // Delete data for user1 only.
        $contextlist = new approved_contextlist($user1, 'mod_kahoodle', [$context->id]);
        provider::delete_data_for_user($contextlist);

        // User1 data should be gone, user2 data should remain.
        $this->assertFalse($DB->record_exists('kahoodle_participants', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('kahoodle_participants', ['userid' => $user2->id]));

        // User2's responses should still exist.
        $participant2 = $DB->get_record('kahoodle_participants', ['userid' => $user2->id]);
        $this->assertTrue($DB->record_exists('kahoodle_responses', ['participantid' => $participant2->id]));
    }

    /**
     * Test deleting data for multiple users in a context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, 'student');

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);
        $context = \context_module::instance($cm->id);

        $this->create_participation($generator, $kahoodle->id, $user1->id);
        $this->create_participation($generator, $kahoodle->id, $user2->id);
        $this->create_participation($generator, $kahoodle->id, $user3->id);

        // Delete data for user1 and user2.
        $approveduserlist = new approved_userlist($context, 'mod_kahoodle', [$user1->id, $user2->id]);
        provider::delete_data_for_users($approveduserlist);

        // User1 and user2 data should be gone, user3 data should remain.
        $this->assertFalse($DB->record_exists('kahoodle_participants', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('kahoodle_participants', ['userid' => $user2->id]));
        $this->assertTrue($DB->record_exists('kahoodle_participants', ['userid' => $user3->id]));

        // User3's responses should still exist.
        $participant3 = $DB->get_record('kahoodle_participants', ['userid' => $user3->id]);
        $this->assertTrue($DB->record_exists('kahoodle_responses', ['participantid' => $participant3->id]));
    }

    /**
     * Test that multiple kahoodle instances return separate contexts.
     */
    public function test_multiple_contexts(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $kahoodle1 = $generator->create_instance(['course' => $course->id]);
        $kahoodle2 = $generator->create_instance(['course' => $course->id]);

        $cm1 = get_coursemodule_from_instance('kahoodle', $kahoodle1->id);
        $cm2 = get_coursemodule_from_instance('kahoodle', $kahoodle2->id);
        $context1 = \context_module::instance($cm1->id);
        $context2 = \context_module::instance($cm2->id);

        $this->create_participation($generator, $kahoodle1->id, $user->id);
        $this->create_participation($generator, $kahoodle2->id, $user->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(2, $contextlist);
        $this->assertEqualsCanonicalizing(
            [$context1->id, $context2->id],
            $contextlist->get_contextids()
        );
    }

    /**
     * Helper: create a round with a question, participant and response in a kahoodle instance.
     *
     * @param \mod_kahoodle_generator $generator The generator instance.
     * @param int $kahoodleid The kahoodle instance ID.
     * @param int $userid The user ID.
     * @return array [roundid, participantid, responseid]
     */
    private function create_participation(\mod_kahoodle_generator $generator, int $kahoodleid, int $userid): array {
        // Creating a question auto-creates a round if none exists.
        $roundquestion = $generator->create_question(['kahoodleid' => $kahoodleid]);
        $roundid = $roundquestion->get_data()->roundid;

        // Create participant.
        $participantid = $generator->create_participant([
            'roundid' => $roundid,
            'userid' => $userid,
        ]);

        // Create response.
        $responseid = $generator->create_response([
            'participantid' => $participantid,
            'roundquestionid' => $roundquestion->get_id(),
        ]);

        return [$roundid, $participantid, $responseid];
    }
}
