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
        if ($this->questionscount !== null) {
            return $this->questionscount;
        }
        $this->get_all_stages(); // Ensures questionscount is populated.
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
        static $showinpreview = function (round_stage $stage) {
            return in_array($stage->get_stage_name(), [
                constants::STAGE_QUESTION_PREVIEW,
                constants::STAGE_QUESTION,
                constants::STAGE_QUESTION_RESULTS,
            ], true);
        };

        if ($this->stagescache !== null) {
            if ($ispreview) {
                return array_values(array_filter($this->stagescache, $showinpreview));
            }
            return $this->stagescache;
        }

        $stages = [];
        $roundquestions = round_question::get_all_questions_for_round($this);
        $this->questionscount = count($roundquestions);

        // Live game starts with lobby.
        $stages[] = new round_stage(
            $this,
            constants::STAGE_LOBBY,
            null,
            (int)$this->get_kahoodle()->lobbyduration
        );

        // Question stages for each question.
        foreach ($roundquestions as $i => $roundquestion) {
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

            if ($i < $this->questionscount - 1) {
                // No leaderboard stage after the last question.
                $stage = round_stage::create_from_round_question($roundquestion, constants::STAGE_LEADERS);
                if ($stage->get_duration() > 0) {
                    // Live game shows leaderboard after each question. Except for non-graded questions.
                    // No leaderboard after the last question.
                    $stages[] = $stage;
                }
            }
        }

        if ($this->questionscount > 0) {
            // Live game ends with revision stage.
            $stages[] = new round_stage(
                $this,
                constants::STAGE_REVISION,
                null,
                0 // No auto-advance from revision.
            );
        }

        $this->stagescache = $stages;

        if ($ispreview) {
            return array_values(array_filter($this->stagescache, $showinpreview));
        }
        return $this->stagescache;
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
     * @return participant[] Array of participant objects, indexed by participant ID
     */
    public function get_all_participants(): array {
        global $DB;
        if ($this->participantscache !== null) {
            return $this->participantscache;
        }

        $this->participantscache = participant::load_round_participants($this);
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
     * @return participant|null The participant record or null
     */
    public function is_participant(): ?participant {
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
        $result = participant::load_round_participants($this, ' AND p.userid = :userid', ['userid' => $USER->id]);

        $this->currentuserparticipant = !empty($result) ? reset($result) : false;
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
        $this->questionrankings = [];
    }

    /**
     * To be executed when we know that question data changed
     *
     * @return void
     */
    public function clear_questions_cache(): void {
        $this->questionscount = null;
        $this->stagescache = null;
        $this->questionrankings = [];
    }

    /**
     * Set current stage
     *
     * @param round_stage $newstage
     * @return void
     */
    public function set_current_stage(round_stage $newstage): void {
        global $DB;
        $updaterecord = [
            'currentstage' => $newstage->get_stage_name(),
            'currentquestion' => $newstage->get_question_number(),
        ];

        if ($this->get_current_stage_name() === constants::STAGE_PREPARATION) {
            $updaterecord['timestarted'] = time();
        }

        if ($newstage->get_stage_name() === constants::STAGE_ARCHIVED) {
            $updaterecord['timecompleted'] = time();
        } else {
            $updaterecord['stagestarttime'] = time();
        }

        $DB->update_record('kahoodle_rounds', ['id' => $this->get_id()] + $updaterecord);

        foreach ($updaterecord as $key => $value) {
            $this->data->$key = $value;
        }

        $this->questionrankings = [];
    }

    /**
     * Get rankings for all participants in this round (excluding the current question if it is still in progress)
     *
     * Calculates rankings based on total score (descending), with ties handled
     * by join time (earlier joiners ranked higher) and participant ID as final tiebreaker.
     *
     * Returns an associative array keyed by participant ID, where each entry contains:
     * - minrank: The best possible rank (1 = first place)
     * - maxrank: The worst possible rank (equals minrank if no tie)
     * - tiewith: Array of participant IDs sharing the same score
     * - prevscore: The score of the rank immediately above (null for 1st place)
     * - withprevrank: Array of participant IDs at the previous rank
     *
     * Example: If participants A(100pts), B(80pts), C(80pts), D(50pts):
     * - A: minrank=1, maxrank=1, tiewith=[]
     * - B: minrank=2, maxrank=3, tiewith=[C], prevscore=100
     * - C: minrank=2, maxrank=3, tiewith=[B], prevscore=100
     * - D: minrank=4, maxrank=4, tiewith=[], prevscore=80
     *
     * @return rank[] Array of rank objects indexed by participant ID
     */
    public function get_rankings(): array {
        return $this->get_question_rankings($this->get_last_answered_question_number());
    }

    /**
     * Get randkings of all participants in this round after the previous question
     *
     * Can be used to compare how rankings changed after the last question.
     *
     * @return rank[] Array of rank objects indexed by participant ID
     */
    public function get_prev_question_rankings(): array {
        return $this->get_question_rankings($this->get_last_answered_question_number() - 1);
    }

    /**
     * Get sortorder of the last question for which the STAGE_QUESTION is finished
     *
     * @return int
     */
    public function get_last_answered_question_number(): int {
        $currentstage = $this->get_current_stage_name();
        $currentquestion = (int)$this->data->currentquestion;

        if (
            $currentstage === constants::STAGE_QUESTION_RESULTS ||
            $currentstage === constants::STAGE_LEADERS
        ) {
            return $currentquestion;
        }
        if (
            $currentstage === constants::STAGE_REVISION ||
            $currentstage === constants::STAGE_ARCHIVED
        ) {
            return $this->get_questions_count();
        }
        if (
            $currentstage === constants::STAGE_QUESTION_PREVIEW ||
            $currentstage === constants::STAGE_QUESTION
        ) {
            return $currentquestion - 1;
        }
        return 0;
    }

    /**
     * Get rankings for all participants in this round
     *
     * Calculates rankings based on total score (descending), with ties handled
     * by join time (earlier joiners ranked higher) and participant ID as final tiebreaker.
     *
     * Returns an associative array keyed by participant ID, where each entry contains:
     * - minrank: The best possible rank (1 = first place)
     * - maxrank: The worst possible rank (equals minrank if no tie)
     * - tiewith: Array of participant IDs sharing the same score
     * - prevscore: The score of the rank immediately above (null for 1st place)
     * - withprevrank: Array of participant IDs at the previous rank
     *
     * Example: If participants A(100pts), B(80pts), C(80pts), D(50pts):
     * - A: minrank=1, maxrank=1, tiewith=[]
     * - B: minrank=2, maxrank=3, tiewith=[C], prevscore=100
     * - C: minrank=2, maxrank=3, tiewith=[B], prevscore=100
     * - D: minrank=4, maxrank=4, tiewith=[], prevscore=80
     *
     * @param int[] $scores Participants scores indexed by participant id
     * @return rank[] Array of rank objects indexed by participant ID
     */
    public function get_rankings_int(array $scores): array {
        $participants = $this->get_all_participants();

        // Ensure scores are available for all participants.
        if ($scores !== null) {
            foreach ($participants as $participant) {
                if (!isset($scores[$participant->get_id()])) {
                    $scores[$participant->get_id()] = 0;
                }
            }
        }

        $comparerank = function (participant $one, participant $two) use ($scores) {
            // Sort by total score desc, by id asc to have consistent order.
            return $scores[$two->get_id()] <=> $scores[$one->get_id()] ?:
                $one->get_id() <=> $two->get_id();
        };

        uasort($participants, $comparerank);

        $groupbyscore = [];
        foreach ($participants as $participant) {
            $score = $scores[$participant->get_id()];
            if (!isset($groupbyscore[$score])) {
                $groupbyscore[$score] = [];
            }
            $groupbyscore[$score][] = $participant;
        }

        $rankings = [];
        $lastrank = 0;
        $prevscore = null;
        foreach ($groupbyscore as $score => $participantswithscore) {
            $minrank = $lastrank + 1;
            $maxrank = $lastrank + count($participantswithscore);
            foreach ($participantswithscore as $participant) {
                $rankings[$participant->get_id()] =
                    new rank(
                        $participant,
                        $score,
                        $minrank,
                        $maxrank,
                        array_filter($participantswithscore, fn($p) => $p !== $participant),
                        $prevscore,
                        $prevscore > 0 ? array_filter(
                            $groupbyscore[$prevscore] ?? [],
                            fn($p) => $p !== $participant
                        ) : []
                    );
            }
            $lastrank = $maxrank;
            $prevscore = $score;
        }

        return $rankings;
    }

    /** @var array cache of question rankings */
    protected array $questionrankings = [];

    /**
     * Get rankings up to the question with the given question number
     *
     * @param int $questionnumber
     * @return rank[] Array of rank objects indexed by participant ID
     */
    protected function get_question_rankings(int $questionnumber): array {
        global $DB;
        if ($questionnumber < 1) {
            return [];
        }
        if (isset($this->questionrankings[$questionnumber])) {
            return $this->questionrankings[$questionnumber];
        }

        $scores = $DB->get_records_sql_menu('
            SELECT p.id, SUM(r.points) AS totalscore
            FROM {kahoodle_participants} p
            JOIN {kahoodle_responses} r ON r.participantid = p.id
            JOIN {kahoodle_round_questions} rq ON rq.id = r.roundquestionid
            WHERE p.roundid = :roundid AND rq.sortorder <= :questionnumber
            GROUP BY p.id
        ', [
            'roundid' => $this->get_id(),
            'questionnumber' => $questionnumber,
        ]);

        $this->questionrankings[$questionnumber] = $this->get_rankings_int($scores);
        return $this->questionrankings[$questionnumber];
    }

    /**
     * Get top participants (leaders) for this round
     *
     * @param int $maxnumber
     * @return rank[]
     */
    public function get_leaders(int $maxnumber = 5): array {
        $rankings = $this->get_rankings();
        $rankingsprev = $this->get_prev_question_rankings();
        $leaders = count($rankings) > $maxnumber ? array_slice($rankings, 0, $maxnumber, true) : $rankings;
        foreach ($leaders as $participantid => $rank) {
            $prevrank = $rankingsprev[$participantid] ?? null;
            $leaders[$participantid]->prevquestionrank = $prevrank;
        }
        return $leaders;
    }
}
