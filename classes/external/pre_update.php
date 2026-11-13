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
use mod_kahoodle\local\game\questions;

/**
 * Implementation of web service mod_kahoodle_pre_update
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pre_update extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_pre_update
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Kahoodle activity'),
        ]);
    }

    /**
     * The settings of a Kahoodle activity as they are stored, named as mod_kahoodle_update_module takes them
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB;
        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        [$cm, $context, $kahoodle] = helper::validate_cm($cmid);
        require_capability('moodle/course:manageactivities', $context);

        $round = questions::get_last_round($kahoodle->id);
        $result = [
            'cmid' => $cm->id,
            'kahoodleid' => $kahoodle->id,
            'courseid' => $cm->course,
            'name' => $kahoodle->name,
            'intro' => $kahoodle->intro,
            'introformat' => (int)$kahoodle->introformat,
            'visible' => !$cm->visible ? 0 : ($cm->visibleoncoursepage ? 1 : -1),
            'idnumber' => (string)$cm->idnumber,
            'lang' => (string)$cm->lang,
            'tags' => array_values(\core_tag_tag::get_item_tags_array('core', 'course_modules', $cm->id)),
            'questioncount' => $round->get_questions_count(),
            'lastroundeditable' => $round->is_fully_editable(),
        ];
        foreach (array_keys(helper::settings_parameters(true)) as $field) {
            $result[$field] = (int)$kahoodle->$field;
        }
        return $result;
    }

    /**
     * Describe the return structure for mod_kahoodle_pre_update
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $settings = [];
        foreach (helper::settings_parameters(true) as $field => $description) {
            $settings[$field] = new external_value(PARAM_INT, $description->desc);
        }
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'kahoodleid' => new external_value(PARAM_INT, 'Kahoodle instance id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'intro' => new external_value(PARAM_RAW, 'Description as stored, with @@PLUGINFILE@@ for embedded files'),
            'introformat' => new external_value(PARAM_INT, 'Format of intro'),
            'visible' => new external_value(
                PARAM_INT,
                '1 = shown on the course page, 0 = hidden, -1 = available but not shown on the course page'
            ),
            'idnumber' => new external_value(PARAM_RAW, 'ID number'),
            'lang' => new external_value(PARAM_RAW, 'Forced language, empty when none'),
            'tags' => new external_multiple_structure(new external_value(PARAM_TAG, 'Tag name'), 'Tags'),
            'questioncount' => new external_value(PARAM_INT, 'Number of questions in the last round'),
            'lastroundeditable' => new external_value(
                PARAM_BOOL,
                'Whether the last round has not started yet, so its questions can be added, removed and moved'
            ),
        ] + $settings);
    }
}
