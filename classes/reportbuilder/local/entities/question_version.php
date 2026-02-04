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
use stdClass;

/**
 * Question version entity for report builder (kahoodle_question_versions table)
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_version extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
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
        return new lang_string('entity_questionversion', 'mod_kahoodle');
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
     * Return syntax for joining on the question versions table (assumes questions is already joined)
     *
     * @return string
     */
    public function get_question_versions_join(): string {
        $versionalias = $this->get_table_alias('kahoodle_question_versions');
        $questionalias = $this->get_table_alias('kahoodle_questions');

        return "JOIN {kahoodle_question_versions} {$versionalias}
            ON {$versionalias}.questionid = {$questionalias}.id";
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $versionalias = $this->get_table_alias('kahoodle_question_versions');
        $kahoodlealias = $this->get_table_alias('kahoodle');

        $columns = [];

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
            ->set_is_sortable(false)
            ->add_callback(static function (?string $value, stdClass $row): string {
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
            ->set_is_sortable(false)
            ->add_callback(static function (?string $value, stdClass $row): string {
                return round_question::create_from_partial_record($row)->preview_question_images();
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

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $versionalias = $this->get_table_alias('kahoodle_question_versions');

        $filters = [];

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
