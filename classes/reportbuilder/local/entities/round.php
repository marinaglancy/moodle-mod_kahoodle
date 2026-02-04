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
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use html_writer;
use lang_string;
use moodle_url;

/**
 * Round entity for report builder
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class round extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'kahoodle_rounds',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity_round', 'mod_kahoodle');
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
        $roundalias = $this->get_table_alias('kahoodle_rounds');

        $columns = [];

        // Round name column with link.
        $columns[] = (new column(
            'name',
            new lang_string('round', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$roundalias}.name")
            ->add_field("{$roundalias}.id")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $value, \stdClass $row): string {
                if ($value === null || $value === '') {
                    return get_string('roundname', 'mod_kahoodle', $row->id);
                }
                return s($value);
            });

        // Round name with link to participants.
        $columns[] = (new column(
            'namelinked',
            new lang_string('round', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$roundalias}.name")
            ->add_field("{$roundalias}.id")
            ->set_is_sortable(true, ["{$roundalias}.name"])
            ->add_callback(static function (?string $value, \stdClass $row): string {
                $name = ($value === null || $value === '')
                    ? get_string('roundname', 'mod_kahoodle', $row->id)
                    : s($value);
                $url = new moodle_url('/mod/kahoodle/results.php', [
                    'roundid' => $row->id,
                    'view' => 'participants',
                ]);
                return html_writer::link($url, $name);
            });

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $roundalias = $this->get_table_alias('kahoodle_rounds');

        $filters = [];

        // Round name filter.
        $filters[] = (new filter(
            text::class,
            'name',
            new lang_string('round', 'mod_kahoodle'),
            $this->get_entity_name(),
            "{$roundalias}.name"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * Return syntax for joining on the rounds table from participants.
     *
     * @return string
     */
    public function get_round_join_from_participants(): string {
        $roundalias = $this->get_table_alias('kahoodle_rounds');
        return "JOIN {kahoodle_rounds} {$roundalias} ON {$roundalias}.id = kp.roundid";
    }

    /**
     * Return syntax for joining on the rounds table from round_questions.
     *
     * @return string
     */
    public function get_round_join_from_round_questions(): string {
        $roundalias = $this->get_table_alias('kahoodle_rounds');
        return "JOIN {kahoodle_rounds} {$roundalias} ON {$roundalias}.id = krq.roundid";
    }
}
