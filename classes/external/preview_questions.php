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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_api;
use core_external\external_value;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\output\roundquestion as roundquestion_output;

/**
 * Implementation of web service mod_kahoodle_preview_questions
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview_questions extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_preview_questions
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roundid' => new external_value(PARAM_INT, 'Round ID'),
        ]);
    }

    /**
     * Implementation of web service mod_kahoodle_preview_questions
     *
     * @param int $roundid
     * @return array
     */
    public static function execute(int $roundid): array {
        global $DB, $PAGE;

        // Parameter validation.
        ['roundid' => $roundid] = self::validate_parameters(
            self::execute_parameters(),
            ['roundid' => $roundid]
        );

        // Get round and validate context.
        $round = round::create_from_id($roundid);
        $context = $round->get_context();
        self::validate_context($context);

        // Check permission to manage questions (teachers only).
        require_capability('mod/kahoodle:manage_questions', $context);

        // Set up page for rendering.
        $PAGE->set_context($context);

        // Prepare question data.
        $roundquestions = \mod_kahoodle\local\entities\round_question::get_all_questions_for_round($round);
        $totalquestions = count($roundquestions);
        $renderer = $PAGE->get_renderer('core');
        $questions = [];

        foreach ($roundquestions as $roundquestion) {
            $output = new roundquestion_output($roundquestion);
            $questions[] = $output->export_for_template($renderer);
        }

        return [
            'quiztitle' => $round->get_kahoodle_name(),
            'totalquestions' => $totalquestions,
            'isedit' => true,
            'cancontrol' => true,
            'questions' => $questions,
        ];
    }

    /**
     * Describe the return structure for mod_kahoodle_preview_questions
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'quiztitle' => new external_value(PARAM_TEXT, 'Quiz title'),
            'totalquestions' => new external_value(PARAM_INT, 'Total questions'),
            'isedit' => new external_value(PARAM_BOOL, 'Is it rendered for teacher when they edit the questions'),
            'cancontrol' => new external_value(PARAM_BOOL, 'Can control quiz'),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'sortorder' => new external_value(PARAM_INT, 'Question order'),
                    'roundquestionid' => new external_value(PARAM_INT, 'Round question ID'),
                    'questiontext' => new external_value(PARAM_RAW, 'Question text HTML'),
                    'hasimage' => new external_value(PARAM_BOOL, 'Has image'),
                    'imageurl' => new external_value(PARAM_URL, 'Image URL', VALUE_OPTIONAL),
                    'imagealt' => new external_value(PARAM_TEXT, 'Image alt text', VALUE_OPTIONAL),
                    'imagelandscape' => new external_value(PARAM_BOOL, 'Image is landscape', VALUE_OPTIONAL),
                    'questiontype' => new external_value(PARAM_ALPHANUMEXT, 'Question type identifier'),
                    'typedata' => new external_value(PARAM_RAW, 'JSON-encoded question type specific data'),
                    'backgroundurl' => new external_value(PARAM_URL, 'Background image URL', VALUE_OPTIONAL),
                ])
            ),
        ]);
    }
}
