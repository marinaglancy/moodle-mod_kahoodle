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
use mod_kahoodle\local\entities\round_question;
use mod_kahoodle\questions;

/**
 * Round question entity for report builder
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
        return new lang_string('question', 'mod_kahoodle');
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
        $roundquestionalias = $this->get_table_alias('kahoodle_round_questions');
        $versionalias = $this->get_table_alias('kahoodle_question_versions');
        $questionalias = $this->get_table_alias('kahoodle_questions');
        $kahoodlealias = $this->get_table_alias('kahoodle');

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

        // Question text column.
        $columns[] = (new column(
            'questiontext',
            new lang_string('questiontext', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$versionalias}.questiontext")
            ->add_field("{$kahoodlealias}.questionformat")
            ->add_field("{$versionalias}.id", 'questionversionid')
            ->add_field("{$kahoodlealias}.id", 'kahoodleid')
            ->add_field("{$roundquestionalias}.id", 'id')
            ->set_is_sortable(false)
            ->add_callback(static function (?string $value, \stdClass $row): string {
                return round_question::create_from_partial_record($row)->preview_question_text();
            });

        // Question images column.
        $columns[] = (new column(
            'questionimages',
            new lang_string('questionimage', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$versionalias}.questiontext")
            ->add_field("{$kahoodlealias}.questionformat")
            ->add_field("{$versionalias}.id", 'questionversionid')
            ->add_field("{$kahoodlealias}.id", 'kahoodleid')
            ->add_field("{$roundquestionalias}.id", 'id')
            ->set_is_sortable(false)
            ->add_callback(static function (?string $value, \stdClass $row): string {
                return round_question::create_from_partial_record($row)->preview_question_images();
            });

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
            ->add_callback(static function (?string $value, \stdClass $row): string {
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
            ->add_field("{$kahoodlealias}.minpoints")
            ->add_field("{$kahoodlealias}.maxpoints")
            ->set_is_sortable(false)
            ->add_callback(static function (?string $value, \stdClass $row): string {
                $min = self::value_or_default($row->minpoints, $row->minpoints);
                $max = self::value_or_default($row->maxpoints, $row->maxpoints);
                return "{$min} - {$max}";
            });

        // Version column.
        $columns[] = (new column(
            'version',
            new lang_string('version', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$versionalias}.version")
            ->set_is_sortable(true);

        // Total responses column (count of responses for this question).
        $columns[] = (new column(
            'totalresponses',
            new lang_string('totalresponses', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(*) FROM {kahoodle_responses} r
                WHERE r.roundquestionid = {$roundquestionalias}.id)", 'totalresponses')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? (string)$value : '0';
            });

        // Correct responses column (count of correct responses).
        $columns[] = (new column(
            'correctresponses',
            new lang_string('correctresponses', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(*) FROM {kahoodle_responses} r
                WHERE r.roundquestionid = {$roundquestionalias}.id AND r.iscorrect = 1)", 'correctresponses')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? (string)$value : '0';
            });

        // Average score column.
        // Calculate average including participants who didn't answer (counted as 0).
        // Sum of points / total participants in the round.
        // Note: The callback needs totalparticipants which should be set by the system report.
        $columns[] = (new column(
            'averagescore',
            new lang_string('results_averagescore', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("(SELECT COALESCE(SUM(r.points), 0) FROM {kahoodle_responses} r
                WHERE r.roundquestionid = {$roundquestionalias}.id)", 'totalpoints')
            ->set_is_sortable(true);

        return $columns;
    }

    /**
     * Formatter returning value in bold if it is set specifically for the question, otherwise default value.
     *
     * @param mixed $value
     * @param mixed $default
     */
    protected static function value_or_default($value, $default) {
        if ($value !== null && $value != $default) {
            return "<b>" . $value . "</b>";
        }
        return $default;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $questionalias = $this->get_table_alias('kahoodle_questions');
        $versionalias = $this->get_table_alias('kahoodle_question_versions');

        // Question type filter.
        $filters[] = (new filter(
            text::class,
            'questiontype',
            new lang_string('questiontype', 'mod_kahoodle'),
            $this->get_entity_name(),
            "{$questionalias}.questiontype"
        ))
            ->add_joins($this->get_joins());

        // Question text filter.
        $filters[] = (new filter(
            text::class,
            'questiontext',
            new lang_string('questiontext', 'mod_kahoodle'),
            $this->get_entity_name(),
            "{$versionalias}.questiontext"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
