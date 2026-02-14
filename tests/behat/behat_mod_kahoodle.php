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
use mod_kahoodle\local\game\participants;
use mod_kahoodle\local\game\progress;
use mod_kahoodle\local\game\realtime_channels;
use mod_kahoodle\local\game\questions;

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
     * Return the list of exact named selectors for this plugin.
     *
     * @return behat_component_named_selector[]
     */
    public static function get_exact_named_selectors(): array {
        return [
            new behat_component_named_selector('round result', [
                "//div[contains(@class,'mod-kahoodle-results')]" .
                "//div[contains(@class,'card') and .//div[contains(@class,'card-header')][contains(., %locator%)]]",
            ]),
        ];
    }

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

        if ($round->get_current_stage_name() === constants::STAGE_PREPARATION) {
            if ($stage === constants::STAGE_PREPARATION) {
                return;
            }
            // Start the game (preparation -> first stage).
            progress::start_game($round);
        }

        // Advance stages until we reach the target.
        while ($round->get_current_stage()->get_stage_signature() !== $stage) {
            if ($round->get_current_stage()->get_stage_signature() === constants::STAGE_ARCHIVED) {
                throw new \Exception(
                    "Reached archived stage without finding target stage '$stage' for kahoodle '$activityname'"
                );
            }
            sleep(1); // Small delay to ensure stage change is processed before next check.
            $currentsignature = $round->get_current_stage()->get_stage_signature();
            progress::advance_to_next_stage($round, $currentsignature);
        }
    }

    /**
     * Reveals participant ranks for the given kahoodle activity during the revision stage.
     *
     * Simulates the facilitator's podium animation by sending the rank reveal
     * notification. The rank parameter matches the values used by the animation:
     * 'rank3', 'rank2', 'rank1' for individual podium positions, or 'all' for everyone.
     *
     * @When /^the kahoodle "(?P<activityname_string>(?:[^"]|\\")*)" rank "(?P<rank_string>(?:[^"]|\\")*)" is revealed$/
     * @param string $activityname The kahoodle activity name
     * @param string $rank Which rank to reveal (rank1, rank2, rank3, or all)
     */
    public function the_kahoodle_rank_is_revealed(string $activityname, string $rank): void {
        global $DB;

        $kahoodle = $DB->get_record('kahoodle', ['name' => $activityname], '*', MUST_EXIST);
        $round = questions::get_last_round($kahoodle->id);

        realtime_channels::notify_participants_rank_revealed($round, $rank);
    }

    /**
     * Joins a user as a participant in the given kahoodle activity's current round.
     *
     * Switches the global $USER to the specified user, calls participants::join_round(),
     * then restores the original user. This triggers the realtime notification so the
     * facilitator overlay updates with the new participant.
     *
     * @When /^"(?P<username_string>(?:[^"]|\\")*)" joins the kahoodle "(?P<activityname_string>(?:[^"]|\\")*)"$/
     * @param string $username The username of the user to join
     * @param string $activityname The kahoodle activity name
     */
    public function user_joins_the_kahoodle(string $username, string $activityname): void {
        global $DB, $USER;

        $kahoodle = $DB->get_record('kahoodle', ['name' => $activityname], '*', MUST_EXIST);
        $round = questions::get_last_round($kahoodle->id);

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $originaluser = $USER;
        $USER = $user;

        participants::join_round($round);

        $USER = $originaluser;
    }

    /**
     * Creates a new round for the given kahoodle activity by duplicating the last round.
     *
     * @Given /^a new round is prepared for the kahoodle "(?P<activityname_string>(?:[^"]|\\")*)"$/
     * @param string $activityname The kahoodle activity name
     */
    public function a_new_round_is_prepared(string $activityname): void {
        global $DB;

        $kahoodle = $DB->get_record('kahoodle', ['name' => $activityname], '*', MUST_EXIST);
        $round = questions::get_last_round($kahoodle->id);
        $round->duplicate();
    }
}
