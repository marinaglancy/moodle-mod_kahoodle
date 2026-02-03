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

use core_reportbuilder\system_report;
use mod_kahoodle\local\entities\participant;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\reportbuilder\local\entities\question;
use mod_kahoodle\reportbuilder\local\entities\response;

/**
 * Participant answers system report - shows all answers for a specific participant
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participant_answers extends system_report {
    /** @var round|null Cached round instance */
    protected ?round $round = null;

    /**
     * Get the participant entity for this report
     *
     * @return participant
     */
    public function get_participant(): participant {
        $participantid = $this->get_parameter('participantid', 0, PARAM_INT);
        return $this->get_round()->get_participant_by_id($participantid);
    }

    /**
     * Get the round entity for this report
     *
     * @return round
     */
    protected function get_round(): round {
        global $DB;
        if ($this->round === null) {
            $participantid = $this->get_parameter('participantid', 0, PARAM_INT);
            $record = $DB->get_record_sql('
                SELECT r.*
                FROM {kahoodle_participants} p
                JOIN {kahoodle_rounds} r ON r.id = p.roundid
                WHERE p.id = :participantid
            ', ['participantid' => $participantid], MUST_EXIST);
            $this->round = round::create_from_object($record);
        }
        return $this->round;
    }

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        // Add question entity for question-related columns.
        $questionentity = new question();
        $roundquestionalias = $questionentity->get_table_alias('kahoodle_round_questions');

        // Use round_questions as main table to show all questions (even unanswered ones).
        $this->set_main_table('kahoodle_round_questions', $roundquestionalias);
        $this->add_entity($questionentity);

        // Add response entity for response-specific columns.
        // Set the same alias for round_questions as the main table.
        $responseentity = (new response())
            ->set_table_alias('kahoodle_round_questions', $roundquestionalias);
        $responsealias = $responseentity->get_table_alias('kahoodle_responses');

        // LEFT JOIN to responses for this specific participant.
        $participantid = $this->get_parameter('participantid', 0, PARAM_INT);
        $this->add_entity($responseentity
            ->add_join("LEFT JOIN {kahoodle_responses} {$responsealias}
                ON {$responsealias}.roundquestionid = {$roundquestionalias}.id
                AND {$responsealias}.participantid = {$participantid}"));

        // Filter by roundid from the participant.
        $this->add_base_condition_simple("{$roundquestionalias}.roundid", $this->get_round()->get_id());

        // Add base fields.
        $this->add_base_fields("{$roundquestionalias}.id");

        // Add columns.
        $this->add_columns();

        // Add filters.
        $this->add_filters();

        // Set initial sort order by question order.
        $this->set_initial_sort_column('question:sortorder', SORT_ASC);

        // Set downloadable.
        $this->set_downloadable(true, get_string('participantanswers', 'mod_kahoodle'));
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
            'question:sortorder',
            'question:questiontype',
            'question:questionimages',
            'question:questiontext',
            'response:correct',
            'response:score',
            'response:responsetime',
        ]);
    }

    /**
     * Adds the filters we want to display in the report
     *
     * @return void
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'question:questiontext',
            'response:correct',
            'response:score',
        ]);
    }
}
