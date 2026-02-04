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
use mod_kahoodle\reportbuilder\local\entities\round_question;
use stdClass;

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
        $roundquestionentity->set_table_aliases([
            'kahoodle' => $kahoodlealias,
            'kahoodle_questions' => $questionentity->get_table_alias('kahoodle_questions'),
            'kahoodle_question_versions' => $questionversionentity->get_table_alias('kahoodle_question_versions'),
        ]);
        $roundquestionentity->add_join($questionentity->get_questions_join());
        $roundquestionentity->add_join($questionversionentity->get_question_versions_join());
        $roundquestionentity->add_join($roundquestionentity->get_round_questions_join());
        $this->add_entity($roundquestionentity);

        // Filter by kahoodleid and roundid.
        $this->add_base_condition_simple("{$kahoodlealias}.id", $this->get_round()->get_kahoodle()->id);
        $roundquestionalias = $roundquestionentity->get_table_alias('kahoodle_round_questions');
        $this->add_base_condition_simple("{$roundquestionalias}.roundid", $this->get_round()->get_id());

        // Add base fields for potential actions.
        $this->add_base_fields("{$roundquestionalias}.id");

        // Add columns.
        $this->add_columns($roundquestionentity);

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
        return has_capability('mod/kahoodle:viewresults', $context);
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @param round_question $roundquestionentity The round question entity
     * @return void
     */
    protected function add_columns(round_question $roundquestionentity): void {
        $this->add_columns_from_entities([
            'round_question:sortorder',
            'question:questiontype',
            'question_version:questionimages',
            'question_version:questiontext',
        ]);

        // Add statistics columns directly in the report.
        $roundquestionalias = $roundquestionentity->get_table_alias('kahoodle_round_questions');
        $entityname = $roundquestionentity->get_entity_name();

        // Total responses column.
        $this->add_column((new column(
            'totalresponses',
            new lang_string('totalresponses', 'mod_kahoodle'),
            $entityname
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(*) FROM {kahoodle_responses} r
                WHERE r.roundquestionid = {$roundquestionalias}.id)", 'totalresponses')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? (string)$value : '0';
            }));

        // Correct responses column.
        $this->add_column((new column(
            'correctresponses',
            new lang_string('correctresponses', 'mod_kahoodle'),
            $entityname
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(*) FROM {kahoodle_responses} r
                WHERE r.roundquestionid = {$roundquestionalias}.id AND r.iscorrect = 1)", 'correctresponses')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? (string)$value : '0';
            }));

        // Average score column.
        // Get participant count once for the entire report.
        $totalparticipants = $this->get_round()->get_participants_count();

        $this->add_column((new column(
            'averagescore',
            new lang_string('results_averagescore', 'mod_kahoodle'),
            $entityname
        ))
            ->set_type(column::TYPE_FLOAT)
            ->add_field("(SELECT COALESCE(SUM(r.points), 0) FROM {kahoodle_responses} r
                WHERE r.roundquestionid = {$roundquestionalias}.id)", 'totalpoints')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, stdClass $row) use ($totalparticipants): string {
                $totalpoints = (int)($row->totalpoints ?? 0);
                if ($totalparticipants === 0) {
                    return '-';
                }
                $average = $totalpoints / $totalparticipants;
                return number_format($average, 1);
            }));
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
