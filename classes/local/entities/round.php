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
use stdClass;

/**
 * Represents a question round in Kahoodle
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class round {
    /** @var stdClass The round record */
    protected stdClass $data;

    /**
     * Protected constructor
     *
     * @param stdClass $data The round record
     */
    protected function __construct(stdClass $data) {
        $this->data = $data;
    }

    /**
     * Create a round instance from a database record object
     *
     * @param stdClass $record The round record from database
     * @return self
     */
    public static function create_from_object(stdClass $record): self {
        return new self($record);
    }

    /**
     * Create a round instance from a round ID
     *
     * @param int $id The round ID
     * @return self
     */
    public static function create_from_id(int $id): self {
        global $DB;
        $record = $DB->get_record('kahoodle_rounds', ['id' => $id], '*', MUST_EXIST);
        return new self($record);
    }

    /**
     * Get the round ID
     *
     * @return int
     */
    public function get_id(): int {
        return $this->data->id;
    }

    /**
     * Get the Kahoodle activity ID
     *
     * @return int
     */
    public function get_kahoodleid(): int {
        return $this->data->kahoodleid;
    }

    /**
     * Check if the round is editable
     *
     * A round is editable if it's in preparation stage and hasn't been started yet.
     *
     * @return bool
     */
    public function is_editable(): bool {
        return $this->data->currentstage === constants::STAGE_PREPARATION;
    }

    /**
     * Check if the round is in progress
     *
     * A round is in progress if it's between lobby and revision stages (not preparation or archived).
     *
     * @return bool
     */
    public function is_in_progress(): bool {
        return $this->data->currentstage !== constants::STAGE_PREPARATION
            && $this->data->currentstage !== constants::STAGE_ARCHIVED;
    }

    /**
     * Get the current stage of the round
     *
     * @return string
     */
    public function get_current_stage_name(): string {
        return $this->data->currentstage;
    }

    /** @var stdClass|null Cached Kahoodle activity record */
    private ?stdClass $kahoodle = null;

    /**
     * Get the Kahoodle activity record
     *
     * @return stdClass
     */
    public function get_kahoodle(): stdClass {
        global $DB;
        if ($this->kahoodle !== null) {
            return $this->kahoodle;
        }
        $this->kahoodle = $DB->get_record('kahoodle', ['id' => $this->data->kahoodleid], '*', MUST_EXIST);
        return $this->kahoodle;
    }

    /**
     * Formatted name of the activity
     *
     * @return string
     */
    public function get_kahoodle_name(): string {
        return format_string($this->get_kahoodle()->name, true, ['context' => $this->get_context()]);
    }

    /** @var stdClass|null Cached course module record */
    private ?stdClass $cm = null;

    /**
     * Get the course module record
     *
     * @return stdClass
     */
    public function get_cm(): stdClass {
        if ($this->cm !== null) {
            return $this->cm;
        }
        $this->cm = get_coursemodule_from_instance('kahoodle', $this->data->kahoodleid, 0, false, MUST_EXIST);
        return $this->cm;
    }

    /**
     * Get the context module instance
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return \context_module::instance($this->get_cm()->id);
    }

    /** @var int|null Cached questions count */
    protected ?int $questionscount = null;

    /**
     * Get the number of questions in this round
     *
     * @return int
     */
    public function get_questions_count(): int {
        global $DB;
        if ($this->questionscount !== null) {
            return $this->questionscount;
        }
        $this->questionscount = $DB->count_records('kahoodle_round_questions', ['roundid' => $this->get_id()]);
        return $this->questionscount;
    }

    /** @var round_stage[]|null Cached stages */
    protected ?array $stagescache = null;

    /**
     * Get all stages for this round in order
     *
     * For preview mode, returns only question stages (preview, question, results) for each question.
     * For live game mode, includes lobby at the start, leaders after each question, and revision at the end.
     *
     * @param bool $ispreview Whether this is for preview mode (true) or live game (false)
     * @return round_stage[] Array of round_stage objects in order
     */
    public function get_all_stages(bool $ispreview = false): array {
        if ($this->stagescache !== null && !$ispreview) {
            return $this->stagescache;
        }

        $stages = [];
        $kahoodle = $this->get_kahoodle();
        $roundquestions = round_question::get_all_questions_for_round($this);

        if (!$ispreview) {
            // Live game starts with lobby.
            $stages[] = new round_stage(
                $this,
                constants::STAGE_LOBBY,
                null,
                (int)$kahoodle->lobbyduration
            );
        }

        // Question stages for each question.
        foreach ($roundquestions as $roundquestion) {
            // Question preview stage.
            $stage = round_stage::create_from_round_question($roundquestion, constants::STAGE_QUESTION_PREVIEW);
            if ($stage->get_duration() > 0) {
                $stages[] = $stage;
            }

            // Question stage. Must always be present.
            $stage = round_stage::create_from_round_question($roundquestion, constants::STAGE_QUESTION);
            $stages[] = $stage;

            // Question results stage.
            $stage = round_stage::create_from_round_question($roundquestion, constants::STAGE_QUESTION_RESULTS);
            if ($stage->get_duration() > 0) {
                $stages[] = $stage;
            }

            if (!$ispreview) {
                $stage = round_stage::create_from_round_question($roundquestion, constants::STAGE_LEADERS);
                if ($stage->get_duration() > 0) {
                    // Live game shows leaderboard after each question. Except for non-graded questions.
                    // No leaderboard after the last question.
                    $stages[] = $stage;
                }
            }
        }

        if (!$ispreview && count($roundquestions) > 0) {
            // Live game ends with revision stage.
            $stages[] = new round_stage(
                $this,
                constants::STAGE_REVISION,
                null,
                0 // No auto-advance from revision.
            );
        }

        if (!$ispreview) {
            $this->stagescache = $stages;
        }
        return $stages;
    }

    /**
     * Get the current stage object
     *
     * @return round_stage
     */
    public function get_current_stage(): round_stage {
        $stagename = $this->get_current_stage_name();
        $questionnumber = (int)$this->data->currentquestion;

        return $this->find_stage($stagename, $questionnumber);
    }

    /**
     * Find the next stage after the given stage and question number
     *
     * @return round_stage|null The next stage, or null if at the end (should transition to archived)
     */
    public function get_next_stage(): ?round_stage {
        $stages = $this->get_all_stages(false);
        $current = $this->get_current_stage();

        for ($i = 1; $i < count($stages); $i++) {
            if ($stages[$i - 1] === $current) {
                return $stages[$i];
            }
        }

        return null;
    }

    /**
     * Find the stage matching the given stage constant and question number
     *
     * @param string $stagename Stage constant
     * @param int $questionnumber Question number (1-based, 0 for non-question stages like lobby)
     * @return round_stage|null The matching stage, or null if not found
     */
    protected function find_stage(string $stagename, int $questionnumber): ?round_stage {
        $stages = $this->get_all_stages(false);

        foreach ($stages as $stage) {
            if ($stage->matches($stagename, $questionnumber)) {
                return $stage;
            }
        }

        // For some reason we can not find the current stage. There may be some race condition and we refer to
        // non-existing question stage. Try to find the closest one.
        if ($stagename === constants::STAGE_LOBBY) {
            return $stages[0];
        }

        if ($questionnumber > 0 && $questionnumber > $this->get_questions_count()) {
            // Last stage is revision before archived.
            return $stages[count($stages) - 1];
        }

        if ($questionnumber > 0 && $stagename === constants::STAGE_QUESTION_PREVIEW) {
            // Preview does not exist, go to the question itself.
            return $this->find_stage(constants::STAGE_QUESTION, $questionnumber);
        }

        if ($questionnumber > 0 && $stagename === constants::STAGE_QUESTION_RESULTS) {
            // Question results stage does not exist, try next stage (leaders).
            return $this->find_stage(constants::STAGE_LEADERS, $questionnumber);
        }

        if ($questionnumber > 0 && $stagename === constants::STAGE_LEADERS) {
            // Question leaders stage does not exist, try next question.
            return $this->find_stage(constants::STAGE_QUESTION_PREVIEW, $questionnumber + 1);
        }

        // We exhaused all options, return the last stage as fallback.
        return $stages[count($stages) - 1];
    }

    /** @var array|null Cached participants array */
    protected ?array $participantscache = null;

    /**
     * Get all participants for this round
     *
     * @return array Array of participant objects with id, displayname, avatar
     */
    public function get_all_participants(): array {
        global $DB;
        if ($this->participantscache !== null) {
            return $this->participantscache;
        }
        $this->participantscache = $DB->get_records(
            'kahoodle_participants',
            ['roundid' => $this->get_id()],
            'timecreated ASC',
            'id, displayname, avatar'
        );
        return $this->participantscache;
    }

    /** @var stdClass|false|null Cached participant record for current user (null = not checked, false = not participant) */
    protected $currentuserparticipant = null;

    /**
     * Check if the current user is a participant in this round
     *
     * Returns the participant record if the user has the participate capability and
     * has joined this round. Returns null if user cannot participate or hasn't joined.
     * The result is cached for subsequent calls.
     *
     * @return stdClass|null The participant record or null
     */
    public function is_participant(): ?stdClass {
        global $DB, $USER;

        if ($this->currentuserparticipant !== null) {
            return $this->currentuserparticipant ?: null;
        }

        // Check capability first.
        if (!has_capability('mod/kahoodle:participate', $this->get_context())) {
            $this->currentuserparticipant = false;
            return null;
        }

        // Check if user has joined this round.
        $participant = $DB->get_record('kahoodle_participants', [
            'roundid' => $this->get_id(),
            'userid' => $USER->id,
        ]);

        $this->currentuserparticipant = $participant ?: false;
        return $this->currentuserparticipant ?: null;
    }

    /**
     * Check if the current user is a facilitator for this round
     *
     * Returns true if the user has the facilitate capability and is NOT currently
     * participating in the round. Once a user joins as participant, they are no
     * longer considered a facilitator until they leave the round.
     *
     * @return bool
     */
    public function is_facilitator(): bool {
        // If user is a participant, they are not a facilitator (participation takes precedence).
        if ($this->is_participant() !== null) {
            return false;
        }

        return has_capability('mod/kahoodle:facilitate', $this->get_context());
    }

    /**
     * To be executed when we know that list of participants changed
     *
     * @return void
     */
    public function clear_participant_cache(): void {
        $this->currentuserparticipant = null;
        $this->participantscache = null;
    }

    /**
     * To be executed when we know that question data changed
     *
     * @return void
     */
    public function clear_questions_cache(): void {
        $this->questionscount = null;
        $this->stagescache = null;
    }

    /**
     * To be executed after updating the round (i.e. advancing stages)
     *
     * @return void
     */
    public function refetch_data(): void {
        global $DB;
        $this->data = $DB->get_record('kahoodle_rounds', ['id' => $this->data->id], '*', MUST_EXIST);
    }
}
