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
use mod_kahoodle\local\entities\participant;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\questions;

/**
 * Tests for participant game management
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\local\game\participants
 */
final class participants_test extends \advanced_testcase {
    /**
     * Helper to set round stage in the database
     *
     * @param round $round
     * @param string $stage
     * @param int $currentquestion
     */
    protected function set_round_stage(round $round, string $stage, int $currentquestion = 0): void {
        global $DB;
        $DB->update_record('kahoodle_rounds', [
            'id' => $round->get_id(),
            'currentstage' => $stage,
            'currentquestion' => $currentquestion,
        ]);
    }

    /**
     * Create dummy avatar image files in the allavatars file area.
     *
     * @param int $count Number of avatar files to create
     */
    protected function create_allavatars(int $count): void {
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        for ($i = 1; $i <= $count; $i++) {
            $fs->create_file_from_string([
                'contextid' => $syscontext->id,
                'component' => 'mod_kahoodle',
                'filearea' => 'allavatars',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => "avatar{$i}.png",
            ], "image-content-{$i}");
        }
    }

    /**
     * Test get_avatar_candidates returns candidates and respects the onlynew flag.
     */
    public function test_get_avatar_candidates(): void {
        $this->resetAfterTest();

        // Create 20 avatar images in the pool.
        $this->create_allavatars(20);

        // Create a kahoodle with ALIAS identity mode (allows avatar change).
        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', [
            'course' => $course->id,
            'identitymode' => constants::IDENTITYMODE_ALIAS,
        ]);
        $round = questions::get_last_round($kahoodle->id);

        // Set round to lobby stage.
        $this->set_round_stage($round, constants::STAGE_LOBBY);
        $round = round::create_from_id($round->get_id());

        // Create a user and join as participant.
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $user = $this->getDataGenerator()->create_user();
        $pid = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user->id]);

        // Load the participant entity.
        $allparticipants = participant::load_round_participants($round);
        $participant = $allparticipants[$pid];

        // First call: get_avatar_candidates(false) should return 8 candidates.
        $result1 = participants::get_avatar_candidates($participant, false);
        $this->assertCount(8, $result1['candidates']);
        $this->assertTrue($result1['hasmore']);
        $filenames1 = array_column($result1['candidates'], 'filename');

        // Second call: get_avatar_candidates(true) should return 8 NEW candidates.
        $result2 = participants::get_avatar_candidates($participant, true);
        $this->assertCount(8, $result2['candidates']);
        $filenames2 = array_column($result2['candidates'], 'filename');

        // The two sets of candidates should be completely different.
        $this->assertEmpty(
            array_intersect($filenames1, $filenames2),
            'Second batch should contain entirely different candidates'
        );

        // Third call: get_avatar_candidates(false) should return all 16 existing candidates.
        $result3 = participants::get_avatar_candidates($participant, false);
        $this->assertCount(16, $result3['candidates']);
        $filenames3 = array_column($result3['candidates'], 'filename');

        // The 16 returned should be exactly the union of the first two batches.
        sort($filenames3);
        $combined = array_merge($filenames1, $filenames2);
        sort($combined);
        $this->assertEquals($combined, $filenames3);
    }
}
