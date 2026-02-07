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

    'mod_kahoodle_create_instance' => [
        'classname' => mod_kahoodle\external\create_instance::class,
        'description' => 'Create an instance of Kahoodle module',
        'type' => 'write',
        'capabilities' => 'mod/kahoodle:addinstance',
    ],

    'mod_kahoodle_add_questions' => [
        'classname' => mod_kahoodle\external\add_questions::class,
        'description' => 'Add questions to an instance of Kahoodle',
        'type' => 'write',
        'capabilities' => 'mod/kahoodle:manage_questions',
    ],

    'mod_kahoodle_delete_question' => [
        'classname' => mod_kahoodle\external\delete_question::class,
        'description' => 'Delete Kahoodle question',
        'type' => 'write',
        'ajax' => true,
    ],

    'mod_kahoodle_preview_questions' => [
        'classname' => mod_kahoodle\external\preview_questions::class,
        'description' => 'Preview Kahoodle questions in one game round (for teachers only)',
        'type' => 'read',
        'ajax' => true,
    ],

    'mod_kahoodle_change_question_sortorder' => [
        'classname' => mod_kahoodle\external\change_question_sortorder::class,
        'description' => 'Change question sortorder in Kahoodle',
        'type' => 'write',
        'ajax' => true,
    ],

    'mod_kahoodle_duplicate_question' => [
        'classname' => mod_kahoodle\external\duplicate_question::class,
        'description' => 'Duplicate a question in Kahoodle',
        'type' => 'write',
        'ajax' => true,
    ],
];

$services = [
];
