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
 * Represents a question in a Kahoodle round
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class round_question {
    /** @var stdClass The round question record */
    protected stdClass $data;
    /** @var round|null The round entity, lazy loaded */
    protected ?round $round = null;
    /** @var \mod_kahoodle\local\questiontypes\base|null The question type instance */
    protected ?\mod_kahoodle\local\questiontypes\base $type = null;

    /**
     * Protected constructor
     *
     * @param stdClass $data The round question record
     */
    protected function __construct(stdClass $data) {
        $this->data = $data;
    }

    /**
     * Get SQL to select all fields needed to create round question entity
     *
     * @return string
     */
    protected static function get_fields_sql(): string {
        $fields = array_merge(
            ["rq.id", "rq.roundid", "rq.questionversionid", "rq.sortorder", "rq.timecreated", "rq.timemodified"],
            array_map(fn($field) => "rq.$field", constants::FIELDS_ROUND_QUESTION),
            ['rq.totalresponses', 'rq.answerdistribution'], // Stats fields.
            ["qv.questionid", "qv.version"],
            array_map(fn($field) => "qv.$field", constants::FIELDS_QUESTION_VERSION),
            ["q.kahoodleid", "q.questiontype"]
        );

        return 'SELECT ' . implode(', ', $fields) . '
            FROM {kahoodle_round_questions} rq
            JOIN {kahoodle_question_versions} qv ON rq.questionversionid = qv.id
            JOIN {kahoodle_questions} q ON qv.questionid = q.id';
    }

    /**
     * Create a round question instance from a round question ID
     *
     * @param int $id The round question ID
     * @return self
     */
    public static function create_from_round_question_id(int $id): self {
        global $DB;
        $record = $DB->get_record_sql(self::get_fields_sql() .
            ' WHERE rq.id = ?', [$id], MUST_EXIST);
        return new self($record);
    }

    /**
     * Create a round question instance from a question ID and optional round
     *
     * @param int $id The question ID
     * @param round|null $round Optional round entity
     * @return self
     */
    public static function create_from_question_id(int $id, ?round $round = null): self {
        global $DB;
        if (!$round) {
            $question = $DB->get_record('kahoodle_questions', ['id' => $id], 'id, kahoodleid', MUST_EXIST);
            $round = \mod_kahoodle\questions::get_last_round($question->kahoodleid);
        }
        $record = $DB->get_record_sql(self::get_fields_sql() .
            ' WHERE q.id = ? AND rq.roundid = ?', [$id, $round->get_id()], MUST_EXIST);
        $q = new self($record);
        $q->round = $round;
        return $q;
    }

    /**
     * Create a new round question instance for a given round and question type
     *
     * @param round $round The round entity
     * @param string|null $questiontype The question type
     * @return self
     */
    public static function new_for_round_and_type(round $round, ?string $questiontype = null): self {
        global $DB;
        $type = $questiontype !== null ?
            \mod_kahoodle\questions::get_question_type_instance_or_default($questiontype) :
            \mod_kahoodle\questions::get_default_question_type();

        $record = (object)[
            'id' => 0,
            'roundid' => $round->get_id(),
            'questionversionid' => 0,
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'questionid' => 0,
            'version' => 0,
            'kahoodleid' => $round->get_kahoodleid(),
            'questiontype' => $type->get_type(),
            'totalresponses' => null,
            'answerdistribution' => null,
        ];
        foreach (constants::FIELDS_ROUND_QUESTION as $field) {
            $record->$field = null;
        }
        foreach (constants::FIELDS_QUESTION_VERSION as $field) {
            $record->$field = null;
        }
        $q = new self($record);
        $q->round = $round;
        $q->type = $type;
        return $q;
    }

    /**
     * Get the round entity for this question
     *
     * @return round
     */
    public function get_round(): round {
        if ($this->round === null) {
            $this->round = round::create_from_id($this->data->roundid);
        }
        return $this->round;
    }

    /**
     * Get the question type instance for this question
     *
     * @return \mod_kahoodle\local\questiontypes\base
     */
    public function get_question_type(): \mod_kahoodle\local\questiontypes\base {
        if ($this->type === null) {
            $this->type = \mod_kahoodle\questions::get_question_type_instance_or_default("" . $this->data->questiontype);
        }
        return $this->type;
    }

    /**
     * Get the round question ID
     *
     * @return int
     */
    public function get_id(): int {
        return $this->data->id;
    }

    /**
     * Get the question ID
     *
     * @return int
     */
    public function get_question_id(): int {
        return $this->data->questionid;
    }

    /**
     * Get the round question data record
     *
     * @return stdClass
     */
    public function get_data(): stdClass {
        return $this->data;
    }
}
