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
use mod_kahoodle\output\playback;

/**
 * Implementation of web service mod_kahoodle_playback_stages
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class playback_stages extends external_api {
    /**
     * Describes the parameters for mod_kahoodle_playback_stages
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roundid' => new external_value(PARAM_INT, 'Round ID (for single-round playback)', VALUE_DEFAULT, 0),
            'kahoodleid' => new external_value(PARAM_INT, 'Kahoodle ID (for all-rounds playback)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Implementation of web service mod_kahoodle_playback_stages
     *
     * @param int $roundid
     * @param int $kahoodleid
     * @return array
     */
    public static function execute(int $roundid, int $kahoodleid): array {
        global $PAGE;

        // Parameter validation.
        ['roundid' => $roundid, 'kahoodleid' => $kahoodleid] = self::validate_parameters(
            self::execute_parameters(),
            ['roundid' => $roundid, 'kahoodleid' => $kahoodleid]
        );

        // Exactly one of roundid/kahoodleid must be provided.
        if (($roundid && $kahoodleid) || (!$roundid && !$kahoodleid)) {
            throw new \invalid_parameter_exception('Exactly one of roundid or kahoodleid must be provided');
        }

        if ($roundid) {
            // Single-round mode.
            $round = round::create_from_id($roundid);
        } else {
            // All-rounds mode.
            $round = new \mod_kahoodle\local\entities\statistics($kahoodleid);
        }

        $context = $round->get_context();
        self::validate_context($context);
        require_capability('mod/kahoodle:viewresults', $context);

        $PAGE->set_context($context);
        $renderer = $PAGE->get_renderer('core');

        $output = new playback($round);

        return $output->export_all_stages($renderer);
    }

    /**
     * Describe the return structure for mod_kahoodle_playback_stages
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'quiztitle' => new external_value(PARAM_TEXT, 'Quiz title'),
            'totalquestions' => new external_value(PARAM_INT, 'Total number of questions'),
            'stages' => new external_multiple_structure(
                new external_single_structure([
                    'stagesignature' => new external_value(PARAM_TEXT, 'Stage signature for identification'),
                    'template' => new external_value(PARAM_TEXT, 'Mustache template name'),
                    'duration' => new external_value(PARAM_INT, 'Stage duration in seconds'),
                    'templatedata' => new external_value(PARAM_RAW, 'JSON-encoded template context data'),
                ])
            ),
        ]);
    }
}
