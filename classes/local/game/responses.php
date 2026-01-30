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
use mod_kahoodle\local\entities\round_question;

/**
 * Response management for Kahoodle rounds
 *
 * Handles recording participant answers, calculating points, and updating scores.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class responses {
    /**
     * Record a participant's answer to a question
     *
     * This method handles all validation, storage, points calculation, and notification.
     * Invalid or duplicate submissions are silently ignored.
     *
     * @param participant $participant The participant
     * @param string $response The participant's answer
     * @param string $currentstagesignature The current stage signature according to the user (for validation)
     */
    public static function record_answer(participant $participant, string $response, string $currentstagesignature): void {
        global $DB;

        // Validate parameters.
        if ($response === '' || empty($currentstagesignature)) {
            return;
        }

        $round = $participant->get_round();

        // Get current stage and validate.
        $stage = $round->get_current_stage();
        if ($stage->get_stage_name() !== constants::STAGE_QUESTION) {
            return;
        }
        if ($stage->get_stage_signature() !== $currentstagesignature) {
            return;
        }

        $roundquestion = $stage->get_round_question();
        if (!$roundquestion) {
            return;
        }

        $participantid = $participant->get_id();

        // Check for duplicate submission - silently ignore.
        if (
            $DB->record_exists('kahoodle_responses', [
            'participantid' => $participantid,
            'roundquestionid' => $roundquestion->get_id(),
            ])
        ) {
            return;
        }

        // Validate answer via question type - returns null if invalid.
        $questiontype = $roundquestion->get_question_type();
        $iscorrect = $questiontype->validate_answer($roundquestion, $response);

        if ($iscorrect === null) {
            // Invalid answer, silently ignore.
            return;
        }

        // Calculate response time.
        $stagestarttime = (float)$DB->get_field('kahoodle_rounds', 'stagestarttime', ['id' => $round->get_id()]);
        $responsetime = microtime(true) - $stagestarttime;

        // Cap response time at question duration.
        $maxtime = $roundquestion->get_stage_duration(constants::STAGE_QUESTION);
        $responsetime = max(0, min($responsetime, $maxtime));

        // Calculate points (only for correct answers).
        $points = 0;
        if ($iscorrect) {
            $points = self::calculate_points($roundquestion, $responsetime);
        }

        // Insert response record.
        $responseid = $DB->insert_record('kahoodle_responses', (object)[
            'participantid' => $participantid,
            'roundquestionid' => $roundquestion->get_id(),
            'response' => $response,
            'iscorrect' => $iscorrect ? 1 : 0,
            'points' => $points,
            'responsetime' => $responsetime,
            'timecreated' => time(),
        ]);

        // Update participant's total score.
        if ($points > 0) {
            $DB->execute(
                'UPDATE {kahoodle_participants} SET totalscore = totalscore + ? WHERE id = ?',
                [$points, $participantid]
            );
        }

        // Trigger response submitted event.
        $event = \mod_kahoodle\event\response_submitted::create([
            'objectid' => $responseid,
            'context' => $round->get_context(),
            'relateduserid' => $participant->get_user_id(),
            'other' => [
                'roundid' => $round->get_id(),
                'questionnumber' => $stage->get_question_number(),
                'iscorrect' => $iscorrect,
                'points' => $points,
            ],
        ]);
        $event->trigger();

        // Send updated stage data to participant via their channel.
        realtime_channels::notify_participant_stage_changed($participant);
    }

    /**
     * Check if a participant has already answered a question
     *
     * @param participant $participant
     * @param round_question $roundquestion
     * @return bool
     */
    public static function has_answered(participant $participant, round_question $roundquestion): bool {
        global $DB;
        return $DB->record_exists('kahoodle_responses', [
            'participantid' => $participant->get_id(),
            'roundquestionid' => $roundquestion->get_id(),
        ]);
    }

    /**
     * Get a participant's response to a question
     *
     * @param participant $participant
     * @param round_question $roundquestion
     * @return object|null The response record or null
     */
    public static function get_response(participant $participant, round_question $roundquestion): ?object {
        global $DB;
        return $DB->get_record('kahoodle_responses', [
            'participantid' => $participant->get_id(),
            'roundquestionid' => $roundquestion->get_id(),
        ]) ?: null;
    }

    /**
     * Calculate points based on response time
     *
     * Uses linear interpolation: faster responses get more points.
     * Formula: points = maxpoints - (responsetime / maxtime) * (maxpoints - minpoints)
     *
     * @param round_question $roundquestion
     * @param float $responsetime Time taken to answer in seconds
     * @return int Points earned
     */
    protected static function calculate_points(round_question $roundquestion, float $responsetime): int {
        $kahoodle = $roundquestion->get_round()->get_kahoodle();
        $data = $roundquestion->get_data();

        $maxpoints = $data->maxpoints ?? $kahoodle->maxpoints;
        $minpoints = $data->minpoints ?? $kahoodle->minpoints;
        $maxtime = $roundquestion->get_stage_duration(constants::STAGE_QUESTION);

        // Avoid division by zero.
        if ($maxtime <= 0) {
            return (int)$maxpoints;
        }

        // Linear interpolation: faster = more points.
        $timefraction = $responsetime / $maxtime;
        $points = $maxpoints - ($timefraction * ($maxpoints - $minpoints));

        return (int)round($points);
    }
}
