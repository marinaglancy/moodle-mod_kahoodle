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
use core_external\external_multiple_structure;
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
            'activity' => new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'section' => new external_value(PARAM_INT, 'Section number'),
                'name' => new external_value(PARAM_TEXT, 'Activity name'),
                'intro' => new external_value(PARAM_RAW, 'Activity description (intro text)', VALUE_OPTIONAL),
                'introformat' => new external_value(
                    PARAM_INT,
                    'Intro format (1 = HTML, 0 = MOODLE, 2 = PLAIN, 4 = MARKDOWN)',
                    VALUE_OPTIONAL
                ),
                'introdraftitemid' => new external_value(
                    PARAM_INT,
                    'Draft file area ID for intro attachments. ' .
                    'These files can be referenced from the intro text as @@PLUGINFILE@@.',
                    VALUE_OPTIONAL
                ),
                'visible' => new external_value(
                    PARAM_INT,
                    "Availability: 1 = Show on course page, 0 = Hide on course page, " .
                    "-1 = Make available but don't show on course page (if allowed in site settings)",
                    VALUE_DEFAULT,
                    1
                ),
                'idnumber' => new external_value(PARAM_RAW, 'ID number', VALUE_OPTIONAL),
                'lang' => new external_value(
                    PARAM_LANG,
                    'Force language (e.g. "en", "de"). Do not set if you do not want to force it.',
                    VALUE_OPTIONAL
                ),
                'tags' => new external_multiple_structure(
                    new external_value(PARAM_TAG, 'Tag name'),
                    'Tags',
                    VALUE_OPTIONAL
                ),
                'allowrepeat' => new external_value(
                    PARAM_INT,
                    'Allow repeat participation (1 = yes, 0 = no)',
                    VALUE_OPTIONAL
                ),
                'lobbyduration' => new external_value(
                    PARAM_INT,
                    'Lobby duration in seconds',
                    VALUE_OPTIONAL
                ),
                'questionpreviewduration' => new external_value(
                    PARAM_INT,
                    'Question preview duration in seconds',
                    VALUE_OPTIONAL
                ),
                'questionduration' => new external_value(
                    PARAM_INT,
                    'Question duration in seconds',
                    VALUE_OPTIONAL
                ),
                'questionresultsduration' => new external_value(
                    PARAM_INT,
                    'Question results duration in seconds',
                    VALUE_OPTIONAL
                ),
                'defaultmaxpoints' => new external_value(
                    PARAM_INT,
                    'Maximum points for correct answer',
                    VALUE_OPTIONAL
                ),
                'defaultminpoints' => new external_value(
                    PARAM_INT,
                    'Minimum points for correct answer',
                    VALUE_OPTIONAL
                ),
            ]),
        ]);
    }

    /**
     * Implementation of web service mod_kahoodle_create_instance
     *
     * @param array $activity Activity data
     * @return array Course module ID and instance ID
     */
    public static function execute(array $activity): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['activity' => $activity]
        )['activity'];

        // Apply defaults for optional parameters.
        $params['intro'] = $params['intro'] ?? '';
        $params['introformat'] = $params['introformat'] ?? FORMAT_HTML;
        $params['introdraftitemid'] = $params['introdraftitemid'] ?? 0;
        $params['visible'] = $params['visible'] ?? 1;
        $params['allowrepeat'] = $params['allowrepeat'] ?? constants::DEFAULT_ALLOW_REPEAT;
        $params['lobbyduration'] = $params['lobbyduration'] ?? constants::DEFAULT_LOBBY_DURATION;
        $params['questionpreviewduration'] = $params['questionpreviewduration'] ?? constants::DEFAULT_QUESTION_PREVIEW_DURATION;
        $params['questionduration'] = $params['questionduration'] ?? constants::DEFAULT_QUESTION_DURATION;
        $params['questionresultsduration'] = $params['questionresultsduration'] ?? constants::DEFAULT_QUESTION_RESULTS_DURATION;
        $params['defaultmaxpoints'] = $params['defaultmaxpoints'] ?? constants::DEFAULT_MAX_POINTS;
        $params['defaultminpoints'] = $params['defaultminpoints'] ?? constants::DEFAULT_MIN_POINTS;

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

        // Set optional common module properties.
        if (!empty($params['idnumber'])) {
            $moduleinfo->cmidnumber = $params['idnumber'];
        }
        if (!empty($params['lang'])) {
            $moduleinfo->lang = $params['lang'];
        }
        if (!empty($params['tags'])) {
            $moduleinfo->tags = $params['tags'];
        }

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
