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
use mod_kahoodle\reportbuilder\local\entities\question;
use mod_kahoodle\reportbuilder\local\entities\question_version;
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
        $lastround = \mod_kahoodle\questions::get_last_round($this->get_kahoodleid());

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

        // Filter by kahoodleid.
        $this->add_base_condition_simple("{$kahoodlealias}.id", $this->get_kahoodleid());

        // Add columns.
        $this->add_columns();

        // Add filters.
        $this->add_filters();

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
        return has_capability('mod/kahoodle:viewresults', $this->get_context());
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
