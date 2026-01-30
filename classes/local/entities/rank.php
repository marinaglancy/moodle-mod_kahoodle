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

/**
 * Represents a participant's score and rank in a Kahoodle round
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rank {
    /** @var participant The participant entity */
    public participant $participant;
    /** @var int The participant's total score after this question */
    public int $score;
    /** @var int The participant's rank after this question (minimum rank in case of ties) */
    public int $minrank;
    /** @var int The participant's rank after this question (maximum rank in case of ties) */
    public int $maxrank;
    /** @var participant[] List of participants tied with this participant */
    public array $tiewith;

    /** @var int|null Score of the participant with the previous rank, null if this participant is the first */
    public ?int $prevscore;
    /** @var participant[] List of participants with the previous score */
    public array $withprevscore;
    /** @var rank|null Rank of the participant after the previous question (to show rank up/down) */
    public ?rank $prevquestionrank = null;

    /**
     * Constructor
     *
     * @param participant $participant The participant entity
     * @param int $score The participant's total score after this question
     * @param int $minrank The participant's minimum rank after this question
     * @param int $maxrank The participant's maximum rank after this question
     * @param participant[] $tiewith List of participants tied with this participant
     * @param int|null $prevscore Score of the participant with the previous score, null if this participant is the first
     * @param participant[] $withprevscore List of participants with the previous score
     */
    public function __construct(
        participant $participant,
        int $score,
        int $minrank,
        int $maxrank,
        array $tiewith,
        ?int $prevscore,
        array $withprevscore
    ) {
        $this->participant = $participant;
        $this->score = $score;
        $this->minrank = $minrank;
        $this->maxrank = $maxrank;
        $this->tiewith = $tiewith;
        $this->prevscore = $prevscore;
        $this->withprevscore = $withprevscore;
    }

    /**
     * Empty rank object when there is no data available
     *
     * @param participant $participant
     * @return rank
     */
    public static function create_empty(participant $participant): rank {
        return new rank($participant, 0, 0, 0, [], null, []);
    }

    public function get_data_for_revision(): array {
        global $CFG;
        $hascompletion = false; // TODO placeholder.
        $pointstocomplete = 0; // TODO placeholder.
        $completed = !$hascompletion || $this->score >= $pointstocomplete;

        $imagedir = $CFG->wwwroot . '/mod/kahoodle/pix/ranks/';


        // TODO image/status (assess in the following order):
        // - if completion criteria is set and not met, show message "You need X more points to complete.", image fail.png, header "Next time!"
        // - if completion criteria is not set and score is zero, show motivational message (to define!) and image fail.png, header "Keep trying!"
        // - else rank message is get_rank_message(), and also:
        //    - if minrank == 1, 2 or 3, show image 1.png, 2.png, 3.png and header "Congratulations!"
        //    - else show participation award.png image and header "Well done!" or "Good job!"
        return [
            'rankimage' => $imagedir.'award.png', // TODO implement
            'rankheader' => 'Good job!', // TODO implement
            'rankstatus' => $this->get_rank_message(true), // TODO implement
        ];
    }

    /**
     * Rank status message displayed after each question
     *
     * Examples:
     * - "You are in 1st place! Well done."
     * - "You are in 1st place tied with NAME and NAME."
     * - "You are in 5th place, 123 points behind NAME."
     * - "You are in 5th place tied with NAME and NAME, 123 points behind NAME."
     *
     * @return array
     */
    public function get_data_for_question_results(): array {
        if ($this->minrank == 0 || $this->maxrank == 0) {
            return [];
        }

        if ($this->score == 0) {
            // TODO review and add language strings.
            $motivationmessages = [
                "Keep going, you'll get points soon!",
                "Don't give up, try the next question!",
                "You're doing great, stay focused!",
                "Every point counts, keep trying!",
                "Stay motivated, your score will improve!",
            ];
            return ['rankstatus' => $motivationmessages[array_rand($motivationmessages)]];
        }

        return ['rankstatus' => $this->get_rank_message(false)];
    }

    protected function get_rank_message(bool $isrevision = false): string {

        $myrank = $this->minrank . $this->get_rank_suffix($this->minrank);
        if ($this->minrank != $this->maxrank) {
            $myrank = $this->minrank . '-' . $this->maxrank . $this->get_rank_suffix($this->maxrank);
        }

        $withbehind = !$isrevision && count($this->withprevscore) > 0;
        if ($withbehind && count($this->tiewith) > 2 && count($this->withprevscore) > 2) {
            // Too many names to display, do not display "you are behind".
            $withbehind = false;
        }

        $namelist1 = $this->name_list($this->tiewith);
        $namelist2 = $this->name_list($this->withprevscore);
        if ($withbehind) {
            // TODO implement with proper language strings.
            return "You are in " . $myrank . " place"
                . (!empty($this->tiewith) ? " tied with " . $namelist1 : "")
                . ", " . ($this->prevscore - $this->score) . " points behind "
                        . $namelist2
                . ".";
        } else {
            // TODO implement with proper language strings.
            return "You are in " . $myrank . " place"
                . (!empty($this->tiewith) ? " tied with " . $namelist1 : "")
                . ".";
        }
    }

    /**
     * Format list of names (for "tied with" and/or "behind" sentences)
     *
     * @param array $participants
     * @return string
     */
    protected function name_list(array $participants): string {
        if (count($participants) === 0) {
            return '';
        }
        $participants = array_values($participants);
        if (count($participants) === 1) {
            return $participants[0]->get_display_name();
        }
        if (count($participants) === 2) {
            return get_string('twonames', 'mod_kahoodle', [
                'one' => $participants[0]->get_display_name(),
                'two' => $participants[1]->get_display_name(),
            ]);
        }

        $p = $participants[array_rand($participants)];
        return get_string('morethantwo', 'mod_kahoodle', [
            'one' => $p->get_display_name(),
            'count' => count($participants) - 1,
        ]);
    }

    /**
     * Suffix for the rank number: st, nd, rd, th
     *
     * @param int $rank
     * @return string
     */
    protected function get_rank_suffix(int $rank): string {
        global $CFG;
        $lang = current_language();
        if ($lang !== 'en' && strpos($lang, 'en_') !== 0) {
            // Suffixes are only implemented for English.
            return '';
        }

        $lastdigit = $rank % 10;
        $last2digits = $rank % 100;
        if ($lastdigit === 1 && $last2digits !== 11) {
            return 'st';
        } else if ($lastdigit === 2 && $last2digits !== 12) {
            return 'nd';
        } else if ($lastdigit === 3 && $last2digits !== 13) {
            return 'rd';
        } else {
            return 'th';
        }
    }

    /**
     * Get rank movement status: up/down/no change
     *
     * @return int Returns positive number if rank went down, negative if rank went up, zero if no change
     */
    public function get_rank_movement_status(): int {
        if ($this->prevquestionrank === null) {
            return 0;
        }
        return $this->minrank - $this->prevquestionrank->minrank;
    }

    /**
     * Get rank either as a single number or a range (for a tie)
     *
     * @return string "4" or "2-5"
     */
    public function get_rank_as_range(): string {
        if ($this->minrank == $this->maxrank) {
            return (string)$this->minrank;
        } else {
            return $this->minrank . '-' . $this->maxrank;
        }
    }
}
