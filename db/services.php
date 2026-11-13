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

/**
 * External functions and service declaration for Kahoodle
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * @package    mod_kahoodle
 * @category   webservice
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    // Functions for any client, including the AI agent (tool_aiagent) and MCP servers. The 'tool_aiagent' key is
    // read only by tool_aiagent: AIAGENT_WS_GUIDE.md in that plugin documents it.
    'mod_kahoodle_create_modules' => [
        'classname' => mod_kahoodle\external\create_modules::class,
        'description' => 'Create (add) one or more Kahoodle activities, live classroom quiz games, in a course. ' .
            'Add their questions afterwards with mod_kahoodle_create_questions.',
        'type' => 'write',
        'capabilities' => 'mod/kahoodle:addinstance',
        'tool_aiagent' => ['relevancescore' => 8, 'tags' => ['create', 'content', 'teacher']],
    ],

    'mod_kahoodle_pre_update' => [
        'classname' => mod_kahoodle\external\pre_update::class,
        'description' => 'Read (get) the settings of a Kahoodle activity as they are stored, before changing them ' .
            'with mod_kahoodle_update_module.',
        'type' => 'read',
        'capabilities' => 'moodle/course:manageactivities',
        'tool_aiagent' => ['relevancescore' => 7, 'tags' => ['read', 'content', 'teacher']],
    ],

    'mod_kahoodle_update_module' => [
        'classname' => mod_kahoodle\external\update_module::class,
        'description' => 'Edit (update, rename, hide, configure) a Kahoodle activity: its name, description, ' .
            'visibility, timing, points and identity mode. Call mod_kahoodle_pre_update first.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'tool_aiagent' => ['relevancescore' => 7, 'tags' => ['update', 'content', 'teacher']],
    ],

    'mod_kahoodle_get_questions' => [
        'classname' => mod_kahoodle\external\get_questions::class,
        'description' => 'List (read, view) the questions of a Kahoodle activity in the order they are played, ' .
            'optionally with their answers, timing and points.',
        'type' => 'read',
        'capabilities' => 'mod/kahoodle:manage_questions',
        'tool_aiagent' => ['relevancescore' => 8, 'tags' => ['read', 'content', 'teacher']],
    ],

    'mod_kahoodle_question_pre_update' => [
        'classname' => mod_kahoodle\external\question_pre_update::class,
        'description' => 'Read (get) Kahoodle questions as they are stored, before changing them with ' .
            'mod_kahoodle_update_question.',
        'type' => 'read',
        'capabilities' => 'mod/kahoodle:manage_questions',
        'tool_aiagent' => ['relevancescore' => 6, 'tags' => ['read', 'content', 'teacher']],
    ],

    'mod_kahoodle_create_questions' => [
        'classname' => mod_kahoodle\external\create_questions::class,
        'description' => 'Add (create, write, generate) questions to a Kahoodle activity, before its next round starts.',
        'type' => 'write',
        'capabilities' => 'mod/kahoodle:manage_questions',
        'tool_aiagent' => ['relevancescore' => 8, 'tags' => ['create', 'content', 'teacher']],
    ],

    'mod_kahoodle_update_question' => [
        'classname' => mod_kahoodle\external\update_question::class,
        'description' => 'Edit (update, rewrite, fix, move, reorder) one question of a Kahoodle activity. '
            . 'Call mod_kahoodle_question_pre_update first.',
        'type' => 'write',
        'capabilities' => 'mod/kahoodle:manage_questions',
        'ajax' => true,
        'tool_aiagent' => ['relevancescore' => 8, 'tags' => ['update', 'content', 'teacher']],
    ],

    'mod_kahoodle_delete_questions' => [
        'classname' => mod_kahoodle\external\delete_questions::class,
        'description' => 'Delete (remove) questions from a Kahoodle activity, before its next round starts.',
        'type' => 'write',
        'capabilities' => 'mod/kahoodle:manage_questions',
        'ajax' => true,
        'tool_aiagent' => ['relevancescore' => 7, 'tags' => ['delete', 'content', 'teacher']],
    ],

    'mod_kahoodle_prepare_round' => [
        'classname' => mod_kahoodle\external\prepare_round::class,
        'description' => 'Prepare a new round of a Kahoodle activity after the last one has finished, so that its ' .
            'questions can be added, removed and moved again.',
        'type' => 'write',
        'capabilities' => 'mod/kahoodle:facilitate',
        'tool_aiagent' => ['relevancescore' => 6, 'tags' => ['create', 'teacher']],
    ],

    'mod_kahoodle_get_rounds' => [
        'classname' => mod_kahoodle\external\get_rounds::class,
        'description' => 'List the rounds (games played) of a Kahoodle activity with their status, dates, ' .
            'number of participants and scores.',
        'type' => 'read',
        'capabilities' => 'mod/kahoodle:viewresults',
        'tool_aiagent' => ['relevancescore' => 7, 'tags' => ['read', 'teacher']],
    ],

    'mod_kahoodle_get_round_results' => [
        'classname' => mod_kahoodle\external\get_round_results::class,
        'description' => 'Get the results (leaderboard, ranks, scores, correct answers) of one round of a Kahoodle activity.',
        'type' => 'read',
        'capabilities' => 'mod/kahoodle:viewresults',
        'tool_aiagent' => ['relevancescore' => 7, 'tags' => ['read', 'teacher']],
    ],

    // Functions for the Kahoodle pages. The AI agent starts with them disabled.
    'mod_kahoodle_duplicate_question' => [
        'classname' => mod_kahoodle\external\duplicate_question::class,
        'description' => 'Duplicate a question in Kahoodle',
        'type' => 'write',
        'capabilities' => 'mod/kahoodle:manage_questions',
        'ajax' => true,
        'tool_aiagent' => ['relevancescore' => 2, 'tags' => ['create', 'teacher']],
    ],

    'mod_kahoodle_preview_questions' => [
        'classname' => mod_kahoodle\external\preview_questions::class,
        'description' => 'Preview Kahoodle questions in one game round (for teachers only)',
        'type' => 'read',
        'capabilities' => 'mod/kahoodle:manage_questions',
        'ajax' => true,
        'tool_aiagent' => ['relevancescore' => 1, 'tags' => ['read', 'teacher']],
    ],

    'mod_kahoodle_playback_stages' => [
        'classname' => mod_kahoodle\external\playback_stages::class,
        'description' => 'Get playback stages for replaying completed Kahoodle rounds',
        'type' => 'read',
        'capabilities' => 'mod/kahoodle:viewresults',
        'ajax' => true,
        'tool_aiagent' => ['relevancescore' => 1, 'tags' => ['read', 'teacher']],
    ],
];

$services = [
];
