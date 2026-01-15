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

namespace mod_kahoodle\local\entities;

use mod_kahoodle\constants;
use stdClass;

/**
 * Represents a question round in Kahoodle
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class round {
    /** @var stdClass The round record */
    protected stdClass $data;

    /**
     * Protected constructor
     *
     * @param stdClass $data The round record
     */
    protected function __construct(stdClass $data) {
        $this->data = $data;
    }

    /**
     * Create a round instance from a database record object
     *
     * @param stdClass $record The round record from database
     * @return self
     */
    public static function create_from_object(stdClass $record): self {
        return new self($record);
    }

    /**
     * Create a round instance from a round ID
     *
     * @param int $id The round ID
     * @return self
     */
    public static function create_from_id(int $id): self {
        global $DB;
        $record = $DB->get_record('kahoodle_rounds', ['id' => $id], '*', MUST_EXIST);
        return new self($record);
    }

    /**
     * Get the round ID
     *
     * @return int
     */
    public function get_id(): int {
        return $this->data->id;
    }

    /**
     * Get the Kahoodle activity ID
     *
     * @return int
     */
    public function get_kahoodleid(): int {
        return $this->data->kahoodleid;
    }

    /**
     * Check if the round is editable
     *
     * A round is editable if it's in preparation stage and hasn't been started yet.
     *
     * @return bool
     */
    public function is_editable(): bool {
        return $this->data->currentstage === constants::STAGE_PREPARATION;
    }
}
