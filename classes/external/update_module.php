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
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Implementation of web service mod_kahoodle_update_module
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_module extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_update_module
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Kahoodle activity'),
            'name' => new external_value(PARAM_TEXT, 'Activity name', VALUE_DEFAULT, null),
        ] + helper::common_parameters(true) + helper::settings_parameters(true));
    }

    /**
     * Change the settings of a Kahoodle activity. Only the parameters given change.
     *
     * @param int $cmid
     * @param string|null $name
     * @param string|null $intro
     * @param int|null $introformat
     * @param int|null $introdraftitemid
     * @param int|null $visible
     * @param string|null $idnumber
     * @param string|null $lang
     * @param array|null $tags
     * @param int|null $questionformat
     * @param int|null $identitymode
     * @param int|null $allowrepeat
     * @param int|null $lobbyduration
     * @param int|null $questionpreviewduration
     * @param int|null $questionduration
     * @param int|null $questionresultsduration
     * @param int|null $maxpoints
     * @param int|null $minpoints
     * @return array
     */
    public static function execute(
        int $cmid,
        ?string $name = null,
        ?string $intro = null,
        ?int $introformat = null,
        ?int $introdraftitemid = null,
        ?int $visible = null,
        ?string $idnumber = null,
        ?string $lang = null,
        ?array $tags = null,
        ?int $questionformat = null,
        ?int $identitymode = null,
        ?int $allowrepeat = null,
        ?int $lobbyduration = null,
        ?int $questionpreviewduration = null,
        ?int $questionduration = null,
        ?int $questionresultsduration = null,
        ?int $maxpoints = null,
        ?int $minpoints = null
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = array_filter(self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'name' => $name,
            'intro' => $intro,
            'introformat' => $introformat,
            'introdraftitemid' => $introdraftitemid,
            'visible' => $visible,
            'idnumber' => $idnumber,
            'lang' => $lang,
            'tags' => $tags,
            'questionformat' => $questionformat,
            'identitymode' => $identitymode,
            'allowrepeat' => $allowrepeat,
            'lobbyduration' => $lobbyduration,
            'questionpreviewduration' => $questionpreviewduration,
            'questionduration' => $questionduration,
            'questionresultsduration' => $questionresultsduration,
            'maxpoints' => $maxpoints,
            'minpoints' => $minpoints,
        ]), fn($value) => $value !== null);
        [$cm, $context] = helper::validate_cm($params['cmid']);
        require_capability('moodle/course:manageactivities', $context);

        $course = get_course($cm->course);
        [$cm, , , $data] = \get_moduleinfo_data(get_coursemodule_from_id('kahoodle', $cm->id, 0, false, MUST_EXIST), $course);
        foreach (['name', 'lang', 'tags'] as $field) {
            if (array_key_exists($field, $params)) {
                $data->$field = $params[$field];
            }
        }
        if (array_key_exists('idnumber', $params)) {
            $data->cmidnumber = $params['idnumber'];
        }
        if (array_key_exists('visible', $params)) {
            helper::apply_visibility($data, $params['visible']);
        }
        // The prepared editor keeps the stored files unless a new draft area replaces them.
        if (array_key_exists('intro', $params)) {
            $data->introeditor['text'] = $params['intro'];
        }
        if (array_key_exists('introformat', $params)) {
            $data->introeditor['format'] = $params['introformat'];
        }
        if (array_key_exists('introdraftitemid', $params)) {
            $data->introeditor['itemid'] = $params['introdraftitemid'];
        }
        foreach (array_keys(helper::settings_parameters(true)) as $field) {
            if (array_key_exists($field, $params)) {
                $data->$field = $params[$field];
            }
        }
        \update_moduleinfo($cm, $data, $course);
        return [];
    }

    /**
     * Describe the return structure for mod_kahoodle_update_module
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([]);
    }
}
