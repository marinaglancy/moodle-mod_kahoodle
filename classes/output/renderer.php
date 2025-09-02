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

namespace mod_kahoodle\output;

use context_module;
use core\output\html_writer;
use mod_kahoodle\api;

/**
 * Renderer for Kahoodle
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Summary of render_game
     * @param \cm_info $cm
     * @return string
     */
    public function game(api $api) {
        $data = $api->get_game_state();
        $context = $api->get_context();
        $attrs = ['data-cmid' => $api->get_cm()->id, 'data-contextid' => $context->id];
        if ($api->can_transition()) {
            $channel = new \tool_realtime\channel($context, 'mod_kahoodle', 'gamemaster', 0);
            $channel->subscribe();
        } else if ($playerid = $api->get_player_id()) {
            $channel = new \tool_realtime\channel($context, 'mod_kahoodle', 'game', $playerid);
            $channel->subscribe();
            $channel2 = new \tool_realtime\channel($context, 'mod_kahoodle', 'game', 0);
            $channel2->subscribe();
            $attrs['playerid'] = $playerid;
        }
        $this->page->requires->js_call_amd('mod_kahoodle/game', 'init');
        $res = html_writer::start_div('', ['id' => 'mod_kahoodle_game'] + $attrs)
            . $this->render_from_template($data['template'], $data['data'])
            . html_writer::end_div();

        if ($api->can_transition()) {
            $reseturl = new \moodle_url("/mod/kahoodle/view.php",
                ['id' => $api->get_cm()->id, 'action' => 'reset', 'sesskey' => sesskey()]);
            $res .= "<div style=\"text-align: right;\">".
            html_writer::link($reseturl, "Reset game")
            ."</div>";
        }

        return $res;
    }
}
