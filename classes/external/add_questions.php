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
                        'Question type. Currently only "multichoice" is supported. Default is "multichoice".',
                        VALUE_OPTIONAL,
                    ),
                    'questiontext' => new external_value(PARAM_RAW, 'Question text'),
                    'questionconfig' => new external_value(
                        PARAM_RAW,
                        "Type-specific configuration.\n\n" .
                        "For multichoice question, provide possible answers, one per line, with the " .
                        "correct answer prefixed with '*'.\n\n" .
                        "For some question types the value is required.",
                        VALUE_OPTIONAL
                    ),
                    'questionpreviewduration' => new external_value(
                        PARAM_INT,
                        'Question preview duration in seconds. Do not set to use the default value.',
                        VALUE_OPTIONAL,
                    ),
                    'questionduration' => new external_value(
                        PARAM_INT,
                        'Question duration in seconds. Do not set to use the default value.',
                        VALUE_OPTIONAL,
                    ),
                    'questionresultsduration' => new external_value(
                        PARAM_INT,
                        'Question results display duration in seconds. Do not set to use the default value.',
                        VALUE_OPTIONAL,
                    ),
                    'maxpoints' => new external_value(
                        PARAM_INT,
                        'Maximum points for correct answer. Do not set to use the default value.',
                        VALUE_OPTIONAL,
                    ),
                    'minpoints' => new external_value(
                        PARAM_INT,
                        'Minimum points for correct answer. Do not set to use the default value.',
                        VALUE_OPTIONAL,
                    ),
                    'imagedraftitemid' => new external_value(
                        PARAM_INT,
                        'Draft item ID for question image(s) file area. ' .
                        'If kahoodle instance setting is to use rich text questions, ' .
                        'these files can be referenced from the questiontext as @@PLUGINFILE@@. ' .
                        'If question format is plain text, these images will be shown underneath the question text.',
                        VALUE_OPTIONAL,
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
                $round = \mod_kahoodle\questions::get_last_round($questiondata['kahoodleid']);
                if (!$round->is_fully_editable()) {
                    throw new \moodle_exception('noeditableround', 'mod_kahoodle');
                }
                $context = $round->get_context();

                // Validate context.
                self::validate_context($context);

                // Check permissions.
                require_capability('mod/kahoodle:manage_questions', $context);

                // Add the question (this will also handle file uploads).
                $roundquestion = \mod_kahoodle\questions::add_question((object)$questiondata);

                $questionids[] = [
                    'index' => $index,
                    'questionid' => $roundquestion->get_question_id(),
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
