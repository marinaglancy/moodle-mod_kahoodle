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
use mod_kahoodle\reportbuilder\local\entities\question;
use moodle_url;
use pix_icon;

/**
 * Round questions list system report
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions extends system_report {
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

        // Add base fields for actions.
        $this->add_base_fields("{$roundquestionalias}.id, {$questionalias}.id AS questionid");

        // Add columns.
        $this->add_columns();

        // Add filters.
        $this->add_filters();

        // Add actions.
        $this->add_actions();

        // Set initial sort order.
        $this->set_initial_sort_column('question:sortorder', SORT_ASC);

        // Set downloadable.
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $cm = get_coursemodule_from_instance('kahoodle', $this->get_round()->get_kahoodleid(), 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        return has_capability('mod/kahoodle:manage_questions', $context);
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
            'question:timing',
            'question:score',
        ]);
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

    /**
     * Adds the actions we want to display in the report
     *
     * @return void
     */
    protected function add_actions(): void {
        // Preview action.
        $this->add_action(new action(
            new moodle_url('#'),
            new pix_icon('t/preview', ''),
            [
                'data-action' => 'mod_kahoodle-preview-question',
                'data-roundid' => $this->get_round()->get_id(),
                'data-roundquestionid' => ':id',
            ],
            false,
            new lang_string('previewquestion', 'mod_kahoodle')
        ));

        // Edit action.
        $this->add_action(new action(
            new moodle_url('#'),
            new pix_icon('t/edit', ''),
            ['data-action' => 'mod_kahoodle-edit-question', 'data-roundquestionid' => ':id'],
            false,
            new lang_string('editquestion', 'mod_kahoodle')
        ));

        // Delete action.
        if ($this->get_round()->is_editable()) {
            $this->add_action(new action(
                new moodle_url('#'),
                new pix_icon('t/delete', ''),
                ['data-action' => 'mod_kahoodle-delete-question', 'data-questionid' => ':questionid'],
                false,
                new lang_string('deletequestion', 'mod_kahoodle')
            ));
        }
    }
}
