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

use cm_info;
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
     * @param stdClass|null $kahoodle Optional Kahoodle activity record, if known
     * @param cm_info|null $cm Optional course module, if known
     * @return self
     */
    public static function create_from_object(stdClass $record, ?stdClass $kahoodle = null, ?cm_info $cm = null): self {
        $round = new self($record);
        if ($kahoodle !== null) {
            $round->kahoodle = $kahoodle;
        }
        if ($cm !== null) {
            $round->cm = $cm;
        }
        return $round;
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
     * Get the display name of the round
     *
     * @return string
     */
    public function get_display_name(): string {
        return format_string($this->data->name, true, ['context' => $this->get_context()]);
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
     * Get the timestamp when the lobby opened (round started)
     *
     * @return int|null Timestamp or null if not started
     */
    public function get_timestarted(): ?int {
        return $this->data->timestarted ? (int)$this->data->timestarted : null;
    }

    /**
     * Get the timestamp when the round was completed
     *
     * @return int|null Timestamp or null if not completed
     */
    public function get_timecompleted(): ?int {
        return $this->data->timecompleted ? (int)$this->data->timecompleted : null;
    }

    /**
     * Get the total duration of the round
     *
     * @return int|null Duration in seconds, or null if round not completed
     */
    public function get_duration(): ?int {
        $timestarted = $this->get_timestarted();
        $timecompleted = $this->get_timecompleted();
        if ($timestarted && $timecompleted) {
            return $timecompleted - $timestarted;
        }
        return null;
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

    /**
     * Time elapsed since the current stage started
     *
     * @return int|null
     */
    public function get_current_stage_elapsed_time(): ?int {
        if ($this->data->stagestarttime === null) {
            return null;
        }
        return time() - (int)$this->data->stagestarttime;
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

    /** @var cm_info|null Cached course module */
    private ?cm_info $cm = null;

    /**
     * Get the course module
     *
     * @return cm_info
     */
    public function get_cm(): cm_info {
        if ($this->cm !== null) {
            return $this->cm;
        }
        [, $this->cm] = get_course_and_cm_from_instance($this->data->kahoodleid, 'kahoodle');
        return $this->cm;
    }

    /**
     * Get the URL to view this round
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        $cm = $this->get_cm();
        return new \moodle_url('/mod/kahoodle/view.php', ['id' => $cm->id]);
    }

    /**
     * Get the context module instance
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return \context_module::instance($this->get_cm()->id);
    }

    /**
     * In most cases we can use the page context and save on DB queries
     *
     * @return \context_module
     */
    public function guess_context(): \context_module {
        global $PAGE;
        if (
            $PAGE->context->contextlevel == CONTEXT_MODULE &&
                $PAGE->cm &&
                $PAGE->activityrecord->id == $this->data->kahoodleid &&
                $PAGE->cm->modname == 'kahoodle'
        ) {
            return $PAGE->context;
        } else {
            return $this->get_context();
        }
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
        $showinpreview = function (round_stage $stage) {
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
                constants::DEFAULT_REVISION_DURATION
            );
        }

        $stages[] = new round_stage(
            $this,
            constants::STAGE_ARCHIVED,
            null,
            0
        );

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

    /** @var int|null Cached count of files in allavatars area */
    protected ?int $allavatarscount = null;

    /**
     * Get the number of avatar images in the admin-uploaded pool.
     *
     * Cached on the round instance so it's only queried once per request,
     * even when called in a loop across many participants.
     *
     * @return int
     */
    public function get_allavatars_count(): int {
        if ($this->allavatarscount !== null) {
            return $this->allavatarscount;
        }
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $files = $fs->get_area_files(
            $syscontext->id, 'mod_kahoodle', 'allavatars', 0,
            'filepath, filename', false
        );
        // Only count web image files, ignoring any non-image files in the area.
        $this->allavatarscount = count(array_filter($files, function (\stored_file $file) {
            return file_mimetype_in_typegroup($file->get_mimetype(), ['web_image']);
        }));
        return $this->allavatarscount;
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

    /**
     * Get the count of participants in this round
     *
     * @return int
     */
    public function get_participants_count(): int {
        global $DB;
        if ($this->participantscache !== null) {
            return count($this->participantscache);
        }
        return $DB->count_records('kahoodle_participants', ['roundid' => $this->get_id()]);
    }

    /**
     * Get participant by id (if exists)
     *
     * @param int $participantid
     * @return participant|null
     */
    public function get_participant_by_id(int $participantid): ?participant {
        $participants = $this->get_all_participants();
        return $participants[$participantid] ?? null;
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
        $oldstage = $this->get_current_stage();
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

        // Trigger round updated event.
        $event = \mod_kahoodle\event\round_updated::create([
            'objectid' => $this->get_id(),
            'context' => $this->get_context(),
            'other' => [
                'kahoodleid' => $this->data->kahoodleid,
                'stage' => $newstage->get_stage_name(),
                'questionnumber' => $newstage->get_question_number(),
            ],
        ]);
        $event->trigger();

        // Clear ranking cache.
        $this->questionrankings = [];

        // Update question statistics cache if we changed question.
        if ($oldstage->get_stage_name() === constants::STAGE_QUESTION && $oldstage->get_round_question()) {
            $oldstage->get_round_question()->update_statistics();
        }

        // Update final ranks when entering revision stage.
        if (
            $newstage->get_stage_name() === constants::STAGE_REVISION
                || $newstage->get_stage_name() === constants::STAGE_ARCHIVED
        ) {
            $this->update_final_ranks();
        }
    }

    /**
     * Update final ranks and total scores for all participants in this round
     *
     * This should be called when the round enters the revision stage to persist
     * the final standings. Uses the standard competition ranking where tied
     * participants share the same rank.
     *
     * @return void
     */
    public function update_final_ranks(): void {
        global $DB;

        $rankings = $this->get_rankings();
        $haschanges = false;

        foreach ($rankings as $participantid => $rank) {
            $participant = $rank->participant;
            if (
                $participant->get_final_rank() === $rank->minrank &&
                $participant->get_total_score() === $rank->score
            ) {
                // No change, skip update.
                continue;
            }
            $DB->update_record('kahoodle_participants', [
                'id' => $participantid,
                'finalrank' => $rank->minrank,
                'totalscore' => $rank->score,
            ]);
            $haschanges = true;
        }

        // Clear participant cache since we updated the data.
        if ($haschanges) {
            $this->clear_participant_cache();
        }
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

    /**
     * Get all winners on the podium (ranks 1, 2, and 3)
     *
     * @return rank[][] Array of arrays of rank objects, keyed by rank number (1, 2, 3).
     *      Some indexes may be missing, i.e. if two people tied for first place, there will be no second place.
     */
    public function get_podium_ranks(): array {
        $rankings = $this->get_rankings();
        $podium = [];
        foreach ($rankings as $rank) {
            if ($rank->minrank <= 3) {
                $podium[$rank->minrank] = $podium[$rank->minrank] ?? [];
                $podium[$rank->minrank][] = $rank;
            } else {
                break;
            }
        }
        return $podium;
    }

    /**
     * Should the podium be shown (more than one rank on podium)
     *
     * For example, if there are three or more participants tied for the first place we
     * can just go straight to the leaderboard.
     *
     * @return bool
     */
    public function is_podium_shown(): bool {
        $podiumranks = $this->get_podium_ranks();
        return count($podiumranks) > 1;
    }

    /**
     * Update the round name
     *
     * @param string $name The new name (not cleaned!)
     * @return \core\output\inplace_editable
     */
    public function update_name(string $name): \core\output\inplace_editable {
        global $DB;
        $name = \core_text::substr(trim(clean_param($name, PARAM_TEXT)), 0, 255);
        $DB->set_field('kahoodle_rounds', 'name', $name, ['id' => $this->get_id()]);
        $this->data->name = $name;
        return $this->get_name_inplace_editable();
    }

    /**
     * Create an inplace editable element for round name
     *
     * @return \core\output\inplace_editable
     */
    public function get_name_inplace_editable(): \core\output\inplace_editable {
        $displayvalue = $this->get_display_name();
        $editable = has_capability('mod/kahoodle:facilitate', $this->get_context());

        return new \core\output\inplace_editable(
            'mod_kahoodle',
            'roundname',
            $this->get_id(),
            $editable,
            $displayvalue,
            $this->data->name,
            get_string('editroundname', 'mod_kahoodle'),
            get_string('editroundname_label', 'mod_kahoodle', $displayvalue)
        );
    }

    /**
     * Create a new round based on this round's question configuration
     *
     * Creates a new round in preparation stage and copies all round_questions
     * from this round to the new one.
     *
     * @return self The newly created round
     */
    public function duplicate(): self {
        global $DB;

        $time = time();

        // Count existing rounds for naming.
        $roundcount = $DB->count_records('kahoodle_rounds', ['kahoodleid' => $this->data->kahoodleid]);

        // Create the new round record.
        $record = new stdClass();
        $record->kahoodleid = $this->data->kahoodleid;
        $record->name = get_string('roundname', 'mod_kahoodle', $roundcount + 1);
        $record->currentstage = constants::STAGE_PREPARATION;
        $record->currentquestion = null;
        $record->stagestarttime = null;
        $record->timecreated = $time;
        $record->timestarted = null;
        $record->timecompleted = null;
        $record->timemodified = $time;

        $record->id = $DB->insert_record('kahoodle_rounds', $record);

        // Trigger round created event.
        $event = \mod_kahoodle\event\round_created::create([
            'objectid' => $record->id,
            'context' => $this->get_context(),
            'other' => [
                'kahoodleid' => $this->data->kahoodleid,
            ],
        ]);
        $event->trigger();

        // Copy all round_questions from this round to the new round.
        $questions = $DB->get_records('kahoodle_round_questions', ['roundid' => $this->get_id()], 'sortorder ASC');
        foreach ($questions as $question) {
            $newquestion = new stdClass();
            $newquestion->roundid = $record->id;
            $newquestion->questionversionid = $question->questionversionid;
            $newquestion->sortorder = $question->sortorder;
            foreach (constants::FIELDS_ROUND_QUESTION as $field) {
                $newquestion->$field = $question->$field;
            }
            // Don't copy statistics fields (totalresponses, answerdistribution).
            $newquestion->timecreated = $time;
            $newquestion->timemodified = $time;

            $DB->insert_record('kahoodle_round_questions', $newquestion);
        }

        return self::create_from_object($record);
    }
}
