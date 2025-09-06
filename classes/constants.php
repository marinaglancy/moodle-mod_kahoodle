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

/**
 * Constants for the kahoodle module
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {
    /** @var string game state: game did not start yet, teacher is editing, players are not allowed to join */
    public const STATE_PREPARATION = 'PREPARATION';
    /** @var string game state: waiting for players to join (aka lobby) */
    public const STATE_WAITING = 'WAITING';
    /** @var string game state: game is in progress */
    public const STATE_INPROGRESS = 'INPROGRESS';
    /** @var string game state: game is finished */
    public const STATE_DONE = 'DONE';

    /** @var string question state: waiting for answers to the current question */
    public const QSTATE_ASKING = 'ASKING';
    /** @var string question state: showing results for the current question */
    public const QSTATE_RESULTS = 'RESULTS';
    /** @var string question state: showing leaderboard for the current question */
    public const QSTATE_LEADERBOARD = 'LEADERBOARD';
}
