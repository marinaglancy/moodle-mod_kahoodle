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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use mod_kahoodle\constants;
use mod_kahoodle\local\game\progress;
use mod_kahoodle\questions;

/**
 * Behat step definitions for mod_kahoodle.
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_kahoodle extends behat_base {
    /**
     * Advances the round for the given kahoodle activity to the specified stage using the API.
     *
     * Starts from preparation and advances through each stage until the target
     * stage is reached. Throws an exception if the target stage is not found
     * before the round reaches the archived stage.
     *
     * @Given /^the kahoodle "(?P<activityname_string>(?:[^"]|\\")*)" round stage is "(?P<stage_string>(?:[^"]|\\")*)"$/
     * @param string $activityname The kahoodle activity name
     * @param string $stage The target stage signature (e.g. preparation, lobby, question-1, results-1, revision)
     */
    public function the_kahoodle_round_stage_is(string $activityname, string $stage): void {
        global $DB;

        $kahoodle = $DB->get_record('kahoodle', ['name' => $activityname], '*', MUST_EXIST);
        $round = questions::get_last_round($kahoodle->id);

        if ($round->get_current_stage()->get_stage_signature() === $stage) {
            // Already at the target stage, nothing to do.
            return;
        }

        // Start the game (preparation -> first stage).
        progress::start_game($round);

        // Advance stages until we reach the target.
        while ($round->get_current_stage()->get_stage_signature() !== $stage) {
            if ($round->get_current_stage()->get_stage_signature() === constants::STAGE_ARCHIVED) {
                throw new \Exception(
                    "Reached archived stage without finding target stage '$stage' for kahoodle '$activityname'"
                );
            }
            $currentsignature = $round->get_current_stage()->get_stage_signature();
            progress::advance_to_next_stage($round, $currentsignature);
        }
    }
}
