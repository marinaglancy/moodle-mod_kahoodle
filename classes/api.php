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

use context_module;
use moodle_url;

/**
 * Class api
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    protected $playerid = null;
    protected game $game;

    public function __construct(\cm_info $cm, \stdClass $activity) {
        $this->game = new game($cm, $activity);
    }

    public function get_game_state() {
        global $USER;
        $cantransition = self::can_transition();
        if ($cantransition) {
            return $this->get_game_state_gamemaster();
        } else if (self::can_join()) {
            return [
                'template' => 'mod_kahoodle/joinscreen',
                'data' => [
                    'name' => (isloggedin() && !isguestuser()) ? fullname($USER) : '',
                    'url' => $this->game->get_url()->out(false),
                    'sesskey' => sesskey(),
                ],
            ];
        } else if ($this->game->is_done()) {
            return [
                'template' => 'mod_kahoodle/donescreen',
                'data' => [
                ],
            ];
        // } else if ($this->game->state === constants::STATE_INPROGRESS) {
        //     // TODO implement.
        //     return [
        //         'template' => 'mod_kahoodle/notready',
        //         'data' => [
        //         ],
        //     ];
        // } else if ($this->game->state === constants::STATE_WAITING) {
        //     if (self::can_answer()) {
        //         return $this->get_game_state_player();
        //     } else {
        //         return [
        //             'template' => 'mod_kahoodle/waitingroom',
        //             'data' => [
        //             ],
        //         ];
        //     }
        } else if (self::can_answer()) {
            return $this->get_game_state_player($this->get_player_id());
        } else {
            return [
                'template' => 'mod_kahoodle/notready',
                'data' => [
                ],
            ];
        }
    }

    protected function get_game_state_gamemaster() {
        if ($this->game->is_in_preparation()) {
            return [
                'template' => 'mod_kahoodle/preparation',
                'data' => [],
            ];
        } else if ($this->game->is_in_lobby()) {
            return [
                'template' => 'mod_kahoodle/lobby',
                'data' => [
                    'players' => array_values($this->get_players('name')),
                ],
            ];
        // } else if ($this->game->state == constants::STATE_INPROGRESS) {

        // } else if ($this->game->state == constants::STATE_DONE) {

        }
        return [
            'template' => 'mod_kahoodle/notready',
            'data' => [
            ],
        ];
    }

    protected function get_game_state_player(int $playerid) {
        return [
            'template' => 'mod_kahoodle/waitscreen',
            'data' => [
            ],
        ];
    }

    public function can_transition() {
        return has_capability('mod/kahoodle:transition', $this->get_context());
    }

    public function can_join() {
        return ($this->game->is_in_progress() || $this->game->is_in_lobby()) &&
            !self::get_player_id() && has_capability('mod/kahoodle:answer', $this->get_context());
    }

    public function can_answer() {
        return $this->game->is_in_progress() &&
            self::get_player_id() && has_capability('mod/kahoodle:answer', $this->get_context());
    }

    public function get_cm(): \cm_info {
        return $this->game->get_cm();
    }

    public function get_context(): \context {
        return $this->game->get_context();
    }

    public function get_player_id(): int {
        global $DB, $USER;
        if ($this->playerid !== null) {
            return $this->playerid;
        }

        if (isloggedin() && !isguestuser()) {
            $playerrecord = $DB->get_record('kahoodle_players',
                ['user_id' => $USER->id, 'kahoodle_id' => $this->game->get_id()]);
        } else {
            $playerrecord = $DB->get_record('kahoodle_players',
                ['session_id' => session_id(), 'kahoodle_id' => $this->game->get_id()]);
        }
        $this->playerid = $playerrecord ? $playerrecord->id : 0;
        return $this->playerid;
    }

    public function do_join(string $name) {
        global $DB, $USER;
        if (!$this->can_join()) {
            return;
        }
        $this->playerid = $DB->insert_record('kahoodle_players', [
            'name' => $name,
            'user_id' => (isloggedin() && !isguestuser()) ? $USER->id : null,
            'session_id' => session_id(),
            'kahoodle_id' => $this->game->get_id(),
            'timejoined' => time(), // TODO add a field to the database
        ]);
        $this->notify_gamemaster();
    }

    public function get_players($fields = 'id, name') {
        global $DB;
        return $DB->get_records(
            'kahoodle_players',
            ['kahoodle_id' => $this->game->get_id()],
            '', // TODO 'timejoined'
            $fields
        );
    }

    public function process_simple_action() {
        $action = optional_param('action', '', PARAM_TEXT);
        if ($action == 'reset' && $this->can_transition() && confirm_sesskey()) {
            $this->game->reset_game();
            redirect($this->game->get_url());
        } else if ($action == 'join' && $this->can_join() && confirm_sesskey()) {
            $name = required_param('name', PARAM_TEXT);
            // TODO validate, trim name
            $this->do_join($name);
            redirect($this->game->get_url());
        }
    }

    public function handle_realtime_event($payload) {
        $action = $payload['action'] ?: null;
        if ($action == 'transition' && $this->can_transition()) {
            $this->transition_game();
        }

        // TODO
    }

    protected function notify_gamemaster() {
        $channel = new \tool_realtime\channel($this->get_context(),
            'mod_kahoodle', 'gamemaster', 0);
        $channel->notify($this->get_game_state_gamemaster());
    }

    protected function notify_all_players() {
        // TODO if content does not depend on the player, push only one event to itemid = 0
        foreach ($this->get_players() as $player) {
            $channel = new \tool_realtime\channel($this->get_context(),
                'mod_kahoodle', 'game', $player->id);
            $channel->notify($this->get_game_state_player($player->id));
        }
    }

    protected function transition_game() {
        $this->game->transition_game();

        $this->notify_gamemaster();
        $this->notify_all_players();
    }
}
