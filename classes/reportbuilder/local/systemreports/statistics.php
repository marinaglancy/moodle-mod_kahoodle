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

use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use lang_string;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\reportbuilder\local\entities\question;
use mod_kahoodle\reportbuilder\local\entities\question_version;
use mod_kahoodle\reportbuilder\local\entities\response;
use mod_kahoodle\reportbuilder\local\entities\round_question;

/**
 * Round question statistics system report
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statistics extends system_report {
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
        // Set up question entity and get kahoodle alias.
        $questionentity = new question();
        $kahoodlealias = $questionentity->get_table_alias('kahoodle');

        // Set up kahoodle as main table.
        $this->set_main_table('kahoodle', $kahoodlealias);

        // Add question entity with join.
        $questionentity->add_join($questionentity->get_questions_join());
        $this->add_entity($questionentity);

        // Set up question_version entity and join.
        $questionversionentity = new question_version();
        $questionversionentity->set_table_aliases([
            'kahoodle' => $kahoodlealias,
            'kahoodle_questions' => $questionentity->get_table_alias('kahoodle_questions'),
        ]);
        $questionversionentity->add_join($questionentity->get_questions_join());
        $questionversionentity->add_join($questionversionentity->get_question_versions_join());
        $this->add_entity($questionversionentity);

        // Set up round_question entity and join.
        $roundquestionentity = new round_question();
        $questionalias = $questionentity->get_table_alias('kahoodle_questions');
        $versionalias = $questionversionentity->get_table_alias('kahoodle_question_versions');
        $roundquestionalias = $roundquestionentity->get_table_alias('kahoodle_round_questions');
        $roundquestionentity->set_table_aliases([
            'kahoodle' => $kahoodlealias,
            'kahoodle_questions' => $questionalias,
            'kahoodle_question_versions' => $versionalias,
        ]);
        $questionsjoin = $questionentity->get_questions_join();
        $versionsjoin = $questionversionentity->get_question_versions_join();
        $roundquestionsjoin = $roundquestionentity->get_round_questions_join();
        $roundquestionentity->add_join($questionsjoin);
        $roundquestionentity->add_join($versionsjoin);
        $roundquestionentity->add_join($roundquestionsjoin);
        $this->add_entity($roundquestionentity);

        // Set up response entity with LEFT JOIN through participants.
        // Join chain: round_questions -> participants (via roundid) -> responses (via participantid + roundquestionid).
        // This ensures participants who didn't respond are still counted with 0 points.
        $responseentity = new response();
        $responseentity->set_entity_name('responses');
        $participantsalias = 'kp';
        $responsesalias = 'kr';
        $responseentity->set_table_alias('kahoodle_responses', $responsesalias);
        $responseentity->set_table_aliases([
            'kahoodle_round_questions' => $roundquestionalias,
            'kahoodle_question_versions' => $versionalias,
            'kahoodle_questions' => $questionalias,
        ]);
        $responseentity->add_join($questionsjoin);
        $responseentity->add_join($versionsjoin);
        $responseentity->add_join($roundquestionsjoin);
        // LEFT JOIN to get all participants for this round.
        $participantsjoin = "LEFT JOIN {kahoodle_participants} {$participantsalias}
                ON {$participantsalias}.roundid = {$roundquestionalias}.roundid";
        $responseentity->add_join($participantsjoin);
        // LEFT JOIN to get responses (linking participant to round question).
        $responseentity->add_join(
            "LEFT JOIN {kahoodle_responses} {$responsesalias}
                ON {$responsesalias}.participantid = {$participantsalias}.id
                AND {$responsesalias}.roundquestionid = {$roundquestionalias}.id"
        );
        $this->add_entity($responseentity);

        // Filter by kahoodleid and roundid.
        $this->add_base_condition_simple("{$kahoodlealias}.id", $this->get_round()->get_kahoodle()->id);
        $this->add_base_condition_simple("{$roundquestionalias}.roundid", $this->get_round()->get_id());

        // Add base fields for potential actions.
        $this->add_base_fields("{$roundquestionalias}.id");

        // Add columns.
        $this->add_columns();

        // Add filters.
        $this->add_filters();

        // Set initial sort order by question order.
        $this->set_initial_sort_column('round_question:sortorder', SORT_ASC);

        // Set downloadable.
        $this->set_downloadable(true, get_string('results_viewstatistics', 'mod_kahoodle'));
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $context = $this->get_round()->get_context();
        if ($this->get_context()->id !== $context->id) {
            // Context mismatch, deny access.
            return false;
        }
        return has_capability('mod/kahoodle:viewresults', $context);
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @return void
     */
    protected function add_columns(): void {
        $this->add_columns_from_entities([
            'round_question:sortorder',
            'question:questiontype',
            'question_version:questionimages',
            'question_version:questiontext',
        ]);

        // Add statistics columns using joined participants/responses tables.
        $responseentity = $this->get_entity('responses');
        $responsesalias = $responseentity->get_table_alias('kahoodle_responses');
        $responsejoins = $responseentity->get_joins();
        $participantsalias = 'kp';

        // Total responses.
        $this->add_column(
            (new column(
                'totalresponses',
                new lang_string('totalresponses', 'mod_kahoodle'),
                'responses'
            ))
                ->add_joins($responsejoins)
                ->set_type(column::TYPE_INTEGER)
                ->add_field("CASE WHEN {$responsesalias}.id IS NOT NULL THEN 1 ELSE 0 END", 'totalresponses')
                ->set_is_sortable(true)
                ->set_aggregation('sum')
        );

        // Correct responses (counts only actual correct responses).
        $this->add_column(
            (new column(
                'correctresponses',
                new lang_string('correctresponses', 'mod_kahoodle'),
                'responses'
            ))
                ->add_joins($responsejoins)
                ->set_type(column::TYPE_INTEGER)
                ->add_field("CASE WHEN {$responsesalias}.iscorrect = 1 THEN 1 ELSE 0 END", 'correctresponses')
                ->set_is_sortable(true)
                ->set_aggregation('sum')
        );

        // Average score (includes participants who didn't respond as 0 points).
        $this->add_column(
            (new column(
                'averagescore',
                new lang_string('results_averagescore', 'mod_kahoodle'),
                'responses'
            ))
                ->add_joins($responsejoins)
                ->set_type(column::TYPE_FLOAT)
                ->add_field("CASE WHEN {$participantsalias}.id IS NOT NULL " .
                    "THEN COALESCE({$responsesalias}.points, 0) ELSE NULL END", 'points')
                ->set_is_sortable(true)
                ->set_aggregation('avg')
                ->add_callback(static fn(?float $value): string => number_format($value ?? 0, 1))
        );
    }

    /**
     * Adds the filters we want to display in the report
     *
     * @return void
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'question:questiontype',
            'question_version:questiontext',
        ]);
    }
}
