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

declare(strict_types=1);

namespace mod_kahoodle\reportbuilder\local\systemreports;

use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\reportbuilder\local\entities\participant;
use moodle_url;
use pix_icon;

/**
 * Round participants list system report
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants extends system_report {
    /** @var round|null Cached round instance */
    protected ?round $round = null;

    /**
     * Get the round entity for this report
     *
     * @return round
     */
    protected function get_round(): round {
        if ($this->round === null) {
            $roundid = $this->get_parameter('roundid', 0, PARAM_INT);
            $this->round = round::create_from_id($roundid);
        }
        return $this->round;
    }

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $participantentity = new participant();
        $participantalias = $participantentity->get_table_alias('kahoodle_participants');
        $useralias = $participantentity->get_table_alias('user');

        $this->set_main_table('kahoodle_participants', $participantalias);
        $this->add_entity($participantentity);

        // LEFT JOIN to user table with condition that user is not deleted.
        // This allows showing participants even if the user has been deleted.
        $this->add_join("
            LEFT JOIN {user} {$useralias}
                ON {$useralias}.id = {$participantalias}.userid
                AND {$useralias}.deleted = 0
        ");

        // Filter by roundid parameter.
        $this->add_base_condition_simple("{$participantalias}.roundid", $this->get_round()->get_id());

        // Add base fields for potential actions.
        $this->add_base_fields("{$participantalias}.id AS participantid, {$participantalias}.userid");

        // Add columns.
        $this->add_columns();

        // Add filters.
        $this->add_filters();

        // Add actions.
        $this->add_actions();

        // Set initial sort order by rank (ascending), then by score (descending).
        $this->set_initial_sort_column('participant:score', SORT_DESC);

        // Set downloadable.
        $this->set_downloadable(true, get_string('participants', 'mod_kahoodle'));
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $context = $this->get_round()->get_context();
        return has_capability('mod/kahoodle:viewresults', $context);
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @return void
     */
    protected function add_columns(): void {
        $this->add_columns_from_entities([
            'participant:participant',
            'participant:user',
            'participant:rank',
            'participant:score',
            'participant:correctanswers',
            'participant:questionsanswered',
        ]);
    }

    /**
     * Adds the filters we want to display in the report
     *
     * @return void
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'participant:displayname',
            'participant:user',
            'participant:rank',
            'participant:score',
        ]);
    }

    /**
     * Adds the actions we want to display in the report
     *
     * @return void
     */
    protected function add_actions(): void {
        // View answers action.
        $this->add_action(new action(
            new moodle_url('/mod/kahoodle/results.php', ['view' => 'details', 'participantid' => ':participantid']),
            new pix_icon('i/preview', ''),
            [],
            false,
            new lang_string('viewanswers', 'mod_kahoodle')
        ));
    }
}
