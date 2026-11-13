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
use core_external\external_single_structure;
use core_external\external_value;
use mod_kahoodle\local\game\questions;

/**
 * Implementation of web service mod_kahoodle_update_question
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_question extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_update_question
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_INT,
                'Question id, from mod_kahoodle_get_questions. Pass only the fields that change; the others stay.'),
            'questiontext' => new external_value(PARAM_RAW,
                'New question text, in the same format as in mod_kahoodle_create_questions.', VALUE_DEFAULT, null),
            'questionconfig' => new external_value(PARAM_RAW, helper::questionconfig_description(), VALUE_DEFAULT, null),
            'imagedraftitemid' => new external_value(PARAM_INT,
                'Draft file area id with the images that replace the current ones.', VALUE_DEFAULT, null),
        ] + helper::question_override_parameters(' Omit to keep the current value.', true) + [
            'position' => new external_value(PARAM_INT,
                'New position (1-based) of the question in the round. Only before the round starts.',
                VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Change one question of the last round of its activity. Only the parameters given change.
     *
     * Once the round has started, a change of text or configuration makes a new version of the question,
     * so the results of earlier rounds keep the version they were played with.
     *
     * @param int $questionid
     * @param string|null $questiontext
     * @param string|null $questionconfig
     * @param int|null $imagedraftitemid
     * @param int|null $questionpreviewduration
     * @param int|null $questionduration
     * @param int|null $questionresultsduration
     * @param int|null $maxpoints
     * @param int|null $minpoints
     * @param int|null $position
     * @return array
     */
    public static function execute(
        int $questionid,
        ?string $questiontext = null,
        ?string $questionconfig = null,
        ?int $imagedraftitemid = null,
        ?int $questionpreviewduration = null,
        ?int $questionduration = null,
        ?int $questionresultsduration = null,
        ?int $maxpoints = null,
        ?int $minpoints = null,
        ?int $position = null
    ): array {
        $params = array_filter(self::validate_parameters(self::execute_parameters(), [
            'questionid' => $questionid,
            'questiontext' => $questiontext,
            'questionconfig' => $questionconfig,
            'imagedraftitemid' => $imagedraftitemid,
            'questionpreviewduration' => $questionpreviewduration,
            'questionduration' => $questionduration,
            'questionresultsduration' => $questionresultsduration,
            'maxpoints' => $maxpoints,
            'minpoints' => $minpoints,
            'position' => $position,
        ]), fn($value) => $value !== null);
        $roundquestion = helper::validate_question($params['questionid']);
        require_capability('mod/kahoodle:manage_questions', $roundquestion->get_round()->get_context());

        if (array_key_exists('position', $params)) {
            helper::require_editable_round($roundquestion->get_round());
        }
        $changes = array_diff_key($params, ['questionid' => 1, 'position' => 1]);
        if ($changes) {
            questions::edit_question($roundquestion, helper::question_data($changes));
        }
        if (array_key_exists('position', $params)) {
            questions::change_question_sortorder($roundquestion, $params['position']);
        }
        return [];
    }

    /**
     * Describe the return structure for mod_kahoodle_update_question
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([]);
    }
}
