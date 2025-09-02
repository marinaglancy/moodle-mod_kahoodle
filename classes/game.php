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
 * Class game
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class game {
    protected $questions = null;
    public function __construct(protected \cm_info $cm, protected \stdClass $game) {
    }

    public function get_cm(): \cm_info {
        return $this->cm;
    }

    public function get_game(): \stdClass {
        return $this->game;
    }

    public function get_context(): \context {
        return \context_module::instance($this->cm->id);
    }

    public function get_url(): moodle_url {
        return new moodle_url('/mod/kahoodle/view.php', ['id' => $this->cm->id]);
    }

    public function is_in_preparation(): bool {
        return $this->game->state === constants::STATE_PREPARATION;
    }

    public function is_done(): bool {
        return $this->game->state === constants::STATE_DONE;
    }

    public function is_in_lobby(): bool {
        return $this->game->state === constants::STATE_WAITING;
    }

    public function is_in_progress(): bool {
        return $this->game->state === constants::STATE_INPROGRESS;
    }

    public function get_current_question_id(): int|null {
        return $this->game->state === constants::STATE_INPROGRESS && $this->game->current_question_id ?
            $this->game->current_question_id : null;
    }

    public function is_current_question_state_asking(): bool {
        return $this->get_current_question_id() > 0 &&
            $this->game->current_question_state === constants::QSTATE_ASKING;
    }

    public function is_current_question_state_results(): bool {
        return $this->get_current_question_id() > 0 &&
            $this->game->current_question_state === constants::QSTATE_RESULTS;
    }

    public function is_current_question_state_leaderboard(): bool {
        return $this->get_current_question_id() > 0 &&
            $this->game->current_question_state === constants::QSTATE_LEADERBOARD;
    }

    public function get_id(): int {
        return $this->game->id;
    }
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

    protected function get_questions() {
        if ($this->questions === null) {
            global $DB;
            $this->questions = $DB->get_records('kahoodle_questions', ['kahoodle_id' => $this->game->id],
                'sortorder ASC');
        }
        return $this->questions;
    }

    protected function get_next_question_id(?int $currentquestionid): int|null {
        $questionids = array_keys($this->get_questions());
        if ($currentquestionid === null) {
            return reset($questionids);
        }
        $currentindex = array_search($currentquestionid, $questionids);
        return $currentindex !== false && isset($questionids[$currentindex + 1]) ? $questionids[$currentindex + 1] : null;
    }

    public function transition_game() {
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
                    return;
                } else if ($this->is_current_question_state_results()) {
                    $this->update_game_state(constants::STATE_INPROGRESS, $currentquestionid,
                        constants::QSTATE_LEADERBOARD);
                    return;
                } else if ($nextquestionid = $this->get_next_question_id($currentquestionid)) {
                    // Move on to the next question.
                    $this->update_game_state(constants::STATE_INPROGRESS, $nextquestionid, constants::QSTATE_ASKING);
                    $DB->update_record('kahoodle_questions', ['started_at' => time(), 'id' => $nextquestionid]);
                    $questions = null;
                } else {
                    // This was the last question.
                    $this->update_game_state(constants::STATE_DONE, $currentquestionid, constants::QSTATE_LEADERBOARD);
                }
            }
        }
    }

    public function reset_game() {
        global $DB;
        $this->update_game_state(constants::STATE_PREPARATION, null);
        $DB->execute('DELETE FROM {kahoodle_answers}
            WHERE question_id IN (SELECT id FROM {kahoodle_questions} WHERE kahoodle_id = ?)',
            [$this->game->id]);
        $DB->delete_records('kahoodle_players', ['kahoodle_id' => $this->game->id]);
        $DB->delete_records('kahoodle_questions', ['kahoodle_id' => $this->game->id]);

        $questions = [
            [
                'text' => 'What is the color of the sky?',
                'answers' => ['Red', 'Green', 'Blue', 'Purple'],
                'correctanswer' => 2, // 0-based.
            ],
            [
                'text' => 'What is the capital of France?',
                'answers' => ['Berlin', 'Madrid', 'Paris', 'Rome'],
                'correctanswer' => 2, // 0-based.
            ],
            [
                'text' => 'What is 2+2?',
                'answers' => ['3', '4', '5', '22'],
                'correctanswer' => 1, // 0-based.
            ],
        ];

        foreach ($questions as $i => $question) {
            $DB->insert_record('kahoodle_questions', [
                'kahoodle_id' => $this->game->id,
                'question' => json_encode($question),
                'duration' => 60,
                'sortorder' => $i,
                'timecreated' => time(),
                'timemodified' => time(),
                'started_at' => 0, // TODO make nullable
            ]);
        }
    }

    public function get_current_question(bool $withanswers = false) {
        $question = $this->get_current_question_id() ? $this->get_questions()[$this->get_current_question_id()] : null;

        if (!$question) {
            return null;
        }
        $questiondata = json_decode($question->question, true);

        $options = [];
        foreach ($questiondata['answers'] as $i => $answer) {
            $option = [
                'id' => $i + 1,
                'text' => $answer,
            ];
            if ($withanswers) {
                $option['iscorrect'] = ($i === ($questiondata['correctanswer'] ?? -1));
            }
            $options[] = $option;
        }

        return [
            'questionid' => $question->id,
            'question' => $questiondata['text'] ?? '',
            'options' => $options,
        ];
    }
}
