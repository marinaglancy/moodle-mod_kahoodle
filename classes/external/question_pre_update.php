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
use mod_kahoodle\local\game\questions;

/**
 * Implementation of web service mod_kahoodle_question_pre_update
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_pre_update extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_question_pre_update
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Question id'),
                'Questions to read, from mod_kahoodle_get_questions'
            ),
        ]);
    }

    /**
     * Questions as they are stored, named as mod_kahoodle_update_question takes them
     *
     * @param array $questionids
     * @return array
     */
    public static function execute(array $questionids): array {
        ['questionids' => $questionids] = self::validate_parameters(
            self::execute_parameters(),
            ['questionids' => $questionids]
        );
        $result = [];
        foreach ($questionids as $questionid) {
            $roundquestion = helper::validate_question($questionid);
            $context = $roundquestion->get_round()->get_context();
            require_capability('mod/kahoodle:manage_questions', $context);
            $data = $roundquestion->get_data();
            $question = [
                'questionid' => $roundquestion->get_question_id(),
                'position' => (int)$data->sortorder,
                'questiontype' => $data->questiontype,
                'questionformat' => (int)$data->questionformat,
                'questiontext' => $data->questiontext,
                'questionconfig' => (string)$data->questionconfig,
                'images' => array_values(array_map(fn($file) => [
                    'filename' => $file->get_filename(),
                    'fileurl' => \moodle_url::make_pluginfile_url($context->id, 'mod_kahoodle',
                        constants::FILEAREA_QUESTION_IMAGE, $data->questionversionid, '/', $file->get_filename())->out(false),
                ], $roundquestion->get_question_files())),
                'hasresponses' => questions::question_has_responses($roundquestion->get_question_id()),
            ];
            foreach (constants::FIELDS_ROUND_QUESTION as $field) {
                $question[$field] = (int)($data->$field ?? 0);
            }
            $result[] = $question;
        }
        return ['questions' => $result];
    }

    /**
     * Describe the return structure for mod_kahoodle_question_pre_update
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $overrides = [];
        foreach (helper::question_override_parameters('', false) as $field => $description) {
            $overrides[$field] = new external_value(PARAM_INT, $description->desc);
        }
        return new external_single_structure([
            'questions' => new external_multiple_structure(new external_single_structure([
                'questionid' => new external_value(PARAM_INT, 'Question id'),
                'position' => new external_value(PARAM_INT, 'Position in the last round, 1-based'),
                'questiontype' => new external_value(PARAM_ALPHANUMEXT, 'Question type'),
                'questionformat' => new external_value(PARAM_INT,
                    'Format of questiontext, from the activity: 0 = plain text, 1 = rich text (HTML)'),
                'questiontext' => new external_value(PARAM_RAW,
                    'Question text as stored, with @@PLUGINFILE@@ for embedded files'),
                'questionconfig' => new external_value(PARAM_RAW, 'Type-specific configuration as stored'),
                'images' => new external_multiple_structure(new external_single_structure([
                    'filename' => new external_value(PARAM_FILE, 'File name'),
                    'fileurl' => new external_value(PARAM_URL, 'File URL'),
                ]), 'Images of the question'),
                'hasresponses' => new external_value(PARAM_BOOL,
                    'Whether participants have answered it in any round, which limits what can change'),
            ] + $overrides)),
        ]);
    }
}
