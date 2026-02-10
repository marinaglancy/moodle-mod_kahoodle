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

namespace mod_kahoodle\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use html_writer;
use lang_string;
use mod_kahoodle\local\entities\round_question;

/**
 * Response entity for report builder - displays participant response data
 *
 * This entity only contains response-specific columns (correct, score, responsetime).
 * Question-related columns should come from the question entity.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'kahoodle_responses',
            'kahoodle_round_questions',
            'kahoodle_question_versions',
            'kahoodle_questions',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity_response', 'mod_kahoodle');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $responsealias = $this->get_table_alias('kahoodle_responses');
        $versionalias = $this->get_table_alias('kahoodle_question_versions');
        $questionalias = $this->get_table_alias('kahoodle_questions');

        $columns = [];

        // Correct column (Yes/No/No answer).
        $columns[] = (new column(
            'correct',
            new lang_string('correct', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$responsealias}.iscorrect")
            ->add_field("{$responsealias}.id", 'responseid')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value, \stdClass $row): string {
                global $PAGE;
                /** @var \mod_kahoodle\output\renderer $renderer */
                $renderer = $PAGE->get_renderer('mod_kahoodle');
                if ($row->responseid === null) {
                    return $renderer->badge(get_string('noanswer', 'mod_kahoodle'), 'warning');
                }
                return $value ?
                    $renderer->badge(get_string('yes'), 'success') :
                    $renderer->badge(get_string('no'), 'danger');
            });

        // Score column.
        $columns[] = (new column(
            'score',
            new lang_string('score', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$responsealias}.points")
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? number_format($value) : '0';
            });

        // Response time column.
        $columns[] = (new column(
            'responsetime',
            new lang_string('responsetime', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$responsealias}.responsetime")
            ->add_field("{$responsealias}.id", 'responseid')
            ->set_is_sortable(true)
            ->add_callback(static function (?float $value, \stdClass $row): string {
                if ($row->responseid === null) {
                    return '-';
                }
                if ($value === null) {
                    return '-';
                }
                return get_string('numseconds', 'moodle', number_format($value, 1));
            });

        // Response column (formatted response text).
        // Assumes question_versions and questions tables are already joined.
        $columns[] = (new column(
            'response',
            new lang_string('response', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$responsealias}.response")
            ->add_field("{$responsealias}.id", 'responseid')
            ->add_field("{$versionalias}.id", 'questionversionid')
            ->add_field("{$versionalias}.questionconfig")
            ->add_field("{$questionalias}.questiontype")
            ->set_is_sortable(false)
            ->add_callback(static function (?string $value, \stdClass $row): string {
                if ($row->responseid === null) {
                    return '';
                }
                $roundquestion = round_question::create_from_partial_record($row);
                $formatted = $roundquestion->get_question_type()->format_response($value, $roundquestion);
                return $formatted ?? '';
            });

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $responsealias = $this->get_table_alias('kahoodle_responses');

        $filters = [];

        // Correct filter (Yes/No/No answer).
        // Use CASE to map: 1 = Yes, 0 = No, -1 = No answer (NULL response).
        $filters[] = (new filter(
            select::class,
            'correct',
            new lang_string('correct', 'mod_kahoodle'),
            $this->get_entity_name(),
            "CASE WHEN {$responsealias}.id IS NULL THEN -1
                  WHEN {$responsealias}.iscorrect = 1 THEN 1
                  ELSE 0 END"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                1 => get_string('yes'),
                0 => get_string('no'),
                -1 => get_string('noanswer', 'mod_kahoodle'),
            ]);

        // Score filter.
        $filters[] = (new filter(
            number::class,
            'score',
            new lang_string('score', 'mod_kahoodle'),
            $this->get_entity_name(),
            "{$responsealias}.points"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
