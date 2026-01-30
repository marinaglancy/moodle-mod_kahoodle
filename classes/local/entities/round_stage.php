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

/**
 * Represents a single stage in a Kahoodle round
 *
 * A stage can be a non-question stage (lobby, leaders, revision) or a question stage
 * (preview, question, results) associated with a specific round_question.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class round_stage {
    /** @var round The parent round */
    protected round $round;
    /** @var string The stage constant (e.g., STAGE_LOBBY, STAGE_QUESTION) */
    protected string $stagename;
    /** @var round_question|null The associated round question (null for non-question stages) */
    protected ?round_question $roundquestion;
    /** @var int Duration in seconds */
    protected int $duration;

    /**
     * Constructor
     *
     * @param round $round The parent round
     * @param string $stagename The stage constant
     * @param round_question|null $roundquestion The associated round question (null for non-question stages)
     * @param int $duration Duration in seconds
     */
    public function __construct(round $round, string $stagename, ?round_question $roundquestion, int $duration) {
        $this->round = $round;
        $this->stagename = $stagename;
        $this->roundquestion = $roundquestion;
        $this->duration = $duration;
    }

    /**
     * Create an instance for a question stage (preview, question, results, leaders)
     *
     * @param round_question $roundquestion
     * @param string $stagename
     * @return round_stage
     */
    public static function create_from_round_question(
        round_question $roundquestion,
        string $stagename
    ): round_stage {
        return new self($roundquestion->get_round(), $stagename, $roundquestion, $roundquestion->get_stage_duration($stagename));
    }

    /**
     * Get the parent round
     *
     * @return round
     */
    public function get_round(): round {
        return $this->round;
    }

    /**
     * Get the stage constant
     *
     * @return string
     */
    public function get_stage_name(): string {
        return $this->stagename;
    }

    /**
     * Get the associated round question
     *
     * @return round_question|null
     */
    public function get_round_question(): ?round_question {
        return $this->roundquestion;
    }

    /**
     * Get the duration in seconds
     *
     * @return int
     */
    public function get_duration(): int {
        return $this->duration;
    }

    /**
     * Get the question number (1-based)
     *
     * @return int 0 for non-question stages
     */
    public function get_question_number(): int {
        return $this->roundquestion ? $this->roundquestion->get_data()->sortorder : 0;
    }

    /**
     * Check if this is a question-related stage
     *
     * @return bool
     */
    public function is_question_stage(): bool {
        return $this->roundquestion !== null;
    }

    /**
     * Check if this stage matches the given stage and question number
     *
     * @param string $stagename The stage constant
     * @param int $questionnumber The question number (1-based)
     * @return bool
     */
    public function matches(string $stagename, int $questionnumber): bool {
        return $this->stagename === $stagename && $this->get_question_number() === $questionnumber;
    }
}
