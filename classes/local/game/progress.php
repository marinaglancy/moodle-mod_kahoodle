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
use mod_kahoodle\output\roundquestion;

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
     * @return array Stage data including template and duration
     */
    public static function start_game(round $round): array {
        global $DB;

        // Verify round is in preparation stage.
        if ($round->get_current_stage_name() !== constants::STAGE_PREPARATION) {
            // Race condition, game is already started.
            return self::get_stage_data($round->get_current_stage());
        }

        $stages = $round->get_all_stages();
        $firststage = reset($stages);

        // Update round to lobby stage.
        $now = time();
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $round->get_id(),
            'currentstage' => $firststage->get_stage_name(),
            'currentquestion' => $firststage->get_question_number(),
            'stagestarttime' => $now,
            'timestarted' => $now,
        ]);

        return self::get_stage_data($firststage);
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
     * @return array Stage data including template and duration
     */
    public static function advance_to_next_stage(round $round, string $currentstagename, int $currentquestion): array {
        global $DB;

        // Validate current stage to avoid race conditions.
        $actualstage = $round->get_current_stage();
        if (
            $actualstage->get_stage_name() !== $currentstagename ||
            $actualstage->get_question_number() !== $currentquestion
        ) {
            // Stage has already advanced, return current stage data.
            return self::get_stage_data($actualstage);
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

        return self::get_stage_data($nextstage);
    }

    /**
     * Get stage data for rendering
     *
     * @param round_stage $stage The stage object
     * @return array Stage data including template, templatedata, and duration
     */
    public static function get_stage_data(round_stage $stage): array {
        global $PAGE;

        $round = $stage->get_round();
        $kahoodle = $round->get_kahoodle();

        // Ensure PAGE is set up for rendering (needed when called from realtime callback).
        if (!$PAGE->has_set_url()) {
            $cm = $round->get_cm();
            $PAGE->set_url('/mod/kahoodle/view.php', ['id' => $cm->id]);
            $PAGE->set_context($round->get_context());
        }

        $output = $PAGE->get_renderer('mod_kahoodle');

        $data = [
            'stage' => $stage->get_stage_name(),
            'currentquestion' => $stage->get_question_number(),
            'totalquestions' => $round->get_questions_count(),
            'quiztitle' => $kahoodle->name,
        ];

        switch ($stage->get_stage_name()) {
            case constants::STAGE_LOBBY:
                $data['template'] = 'mod_kahoodle/stages/lobby';
                $data['duration'] = (int)$kahoodle->lobbyduration;
                $data['templatedata'] = self::get_lobby_template_data($stage);
                break;

            case constants::STAGE_QUESTION_PREVIEW:
            case constants::STAGE_QUESTION:
            case constants::STAGE_QUESTION_RESULTS:
                $data = array_merge($data, self::get_question_stage_data($stage, $output));
                break;

            case constants::STAGE_LEADERS:
                $data['template'] = 'mod_kahoodle/stages/leaders';
                $data['duration'] = constants::DEFAULT_LEADERS_DURATION;
                $data['templatedata'] = self::get_leaders_template_data($stage);
                break;

            case constants::STAGE_REVISION:
                // TODO: Implement revision stage.
                $data['template'] = 'mod_kahoodle/stages/leaders';
                $data['duration'] = 0; // No auto-advance from revision.
                $data['templatedata'] = self::get_leaders_template_data($stage);
                break;

            case constants::STAGE_ARCHIVED:
                // Game is over, no more content to show.
                $data['template'] = null;
                $data['duration'] = 0;
                $data['templatedata'] = [];
                break;

            default:
                throw new \moodle_exception('invalidstage', 'mod_kahoodle');
        }

        return $data;
    }

    /**
     * Get template data for lobby stage
     *
     * @param round_stage $stage The stage object
     * @return array Template data
     */
    protected static function get_lobby_template_data(round_stage $stage): array {
        global $CFG;

        $round = $stage->get_round();
        $kahoodle = $round->get_kahoodle();

        return [
            'quiztitle' => $kahoodle->name,
            'totalquestions' => $round->get_questions_count(),
            'participantcount' => self::get_participant_count($round),
            'cancontrol' => true, // Teacher view always has control.
            'backgroundurl' => $CFG->wwwroot . '/mod/kahoodle/pix/classroom-bg.jpg',
        ];
    }

    /**
     * Get template data for question stages (preview, question, results)
     *
     * @param round_stage $stage The stage object
     * @param \renderer_base $output The renderer
     * @return array Stage data including template and templatedata
     */
    protected static function get_question_stage_data(
        round_stage $stage,
        \renderer_base $output
    ): array {
        // Create output class - use live mode (no mock results).
        // TODO: When actual results are implemented, pass false for mockresults.
        // For now, we still use mock results as a placeholder.
        $round = $stage->get_round();
        $outputclass = new roundquestion($stage->get_round_question(), $stage->get_stage_name(), true);
        $templatedata = $outputclass->export_for_template($output);

        // Add control flag and total questions.
        $templatedata->cancontrol = true;
        $templatedata->isedit = false; // Live game, not edit mode.
        $templatedata->totalquestions = $round->get_questions_count();

        return [
            'template' => $templatedata->template,
            'duration' => $templatedata->duration,
            'templatedata' => (array)$templatedata,
        ];
    }

    /**
     * Get template data for leaders stage
     *
     * @param round_stage|null $stage The stage object (null if it is the final leaders stage)
     * @return array Template data
     */
    protected static function get_leaders_template_data(round_stage $stage): array {
        global $CFG;

        // TODO: Implement actual leaderboard data retrieval.
        // For now, return placeholder data.
        $round = $stage->get_round();
        $kahoodle = $round->get_kahoodle();
        return [
            'quiztitle' => $kahoodle->name,
            'currentquestion' => $stage->get_question_number() ?: $round->get_questions_count(),
            'totalquestions' => $round->get_questions_count(),
            'cancontrol' => true,
            'isedit' => false,
            'backgroundurl' => $CFG->wwwroot . '/mod/kahoodle/pix/classroom-bg.jpg',
            // Placeholder for actual leaderboard.
            'leaders' => [],
        ];
    }

    /**
     * Get the count of participants in a round
     *
     * @param round $round The round entity
     * @return int Participant count
     */
    protected static function get_participant_count(round $round): int {
        global $DB;
        return $DB->count_records('kahoodle_participants', ['roundid' => $round->get_id()]);
    }
}
