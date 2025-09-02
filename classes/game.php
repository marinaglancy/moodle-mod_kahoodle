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

/**
 * Class game
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class game {
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

    public function get_id(): int {
        return $this->game->id;
    }
    public function update_game_state(string $newstate, ?int $questionid = null) {
        global $DB;
        $DB->update_record('kahoodle', [
            'state' => $newstate,
            'id' => $this->game->id,
            'current_question_id' => $questionid,
        ]);
        $this->game->state = $newstate;
        $this->game->current_question_id = $questionid;
    }
    public function transition_game() {
        global $DB;
        if ($this->game->state == constants::STATE_PREPARATION) {
            $this->update_game_state(constants::STATE_WAITING);
        } else if ($this->game->state == constants::STATE_WAITING) {

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
}
