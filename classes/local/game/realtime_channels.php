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

use mod_kahoodle\local\entities\participant;
use mod_kahoodle\local\entities\round;

/**
 * Class notifications
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtime_channels {
    /**
     * Subscribe the current user as facilitator to the realtime channel
     *
     * @param round $round The round
     */
    public static function subscribe_as_facilitator(round $round): void {
        $channel = new \tool_realtime\channel($round->get_context(), 'mod_kahoodle', 'facilitator', $round->get_id());
        $channel->subscribe();
    }

    /**
     * Subscribe the current user as participant to the realtime channel
     *
     * @param participant $participant The participant
     */
    public static function subscribe_as_participant(participant $participant): void {
        $round = $participant->get_round();
        $context = $round->get_context();

        $gamechannel = new \tool_realtime\channel($context, 'mod_kahoodle', 'game', $round->get_id());
        $gamechannel->subscribe();

        $participantchannel = new \tool_realtime\channel($context, 'mod_kahoodle', 'participant', $participant->get_id());
        $participantchannel->subscribe();
    }

    /**
     * Notify facilitators that a stage advanced or participant list changed during the lobby stage
     *
     * Sends the updated lobby stage data to the facilitator channel.
     *
     * @param round $round The round
     */
    public static function notify_facilitators_stage_changed(round $round): void {
        global $PAGE;
        $context = $round->get_context();

        // Create the facilitator channel.
        $channel = new \tool_realtime\channel($context, 'mod_kahoodle', 'facilitator', $round->get_id());

        // Get current stage data for facilitators.
        $stagedata = (new \mod_kahoodle\output\facilitator($round))->export_for_template(
            $PAGE->get_renderer('mod_kahoodle')
        );

        // Notify all subscribers on the facilitator channel.
        $channel->notify($stagedata);
    }

    /**
     * Notifiy participants that the round stage has advanced
     *
     * @param round $round
     * @return void
     */
    public static function notify_all_participants_stage_changed(round $round): void {
        global $PAGE;
        $context = $round->get_context();

        $participants = $round->get_all_participants();

        foreach ($participants as $participant) {
            // Create the participant channel.
            $channel = new \tool_realtime\channel($context, 'mod_kahoodle', 'participant', $participant->get_id());

            // Get current stage data for participants.
            $stagedata = (new \mod_kahoodle\output\participant($participant))->export_for_template(
                $PAGE->get_renderer('mod_kahoodle')
            );

            // Notify all subscribers on the participant channel.
            $channel->notify($stagedata);
        }
    }

    /**
     * Notifiy participant about the changes in the round stage
     *
     * @param participant $participant
     * @return void
     */
    public static function notify_participant_stage_changed(participant $participant): void {
        global $PAGE;
        $round = $participant->get_round();
        $context = $round->get_context();

        // Create the participant channel.
        $channel = new \tool_realtime\channel($context, 'mod_kahoodle', 'participant', $participant->get_id());

        // Get current stage data for participants.
        $stagedata = (new \mod_kahoodle\output\participant($participant))->export_for_template(
            $PAGE->get_renderer('mod_kahoodle')
        );

        // Notify all subscribers on the participant channel.
        $channel->notify($stagedata);
    }

    /**
     * Notify relevant participants that ranks were revealed on facilitator screen and need to be revealed on participant screens
     *
     * During the revision stage we show the suspense screen first (drumroll).
     *
     * @param round $round
     * @param string $rank which rank to reveal - 'rank1', 'rank2', 'rank3', 'all'
     * @return void
     */
    public static function notify_participants_rank_revealed(round $round, string $rank): void {
        $context = $round->get_context();
        $channels = [];

        if ($rank === 'all') {
            $channels[] = new \tool_realtime\channel($context, 'mod_kahoodle', 'game', $round->get_id());
        } else if (preg_match('/^rank([123])$/', $rank, $matches)) {
            $x = $round->get_podium_ranks()[(int)($matches[1])] ?? [];
            foreach ($x as $r) {
                $channels[] = new \tool_realtime\channel($context, 'mod_kahoodle', 'participant', $r->participant->get_id());
            }
        }

        foreach ($channels as $channel) {
            // Notify all subscribers on the participant channel.
            $channel->notify(['action' => 'reveal_rank']);
        }
    }
}
