<?php
// This file is part of Moodle - http://moodle.org/
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

namespace mod_kahoodle;

use moodle_url;
use stdClass;

/**
 * Stores information about a Kahoodle game instance and provides helper methods
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class game {
    /** @var array|null cached questions */
    protected $questions = null;

    /**
     * Initialises the game instance
     *
     * @param \cm_info $cm
     * @param \stdClass $game
     */
    public function __construct(
        /** @var \cm_info */
        protected \cm_info $cm,
        /** @var \stdClass */
        protected stdClass $game) {
    }

    /**
     * Getter for the course module instance
     *
     * @return \cm_info
     */
    public function get_cm(): \cm_info {
        return $this->cm;
    }

    /**
     * Getter for the game record
     *
     * @return \stdClass
     */
    public function get_game(): \stdClass {
        return $this->game;
    }

    /**
     * Course module context
     *
     * @return \context
     */
    public function get_context(): \context {
        return \context_module::instance($this->cm->id);
    }

    /**
     * URL to the game view page
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/mod/kahoodle/view.php', ['id' => $this->cm->id]);
    }

    /**
     * Is the game in preparation state?
     *
     * @return moodle_url
     */
    public function is_in_preparation(): bool {
        return $this->game->state === constants::STATE_PREPARATION;
    }

    /**
     * Is the game finished?
     *
     * @return moodle_url
     */
    public function is_done(): bool {
        return $this->game->state === constants::STATE_DONE;
    }

    /**
     * Is the game in lobby/waiting for players state?
     *
     * @return bool
     */
    public function is_in_lobby(): bool {
        return $this->game->state === constants::STATE_WAITING;
    }

    /**
     * Is the game in progress state?
     *
     * @return bool
     */
    public function is_in_progress(): bool {
        return $this->game->state === constants::STATE_INPROGRESS;
    }

    /**
     * Gets the current question id if the game is in progress
     *
     * @return int|null question id or null if no question is active
     */
    public function get_current_question_id(): int|null {
        return $this->game->state === constants::STATE_INPROGRESS && $this->game->current_question_id ?
            $this->game->current_question_id : null;
    }

    /**
     * Is current question state ASKING (accepting answers)?
     *
     * @return bool
     */
    public function is_current_question_state_asking(): bool {
        return $this->get_current_question_id() > 0 &&
            $this->game->current_question_state === constants::QSTATE_ASKING;
    }

    /**
     * Is current question state RESULTS (showing results)?
     *
     * @return bool
     */
    public function is_current_question_state_results(): bool {
        return $this->get_current_question_id() > 0 &&
            $this->game->current_question_state === constants::QSTATE_RESULTS;
    }

    /**
     * Is current question state LEADERBOARD (showing leaderboard)?
     *
     * @return bool
     */
    public function is_current_question_state_leaderboard(): bool {
        return $this->get_current_question_id() > 0 &&
            $this->game->current_question_state === constants::QSTATE_LEADERBOARD;
    }

    /**
     * Getter for the game id
     *
     * @return int
     */
    public function get_id(): int {
        return $this->game->id;
    }

    /**
     * Updates the game state and optionally current question id and question state
     *
     * @param string $newstate new game state
     * @param int|null $questionid new current question id or null (when the game status is changed to "PROGRESS")
     * @param string $qstate new current question state
     * @return void
     */
    public function update_game_state(string $newstate, ?int $questionid = null, string $qstate = constants::QSTATE_ASKING) {
        global $DB;
        $DB->update_record('kahoodle', [
            'state' => $newstate,
            'id' => $this->game->id,
            'current_question_id' => $questionid,
            'current_question_state' => $qstate,
        ]);
        $this->game->state = $newstate;
        $this->game->current_question_id = $questionid;
        $this->game->current_question_state = $qstate;
    }

    /**
     * Getter for the questions of this game
     *
     * @return \stdClass[] question records
     */
    protected function get_questions() {
        if ($this->questions === null) {
            global $DB;
            $this->questions = $DB->get_records('kahoodle_questions', ['kahoodle_id' => $this->game->id],
                'sortorder ASC');
        }
        return $this->questions;
    }

    /**
     * Gets the next question id after the given question id
     *
     * @param int|null $currentquestionid current question id or null to get the first question
     * @return int|null next question id or null if there is no next question
     */
    protected function get_next_question_id(?int $currentquestionid): int|null {
        $questionids = array_keys($this->get_questions());
        if ($currentquestionid === null) {
            return reset($questionids);
        }
        $currentindex = array_search($currentquestionid, $questionids);
        return $currentindex !== false && isset($questionids[$currentindex + 1]) ? $questionids[$currentindex + 1] : null;
    }

    /**
     * Transitions the game to the next state
     *
     * @return bool "easy transition", meaning that the notification is the same for all players (usually when question starts)
     */
    public function transition_game(): bool {
        global $DB;
        if ($this->game->state == constants::STATE_PREPARATION) {
            $this->update_game_state(constants::STATE_WAITING);
        } else if ($this->game->state == constants::STATE_WAITING) {
            $this->update_game_state(constants::STATE_INPROGRESS, $this->get_next_question_id(null));
        } else if ($this->game->state == constants::STATE_INPROGRESS) {
            if ($currentquestionid = $this->get_current_question_id()) {
                if ($this->is_current_question_state_asking()) {
                    $this->update_game_state(constants::STATE_INPROGRESS, $currentquestionid,
                        constants::QSTATE_RESULTS);
                } else if ($this->is_current_question_state_results()) {
                    $this->update_game_state(constants::STATE_INPROGRESS, $currentquestionid,
                        constants::QSTATE_LEADERBOARD);
                } else if ($nextquestionid = $this->get_next_question_id($currentquestionid)) {
                    // Move on to the next question.
                    $this->update_game_state(constants::STATE_INPROGRESS, $nextquestionid, constants::QSTATE_ASKING);
                    $DB->update_record('kahoodle_questions', ['started_at' => time(), 'id' => $nextquestionid]);
                    $questions = null;
                    return true;
                } else {
                    // This was the last question.
                    $this->update_game_state(constants::STATE_DONE, $currentquestionid, constants::QSTATE_LEADERBOARD);
                }
            }
        }
        return false;
    }

    /**
     * Resets the game to the initial state and populates it with default questions
     *
     * @return void
     */
    public function reset_game() {
        global $DB;
        $this->update_game_state(constants::STATE_PREPARATION, null);
        $DB->execute('DELETE FROM {kahoodle_answers}
            WHERE question_id IN (SELECT id FROM {kahoodle_questions} WHERE kahoodle_id = ?)',
            [$this->game->id]);
        $DB->delete_records('kahoodle_players', ['kahoodle_id' => $this->game->id]);
        $DB->delete_records('kahoodle_questions', ['kahoodle_id' => $this->game->id]);

        // phpcs:disable moodle.Files.LineLength.MaxExceeded, moodle.Files.LineLength.TooLong
        $questions = [
            [
                'text' => '
                    <div class="d-flex row align-items-center p-3 border rounded shadow-sm">
                        <div class="col-12">
                            <div class="col-4">
                                <img src="https://marina.ninja/pluginfile.php/2/course/section/1/kahoodle_team%20%283%29.jpg" class="img-fluid rounded">
                            </div>
                            <div class="col-8">&nbsp;</div>
                        </div>
                        <div class="col-12">
                            <span class="fw-bold display-5">Our team members are …</span>
                        </div>
                    </div>
                ',
                'answers' => ['Kathleen, Jan, Vasco, Immanuel, Pascal, Lars, Marina, Monika', 'Peter, Heike, Klaus', 'Sabine, Tom, Anna, Otto, Hannah', 'Donald Duck, Goofy, Micky Mouse'],
                'correctanswer' => 0, // 0-based.
                'points' => 100,
            ],
            [
                'text' => '
                    <div class="d-flex row align-items-center p-3 border rounded shadow-sm">
                        <div class="col-12">
                            <div class="col-4">
                                <img src="https://marina.ninja/pluginfile.php/2/course/section/1/kahoodle_tshirt_station.jpg" class="img-fluid rounded">
                            </div>
                            <div class="col-8">&nbsp;</div>
                        </div>
                        <div class="col-12">
                            <span class="fw-bold display-5">What is the price of the MoodleMoot DACH T-Shirt?</span>
                        </div>
                    </div>
                ',
                'answers' => ['19€', '25€', '29€', '50€'],
                'correctanswer' => 0, // 0-based.
                'points' => 100,
            ],
            [
                'text' => '
                    <div class="d-flex row align-items-center p-3 border rounded shadow-sm">
                        <div class="col-12">
                            <div class="col-4">
                                <img src="https://marina.ninja/pluginfile.php/2/course/section/1/kahoodle_session_rooms.jpg" class="img-fluid rounded">
                            </div>
                            <div class="col-8">&nbsp;</div>
                        </div>
                        <div class="col-12">
                            <span class="fw-bold display-5">There was a session room called Trave.</span>
                        </div>
                    </div>
                ',
                'answers' => ['True', 'False' ],
                'correctanswer' => 1, // 0-based.
                'points' => 100,
            ],
            [
                'text' => '
                    <div class="d-flex row align-items-center p-3 border rounded shadow-sm">
                        <div class="col-12">
                            <div class="col-4">
                                <img src="https://marina.ninja/pluginfile.php/2/course/section/1/kahoodle_wien.jpg" class="img-fluid rounded">
                            </div>
                            <div class="col-8">&nbsp;</div>
                        </div>
                        <div class="col-12">
                            <span class="fw-bold display-5">The MoodleMoot DACH in 2024 took place in Vienna.</span>
                        </div>
                    </div>
                ',
                'answers' => ['True', 'False' ],
                'correctanswer' => 0, // 0-based.
                'points' => 100,
            ],
            [
                'text' => '
                    <div class="d-flex row align-items-center p-3 border rounded shadow-sm">
                        <div class="col-12">
                            <div class="col-4">
                                <img src="https://marina.ninja/pluginfile.php/2/course/section/1/kahoodle_devcamp_groups.jpg" class="img-fluid rounded">
                            </div>
                            <div class="col-8">&nbsp;</div>
                        </div>
                        <div class="col-12">
                            <span class="fw-bold display-5">How many groups are there at the DevCamp?</span>
                        </div>
                    </div>
                ',
                'answers' => ['22', '10', '50', '35' ],
                'correctanswer' => 0, // 0-based.
                'points' => 100,
            ],
        ];
        // phpcs:enable

        foreach ($questions as $i => $question) {
            $DB->insert_record('kahoodle_questions', [
                'kahoodle_id' => $this->game->id,
                'question' => json_encode($question),
                'duration' => 10,
                'sortorder' => $i,
                'timecreated' => time(),
                'timemodified' => time(),
                'started_at' => 0, // TODO make nullable.
            ]);
        }
    }

    /**
     * Gets the current question with options and optionally includes information about the correct answer
     *
     * The result is already prepared to be used in templates.
     *
     * @param bool $withcorrect whether to include the correct answer
     * @param int|null $studentanswer the option index of the student's answer or null if not answered yet
     * @return array|null question data or null if there is no current question
     */
    public function get_current_question(bool $withcorrect = false, ?int $studentanswer = null) {
        $question = $this->get_current_raw_question();

        if (!$question) {
            return null;
        }
        $questiondata = json_decode($question->question, true);

        $result = [
            'questionid' => $question->id,
            'question' => $questiondata['text'] ?? '',
            'options' => [],
            'isanswered' => $studentanswer !== null,
        ];
        if ($withcorrect) {
            $result['correctanswer'] = $questiondata['correctanswer'];
        }
        foreach ($questiondata['answers'] as $i => $answer) {
            $option = [
                'id' => $i,
                'text' => $answer,
            ];
            if ($withcorrect) {
                $option['iscorrect'] = ($i == $questiondata['correctanswer']);
            }
            if ($studentanswer !== null) {
                $option['isanswer'] = $studentanswer == $i;
            }
            $result['options'][] = $option;
        }

        return $result;
    }

    /**
     * Gets the current question record
     *
     * @return stdClass|null question record or null if there is no current question
     */
    protected function get_current_raw_question(): ?stdClass {
        return $this->get_current_question_id() ? $this->get_questions()[$this->get_current_question_id()] : null;
    }

    /**
     * Calculates the score for the given option index
     *
     * @param int $optionidx the option index (0-based)
     * @return int score (0..points)
     */
    public function calculate_score(int $optionidx): int {
        $rawquestion = $this->get_current_raw_question();
        $questiondata = json_decode($rawquestion->question, true);
        if ($optionidx != $questiondata['correctanswer']) {
            return 0;
        }

        $timetaken = time() - $rawquestion->started_at;
        $penalty = $rawquestion->duration > 0 ?
            0.5 * min($timetaken, $rawquestion->duration) / $rawquestion->duration : 0;
        return (int)($questiondata['points'] * (1 - $penalty));
    }
}
