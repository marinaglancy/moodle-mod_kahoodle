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
use mod_kahoodle\local\game\instance;

/**
 * Implementation of web service mod_kahoodle_get_rounds
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_rounds extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_get_rounds
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Kahoodle activity'),
        ]);
    }

    /**
     * The rounds of a Kahoodle activity with their totals, as the results page shows them
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB;
        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        [, $context, $kahoodle] = helper::validate_cm($cmid);
        require_capability('mod/kahoodle:viewresults', $context);

        $totals = $DB->get_records_sql(
            "SELECT p.roundid, COUNT(*) AS participantcount, AVG(p.totalscore) AS averagescore, MAX(p.totalscore) AS maxscore
               FROM {kahoodle_participants} p
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = ?
           GROUP BY p.roundid",
            [$kahoodle->id]
        );
        $rounds = [];
        foreach (instance::get_all_rounds($kahoodle->id) as $round) {
            $stage = $round->get_current_stage_name();
            $total = $totals[$round->get_id()] ?? null;
            $rounds[] = [
                'roundid' => $round->get_id(),
                'name' => $round->get_display_name(),
                'status' => match ($stage) {
                    constants::STAGE_PREPARATION => 'preparation',
                    constants::STAGE_REVISION => 'revision',
                    constants::STAGE_ARCHIVED => 'completed',
                    default => 'inprogress',
                },
                'timestarted' => (int)$round->get_timestarted(),
                'timecompleted' => (int)$round->get_timecompleted(),
                'questioncount' => $round->get_questions_count(),
                'participantcount' => (int)($total->participantcount ?? 0),
                'averagescore' => (int)round((float)($total->averagescore ?? 0)),
                'maxscore' => (int)($total->maxscore ?? 0),
            ];
        }
        return ['rounds' => $rounds];
    }

    /**
     * Describe the return structure for mod_kahoodle_get_rounds
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rounds' => new external_multiple_structure(new external_single_structure([
                'roundid' => new external_value(PARAM_INT, 'Round id'),
                'name' => new external_value(PARAM_TEXT, 'Round name'),
                'status' => new external_value(PARAM_ALPHA,
                    'preparation (not started, questions can be changed), inprogress, revision (finished, ' .
                    'participants are reviewing their answers) or completed'),
                'timestarted' => new external_value(PARAM_INT, 'When the lobby opened, 0 if not started'),
                'timecompleted' => new external_value(PARAM_INT, 'When the round finished, 0 if not finished'),
                'questioncount' => new external_value(PARAM_INT, 'Number of questions'),
                'participantcount' => new external_value(PARAM_INT, 'Number of participants'),
                'averagescore' => new external_value(PARAM_INT, 'Average total score of the participants'),
                'maxscore' => new external_value(PARAM_INT, 'Highest total score'),
            ]), 'Rounds, the one in preparation first, then the newest first'),
        ]);
    }
}
