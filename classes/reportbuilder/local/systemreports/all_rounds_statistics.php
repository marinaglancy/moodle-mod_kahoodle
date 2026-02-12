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
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use lang_string;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\reportbuilder\local\entities\question;
use mod_kahoodle\reportbuilder\local\entities\question_version;
use mod_kahoodle\reportbuilder\local\entities\response;
use mod_kahoodle\reportbuilder\local\entities\round_question;

/**
 * All rounds question statistics system report
 *
 * Shows questions from a kahoodle activity with their latest version.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class all_rounds_statistics extends system_report {
    /** @var int|null Cached kahoodle ID */
    protected ?int $kahoodleid = null;

    /** @var round|null Cached last round record */
    protected ?round $lastround = null;

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
     * Get the last round for this kahoodle
     *
     * @return round
     */
    protected function get_last_round(): round {
        if ($this->lastround === null) {
            $this->lastround = \mod_kahoodle\questions::get_last_round($this->get_kahoodleid());
        }
        return $this->lastround;
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

        // Set up last question version entity with join for islast=1 only.
        $lastversionentity = new question_version();
        $questionalias = $questionentity->get_table_alias('kahoodle_questions');
        $versionalias = $lastversionentity->get_table_alias('kahoodle_question_versions');
        $lastversionentity->set_table_aliases([
            'kahoodle' => $kahoodlealias,
            'kahoodle_questions' => $questionalias,
        ]);
        $lastversionentity->add_join($questionentity->get_questions_join());
        // Custom join that only includes the latest version (islast=1).
        $lastversionjoin = "JOIN {kahoodle_question_versions} {$versionalias}
                ON {$versionalias}.questionid = {$questionalias}.id
                AND {$versionalias}.islast = 1";
        $lastversionentity->add_join($lastversionjoin);
        $this->add_entity($lastversionentity);

        // Get the last round for sortorder lookup.
        $lastround = $this->get_last_round();

        // Set up last round question entity with LEFT JOIN for the last round only.
        $lastroundquestionentity = new round_question();
        $roundquestionalias = $lastroundquestionentity->get_table_alias('kahoodle_round_questions');
        $lastroundquestionentity->set_table_aliases([
            'kahoodle' => $kahoodlealias,
            'kahoodle_questions' => $questionalias,
            'kahoodle_question_versions' => $versionalias,
        ]);
        $lastroundquestionentity->add_join($questionentity->get_questions_join());
        $lastroundquestionentity->add_join($lastversionjoin);
        // LEFT JOIN to get sortorder from the last round (if question is in that round).
        $lastroundid = $lastround->get_id();
        $lastroundquestionentity->add_join(
            "LEFT JOIN {kahoodle_round_questions} {$roundquestionalias}
                ON {$roundquestionalias}.questionversionid = {$versionalias}.id
                AND {$roundquestionalias}.roundid = {$lastroundid}"
        );
        $this->add_entity($lastroundquestionentity);

        // Set up entity for ALL question versions (LEFT JOIN, no islast filter).
        $allversionsentity = new question_version();
        $allversionsentity->set_entity_name('all_versions');
        $allversionsalias = 'kqv_all';
        $allversionsentity->set_table_alias('kahoodle_question_versions', $allversionsalias);
        $allversionsentity->set_table_aliases([
            'kahoodle' => $kahoodlealias,
            'kahoodle_questions' => $questionalias,
        ]);
        $allversionsentity->add_join($questionentity->get_questions_join());
        // LEFT JOIN to get all versions (not filtered by islast).
        $allversionsjoin = "LEFT JOIN {kahoodle_question_versions} {$allversionsalias}
                ON {$allversionsalias}.questionid = {$questionalias}.id";
        $allversionsentity->add_join($allversionsjoin);
        $this->add_entity($allversionsentity);

        // Set up entity for ALL round questions (LEFT JOIN through all versions).
        $allroundquestionsentity = new round_question();
        $allroundquestionsentity->set_entity_name('all_round_questions');
        $allroundquestionsalias = 'krq_all';
        $allroundquestionsentity->set_table_alias('kahoodle_round_questions', $allroundquestionsalias);
        $allroundquestionsentity->set_table_aliases([
            'kahoodle' => $kahoodlealias,
            'kahoodle_questions' => $questionalias,
            'kahoodle_question_versions' => $allversionsalias,
        ]);
        $allroundquestionsentity->add_join($questionentity->get_questions_join());
        $allroundquestionsentity->add_join($allversionsjoin);
        // LEFT JOIN to get all round questions (not filtered by round).
        $allroundquestionsjoin = "LEFT JOIN {kahoodle_round_questions} {$allroundquestionsalias}
                ON {$allroundquestionsalias}.questionversionid = {$allversionsalias}.id";
        $allroundquestionsentity->add_join($allroundquestionsjoin);
        $this->add_entity($allroundquestionsentity);

        // Set up entity for ALL responses (LEFT JOIN through participants).
        // Join chain: round_questions -> participants (via roundid) -> responses (via participantid + roundquestionid).
        // This ensures participants who didn't respond are still counted with 0 points.
        $allresponsesentity = new response();
        $allresponsesentity->set_entity_name('all_responses');
        $allparticipantsalias = 'kp_all';
        $allresponsesalias = 'kr_all';
        $allresponsesentity->set_table_alias('kahoodle_responses', $allresponsesalias);
        $allresponsesentity->set_table_aliases([
            'kahoodle_round_questions' => $allroundquestionsalias,
            'kahoodle_question_versions' => $allversionsalias,
            'kahoodle_questions' => $questionalias,
        ]);
        $allresponsesentity->add_join($questionentity->get_questions_join());
        $allresponsesentity->add_join($allversionsjoin);
        $allresponsesentity->add_join($allroundquestionsjoin);
        // LEFT JOIN to get all participants for each round.
        $allparticipantsjoin = "LEFT JOIN {kahoodle_participants} {$allparticipantsalias}
                ON {$allparticipantsalias}.roundid = {$allroundquestionsalias}.roundid";
        $allresponsesentity->add_join($allparticipantsjoin);
        // LEFT JOIN to get responses (linking participant to round question).
        $allresponsesentity->add_join(
            "LEFT JOIN {kahoodle_responses} {$allresponsesalias}
                ON {$allresponsesalias}.participantid = {$allparticipantsalias}.id
                AND {$allresponsesalias}.roundquestionid = {$allroundquestionsalias}.id"
        );
        $this->add_entity($allresponsesentity);

        // Filter by kahoodleid.
        $this->add_base_condition_simple("{$kahoodlealias}.id", $this->get_kahoodleid());

        // Add base fields for actions.
        $this->add_base_fields("{$roundquestionalias}.sortorder");

        // Add columns.
        $this->add_columns();

        // Add filters.
        $this->add_filters();

        // Add actions.
        $this->add_actions();

        // Set initial sort order.
        $this->set_initial_sort_column('round_question:sortorder', SORT_ASC);

        // Set downloadable.
        $this->set_downloadable(true, get_string('allroundsstatistics', 'mod_kahoodle'));
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $context = $this->get_context();
        if ($context->id !== $this->get_last_round()->get_context()->id) {
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
        $allresponsesentity = $this->get_entity('all_responses');
        $allresponsesalias = $allresponsesentity->get_table_alias('kahoodle_responses');
        $allparticipantsalias = 'kp_all'; // Defined in initialise().
        $allresponsejoins = $allresponsesentity->get_joins();

        // Total participants (counts all participants, including those who didn't respond).
        $this->add_column(
            (new column(
                'totalparticipants',
                new lang_string('totalparticipants', 'mod_kahoodle'),
                'all_responses'
            ))
                ->add_joins($allresponsejoins)
                ->set_type(column::TYPE_INTEGER)
                ->add_field("CASE WHEN {$allparticipantsalias}.id IS NOT NULL THEN 1 ELSE 0 END", 'totalparticipants')
                ->set_is_sortable(true)
                ->set_aggregation('sum')
        );

        // Total responses.
        $this->add_column(
            (new column(
                'totalresponses',
                new lang_string('totalresponses', 'mod_kahoodle'),
                'all_responses'
            ))
                ->add_joins($allresponsejoins)
                ->set_type(column::TYPE_INTEGER)
                ->add_field("CASE WHEN {$allresponsesalias}.id IS NOT NULL THEN 1 ELSE 0 END", 'totalresponses')
                ->set_is_sortable(true)
                ->set_aggregation('sum')
        );

        // Correct responses (counts only actual correct responses).
        $this->add_column(
            (new column(
                'correctresponses',
                new lang_string('correctresponses', 'mod_kahoodle'),
                'all_responses'
            ))
                ->add_joins($allresponsejoins)
                ->set_type(column::TYPE_INTEGER)
                ->add_field("CASE WHEN {$allresponsesalias}.iscorrect = 1 THEN 1 ELSE 0 END", 'correctresponses')
                ->set_is_sortable(true)
                ->set_aggregation('sum')
        );

        // Average score (includes participants who didn't respond as 0 points).
        $this->add_column(
            (new column(
                'averagescore',
                new lang_string('results_averagescore', 'mod_kahoodle'),
                'all_responses'
            ))
                ->add_joins($allresponsejoins)
                ->set_type(column::TYPE_FLOAT)
                ->add_field("CASE WHEN {$allparticipantsalias}.id IS NOT NULL " .
                    "THEN COALESCE({$allresponsesalias}.points, 0) ELSE NULL END", 'points')
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

    /**
     * Adds the actions we want to display in the report
     *
     * @return void
     */
    protected function add_actions(): void {
        $this->add_action(new action(
            new \moodle_url('#'),
            new \pix_icon('i/play', ''),
            [
                'data-action' => 'mod_kahoodle-playback',
                'data-kahoodleid' => $this->get_kahoodleid(),
                'data-questionnumber' => ':sortorder',
            ],
            false,
            new lang_string('playback', 'mod_kahoodle')
        ));
    }
}
