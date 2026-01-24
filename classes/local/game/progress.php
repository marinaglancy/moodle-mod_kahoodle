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
use mod_kahoodle\local\entities\round_stage;

/**
 * Game progress manager for handling stage transitions and content generation
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /**
     * Start the game by transitioning from preparation to lobby stage
     *
     * @param round $round The round entity
     */
    public static function start_game(round $round): void {
        global $DB;

        // Verify round is in preparation stage.
        if ($round->get_current_stage_name() !== constants::STAGE_PREPARATION) {
            // Race condition, game is already started.
            return;
        }

        $stages = $round->get_all_stages();
        $firststage = reset($stages);

        // Update round to the first stage (normally the lobby).
        $now = time();
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $round->get_id(),
            'currentstage' => $firststage->get_stage_name(),
            'currentquestion' => $firststage->get_question_number(),
            'stagestarttime' => $now,
            'timestarted' => $now,
        ]);
    }

    /**
     * Finish the game by transitioning to archived stage
     *
     * @param round $round The round entity
     */
    public static function finish_game(round $round): void {
        global $DB;

        // Verify round is in progress (not preparation or already archived).
        if (!$round->is_in_progress()) {
            return;
        }

        // Update round to archived stage.
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $round->get_id(),
            'currentstage' => constants::STAGE_ARCHIVED,
            'currentquestion' => 0,
            'timecompleted' => time(),
        ]);
    }

    /**
     * Advance to the next stage in the game flow
     *
     * @param round $round The round entity
     * @param string $currentstagename The current stage name (for validation)
     * @param int $currentquestion The current question number (for validation)
     * @return round_stage The next stage object
     */
    public static function advance_to_next_stage(round $round, string $currentstagename, int $currentquestion): round_stage {
        global $DB;

        // Validate current stage to avoid race conditions.
        $actualstage = $round->get_current_stage();
        if (
            $actualstage->get_stage_name() !== $currentstagename ||
            $actualstage->get_question_number() !== $currentquestion
        ) {
            // Stage has already advanced, return current stage.
            return $actualstage;
        }

        // Calculate next stage.
        $nextstage = $round->get_next_stage();

        if ($nextstage != null) {
            // Update round.
            $updatedata = [
                'id' => $round->get_id(),
                'currentstage' => $nextstage->get_stage_name(),
                'currentquestion' => $nextstage->get_question_number(),
                'stagestarttime' => time(),
            ];

            // Mark as completed if archived.
            if ($nextstage === constants::STAGE_ARCHIVED) {
                $updatedata['timecompleted'] = time();
            }

            $DB->update_record('kahoodle_rounds', (object)$updatedata);
        } else {
            $nextstage = $round->get_current_stage();
        }

        return $nextstage;
    }
}
