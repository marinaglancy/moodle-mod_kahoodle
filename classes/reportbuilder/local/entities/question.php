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
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use mod_kahoodle\constants;

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
                return get_string('questiontype_' . $value, 'mod_kahoodle');
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
            ->set_is_sortable(false)
            ->add_callback(static function (?string $value, \stdClass $row): string {
                if ($value === null) {
                    return '';
                }
                // Rewrite @@PLUGINFILE@@ URLs for embedded images.
                $cm = get_coursemodule_from_instance('kahoodle', $row->kahoodleid, 0, false, MUST_EXIST);
                $context = \context_module::instance($cm->id);
                $value = file_rewrite_pluginfile_urls(
                    $value,
                    'pluginfile.php',
                    $context->id,
                    'mod_kahoodle',
                    constants::FILEAREA_QUESTION_IMAGE,
                    $row->questionversionid
                );
                // Use FORMAT_HTML for rich text, FORMAT_PLAIN for plain text.
                $format = ($row->questionformat == constants::QUESTIONFORMAT_RICHTEXT) ? FORMAT_HTML : FORMAT_PLAIN;
                return format_text($value, $format, ['filter' => false, 'context' => $context]);
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
