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
use core_user\fields;
use html_writer;
use lang_string;
use moodle_url;
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
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('participant', 'mod_kahoodle');
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
     * Get the list of user fields required for userpic and fullname.
     *
     * @return string[]
     */
    protected static function get_user_fields(): array {
        return fields::for_userpic()->with_name()->get_required_fields();
    }

    /**
     * Add user fields to a column.
     *
     * @param column $column The column to add fields to
     * @param string $useralias The user table alias
     * @return column
     */
    protected function add_user_fields_to_column(column $column, string $useralias): column {
        foreach (self::get_user_fields() as $field) {
            $column->add_field("{$useralias}.{$field}", "user_{$field}");
        }
        return $column;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $participantalias = $this->get_table_alias('kahoodle_participants');
        $useralias = $this->get_table_alias('user');

        $columns = [];

        // Participant column (avatar + display name).
        $participantcolumn = (new column(
            'participant',
            new lang_string('participant', 'mod_kahoodle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$participantalias}.displayname")
            ->add_field("{$participantalias}.userid")
            ->set_is_sortable(true, ["{$participantalias}.displayname"])
            ->add_callback(static function (?string $value, stdClass $row): string {
                global $OUTPUT;

                $displayname = s($value);

                // Build avatar from participant's user if available.
                if (!empty($row->user_id)) {
                    $user = self::extract_user_record($row);
                    $avatar = $OUTPUT->user_picture($user, ['size' => 35, 'link' => false, 'class' => 'mr-2']);
                } else {
                    // User deleted - show initials avatar.
                    $avatar = html_writer::span(
                        self::get_initials($displayname),
                        'userinitials size-35 mr-2'
                    );
                }

                return $avatar . $displayname;
            });
        $columns[] = $this->add_user_fields_to_column($participantcolumn, $useralias);

        // User column (profile picture + fullname, link to profile).
        $usercolumn = (new column(
            'user',
            new lang_string('user'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true, ["{$useralias}.lastname", "{$useralias}.firstname"])
            ->add_callback(static function ($value, stdClass $row): string {
                global $OUTPUT;

                if (empty($row->user_id)) {
                    return html_writer::tag('em', get_string('deleteduser', 'bulkusers'));
                }

                $user = self::extract_user_record($row);
                $fullname = fullname($user);
                $avatar = $OUTPUT->user_picture($user, ['size' => 35, 'link' => false, 'class' => 'mr-2']);
                $profileurl = new moodle_url('/user/profile.php', ['id' => $user->id]);

                return html_writer::link($profileurl, $avatar . $fullname);
            });
        $columns[] = $this->add_user_fields_to_column($usercolumn, $useralias);

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
     * Extract user record from row data
     *
     * @param stdClass $row
     * @return stdClass
     */
    protected static function extract_user_record(stdClass $row): stdClass {
        $user = new stdClass();
        foreach (self::get_user_fields() as $field) {
            $aliasedfield = "user_{$field}";
            $user->$field = $row->$aliasedfield ?? ($field === 'id' ? 0 : '');
        }
        return $user;
    }

    /**
     * Get initials from a display name
     *
     * @param string $displayname
     * @return string
     */
    protected static function get_initials(string $displayname): string {
        $parts = preg_split('/\s+/', trim($displayname));
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
        }
        return mb_strtoupper(mb_substr($displayname, 0, 2));
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $participantalias = $this->get_table_alias('kahoodle_participants');
        $useralias = $this->get_table_alias('user');

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

        // User filter (user selector).
        $filters[] = (new filter(
            \core_reportbuilder\local\filters\user::class,
            'user',
            new lang_string('user'),
            $this->get_entity_name(),
            "{$participantalias}.userid"
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
