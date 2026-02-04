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
use core_reportbuilder\local\report\column;
use lang_string;
use stdClass;

/**
 * Round question entity for report builder (kahoodle_round_questions table)
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class round_question extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'kahoodle_round_questions',
            'kahoodle_question_versions',
            'kahoodle_questions',
            'kahoodle',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity_roundquestion', 'mod_kahoodle');
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

        return $this;
    }

    /**
     * Return syntax for joining on the round questions table (assumes question_versions is already joined)
     *
     * @return string
     */
    public function get_round_questions_join(): string {
        $roundquestionalias = $this->get_table_alias('kahoodle_round_questions');
        $versionalias = $this->get_table_alias('kahoodle_question_versions');

        return "JOIN {kahoodle_round_questions} {$roundquestionalias}
            ON {$roundquestionalias}.questionversionid = {$versionalias}.id";
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $roundquestionalias = $this->get_table_alias('kahoodle_round_questions');
        $kahoodlealias = $this->get_table_alias('kahoodle');

        $columns = [];

        // Sort order column.
        $columns[] = (new column(
            'sortorder',
            new lang_string('sortorder', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$roundquestionalias}.sortorder")
            ->add_field("{$roundquestionalias}.id")
            ->set_is_sortable(true);

        // Timing column (preview / question / results durations).
        $columns[] = (new column(
            'timing',
            new lang_string('timing', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$roundquestionalias}.questionpreviewduration")
            ->add_field("{$roundquestionalias}.questionduration")
            ->add_field("{$roundquestionalias}.questionresultsduration")
            ->add_field("{$kahoodlealias}.questionpreviewduration", 'default_questionpreviewduration')
            ->add_field("{$kahoodlealias}.questionduration", 'default_questionduration')
            ->add_field("{$kahoodlealias}.questionresultsduration", 'default_questionresultsduration')
            ->set_is_sortable(false)
            ->add_callback(static function (?string $value, stdClass $row): string {
                $preview = self::value_or_default($row->questionpreviewduration, $row->default_questionpreviewduration);
                $question = self::value_or_default($row->questionduration, $row->default_questionduration);
                $results = self::value_or_default($row->questionresultsduration, $row->default_questionresultsduration);
                return "{$preview} / {$question} / {$results}";
            });

        // Score column (minpoints - maxpoints).
        $columns[] = (new column(
            'score',
            new lang_string('score', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$roundquestionalias}.minpoints")
            ->add_field("{$roundquestionalias}.maxpoints")
            ->add_field("{$kahoodlealias}.minpoints", 'default_minpoints')
            ->add_field("{$kahoodlealias}.maxpoints", 'default_maxpoints')
            ->set_is_sortable(false)
            ->add_callback(static function (?string $value, stdClass $row): string {
                $min = self::value_or_default($row->minpoints, $row->default_minpoints);
                $max = self::value_or_default($row->maxpoints, $row->default_maxpoints);
                return "{$min} - {$max}";
            });

        return $columns;
    }

    /**
     * Formatter returning value in bold if it is set specifically for the question, otherwise default value.
     *
     * @param mixed $value
     * @param mixed $default
     * @return string
     */
    protected static function value_or_default($value, $default): string {
        if ($value !== null && $value != $default) {
            return "<b>" . $value . "</b>";
        }
        return (string)$default;
    }
}
