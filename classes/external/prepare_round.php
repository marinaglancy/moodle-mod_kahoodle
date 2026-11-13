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
use mod_kahoodle\constants;
use mod_kahoodle\local\game\questions;

/**
 * Implementation of web service mod_kahoodle_prepare_round
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prepare_round extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_prepare_round
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Kahoodle activity'),
        ]);
    }

    /**
     * Prepare a new round with the questions of the last one, as the "Prepare new round" button does
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        [, $context, $kahoodle] = helper::validate_cm($cmid);
        require_capability('mod/kahoodle:facilitate', $context);

        $round = questions::get_last_round($kahoodle->id);
        if ($round->get_current_stage_name() !== constants::STAGE_ARCHIVED) {
            throw new \moodle_exception('cannotprepareround', 'mod_kahoodle');
        }
        return ['roundid' => $round->duplicate()->get_id()];
    }

    /**
     * Describe the return structure for mod_kahoodle_prepare_round
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'roundid' => new external_value(PARAM_INT, 'Id of the new round'),
        ]);
    }
}
