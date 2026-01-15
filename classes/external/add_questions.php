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
use core_external\external_multiple_structure;
use core_external\external_warnings;

/**
 * Implementation of web service mod_kahoodle_add_question
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_questions extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_add_questions
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'kahoodleid' => new external_value(PARAM_INT, 'Kahoodle instance ID (this is not a course module ID)'),
                    'questiontype' => new external_value(
                        PARAM_ALPHA,
                        'Question type',
                        VALUE_DEFAULT,
                        \mod_kahoodle\constants::QUESTION_TYPE_MULTICHOICE
                    ),
                    'questiontext' => new external_value(PARAM_RAW, 'Question text'),
                    'questiontextformat' => new external_value(
                        PARAM_INT,
                        'Question text format',
                        VALUE_DEFAULT,
                        FORMAT_HTML
                    ),
                    'questionconfig' => new external_value(
                        PARAM_RAW,
                        'JSON configuration for question-specific settings',
                        VALUE_DEFAULT,
                        null
                    ),
                    'answersconfig' => new external_value(
                        PARAM_RAW,
                        'JSON configuration for answers',
                        VALUE_DEFAULT,
                        null
                    ),
                    'questionpreviewduration' => new external_value(
                        PARAM_INT,
                        'Question preview duration in seconds',
                        VALUE_DEFAULT,
                        null
                    ),
                    'questionduration' => new external_value(
                        PARAM_INT,
                        'Question duration in seconds',
                        VALUE_DEFAULT,
                        null
                    ),
                    'questionresultsduration' => new external_value(
                        PARAM_INT,
                        'Question results display duration in seconds',
                        VALUE_DEFAULT,
                        null
                    ),
                    'maxpoints' => new external_value(
                        PARAM_INT,
                        'Maximum points for correct answer',
                        VALUE_DEFAULT,
                        null
                    ),
                    'minpoints' => new external_value(
                        PARAM_INT,
                        'Minimum points for correct answer',
                        VALUE_DEFAULT,
                        null
                    ),
                    'imagedraftitemid' => new external_value(
                        PARAM_INT,
                        'Draft item ID for question image(s) file area',
                        VALUE_DEFAULT,
                        0
                    ),
                ])
            ),
        ]);
    }

    /**
     * Implementation of web service mod_kahoodle_add_questions
     *
     * @param array $questions Array of question data
     * @return array Array with questionids and warnings
     */
    public static function execute(array $questions): array {
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['questions' => $questions]
        );

        $questionids = [];
        $warnings = [];

        foreach ($params['questions'] as $index => $questiondata) {
            try {
                // Get the kahoodle instance to validate context.
                $kahoodle = $DB->get_record('kahoodle', ['id' => $questiondata['kahoodleid']], '*', MUST_EXIST);
                $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id, $kahoodle->course, false, MUST_EXIST);
                $context = \context_module::instance($cm->id);

                // Validate context.
                self::validate_context($context);

                // Check permissions.
                require_capability('mod/kahoodle:manage_questions', $context);

                // Prepare question data object.
                $question = new \stdClass();
                $question->kahoodleid = $questiondata['kahoodleid'];
                $question->questiontype = $questiondata['questiontype'];
                $question->questiontext = $questiondata['questiontext'];
                $question->questiontextformat = $questiondata['questiontextformat'];
                $question->questionconfig = $questiondata['questionconfig'];
                $question->answersconfig = $questiondata['answersconfig'];

                // Optional behavior data.
                if ($questiondata['questionpreviewduration'] !== null) {
                    $question->questionpreviewduration = $questiondata['questionpreviewduration'];
                }
                if ($questiondata['questionduration'] !== null) {
                    $question->questionduration = $questiondata['questionduration'];
                }
                if ($questiondata['questionresultsduration'] !== null) {
                    $question->questionresultsduration = $questiondata['questionresultsduration'];
                }
                if ($questiondata['maxpoints'] !== null) {
                    $question->maxpoints = $questiondata['maxpoints'];
                }
                if ($questiondata['minpoints'] !== null) {
                    $question->minpoints = $questiondata['minpoints'];
                }

                // Pass draft item ID to questions API for file handling.
                if ($questiondata['imagedraftitemid'] > 0) {
                    $question->imagedraftitemid = $questiondata['imagedraftitemid'];
                }

                // Add the question (this will also handle file uploads).
                $questionid = \mod_kahoodle\questions::add_question($question);

                $questionids[] = [
                    'index' => $index,
                    'questionid' => $questionid,
                ];
            } catch (\moodle_exception $e) {
                $warnings[] = [
                    'item' => 'question',
                    'itemid' => $index,
                    'warningcode' => $e->errorcode ?? 'error',
                    'message' => $e->getMessage(),
                ];
            } catch (\Exception $e) {
                $warnings[] = [
                    'item' => 'question',
                    'itemid' => $index,
                    'warningcode' => 'exception',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'questionids' => $questionids,
            'warnings' => $warnings,
        ];
    }

    /**
     * Describe the return structure for mod_kahoodle_add_questions
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionids' => new external_multiple_structure(
                new external_single_structure([
                    'index' => new external_value(PARAM_INT, 'Index of the question in the input array'),
                    'questionid' => new external_value(PARAM_INT, 'ID of the created question'),
                ])
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
