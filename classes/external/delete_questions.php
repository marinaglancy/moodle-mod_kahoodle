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
use mod_kahoodle\local\game\questions;

/**
 * Implementation of web service mod_kahoodle_delete_questions
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_questions extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_delete_questions
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Question id'),
                'Questions to remove from the round that has not started yet, from mod_kahoodle_get_questions'
            ),
        ]);
    }

    /**
     * Remove questions from the last round of their activity, which must not have started
     *
     * Earlier rounds keep the questions they were played with.
     *
     * @param array $questionids
     * @return array
     */
    public static function execute(array $questionids): array {
        ['questionids' => $questionids] = self::validate_parameters(
            self::execute_parameters(),
            ['questionids' => $questionids]
        );
        $deleted = [];
        $warnings = [];
        foreach ($questionids as $questionid) {
            try {
                $roundquestion = helper::validate_question($questionid);
                require_capability('mod/kahoodle:manage_questions', $roundquestion->get_round()->get_context());
                helper::require_editable_round($roundquestion->get_round());
                questions::delete_question($roundquestion);
                $deleted[] = $questionid;
            } catch (\Throwable $e) {
                $warnings[] = helper::warning('question', $questionid, $e);
            }
        }
        return ['deleted' => $deleted, 'warnings' => $warnings];
    }

    /**
     * Describe the return structure for mod_kahoodle_delete_questions
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_multiple_structure(new external_value(PARAM_INT, 'Question id'),
                'The questions that were removed'),
            'warnings' => new external_warnings(),
        ]);
    }
}
