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

namespace mod_kahoodle\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_api;
use core_external\external_value;
use mod_kahoodle\constants;

/**
 * Implementation of web service mod_kahoodle_create_instance
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_instance extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_create_instance
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'intro' => new external_value(PARAM_RAW, 'Activity description (intro text)', VALUE_DEFAULT, ''),
            'introformat' => new external_value(
                PARAM_INT,
                'Intro format (1 = HTML, 0 = MOODLE, 2 = PLAIN, 4 = MARKDOWN)',
                VALUE_DEFAULT,
                FORMAT_HTML
            ),
            'introdraftitemid' => new external_value(
                PARAM_INT,
                'Draft file area ID for intro attachments',
                VALUE_DEFAULT,
                0
            ),
            'visible' => new external_value(PARAM_INT, 'Visibility (1 = visible, 0 = hidden)', VALUE_DEFAULT, 1),
            'allowrepeat' => new external_value(
                PARAM_INT,
                'Allow repeat participation (1 = yes, 0 = no)',
                VALUE_DEFAULT,
                constants::DEFAULT_ALLOW_REPEAT
            ),
            'lobbyduration' => new external_value(
                PARAM_INT,
                'Lobby duration in seconds',
                VALUE_DEFAULT,
                constants::DEFAULT_LOBBY_DURATION
            ),
            'questionpreviewduration' => new external_value(
                PARAM_INT,
                'Question preview duration in seconds',
                VALUE_DEFAULT,
                constants::DEFAULT_QUESTION_PREVIEW_DURATION
            ),
            'questionduration' => new external_value(
                PARAM_INT,
                'Question duration in seconds',
                VALUE_DEFAULT,
                constants::DEFAULT_QUESTION_DURATION
            ),
            'questionresultsduration' => new external_value(
                PARAM_INT,
                'Question results duration in seconds',
                VALUE_DEFAULT,
                constants::DEFAULT_QUESTION_RESULTS_DURATION
            ),
            'defaultmaxpoints' => new external_value(
                PARAM_INT,
                'Maximum points for correct answer',
                VALUE_DEFAULT,
                constants::DEFAULT_MAX_POINTS
            ),
            'defaultminpoints' => new external_value(
                PARAM_INT,
                'Minimum points for correct answer',
                VALUE_DEFAULT,
                constants::DEFAULT_MIN_POINTS
            ),
        ]);
    }

    /**
     * Implementation of web service mod_kahoodle_create_instance
     *
     * @param int $courseid Course ID
     * @param int $section Section number
     * @param string $name Activity name
     * @param string $intro Activity description
     * @param int $introformat Intro format
     * @param int $introdraftitemid Draft file area ID for intro attachments
     * @param int $visible Visibility
     * @param int $allowrepeat Allow repeat participation
     * @param int $lobbyduration Lobby duration in seconds
     * @param int $questionpreviewduration Question preview duration in seconds
     * @param int $questionduration Question duration in seconds
     * @param int $questionresultsduration Question results duration in seconds
     * @param int $defaultmaxpoints Maximum points
     * @param int $defaultminpoints Minimum points
     * @return array Course module ID and instance ID
     */
    public static function execute(
        int $courseid,
        int $section,
        string $name,
        string $intro = '',
        int $introformat = FORMAT_HTML,
        int $introdraftitemid = 0,
        int $visible = 1,
        int $allowrepeat = constants::DEFAULT_ALLOW_REPEAT,
        int $lobbyduration = constants::DEFAULT_LOBBY_DURATION,
        int $questionpreviewduration = constants::DEFAULT_QUESTION_PREVIEW_DURATION,
        int $questionduration = constants::DEFAULT_QUESTION_DURATION,
        int $questionresultsduration = constants::DEFAULT_QUESTION_RESULTS_DURATION,
        int $defaultmaxpoints = constants::DEFAULT_MAX_POINTS,
        int $defaultminpoints = constants::DEFAULT_MIN_POINTS
    ): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'courseid' => $courseid,
                'section' => $section,
                'name' => $name,
                'intro' => $intro,
                'introformat' => $introformat,
                'introdraftitemid' => $introdraftitemid,
                'visible' => $visible,
                'allowrepeat' => $allowrepeat,
                'lobbyduration' => $lobbyduration,
                'questionpreviewduration' => $questionpreviewduration,
                'questionduration' => $questionduration,
                'questionresultsduration' => $questionresultsduration,
                'defaultmaxpoints' => $defaultmaxpoints,
                'defaultminpoints' => $defaultminpoints,
            ]
        );

        // Get course and validate context.
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        self::validate_context($context);

        // Prepare module info data - this checks capabilities (addinstance).
        [, , , , $moduleinfo] = \prepare_new_moduleinfo_data($course, 'kahoodle', $params['section']);

        // Set basic properties.
        $moduleinfo->modulename = 'kahoodle';
        $moduleinfo->name = $params['name'];
        $moduleinfo->visible = $params['visible'];

        // Set intro with file handling.
        $moduleinfo->introeditor = [
            'text' => $params['intro'],
            'format' => $params['introformat'],
            'itemid' => $params['introdraftitemid'],
        ];

        // Set Kahoodle-specific fields.
        $moduleinfo->allowrepeat = $params['allowrepeat'];
        $moduleinfo->lobbyduration = $params['lobbyduration'];
        $moduleinfo->questionpreviewduration = $params['questionpreviewduration'];
        $moduleinfo->questionduration = $params['questionduration'];
        $moduleinfo->questionresultsduration = $params['questionresultsduration'];
        $moduleinfo->defaultmaxpoints = $params['defaultmaxpoints'];
        $moduleinfo->defaultminpoints = $params['defaultminpoints'];

        // Create the module instance.
        $moduleinfo = \add_moduleinfo($moduleinfo, $course);

        return [
            'coursemoduleid' => $moduleinfo->coursemodule,
            'instanceid' => $moduleinfo->instance,
        ];
    }

    /**
     * Describe the return structure for mod_kahoodle_create_instance
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'instanceid' => new external_value(PARAM_INT, 'Kahoodle instance ID'),
        ]);
    }
}
