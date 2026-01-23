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
use mod_kahoodle\local\entities\round_question;
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
        $currentstage = $DB->get_field('kahoodle_rounds', 'currentstage', ['id' => $round->get_id()]);
        if ($currentstage !== constants::STAGE_PREPARATION) {
            throw new \moodle_exception('invalidstage', 'mod_kahoodle');
        }

        // Update round to lobby stage.
        $now = time();
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $round->get_id(),
            'currentstage' => constants::STAGE_LOBBY,
            'currentquestion' => 0,
            'stagestarttime' => $now,
            'timestarted' => $now,
        ]);

        return self::get_stage_data($round, constants::STAGE_LOBBY, 0);
    }

    /**
     * Finish the game by transitioning to archived stage
     *
     * @param round $round The round entity
     */
    public static function finish_game(round $round): void {
        global $DB;

        // Verify round is in progress (not preparation or already archived).
        $currentstage = $DB->get_field('kahoodle_rounds', 'currentstage', ['id' => $round->get_id()]);
        if ($currentstage === constants::STAGE_PREPARATION || $currentstage === constants::STAGE_ARCHIVED) {
            throw new \moodle_exception('invalidstage', 'mod_kahoodle');
        }

        // Update round to archived stage.
        $DB->update_record('kahoodle_rounds', (object)[
            'id' => $round->get_id(),
            'currentstage' => constants::STAGE_ARCHIVED,
            'timecompleted' => time(),
        ]);
    }

    /**
     * Advance to the next stage in the game flow
     *
     * @param round $round The round entity
     * @return array Stage data including template and duration
     */
    public static function advance_to_next_stage(round $round): array {
        global $DB;

        // Get current state.
        $roundrecord = $DB->get_record('kahoodle_rounds', ['id' => $round->get_id()], '*', MUST_EXIST);
        $currentstage = $roundrecord->currentstage;
        $currentquestion = (int)$roundrecord->currentquestion;
        $totalquestions = $round->get_questions_count();

        // Calculate next stage.
        [$nextstage, $nextquestion] = self::calculate_next_stage($currentstage, $currentquestion, $totalquestions);

        // Update round.
        $updatedata = [
            'id' => $round->get_id(),
            'currentstage' => $nextstage,
            'currentquestion' => $nextquestion,
            'stagestarttime' => time(),
        ];

        // Mark as completed if archived.
        if ($nextstage === constants::STAGE_ARCHIVED) {
            $updatedata['timecompleted'] = time();
        }

        $DB->update_record('kahoodle_rounds', (object)$updatedata);

        return self::get_stage_data($round, $nextstage, $nextquestion);
    }

    /**
     * Get stage data for rendering
     *
     * @param round $round The round entity
     * @param string $stage The stage name
     * @param int $currentquestion Current question number (1-based for questions, 0 for non-question stages)
     * @return array Stage data including template, templatedata, and duration
     */
    public static function get_stage_data(round $round, string $stage, int $currentquestion): array {
        global $PAGE;

        $kahoodle = $round->get_kahoodle();

        // Ensure PAGE is set up for rendering (needed when called from realtime callback).
        if (!$PAGE->has_set_url()) {
            $cm = $round->get_cm();
            $PAGE->set_url('/mod/kahoodle/view.php', ['id' => $cm->id]);
            $PAGE->set_context($round->get_context());
        }

        $output = $PAGE->get_renderer('mod_kahoodle');

        $data = [
            'stage' => $stage,
            'currentquestion' => $currentquestion,
            'totalquestions' => $round->get_questions_count(),
            'quiztitle' => $kahoodle->name,
        ];

        switch ($stage) {
            case constants::STAGE_LOBBY:
                $data['template'] = 'mod_kahoodle/stages/lobby';
                $data['duration'] = (int)$kahoodle->lobbyduration;
                $data['templatedata'] = self::get_lobby_template_data($round, $kahoodle);
                break;

            case constants::STAGE_QUESTION_PREVIEW:
            case constants::STAGE_QUESTION:
            case constants::STAGE_QUESTION_RESULTS:
                $data = array_merge($data, self::get_question_stage_data($round, $stage, $currentquestion, $output));
                break;

            case constants::STAGE_LEADERS:
                $data['template'] = 'mod_kahoodle/stages/leaders';
                $data['duration'] = constants::DEFAULT_LEADERS_DURATION;
                $data['templatedata'] = self::get_leaders_template_data($round, $kahoodle, $currentquestion);
                break;

            case constants::STAGE_REVISION:
                // TODO: Implement revision stage.
                $data['template'] = 'mod_kahoodle/stages/leaders';
                $data['duration'] = 0; // No auto-advance from revision.
                $data['templatedata'] = self::get_leaders_template_data($round, $kahoodle, $currentquestion);
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
     * Calculate the next stage based on current state
     *
     * @param string $currentstage Current stage
     * @param int $currentquestion Current question number (1-based)
     * @param int $totalquestions Total number of questions
     * @return array [next_stage, next_question_number]
     */
    protected static function calculate_next_stage(string $currentstage, int $currentquestion, int $totalquestions): array {
        switch ($currentstage) {
            case constants::STAGE_LOBBY:
                // After lobby, go to first question preview.
                return [constants::STAGE_QUESTION_PREVIEW, 1];

            case constants::STAGE_QUESTION_PREVIEW:
                // After preview, go to question.
                return [constants::STAGE_QUESTION, $currentquestion];

            case constants::STAGE_QUESTION:
                // After question, go to results.
                return [constants::STAGE_QUESTION_RESULTS, $currentquestion];

            case constants::STAGE_QUESTION_RESULTS:
                // After results, go to leaders.
                return [constants::STAGE_LEADERS, $currentquestion];

            case constants::STAGE_LEADERS:
                // After leaders, either next question or revision.
                if ($currentquestion < $totalquestions) {
                    return [constants::STAGE_QUESTION_PREVIEW, $currentquestion + 1];
                } else {
                    return [constants::STAGE_REVISION, $currentquestion];
                }

            case constants::STAGE_REVISION:
                // After revision, archive.
                return [constants::STAGE_ARCHIVED, $currentquestion];

            default:
                throw new \moodle_exception('invalidstage', 'mod_kahoodle');
        }
    }

    /**
     * Get template data for lobby stage
     *
     * @param round $round The round entity
     * @param \stdClass $kahoodle The kahoodle record
     * @return array Template data
     */
    protected static function get_lobby_template_data(round $round, \stdClass $kahoodle): array {
        global $CFG;

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
     * @param round $round The round entity
     * @param string $stage The stage name
     * @param int $currentquestion Current question number (1-based)
     * @param \renderer_base $output The renderer
     * @return array Stage data including template and templatedata
     */
    protected static function get_question_stage_data(
        round $round,
        string $stage,
        int $currentquestion,
        \renderer_base $output
    ): array {
        // Map stage constant to roundquestion stage name.
        $stagemap = [
            constants::STAGE_QUESTION_PREVIEW => 'preview',
            constants::STAGE_QUESTION => 'question',
            constants::STAGE_QUESTION_RESULTS => 'results',
        ];
        $questionstage = $stagemap[$stage];

        // Get the round question for this position.
        $roundquestion = round_question::create_from_round_and_sortorder($round, $currentquestion);

        // Create output class - use live mode (no mock results).
        // TODO: When actual results are implemented, pass false for mockresults.
        // For now, we still use mock results as a placeholder.
        $outputclass = new roundquestion($roundquestion, $questionstage, true);
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
     * @param round $round The round entity
     * @param \stdClass $kahoodle The kahoodle record
     * @param int $currentquestion Current question number
     * @return array Template data
     */
    protected static function get_leaders_template_data(round $round, \stdClass $kahoodle, int $currentquestion): array {
        global $CFG;

        // TODO: Implement actual leaderboard data retrieval.
        // For now, return placeholder data.
        return [
            'quiztitle' => $kahoodle->name,
            'currentquestion' => $currentquestion,
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
