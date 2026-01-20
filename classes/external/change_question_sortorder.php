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
 * Implementation of web service mod_kahoodle_change_question_sortorder
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class change_question_sortorder extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_change_question_sortorder
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roundquestionid' => new external_value(PARAM_INT, 'Round question ID'),
            'newsortorder' => new external_value(PARAM_INT, 'New sortorder position (1-based)'),
        ]);
    }

    /**
     * Implementation of web service mod_kahoodle_change_question_sortorder
     *
     * @param int $roundquestionid Round question ID
     * @param int $newsortorder The new sortorder position (1-based), -1 means last position
     * @return array Empty array
     */
    public static function execute($roundquestionid, $newsortorder): array {
        // Parameter validation.
        ['roundquestionid' => $roundquestionid, 'newsortorder' => $newsortorder] = self::validate_parameters(
            self::execute_parameters(),
            ['roundquestionid' => $roundquestionid, 'newsortorder' => $newsortorder]
        );

        // From web services we don't call require_login(), but rather validate_context.
        $roundquestion = round_question::create_from_round_question_id($roundquestionid);
        $context = $roundquestion->get_round()->get_context();
        self::validate_context($context);
        require_capability('mod/kahoodle:manage_questions', $context);

        // Perform the action.
        questions::change_question_sortorder($roundquestion, $newsortorder);

        return [];
    }

    /**
     * Describe the return structure for mod_kahoodle_change_question_sortorder
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([]);
    }
}
