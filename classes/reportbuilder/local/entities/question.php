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
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use mod_kahoodle\local\game\questions;

/**
 * Question entity for report builder (kahoodle_questions table)
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'kahoodle_questions',
            'kahoodle',
        ];
    }

    /**
     * Return syntax for joining on the questions table (assumes kahoodle is already joined)
     *
     * @return string
     */
    public function get_questions_join(): string {
        $questionalias = $this->get_table_alias('kahoodle_questions');
        $kahoodlealias = $this->get_table_alias('kahoodle');

        return "JOIN {kahoodle_questions} {$questionalias}
            ON {$questionalias}.kahoodleid = {$kahoodlealias}.id";
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity_question', 'mod_kahoodle');
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
        $questionalias = $this->get_table_alias('kahoodle_questions');

        $columns = [];

        // Question type column.
        $columns[] = (new column(
            'questiontype',
            new lang_string('questiontype', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$questionalias}.questiontype")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $value): string {
                if ($value === null) {
                    return '';
                }
                $type = questions::get_question_type_instance($value);
                return $type ? $type->get_display_name() : s($value);
            });

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $questionalias = $this->get_table_alias('kahoodle_questions');

        $filters = [];

        // Question type filter.
        $filters[] = (new filter(
            text::class,
            'questiontype',
            new lang_string('questiontype', 'mod_kahoodle'),
            $this->get_entity_name(),
            "{$questionalias}.questiontype"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
