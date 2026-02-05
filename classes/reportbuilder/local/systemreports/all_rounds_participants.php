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

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use mod_kahoodle\constants;
use mod_kahoodle\reportbuilder\local\entities\participant;
use mod_kahoodle\reportbuilder\local\entities\round;
use moodle_url;
use pix_icon;

/**
 * All rounds participants list system report
 *
 * Shows participants from all completed rounds for a kahoodle activity.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class all_rounds_participants extends system_report {
    /** @var int|null Cached kahoodle ID */
    protected ?int $kahoodleid = null;

    /**
     * Get the kahoodle ID for this report
     *
     * @return int
     */
    protected function get_kahoodleid(): int {
        if ($this->kahoodleid === null) {
            $this->kahoodleid = $this->get_parameter('kahoodleid', 0, PARAM_INT);
        }
        return $this->kahoodleid;
    }

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        // Set up participant entity and get kahoodle alias.
        $participantentity = new participant();
        $kahoodlealias = $participantentity->get_table_alias('kahoodle');
        $participantalias = $participantentity->get_table_alias('kahoodle_participants');

        // Set up kahoodle as main table.
        $this->set_main_table('kahoodle', $kahoodlealias);

        // Set up round entity and join from kahoodle to rounds.
        $roundentity = new round();
        $roundalias = $roundentity->get_table_alias('kahoodle_rounds');
        $roundsjoin = "JOIN {kahoodle_rounds} {$roundalias}
            ON {$roundalias}.kahoodleid = {$kahoodlealias}.id";
        $roundentity->add_join($roundsjoin);
        $this->add_entity($roundentity);

        // Set up participant entity with joins (rounds + participants).
        $participantsjoin = "JOIN {kahoodle_participants} {$participantalias}
            ON {$participantalias}.roundid = {$roundalias}.id";
        $participantentity->add_join($roundsjoin);
        $participantentity->add_join($participantsjoin);
        $this->add_entity($participantentity);

        // Set up user entity and join.
        $userentity = new user();
        $userentity->set_table_alias('user', $participantentity->get_table_alias('user'));
        $userentity->add_join($roundsjoin);
        $userentity->add_join($participantsjoin);
        $userentity->add_join($participantentity->get_user_join());
        $this->add_entity($userentity);

        // Filter by kahoodleid - only show completed rounds (revision or archived).
        $this->add_base_condition_simple("{$kahoodlealias}.id", $this->get_kahoodleid());
        $paramstagerevision = database::generate_param_name();
        $paramstagearchived = database::generate_param_name();
        $this->add_base_condition_sql(
            "{$roundalias}.currentstage IN (:{$paramstagerevision}, :{$paramstagearchived})",
            [
                $paramstagerevision => constants::STAGE_REVISION,
                $paramstagearchived => constants::STAGE_ARCHIVED,
            ]
        );

        // Add base fields for potential actions.
        $this->add_base_fields("{$participantalias}.id AS participantid, {$participantalias}.userid, {$roundalias}.id AS roundid");

        // Add columns.
        $this->add_columns();

        // Add filters.
        $this->add_filters();

        // Add actions.
        $this->add_actions();

        // Set initial sort order by round name then score.
        $this->set_initial_sort_column('participant:score', SORT_DESC);

        // Set downloadable.
        $this->set_downloadable(true, get_string('allroundsparticipants', 'mod_kahoodle'));
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('mod/kahoodle:viewresults', $this->get_context());
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @return void
     */
    protected function add_columns(): void {
        $this->add_columns_from_entities([
            'round:namelinked',
            'participant:participant',
            'user:fullnamewithpicturelink',
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
            'round:name',
            'participant:displayname',
            'user:userselect',
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
