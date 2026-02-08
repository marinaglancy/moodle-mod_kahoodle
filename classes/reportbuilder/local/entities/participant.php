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
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use stdClass;

/**
 * Participant entity for report builder
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participant extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'kahoodle_participants',
            'user',
            'kahoodle',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity_participant', 'mod_kahoodle');
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
     * Return syntax for joining on the user table.
     * Uses LEFT JOIN with deleted = 0 condition to allow showing participants
     * even if the user has been deleted.
     *
     * @return string
     */
    public function get_user_join(): string {
        $participantalias = $this->get_table_alias('kahoodle_participants');
        $useralias = $this->get_table_alias('user');

        return "LEFT JOIN {user} {$useralias}
            ON {$useralias}.id = {$participantalias}.userid
            AND {$useralias}.deleted = 0";
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $participantalias = $this->get_table_alias('kahoodle_participants');
        $kahoodlealias = $this->get_table_alias('kahoodle');

        $columns = [];

        // Participant column (avatar + display name).
        $columns[] = (new column(
            'participant',
            new lang_string('participant', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$participantalias}.id")
            ->add_field("{$participantalias}.displayname")
            ->add_field("{$participantalias}.avatar")
            ->add_field("{$participantalias}.roundid")
            ->add_field("{$kahoodlealias}.id", 'kahoodleid')
            ->add_field("{$participantalias}.userid", 'user_id')
            ->set_is_sortable(true, ["{$participantalias}.displayname"])
            ->add_callback(static function (?string $value, stdClass $row): string {
                global $CFG;
                $participant = \mod_kahoodle\local\entities\participant::from_partial_record($row);
                $avatarurl = $participant->get_avatar_url();
                $displayname = $participant->get_display_name();
                $class = (int)($CFG->branch) >= 500 ? 'me-2' : 'mr-2';
                $img = \html_writer::img(
                    $avatarurl->out(false),
                    $displayname,
                    ['class' => 'rounded-circle ' . $class, 'width' => 35, 'height' => 35]
                );
                return $img . $displayname;
            });

        // Rank column.
        $columns[] = (new column(
            'rank',
            new lang_string('rank', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$participantalias}.finalrank")
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? (string)$value : '-';
            });

        // Score column.
        $columns[] = (new column(
            'score',
            new lang_string('score', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$participantalias}.totalscore")
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? number_format($value) : '0';
            });

        // Correct answers column (count).
        $columns[] = (new column(
            'correctanswers',
            new lang_string('correctanswers', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(*) FROM {kahoodle_responses} r
                WHERE r.participantid = {$participantalias}.id AND r.iscorrect = 1)", 'correctcount')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? (string)$value : '0';
            });

        // Questions answered column (count).
        $columns[] = (new column(
            'questionsanswered',
            new lang_string('questionsanswered', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(*) FROM {kahoodle_responses} r
                WHERE r.participantid = {$participantalias}.id)", 'answeredcount')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? (string)$value : '0';
            });

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $participantalias = $this->get_table_alias('kahoodle_participants');

        $filters = [];

        // Display name filter.
        $filters[] = (new filter(
            text::class,
            'displayname',
            new lang_string('participantdisplayname', 'mod_kahoodle'),
            $this->get_entity_name(),
            "{$participantalias}.displayname"
        ))
            ->add_joins($this->get_joins());

        // Rank filter.
        $filters[] = (new filter(
            number::class,
            'rank',
            new lang_string('rank', 'mod_kahoodle'),
            $this->get_entity_name(),
            "{$participantalias}.finalrank"
        ))
            ->add_joins($this->get_joins());

        // Score filter.
        $filters[] = (new filter(
            number::class,
            'score',
            new lang_string('score', 'mod_kahoodle'),
            $this->get_entity_name(),
            "{$participantalias}.totalscore"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
