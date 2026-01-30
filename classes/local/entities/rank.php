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

    /**
     * Get data for the revision screen (end of game)
     *
     * Returns rankimage, rankheader, and rankstatus based on completion and score.
     *
     * @return array{rankimage: string, rankheader: string, rankstatus: string}
     */
    public function get_data_for_revision(): array {
        global $CFG;
        $hascompletion = false; // TODO placeholder.
        $pointstocomplete = 0; // TODO placeholder.
        $completed = !$hascompletion || $this->score >= $pointstocomplete;

        $imagedir = $CFG->wwwroot . '/mod/kahoodle/pix/ranks/';

        // Completion criteria set but not met.
        if ($hascompletion && !$completed) {
            $pointsneeded = $pointstocomplete - $this->score;
            return [
                'rankimage' => $imagedir . 'fail.png',
                'rankheader' => get_string('rankheader_completionnotmet', 'mod_kahoodle'),
                'rankstatus' => get_string('rankstatus_completionnotmet', 'mod_kahoodle', $pointsneeded),
            ];
        }

        // No completion criteria and score is zero.
        if ($this->score == 0) {
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            // Mdlcode assume: $idx ['1','2','3','4','5'].
            $idx = rand(1, 5);
            $messagekey = 'rankrevisionzeroscore' . $idx;
            return [
                'rankimage' => $imagedir . 'fail.png',
                'rankheader' => get_string('rankheader_zeroscore', 'mod_kahoodle'),
                'rankstatus' => get_string($messagekey, 'mod_kahoodle'),
            ];
        }

        // Has score - show rank message.
        $rankstatus = $this->get_rank_message(true);

        // Podium finishers (1st, 2nd, 3rd).
        if ($this->minrank >= 1 && $this->minrank <= 3) {
            return [
                'rankimage' => $imagedir . $this->minrank . '.png',
                'rankheader' => get_string('rankheader_medal', 'mod_kahoodle'),
                'rankstatus' => $rankstatus,
            ];
        }

        // Other ranks - participation award with rotating header.
        // Mdlcode assume: $idx ['1','2','3'].
        $idx = rand(1, 3);
        $headerkey = 'rankheader_other' . $idx;
        return [
            'rankimage' => $imagedir . 'award.png',
            'rankheader' => get_string($headerkey, 'mod_kahoodle'),
            'rankstatus' => $rankstatus,
        ];
    }

    /**
     * Data for the results screen (after each question)
     *
     * @return array{rankstatus: string}
     */
    public function get_data_for_question_results(): array {
        if ($this->minrank == 0 || $this->maxrank == 0) {
            return [];
        }

        if ($this->score == 0) {
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            // Mdlcode assume: $idx ['1','2','3','4','5'].
            $idx = rand(1, 5);
            $messagekey = 'rankmotivation' . $idx;
            return ['rankstatus' => get_string($messagekey, 'mod_kahoodle')];
        }

        return ['rankstatus' => $this->get_rank_message(false)];
    }

    /**
     * Build the rank message string
     *
     * @param bool $isrevision True for revision screen ("You finished in..."), false for question results ("You are in...")
     * @return string
     */
    protected function get_rank_message(bool $isrevision = false): string {
        $myrank = $this->minrank . $this->get_rank_suffix($this->minrank);
        if ($this->minrank != $this->maxrank) {
            $myrank = $this->minrank . '-' . $this->maxrank . $this->get_rank_suffix($this->maxrank);
        }

        $tiednames = $this->name_list($this->tiewith);
        $behindnames = $this->name_list($this->withprevscore);
        $pointsbehind = $this->prevscore !== null ? $this->prevscore - $this->score : 0;

        $withbehind = !$isrevision && count($this->withprevscore) > 0;
        if ($withbehind && count($this->tiewith) > 2 && count($this->withprevscore) > 2) {
            // Too many names to display, do not display "you are behind".
            $withbehind = false;
        }

        if ($isrevision) {
            // Revision screen: "You finished in Xth place!".
            if (!empty($this->tiewith)) {
                return get_string('rankstatus_finished_tied', 'mod_kahoodle', [
                    'rank' => $myrank,
                    'names' => $tiednames,
                ]);
            }
            return get_string('rankstatus_finished', 'mod_kahoodle', $myrank);
        }

        // Question results screen: "You are in Xth place...".
        if ($withbehind && !empty($this->tiewith)) {
            return get_string('rankstatus_inplace_tied_behind', 'mod_kahoodle', [
                'rank' => $myrank,
                'tiednames' => $tiednames,
                'points' => $pointsbehind,
                'behindnames' => $behindnames,
            ]);
        }
        if ($withbehind) {
            return get_string('rankstatus_inplace_behind', 'mod_kahoodle', [
                'rank' => $myrank,
                'points' => $pointsbehind,
                'names' => $behindnames,
            ]);
        }
        if (!empty($this->tiewith)) {
            return get_string('rankstatus_inplace_tied', 'mod_kahoodle', [
                'rank' => $myrank,
                'names' => $tiednames,
            ]);
        }
        return get_string('rankstatus_inplace', 'mod_kahoodle', $myrank);
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
