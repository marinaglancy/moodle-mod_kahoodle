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

namespace mod_kahoodle\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Implementation of web service mod_kahoodle_create_modules
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_modules extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_create_modules
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'modules' => new external_multiple_structure(
                new external_single_structure([
                    'section' => new external_value(
                        PARAM_INT,
                        'Section number in the course (0 is the top section).',
                        VALUE_DEFAULT,
                        0
                    ),
                    'name' => new external_value(PARAM_TEXT, 'Activity name'),
                ] + helper::common_parameters(false) + helper::settings_parameters(false)),
                'Kahoodle activities to create. Add the questions afterwards with mod_kahoodle_create_questions.'
            ),
        ]);
    }

    /**
     * Create Kahoodle activities in a course
     *
     * @param int $courseid
     * @param array $modules
     * @return array
     */
    public static function execute(int $courseid, array $modules): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        ['courseid' => $courseid, 'modules' => $modules] = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'modules' => $modules]
        );
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        self::validate_context(\context_course::instance($course->id));

        $created = [];
        $warnings = [];
        foreach ($modules as $index => $module) {
            try {
                // Checks mod/kahoodle:addinstance in the course.
                [, , , , $moduleinfo] = \prepare_new_moduleinfo_data($course, 'kahoodle', $module['section']);
                $moduleinfo->name = $module['name'];
                helper::apply_visibility($moduleinfo, $module['visible']);
                $moduleinfo->cmidnumber = $module['idnumber'];
                $moduleinfo->lang = $module['lang'];
                $moduleinfo->tags = $module['tags'];
                $moduleinfo->introeditor = [
                    'text' => $module['intro'],
                    'format' => $module['introformat'],
                    'itemid' => $module['introdraftitemid'],
                ];
                foreach (array_keys(helper::settings_parameters(false)) as $field) {
                    $moduleinfo->$field = $module[$field];
                }
                $moduleinfo = \add_moduleinfo($moduleinfo, $course);
                $created[] = [
                    'index' => $index,
                    'cmid' => $moduleinfo->coursemodule,
                    'kahoodleid' => $moduleinfo->instance,
                ];
            } catch (\Throwable $e) {
                $warnings[] = helper::warning('module', $index, $e);
            }
        }
        return ['modules' => $created, 'warnings' => $warnings];
    }

    /**
     * Describe the return structure for mod_kahoodle_create_modules
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'modules' => new external_multiple_structure(
                new external_single_structure([
                    'index' => new external_value(PARAM_INT, 'Index of the activity in the modules parameter'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id of the new activity'),
                    'kahoodleid' => new external_value(PARAM_INT, 'Kahoodle instance id of the new activity'),
                ]),
                'The activities that were created'
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
