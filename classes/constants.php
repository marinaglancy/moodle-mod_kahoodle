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

namespace mod_kahoodle;

/**
 * Class constants
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {
    /** @var int Default value for allow repeat participation */
    public const DEFAULT_ALLOW_REPEAT = 0;

    /** @var int Default lobby duration in seconds (5 minutes) */
    public const DEFAULT_LOBBY_DURATION = 300;

    /** @var int Default question preview duration in seconds */
    public const DEFAULT_QUESTION_PREVIEW_DURATION = 10;

    /** @var int Default question duration in seconds */
    public const DEFAULT_QUESTION_DURATION = 30;

    /** @var int Default question results display duration in seconds */
    public const DEFAULT_QUESTION_RESULTS_DURATION = 10;

    /** @var int Default maximum points for fastest correct answer */
    public const DEFAULT_MAX_POINTS = 1000;

    /** @var int Default minimum points for slowest correct answer */
    public const DEFAULT_MIN_POINTS = 500;
}
