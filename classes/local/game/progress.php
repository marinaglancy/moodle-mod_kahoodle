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
        $round->set_current_stage($firststage);
        // No notification to participants because nobody is listening yet.
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
        $stage = new round_stage($round, constants::STAGE_ARCHIVED, null, 0);
        $round->set_current_stage($stage);
        realtime_channels::notify_all_participants_stage_changed($round);
    }

    /**
     * Advance to the next stage in the game flow
     *
     * @param round $round The round entity
     * @param string $currentstagesignature The current stage signature (to prevent double-clicking issues)
     * @return round_stage The next stage object
     */
    public static function advance_to_next_stage(round $round, string $currentstagesignature): round_stage {
        global $DB;

        // Validate current stage to avoid race conditions.
        $actualstage = $round->get_current_stage();
        if ($actualstage->get_stage_signature() !== $currentstagesignature) {
            // Stage has already advanced, return current stage.
            return $actualstage;
        }

        // Calculate next stage.
        $nextstage = $round->get_next_stage();

        if ($nextstage != null) {
            // Update round.
            $round->set_current_stage($nextstage);
            realtime_channels::notify_facilitators_stage_changed($round);
            realtime_channels::notify_all_participants_stage_changed($round);
        } else {
            $nextstage = $round->get_current_stage();
        }

        return $nextstage;
    }
}
