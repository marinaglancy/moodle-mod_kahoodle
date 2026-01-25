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

namespace mod_kahoodle\local\entities;

use mod_kahoodle\constants;
use mod_kahoodle\output\roundquestion;
use tool_brickfield\local\areas\core_course\fullname;

/**
 * Represents a single stage in a Kahoodle round
 *
 * A stage can be a non-question stage (lobby, leaders, revision) or a question stage
 * (preview, question, results) associated with a specific round_question.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class round_stage {
    /** @var round The parent round */
    protected round $round;
    /** @var string The stage constant (e.g., STAGE_LOBBY, STAGE_QUESTION) */
    protected string $stagename;
    /** @var round_question|null The associated round question (null for non-question stages) */
    protected ?round_question $roundquestion;
    /** @var int Duration in seconds */
    protected int $duration;

    /**
     * Constructor
     *
     * @param round $round The parent round
     * @param string $stagename The stage constant
     * @param round_question|null $roundquestion The associated round question (null for non-question stages)
     * @param int $duration Duration in seconds
     */
    public function __construct(round $round, string $stagename, ?round_question $roundquestion, int $duration) {
        $this->round = $round;
        $this->stagename = $stagename;
        $this->roundquestion = $roundquestion;
        $this->duration = $duration;
    }

    /**
     * Create an instance for a question stage (preview, question, results, leaders)
     *
     * @param round_question $roundquestion
     * @param string $stagename
     * @return round_stage
     */
    public static function create_from_round_question(
        round_question $roundquestion,
        string $stagename
    ): round_stage {
        return new self($roundquestion->get_round(), $stagename, $roundquestion, $roundquestion->get_stage_duration($stagename));
    }

    /**
     * Get the parent round
     *
     * @return round
     */
    public function get_round(): round {
        return $this->round;
    }

    /**
     * Get the stage constant
     *
     * @return string
     */
    public function get_stage_name(): string {
        return $this->stagename;
    }

    /**
     * Get the associated round question
     *
     * @return round_question|null
     */
    public function get_round_question(): ?round_question {
        return $this->roundquestion;
    }

    /**
     * Get the duration in seconds
     *
     * @return int
     */
    public function get_duration(): int {
        return $this->duration;
    }

    /**
     * Get the question number (1-based)
     *
     * @return int 0 for non-question stages
     */
    public function get_question_number(): int {
        return $this->roundquestion ? $this->roundquestion->get_data()->sortorder : 0;
    }

    /**
     * Check if this is a question-related stage
     *
     * @return bool
     */
    public function is_question_stage(): bool {
        return $this->roundquestion !== null;
    }

    /**
     * Check if this stage matches the given stage and question number
     *
     * @param string $stagename The stage constant
     * @param int $questionnumber The question number (1-based)
     * @return bool
     */
    public function matches(string $stagename, int $questionnumber): bool {
        return $this->stagename === $stagename && $this->get_question_number() === $questionnumber;
    }

    /**
     * Stage data for facilitators
     *
     * @return array  Stage data including template, templatedata, and duration
     */
    public function export_data_for_facilitators(): array {
        global $PAGE;

        $stage = $this;
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
                $data['template'] = 'mod_kahoodle/facilitator/lobby';
                $data['duration'] = (int)$kahoodle->lobbyduration;
                $data['templatedata'] = $this->get_lobby_template_data();
                break;

            case constants::STAGE_QUESTION_PREVIEW:
            case constants::STAGE_QUESTION:
            case constants::STAGE_QUESTION_RESULTS:
                $data = array_merge($data, $this->get_question_stage_data($output));
                break;

            case constants::STAGE_LEADERS:
                $data['template'] = 'mod_kahoodle/facilitator/leaders';
                $data['duration'] = constants::DEFAULT_LEADERS_DURATION;
                $data['templatedata'] = $this->get_leaders_template_data();
                break;

            case constants::STAGE_REVISION:
                // TODO: Implement revision stage.
                $data['template'] = 'mod_kahoodle/facilitator/revision';
                $data['duration'] = 0; // No auto-advance from revision.
                $data['templatedata'] = $this->get_leaders_template_data();
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
     * Stage data for participants
     *
     * For now, all stages show a simple "Please wait" template.
     *
     * @return array Stage data including template, templatedata, and duration
     */
    public function export_data_for_participants(): array {
        global $PAGE, $CFG;

        $round = $this->get_round();
        $kahoodle = $round->get_kahoodle();

        // Ensure PAGE is set up for rendering (needed when called from realtime callback).
        if (!$PAGE->has_set_url()) {
            $cm = $round->get_cm();
            $PAGE->set_url('/mod/kahoodle/view.php', ['id' => $cm->id]);
            $PAGE->set_context($round->get_context());
        }

        $data = [
            'stage' => $this->get_stage_name(),
            'currentquestion' => $this->get_question_number(),
            'totalquestions' => $round->get_questions_count(),
            'templatedata' => [
                'quiztitle' => $round->get_kahoodle_name(),
            ],
        ];
        $data['duration'] = 0; // No auto-advance for participants.
        $participant = $round->is_participant();

        if ($this->get_stage_name() === constants::STAGE_LOBBY) {
            // Game is over, no more content to show.
            $data['template'] = 'mod_kahoodle/participant/lobby';
            $data['templatedata'] += [
                'avatarurl' => $participant->get_avatar_url(100)->out(false),
                'displayname' => $participant->get_display_name(),
                'caneditavatar' => false, // TODO: Implement avatar editing.
            ];
            return $data;
        }

        // For now, all stages show the waiting template for participants.
        // TODO: Implement stage-specific templates for participants.
        $data['template'] = 'mod_kahoodle/participant/waiting';
        $data['templatedata'] += [];

        return $data;
    }

    /**
     * Get template data for lobby stage
     *
     * @return array Template data
     */
    protected function get_lobby_template_data(): array {
        global $CFG;

        $round = $this->get_round();
        $kahoodle = $round->get_kahoodle();

        // Get participants list.
        $participants = $round->get_all_participants();
        $participantcount = count($participants);

        $participantdata = [];
        foreach ($participants as $participant) {
            $participantdata[] = [
                'participantid' => $participant->get_id(),
                'displayname' => $participant->get_display_name(),
                'imageurl' => $participant->get_avatar_url(35)->out(false),
            ];
        }

        $url = (new \moodle_url('/mod/kahoodle/view.php', ['id' => $round->get_cm()->id]))->out(false);
        $qrcode = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($url);
        // TODO use core_qrcode class to generate QR code, save it in filestorage and serve from there.

        return [
            'quiztitle' => $kahoodle->name,
            'cancontrol' => true, // Teacher view always has control.
            'backgroundurl' => $CFG->wwwroot . '/mod/kahoodle/pix/classroom-bg.jpg',
            'participantcount' => $participantcount,
            'participants' => $participantdata,
            'qrcodeurl' => $qrcode,
            'joincode' => '', // TODO when join codes are implemented.
        ];
    }

    /**
     * Get template data for question stages (preview, question, results)
     *
     * @param \renderer_base $output The renderer
     * @return array Stage data including template and templatedata
     */
    protected function get_question_stage_data(
        \renderer_base $output
    ): array {
        // Create output class - use live mode (no mock results).
        // TODO: When actual results are implemented, pass false for mockresults.
        // For now, we still use mock results as a placeholder.
        $round = $this->get_round();
        $outputclass = new roundquestion($this->get_round_question(), $this->get_stage_name(), true);
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
     * @return array Template data
     */
    protected function get_leaders_template_data(): array {
        global $CFG;

        // TODO: Implement actual leaderboard data retrieval.
        // For now, return placeholder data.
        $round = $this->get_round();
        $kahoodle = $round->get_kahoodle();
        return [
            'quiztitle' => $kahoodle->name,
            'sortorder' => $this->get_question_number() ?: $round->get_questions_count(),
            'totalquestions' => $round->get_questions_count(),
            'cancontrol' => true,
            'isedit' => false,
            'backgroundurl' => $CFG->wwwroot . '/mod/kahoodle/pix/classroom-bg.jpg',
            // Placeholder for actual leaderboard.
            'leaders' => [],
        ];
    }
}
