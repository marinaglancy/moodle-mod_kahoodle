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
use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round_question;
use mod_kahoodle\local\game\questions;

/**
 * Implementation of web service mod_kahoodle_get_questions
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_questions extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_get_questions
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Kahoodle activity'),
            'includedetails' => new external_value(PARAM_BOOL,
                'Also return each question\'s configuration (for example the answers), timing and points.',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * The questions of the last round of a Kahoodle activity, in the order they are played
     *
     * @param int $cmid
     * @param bool $includedetails
     * @return array
     */
    public static function execute(int $cmid, bool $includedetails = false): array {
        ['cmid' => $cmid, 'includedetails' => $includedetails] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'includedetails' => $includedetails]
        );
        [$cm, $context, $kahoodle] = helper::validate_cm($cmid);
        require_capability('mod/kahoodle:manage_questions', $context);

        $round = questions::get_last_round($kahoodle->id);
        $result = [];
        foreach (round_question::get_all_questions_for_round($round) as $roundquestion) {
            $data = $roundquestion->get_data();
            $question = [
                'questionid' => $roundquestion->get_question_id(),
                'position' => (int)$data->sortorder,
                'questiontype' => $data->questiontype,
                'questiontext' => trim(html_to_text(format_text($data->questiontext,
                    $data->questionformat == constants::QUESTIONFORMAT_RICHTEXT ? FORMAT_HTML : FORMAT_PLAIN,
                    ['context' => $context, 'filter' => false]), 0, false)),
            ];
            if ($includedetails) {
                $question += [
                    'questionconfig' => (string)$data->questionconfig,
                    'questionpreviewduration' => $roundquestion->get_stage_duration(constants::STAGE_QUESTION_PREVIEW),
                    'questionduration' => $roundquestion->get_stage_duration(constants::STAGE_QUESTION),
                    'questionresultsduration' => $roundquestion->get_stage_duration(constants::STAGE_QUESTION_RESULTS),
                    'maxpoints' => $roundquestion->get_max_points(),
                    'minpoints' => (int)($data->minpoints ?? $kahoodle->minpoints),
                    'imagecount' => count($roundquestion->get_question_files()),
                    'hasresponses' => questions::question_has_responses($roundquestion->get_question_id()),
                ];
            }
            $result[] = $question;
        }
        return [
            'cmid' => $cm->id,
            'kahoodleid' => $kahoodle->id,
            'roundid' => $round->get_id(),
            'roundeditable' => $round->is_fully_editable(),
            'questions' => $result,
        ];
    }

    /**
     * Describe the return structure for mod_kahoodle_get_questions
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'kahoodleid' => new external_value(PARAM_INT, 'Kahoodle instance id'),
            'roundid' => new external_value(PARAM_INT, 'Id of the last round, which these questions belong to'),
            'roundeditable' => new external_value(PARAM_BOOL,
                'Whether the round has not started, so questions can be added, removed and moved'),
            'questions' => new external_multiple_structure(new external_single_structure([
                'questionid' => new external_value(PARAM_INT, 'Question id'),
                'position' => new external_value(PARAM_INT, 'Position in the round, 1-based'),
                'questiontype' => new external_value(PARAM_ALPHANUMEXT, 'Question type'),
                'questiontext' => new external_value(PARAM_TEXT, 'Question text as plain text'),
                'questionconfig' => new external_value(PARAM_RAW, 'Type-specific configuration', VALUE_OPTIONAL),
                'questionpreviewduration' => new external_value(PARAM_INT,
                    'Seconds the question is shown before answers open', VALUE_OPTIONAL),
                'questionduration' => new external_value(PARAM_INT, 'Seconds to answer', VALUE_OPTIONAL),
                'questionresultsduration' => new external_value(PARAM_INT,
                    'Seconds the results are shown', VALUE_OPTIONAL),
                'maxpoints' => new external_value(PARAM_INT, 'Points for the fastest correct answer', VALUE_OPTIONAL),
                'minpoints' => new external_value(PARAM_INT, 'Points for the slowest correct answer', VALUE_OPTIONAL),
                'imagecount' => new external_value(PARAM_INT, 'Number of images of the question', VALUE_OPTIONAL),
                'hasresponses' => new external_value(PARAM_BOOL,
                    'Whether participants have answered it in any round', VALUE_OPTIONAL),
            ]), 'Questions in the order they are played'),
        ]);
    }
}
