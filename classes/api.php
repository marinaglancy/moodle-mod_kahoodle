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
use stdClass;

/**
 * Helper methods to work with the kahoodle game.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var int|null cached value of the current player id */
    protected $playerid = null;
    /** @var array cached value of the players answers (indexed by player id and then by question id) */
    protected $cachedanswers = [];
    /** @var game the game instance */
    protected game $game;
    /** @var int last used index of the border for the leaderboard */
    protected int $bordercolorindex = 0;

    /**
     * Initialise the API for the given activity.
     *
     * @param \cm_info $cm
     * @param \stdClass $activity
     */
    public function __construct(\cm_info $cm, stdClass $activity) {
        $this->game = new game($cm, $activity);
    }

    /**
     * Gets the current game state for the current user.
     *
     * @return array with 'template' and 'data' keys to be used with the renderer
     */
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
        } else if ($playerid = $this->get_player_id()) {
            return $this->get_game_state_player($playerid);
        } else {
            // TODO this is also displayed for people who did not play after the game has finished.
            return [
                'template' => 'mod_kahoodle/notready',
                'data' => [
                ],
            ];
        }
    }

    /**
     * Gets the game state if the current user is the game master.
     *
     * @return array with 'template' and 'data' keys to be used with the renderer
     */
    protected function get_game_state_gamemaster() {
        if ($this->game->is_done()) {
            return [
                'template' => 'mod_kahoodle/donescreen',
                'data' => [ 'players' => array_values($this->get_leaderboard())],
            ];
        } else if ($this->game->is_in_preparation()) {
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
        } else if ($this->game->is_in_progress() && $this->game->get_current_question_id()) {
            if ($this->game->is_current_question_state_asking()) {
                return [
                    'template' => 'mod_kahoodle/question_gamemaster',
                    'data' => $this->game->get_current_question(false),
                ];
            } else if ($this->game->is_current_question_state_results()) {
                return [
                    'template' => 'mod_kahoodle/questionresult_gamemaster',
                    'data' => [
                        'data' => $this->game->get_current_question(true),
                        'chartdata' => $this->get_chart_data(),
                    ],
                ];
            } else if ($this->game->is_current_question_state_leaderboard()) {
                return [
                    'template' => 'mod_kahoodle/questionleaderboard_gamemaster',
                    'data' => [ 'players' => array_values($this->get_leaderboard())],
                ];
            }
        }
        return [
            'template' => 'mod_kahoodle/notready',
            'data' => [
            ],
        ];
    }

    /**
     * Summary of get_game_state_player
     *
     * @param int $playerid the player id (this function may be called for other players too)
     * @return array with 'template' and 'data' keys to be used with the renderer
     */
    protected function get_game_state_player(int $playerid) {
        if ($this->game->is_done()) {
            return [
                'template' => 'mod_kahoodle/donescreen_player',
                'data' => [ 'player' => $this->get_player_aggregated_points($playerid)],
            ];
        } else if ($this->game->is_in_progress() && $this->game->get_current_question_id()) {
            $answers = $this->get_player_answers($playerid);
            $studentanswer = $answers[$this->game->get_current_question_id()] ?? null;
            $optionidx = $studentanswer !== null ? $studentanswer->answer['option'] : null;
            if ($this->game->is_current_question_state_asking()) {
                return [
                    'template' => 'mod_kahoodle/question_player',
                    'data' => $this->game->get_current_question(false, $optionidx),
                ];
            } else if ($this->game->is_current_question_state_results()) {
                return [
                    'template' => 'mod_kahoodle/questionresult_player',
                    'data' => $this->game->get_current_question(true, $optionidx) +
                    ['points' => $studentanswer?->points ?: 0],
                ];
            } else {
                return [
                    'template' => 'mod_kahoodle/questionleaderboard_player',
                    'data' => $this->game->get_current_question(true, $optionidx) +
                    ['points' => $studentanswer?->points ?: 0, 'score' => $this->get_player_score($playerid)],
                ];
            }
        }
        return [
            'template' => 'mod_kahoodle/waitscreen',
            'data' => [
            ],
        ];
    }

    /**
     * Gets the answers for a specific player.
     *
     * @param int|null $playerid the player id
     * @return array the player's answers (questionid => (object)[question_id, points, answer])
     */
    protected function get_player_answers(?int $playerid): array {
        global $DB;
        if ($playerid === null) {
            return [];
        }
        if ($this->cachedanswers[$playerid] != null) {
            return $this->cachedanswers[$playerid];
        }
        $this->cachedanswers[$playerid] = $DB->get_records_sql('SELECT a.question_id, a.points, a.answer
            FROM {kahoodle_answers} a
            JOIN {kahoodle_questions} q ON a.question_id = q.id
            WHERE q.kahoodle_id = :kahoodleid AND a.player_id = :playerid
            ORDER BY q.sortorder', [
                'kahoodleid' => $this->game->get_id(),
                'playerid' => $playerid,
            ]);
        $totalscore = 0;
        foreach ($this->cachedanswers[$playerid] as $answer) {
            $answer->answer = json_decode($answer->answer, true);
            $totalscore += (int)$answer->points;
            $answer->score = $totalscore;
        }
        return $this->cachedanswers[$playerid];
    }

    /**
     * Data for the statistics chart for the current question.
     *
     * @return bool|string
     */
    protected function get_chart_data(): string {
        $statistics = $this->get_statistics();

        $y = [];
        $x = [];

        foreach ($statistics as $stat) {
            $y[] = $stat['count'];
            $x[] = $stat['text'];
        }

        $data = [
            'type' => 'bar',
            'series' => [
                [
                    'label' => 'Antworten',
                    'labels' => null,
                    'type' => null,
                    'values' => $y,
                    'colors' => [],
                    'axes' => [
                        'x' => null,
                        'y' => null,
                    ],
                    'smooth' => null,
                ],
            ],
            'labels' => $x,
            'title' => 'Results',
            'axes' => [
                'x' => [],
                'y' => [['min' => 0]],
            ],
            'config_colorset' => null,
            'horizontal' => false,
        ];

        return json_encode($data);
    }

    /**
     * Answers statistics for the current question.
     *
     * @return array
     */
    protected function get_statistics(): array {
        global $DB;
        $answers = $DB->get_records_sql('SELECT a.player_id, a.answer
            FROM {kahoodle_answers} a
            JOIN {kahoodle_questions} q ON a.question_id = q.id
            WHERE q.id = ?', [$this->game->get_current_question_id()]
            );
        $res = [];
        foreach ($this->game->get_current_question()['options'] as $option) {
            $res[] = ['text' => $option['text'], 'count' => 0];
        }
        foreach ($answers as &$answer) {
            $answer = json_decode($answer->answer, true);
            $res[$answer['option']]['count']++;
        }
        return array_values($res);
    }

    /**
     * Players with the top scores
     *
     * @param int $limit
     * @return array
     */
    protected function get_leaderboard(int $limit = 10): array {
        global $DB;
        $score = $DB->get_records_sql('SELECT a.player_id AS playerid, p.name AS name, SUM(a.points) AS points
            FROM {kahoodle_answers} a
            JOIN {kahoodle_questions} q ON a.question_id = q.id
            JOIN {kahoodle_players} p on p.id = a.player_id
            WHERE q.kahoodle_id = :kahoodleid
            GROUP BY a.player_id, p.name
            ORDER BY points DESC', [
                'kahoodleid' => $this->game->get_id(),
            ], 0, $limit);
        foreach ($score as $key => $value) {
            $score[$key]->color = $this->get_next_border_color();
        }
        return $score;
    }

    /**
     * Total score of the player across all questions.
     *
     * @param int $playerid
     */
    protected function get_player_aggregated_points(int $playerid): ?stdClass {
        global $DB;
        $score = $DB->get_record_sql('SELECT a.player_id AS playerid, p.name AS name, SUM(a.points) AS points
            FROM {kahoodle_answers} a
            JOIN {kahoodle_questions} q ON a.question_id = q.id
            JOIN {kahoodle_players} p on p.id = a.player_id
            WHERE q.kahoodle_id = :kahoodleid AND a.player_id = :playerid
            GROUP BY a.player_id, p.name',
            [
                'kahoodleid' => $this->game->get_id(),
                'playerid' => $playerid,
            ]);
        return $score ?: null;
    }

    /**
     * Total score for the player.
     *
     * @param int $playerid
     * @return int
     */
    protected function get_player_score(int $playerid): int {
        $answers = $this->get_player_answers($playerid);
        $qids = array_keys($answers);
        return $answers ? $answers[$qids[count($qids) - 1]]->score : 0;
    }

    /**
     * Checks if the current player can transition the game to the next state (is allowed to do that).
     *
     * @return bool
     */
    public function can_transition() {
        return has_capability('mod/kahoodle:transition', $this->get_context());
    }

    /**
     * Checks if the current player can join the game.
     *
     * @return bool
     */
    public function can_join() {
        return ($this->game->is_in_progress() || $this->game->is_in_lobby()) &&
            !self::get_player_id() &&
            ((isloggedin() && !isguestuser()) || $this->can_auth_guests()) &&
            has_capability('mod/kahoodle:answer', $this->get_context());
    }

    /**
     * Checks if the current player can give an answer to the questions (has capability, joined and the game is in progress).
     *
     * @return bool
     */
    public function can_answer() {
        return $this->game->is_in_progress() &&
            self::get_player_id() && has_capability('mod/kahoodle:answer', $this->get_context());
    }

    /**
     * Getter for the course module instance
     *
     * @return \cm_info
     */
    public function get_cm(): \cm_info {
        return $this->game->get_cm();
    }

    /**
     * Getter for the game context
     *
     * @return \context
     */
    public function get_context(): \context {
        return $this->game->get_context();
    }

    /**
     * Getter for the player id
     *
     * @return int|null
     */
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

    /**
     * Does the current game and site settings support authentication of guest users via auth/kahoodle?
     *
     * @return bool
     */
    protected function can_auth_guests(): bool {
        return \core_component::get_component_directory('auth_kahoodle')
                    && is_enabled_auth('kahoodle');
    }

    /**
     * Performs an action of joining the game.
     *
     * @param string $name
     * @return void
     */
    public function do_join(string $name) {
        global $DB, $USER;
        if (!$this->can_join()) {
            return;
        }
        if ((!isloggedin() || isguestuser()) && $this->can_auth_guests()) {
            /** @var \auth_plugin_kahoodle $auth */
            $auth = get_auth_plugin('kahoodle');

            $auth->create_fake_user($name);
        }
        $this->playerid = $DB->insert_record('kahoodle_players', [
            'name' => $name,
            'user_id' => (isloggedin() && !isguestuser()) ? $USER->id : null,
            'session_id' => session_id(),
            'kahoodle_id' => $this->game->get_id(),
            'timejoined' => time(), // TODO add a field to the database.
        ]);
        $this->notify_gamemaster();
    }

    /**
     * List of all players who joined the game (for the lobby screen).
     *
     * @param mixed $fields
     * @return array
     */
    public function get_players($fields = 'id, name') {
        global $DB;
        return $DB->get_records(
            'kahoodle_players',
            ['kahoodle_id' => $this->game->get_id()],
            '', // TODO 'timejoined DESC'.
            $fields
        );
    }

    /**
     * Process action other than playing the game (e.g. join, reset).
     *
     * @return void
     */
    public function process_simple_action() {
        $action = optional_param('action', '', PARAM_TEXT);
        if ($action == 'reset' && $this->can_transition() && confirm_sesskey()) {
            $this->game->reset_game();
            redirect($this->game->get_url());
        } else if ($action == 'join' && $this->can_join() && confirm_sesskey()) {
            $name = trim(required_param('name', PARAM_TEXT));
            // TODO validate name better.
            if (strlen($name) > 0) {
                $this->do_join(substr($name, 0, 20));
                redirect($this->game->get_url());
            }
        }
    }

    /**
     * Process action through the real-time API, i.e. transition game or answer a question.
     *
     * @param array $payload the payload of the event
     * @return void
     */
    public function handle_realtime_event($payload) {
        $action = $payload['action'] ?: null;
        if ($action == 'transition' && $this->can_transition()) {
            $this->transition_game();
        }

        if ($action == 'answer') {
            $questionid = $payload['questionid'] ?? null;
            $optionidx = $payload['answer'] ?? null;
            if ($questionid && $optionidx !== null) {
                $this->do_answer($questionid, $optionidx);
            }
        }
    }

    /**
     * Process an aciton of answering a question.
     *
     * @param int $questionid
     * @param int $optionidx
     * @return void
     */
    protected function do_answer(int $questionid, int $optionidx) {
        global $DB;
        if (!$this->can_answer() || !$this->game->is_current_question_state_asking() ||
            $this->game->get_current_question_id() != $questionid) {
            return;
        }
        $playerid = $this->get_player_id();
        if (!$playerid) {
            return;
        }
        $answers = $this->get_player_answers($playerid);
        // Check if already answered.
        if (array_key_exists($questionid, $answers)) {
            return;
        }
        $points = $this->game->calculate_score($optionidx);
        $DB->insert_record('kahoodle_answers', [
            'question_id' => $questionid,
            'player_id' => $playerid,
            'points' => $points,
            'answer' => json_encode(['option' => $optionidx]),
        ]);
        // Invalidate cached answers.
        unset($this->cachedanswers[$playerid]);

        $this->notify_player($playerid);
    }

    /**
     * Send notificaiton to all game masters through the real-time API notificaiton channel
     *
     * @return void
     */
    protected function notify_gamemaster() {
        $channel = new \tool_realtime\channel($this->get_context(),
            'mod_kahoodle', 'gamemaster', 0);
        $channel->notify($this->get_game_state_gamemaster());
    }

    /**
     * Send notificaiton to all players through the real-time API notificaiton channel
     *
     * @param bool $easytransition if true, the content does not depend on the player, so only one notification is sent to all
     * @return void
     */
    protected function notify_all_players(bool $easytransition = false) {
        // If content does not depend on the player, push only one event to itemid = 0.
        if ($easytransition) {
            $this->notify_player(0);
            return;
        }
        foreach ($this->get_players() as $player) {
            $this->notify_player($player->id);
        }
    }

    /**
     * Send notificaiton to a specific player through the real-time API notificaiton channel
     *
     * @param int $playerid the player id (0 means all players)
     * @return void
     */
    protected function notify_player(int $playerid) {
        $channel = new \tool_realtime\channel($this->get_context(),
                'mod_kahoodle', 'game', $playerid);
        $channel->notify($this->get_game_state_player($playerid));
    }

    /**
     * Process an action of transitioning the game to the next state.
     *
     * @return void
     */
    protected function transition_game() {
        $easytransition = $this->game->transition_game();

        $this->notify_gamemaster();
        $this->notify_all_players($easytransition);
    }

    /**
     * Helper method to iterate through a list of border colors.
     *
     * @return string
     */
    protected function get_next_border_color(): string {
        $colors = [
            'primary',
            'secondary',
            'success',
            'danger',
            'warning',
            'info',
        ];
        $color = $colors[$this->bordercolorindex % count($colors)];
        $this->bordercolorindex++;
        return $color;
    }
}
