<?php
// This file is part of Moodle - http://moodle.org/
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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;

/**
 * Implementation of web service mod_kahoodle_get_round_results
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_round_results extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_get_round_results
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roundid' => new external_value(PARAM_INT, 'Round id, from mod_kahoodle_get_rounds'),
        ]);
    }

    /**
     * The leaderboard of a round
     *
     * @param int $roundid
     * @return array
     */
    public static function execute(int $roundid): array {
        global $DB;
        ['roundid' => $roundid] = self::validate_parameters(self::execute_parameters(), ['roundid' => $roundid]);
        $round = round::create_from_id($roundid);
        $context = $round->get_context();
        self::validate_context($context);
        require_capability('mod/kahoodle:viewresults', $context);

        $anonymous = (int)$round->get_kahoodle()->identitymode === constants::IDENTITYMODE_ANONYMOUS;
        $answers = $DB->get_records_sql(
            "SELECT r.participantid, COUNT(*) AS answered, SUM(r.iscorrect) AS correct
               FROM {kahoodle_responses} r
               JOIN {kahoodle_participants} p ON p.id = r.participantid
              WHERE p.roundid = ?
           GROUP BY r.participantid",
            [$round->get_id()]
        );
        $participants = [];
        foreach ($round->get_all_participants() as $participant) {
            $user = $anonymous ? null : $participant->get_user_record();
            $participants[] = [
                'rank' => (int)$participant->get_final_rank(),
                'displayname' => html_entity_decode($participant->get_display_name(), ENT_QUOTES),
                'userfullname' => $user ? fullname($user) : '',
                'score' => $participant->get_total_score(),
                'correctanswers' => (int)($answers[$participant->get_id()]->correct ?? 0),
                'questionsanswered' => (int)($answers[$participant->get_id()]->answered ?? 0),
            ];
        }
        usort($participants, fn($a, $b) => [$a['rank'] ?: PHP_INT_MAX, -$a['score']] <=> [$b['rank'] ?: PHP_INT_MAX, -$b['score']]);
        return [
            'roundid' => $round->get_id(),
            'name' => $round->get_display_name(),
            'questioncount' => $round->get_questions_count(),
            'participants' => $participants,
        ];
    }

    /**
     * Describe the return structure for mod_kahoodle_get_round_results
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'roundid' => new external_value(PARAM_INT, 'Round id'),
            'name' => new external_value(PARAM_TEXT, 'Round name'),
            'questioncount' => new external_value(PARAM_INT, 'Number of questions in the round'),
            'participants' => new external_multiple_structure(new external_single_structure([
                'rank' => new external_value(PARAM_INT, 'Final rank, 0 while the round is not finished'),
                'displayname' => new external_value(PARAM_TEXT, 'Name or alias shown on the leaderboard'),
                'userfullname' => new external_value(PARAM_TEXT,
                    'Full name of the user, empty when the activity is fully anonymous'),
                'score' => new external_value(PARAM_INT, 'Total score'),
                'correctanswers' => new external_value(PARAM_INT, 'Number of correct answers'),
                'questionsanswered' => new external_value(PARAM_INT, 'Number of questions answered'),
            ]), 'Participants, by rank'),
        ]);
    }
}
