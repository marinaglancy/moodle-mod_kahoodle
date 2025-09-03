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
    protected $answers = null;
    protected game $game;
    protected int $bordercolorindex = 0;

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
        } else if ($playerid = $this->get_player_id()) {
            return $this->get_game_state_player($playerid);
        } else {
            // TODO this is also displayed for people who did not play after the game has finished
            return [
                'template' => 'mod_kahoodle/notready',
                'data' => [
                ],
            ];
        }
    }

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
                    ] // TODO plus stats
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

    protected function get_game_state_player(int $playerid) {
        if ($this->game->is_done()) {
            return [
                'template' => 'mod_kahoodle/donescreen_player',
                // 'data' => [ 'player' => array_keys($this->get_player_aggregated_points($playerid))],
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
                    ['points' => $studentanswer?->points],
                ];
            } else {
                return [
                    'template' => 'mod_kahoodle/questionleaderboard_player',
                    'data' => $this->game->get_current_question(true, $studentanswer) +
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

    protected function get_player_answers(?int $playerid): array {
        global $DB;
        if ($playerid === null) {
            return [];
        }
        if ($this->answers != null) {
            return $this->answers;
        }
        $this->answers = $DB->get_records_sql('SELECT a.question_id, a.points, a.answer
            FROM {kahoodle_answers} a
            JOIN {kahoodle_questions} q ON a.question_id = q.id
            WHERE q.kahoodle_id = :kahoodleid AND a.player_id = :playerid
            ORDER BY q.sortorder', [
                'kahoodleid' => $this->game->get_id(),
                'playerid' => $playerid,
            ]);
        $totalscore = 0;
        foreach ($this->answers as $answer) {
            $answer->answer = json_decode($answer->answer, true);
            $totalscore += (int)$answer->points;
            $answer->score = $totalscore;
        }
        return $this->answers;
    }

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
                        'y' => null
                    ],
                    'smooth' => null
                ]
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

    protected function get_leaderboard(): array {
        global $DB;
        $score = $DB->get_records_sql('SELECT a.player_id AS playerid, p.name AS name, SUM(a.points) AS points 
            FROM {kahoodle_answers} a
            JOIN {kahoodle_questions} q ON a.question_id = q.id
            JOIN {kahoodle_players} p on p.id = a.player_id
            WHERE q.kahoodle_id = :kahoodleid
            GROUP BY a.player_id, p.name
            ORDER BY points', [
                'kahoodleid' => $this->game->get_id(),
            ], 0, 10);
        foreach ($score as $key => $value) {
            $score[$key]->color = $this->get_next_border_color();
        }
        return $score;
    }

    protected function get_player_aggregated_points(int $playerid): object {
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
        return $score;
    }

    protected function get_player_score(int $playerid): int {
        $answers = $this->get_player_answers($playerid);
        $qids = array_keys($answers);
        return $answers ? $answers[$qids[sizeof($qids) - 1]]->score : 0;
    }

    public function can_transition() {
        return has_capability('mod/kahoodle:transition', $this->get_context());
    }

    public function can_join() {
        return ($this->game->is_in_progress() || $this->game->is_in_lobby()) &&
            !self::get_player_id() &&
            ((isloggedin() && !isguestuser()) || $this->can_auth_guests()) &&
            has_capability('mod/kahoodle:answer', $this->get_context());
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

    protected function can_auth_guests(): bool {
        return \core_component::get_component_directory('auth_kahoodle')
                    && is_enabled_auth('kahoodle');
    }

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

        if ($action == 'answer') {
            $questionid = $payload['questionid'] ?? null;
            $optionidx = $payload['answer'] ?? null;
            if ($questionid && $optionidx !== null) {
                $this->do_answer($questionid, $optionidx);
            }
        }
    }

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
        $this->answers = null;

        $this->notify_player($playerid);
    }
    protected function notify_gamemaster() {
        $channel = new \tool_realtime\channel($this->get_context(),
            'mod_kahoodle', 'gamemaster', 0);
        $channel->notify($this->get_game_state_gamemaster());
    }

    protected function notify_all_players() {
        // TODO if content does not depend on the player, push only one event to itemid = 0
        foreach ($this->get_players() as $player) {
            $this->notify_player($player->id);
        }
    }

    protected function notify_player(int $playerid) {
        $channel = new \tool_realtime\channel($this->get_context(),
                'mod_kahoodle', 'game', $playerid);
        $channel->notify($this->get_game_state_player($playerid));
    }

    protected function transition_game() {
        $this->game->transition_game();

        $this->notify_gamemaster();
        $this->notify_all_players();
    }

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
