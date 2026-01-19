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

use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\round_question;
/**
 * Data generator class
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_kahoodle_generator extends testing_module_generator {
    /**
     * Creates an instance of the module for testing purposes.
     *
     * Module type will be taken from the class name.
     *
     * @param array|stdClass $record data for module being generated. Requires 'course' key
     *     (an id or the full object). Also can have any fields from add module form.
     * @param null|array $options general options for course module, can be merged into $record
     * @return stdClass record from module-defined table with additional field
     *     cmid (corresponding id in course_modules table)
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;

        // Set default values for plugin-specific fields if not provided.
        if (!isset($record->allowrepeat)) {
            $record->allowrepeat = \mod_kahoodle\constants::DEFAULT_ALLOW_REPEAT;
        }
        if (!isset($record->lobbyduration)) {
            $record->lobbyduration = \mod_kahoodle\constants::DEFAULT_LOBBY_DURATION;
        }
        if (!isset($record->questionpreviewduration)) {
            $record->questionpreviewduration = \mod_kahoodle\constants::DEFAULT_QUESTION_PREVIEW_DURATION;
        }
        if (!isset($record->questionduration)) {
            $record->questionduration = \mod_kahoodle\constants::DEFAULT_QUESTION_DURATION;
        }
        if (!isset($record->questionresultsduration)) {
            $record->questionresultsduration = \mod_kahoodle\constants::DEFAULT_QUESTION_RESULTS_DURATION;
        }
        if (!isset($record->maxpoints)) {
            $record->maxpoints = \mod_kahoodle\constants::DEFAULT_MAX_POINTS;
        }
        if (!isset($record->minpoints)) {
            $record->minpoints = \mod_kahoodle\constants::DEFAULT_MIN_POINTS;
        }
        if (!isset($record->questionformat)) {
            $record->questionformat = \mod_kahoodle\constants::QUESTIONFORMAT_PLAIN;
        }

        $instance = parent::create_instance($record, (array)$options);

        return $instance;
    }

    /**
     * Creates a question for a Kahoodle instance for testing purposes.
     *
     * @param array|stdClass $record data for question being generated. Requires 'kahoodleid' key.
     *     Optional fields:
     *     - questiontype: Type of question (default: 'multichoice')
     *     - questiontext: Question text (default: 'Sample question')
     *     - questionconfig: Type-specific configuration (may be required)
     *     - questionpreviewduration: Preview duration override (default: null)
     *     - questionduration: Question duration override (default: null)
     *     - questionresultsduration: Results duration override (default: null)
     *     - maxpoints: Maximum points override (default: null)
     *     - minpoints: Minimum points override (default: null)
     * @return round_question The question entity
     */
    public function create_question($record): round_question {
        global $DB;
        static $counter = 1;

        $record = (object)(array)$record;

        if (empty($record->kahoodleid)) {
            throw new coding_exception('kahoodleid must be specified when creating a question');
        }

        if (!isset($record->questiontext)) {
            $record->questiontext = 'Sample question ' . $counter++;
        }
        if (($record->questiontype ?? 'multichoice') === 'multichoice' && empty($record->questionconfig)) {
            $record->questionconfig = "Option 1\n*Option 2\nOption 3";
        }

        // Use the questions API to add the question.
        return \mod_kahoodle\questions::add_question($record);
    }
}
