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

    /** @var int Question format: plain text with optional image */
    public const QUESTIONFORMAT_PLAIN = 0;

    /** @var int Question format: rich text editor */
    public const QUESTIONFORMAT_RICHTEXT = 1;

    /** @var int Maximum length for plain text questions */
    public const QUESTIONTEXT_MAXLENGTH = 300;

    /** @var string File area for question images */
    public const FILEAREA_QUESTION_IMAGE = 'questionimage';

    // Round stages.
    /** @var string Round stage: preparation (before round starts) */
    public const STAGE_PREPARATION = 'preparation';

    /** @var string Round stage: lobby (participants joining) */
    public const STAGE_LOBBY = 'lobby';

    /** @var string Round stage: question preview */
    public const STAGE_QUESTION_PREVIEW = 'questionpreview';

    /** @var string Round stage: question (participants answering) */
    public const STAGE_QUESTION = 'question';

    /** @var string Round stage: question results display */
    public const STAGE_QUESTION_RESULTS = 'questionresults';

    /** @var string Round stage: leaders (leaderboard display) */
    public const STAGE_LEADERS = 'leaders';

    /** @var string Round stage: revision (review after round) */
    public const STAGE_REVISION = 'revision';

    /** @var string Round stage: archived (round completed and archived) */
    public const STAGE_ARCHIVED = 'archived';

    /** @var array Fields in the table kahoodle_question_versions, except for primary/foreign keys, version, time stamps */
    public const FIELDS_QUESTION_VERSION = [
        'questiontext',
        'questionconfig',
    ];

    /** @var array Fields in the table kahoodle_round_questions, except for primary/foreign keys, sortorder, time stamps, stats */
    public const FIELDS_ROUND_QUESTION = [
        'questionpreviewduration',
        'questionduration',
        'questionresultsduration',
        'maxpoints',
        'minpoints',
    ];
}
