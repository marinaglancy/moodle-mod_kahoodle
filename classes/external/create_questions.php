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
use mod_kahoodle\constants;
use mod_kahoodle\local\game\questions;

/**
 * Implementation of web service mod_kahoodle_create_questions
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_questions extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_create_questions
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Kahoodle activity'),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'questiontype' => new external_value(PARAM_ALPHANUMEXT, helper::questiontype_description(),
                        VALUE_OPTIONAL),
                    'questiontext' => new external_value(PARAM_RAW,
                        'Question text. In plain text format (the activity\'s questionformat 0) it is at most ' .
                        constants::QUESTIONTEXT_MAXLENGTH . ' characters. In rich text format (1) it is HTML whose main ' .
                        'question is in an <h3> tag, and it can embed files from imagedraftitemid as ' .
                        '@@PLUGINFILE@@/filename.ext.'),
                    'questionconfig' => new external_value(PARAM_RAW, helper::questionconfig_description(),
                        VALUE_OPTIONAL),
                    'imagedraftitemid' => new external_value(PARAM_INT,
                        'Draft file area id with images: in plain text format one image shown under the text, ' .
                        'in rich text format the files embedded in questiontext.', VALUE_OPTIONAL),
                ] + helper::question_override_parameters(' Omit to use the activity default.', false)),
                'Questions to add, in order'
            ),
            'position' => new external_value(PARAM_INT,
                'Position (1-based) of the first new question in the round; the others follow it. ' .
                '0 adds them at the end.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Add questions to the round of a Kahoodle activity that has not started yet
     *
     * @param int $cmid
     * @param array $questions
     * @param int $position
     * @return array
     */
    public static function execute(int $cmid, array $questions, int $position = 0): array {
        ['cmid' => $cmid, 'questions' => $questions, 'position' => $position] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'questions' => $questions, 'position' => $position]
        );
        [, $context, $kahoodle] = helper::validate_cm($cmid);
        require_capability('mod/kahoodle:manage_questions', $context);
        $round = questions::get_last_round($kahoodle->id);
        helper::require_editable_round($round);

        $created = [];
        $warnings = [];
        foreach ($questions as $index => $question) {
            try {
                $roundquestion = questions::add_question(helper::question_data($question), $round);
                if ($position > 0) {
                    questions::change_question_sortorder($roundquestion, $position + count($created));
                }
                $created[] = ['index' => $index, 'questionid' => $roundquestion->get_question_id()];
            } catch (\Throwable $e) {
                $warnings[] = helper::warning('question', $index, $e);
            }
        }
        return ['questions' => $created, 'warnings' => $warnings];
    }

    /**
     * Describe the return structure for mod_kahoodle_create_questions
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'index' => new external_value(PARAM_INT, 'Index of the question in the questions parameter'),
                    'questionid' => new external_value(PARAM_INT, 'Id of the new question'),
                ]),
                'The questions that were added'
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
