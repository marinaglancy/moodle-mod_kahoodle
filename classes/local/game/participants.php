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
use mod_kahoodle\local\entities\round;

/**
 * Participant management for Kahoodle rounds
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants {
    /**
     * Join a round as a participant
     *
     * Creates a participant record for the current user in the given round.
     * If the round is in the lobby stage, notifies facilitators with the updated participant list.
     *
     * @param round $round The round to join
     * @throws \dml_exception If database operation fails
     */
    public static function join_round(round $round): void {
        global $DB, $USER;

        // Check if user is already a participant.
        if ($round->is_participant()) {
            return;
        }

        // Get user's display name.
        $displayname = fullname($USER);

        // Create participant record.
        $participant = (object)[
            'roundid' => $round->get_id(),
            'userid' => $USER->id,
            'displayname' => $displayname,
            'avatar' => null,
            'totalscore' => 0,
            'finalrank' => null,
            'timecreated' => time(),
        ];

        $participant->id = $DB->insert_record('kahoodle_participants', $participant);
        $round->clear_participant_cache();

        // If round is in lobby stage, notify facilitators with updated stage data.
        if ($round->get_current_stage_name() === constants::STAGE_LOBBY) {
            self::notify_facilitators_participant_joined($round);
        }
    }

    /**
     * Leave a round as a participant
     *
     * Removes the participant record for the current user in the given round.
     * Also deletes any responses the participant may have submitted.
     * If the round is in the lobby stage, notifies facilitators with the updated participant list.
     *
     * @param round $round The round to leave
     */
    public static function leave_round(round $round): void {
        global $DB;

        $participant = $round->is_participant();
        if (!$participant) {
            return;
        }

        // Delete any responses from this participant.
        $DB->delete_records('kahoodle_responses', ['participantid' => $participant->get_id()]);

        // Delete the participant record.
        $DB->delete_records('kahoodle_participants', ['id' => $participant->get_id()]);
        $round->clear_participant_cache();

        // If round is in lobby stage, notify facilitators with updated stage data.
        if ($round->get_current_stage_name() === constants::STAGE_LOBBY) {
            self::notify_facilitators_participant_joined($round);
        }
    }

    /**
     * Notify facilitators that a participant has joined
     *
     * Sends the updated lobby stage data to the facilitator channel.
     *
     * @param round $round The round
     */
    protected static function notify_facilitators_participant_joined(round $round): void {
        $context = $round->get_context();

        // Create the facilitator channel.
        $channel = new \tool_realtime\channel($context, 'mod_kahoodle', 'facilitator', $round->get_id());

        // Get current stage data for facilitators.
        $stage = $round->get_current_stage();
        $stagedata = $stage->export_data_for_facilitators();

        // Notify all subscribers on the facilitator channel.
        $channel->notify($stagedata);
    }
}
