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
use mod_kahoodle\local\entities\round_question;
use mod_kahoodle\questions;

/**
 * Implementation of web service mod_kahoodle_delete_question
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_question extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_delete_question
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
        ]);
    }

    /**
     * Implementation of web service mod_kahoodle_delete_question
     *
     * @param int $questionid Question ID
     * @return array
     */
    public static function execute(int $questionid): array {
        global $DB;

        // Parameter validation.
        ['questionid' => $questionid] = self::validate_parameters(
            self::execute_parameters(),
            ['questionid' => $questionid]
        );

        // Get the question to find the kahoodleid.
        $roundquestion = round_question::create_from_question_id($questionid);

        // Get the course module and validate context.
        $context = $roundquestion->get_round()->get_context();
        self::validate_context($context);

        // Check capability.
        require_capability('mod/kahoodle:manage_questions', $context);

        // Delete the question.
        questions::delete_question($roundquestion);

        return [];
    }

    /**
     * Describe the return structure for mod_kahoodle_delete_question
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([]);
    }
}
