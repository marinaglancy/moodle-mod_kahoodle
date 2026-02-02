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
use mod_kahoodle\local\entities\round;
use mod_kahoodle\reportbuilder\local\entities\question;

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
        $questionentity = new question();
        $roundquestionalias = $questionentity->get_table_alias('kahoodle_round_questions');
        $versionalias = $questionentity->get_table_alias('kahoodle_question_versions');
        $questionalias = $questionentity->get_table_alias('kahoodle_questions');
        $kahoodlealias = $questionentity->get_table_alias('kahoodle');

        $this->set_main_table('kahoodle_round_questions', $roundquestionalias);
        $this->add_entity($questionentity);

        // Join to question versions table.
        $this->add_join("
            JOIN {kahoodle_question_versions} {$versionalias}
                ON {$versionalias}.id = {$roundquestionalias}.questionversionid
        ");

        // Join to questions table for question type.
        $this->add_join("
            JOIN {kahoodle_questions} {$questionalias}
                ON {$questionalias}.id = {$versionalias}.questionid
        ");

        // Join to kahoodle table for question format.
        $this->add_join("
            JOIN {kahoodle} {$kahoodlealias}
                ON {$kahoodlealias}.id = {$questionalias}.kahoodleid
        ");

        // Filter by roundid parameter.
        $this->add_base_condition_simple("{$roundquestionalias}.roundid", $this->get_round()->get_id());

        // Add base fields for potential actions.
        $this->add_base_fields("{$roundquestionalias}.id");

        // Add columns.
        $this->add_columns();

        // Add filters.
        $this->add_filters();

        // Set initial sort order by question order.
        $this->set_initial_sort_column('question:sortorder', SORT_ASC);

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
     * @return void
     */
    protected function add_columns(): void {
        $this->add_columns_from_entities([
            'question:sortorder',
            'question:questiontype',
            'question:questionimages',
            'question:questiontext',
            'question:totalresponses',
            'question:correctresponses',
            'question:averagescore',
        ]);

        // Get participant count once for the entire report.
        $totalparticipants = $this->get_round()->get_participants_count();

        // Override the averagescore column callback to use the pre-calculated participant count.
        foreach ($this->get_columns() as $column) {
            if ($column->get_unique_identifier() === 'question:averagescore') {
                $column->add_callback(static function ($value, \stdClass $row) use ($totalparticipants): string {
                    $totalpoints = (int)($row->totalpoints ?? 0);
                    if ($totalparticipants === 0) {
                        return '-';
                    }
                    $average = $totalpoints / $totalparticipants;
                    return number_format($average, 1);
                });
                break;
            }
        }
    }

    /**
     * Adds the filters we want to display in the report
     *
     * @return void
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'question:questiontype',
            'question:questiontext',
        ]);
    }
}
