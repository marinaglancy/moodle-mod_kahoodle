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

use mod_kahoodle\local\game\instance;
use mod_kahoodle\constants;

/**
 * Pseudo-round in kahoodle play that represents statistics for all rounds
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statistics extends round {
    /** @var round[] */
    protected array $allrounds;

    /**
     * Constructor
     *
     * @param int $kahoodleid The Kahoodle activity ID
     * @param \stdClass|null $kahoodle Optional Kahoodle activity record, if known
     * @param \cm_info|null $cm Optional course module, if known
     * @return self
     */
    public static function create_from_kahoodle_id(int $kahoodleid, ?\stdClass $kahoodle = null, ?\cm_info $cm = null): self {
        $allrounds = instance::get_all_rounds($kahoodleid, 0, $kahoodle, $cm);

        $completedrounds = array_filter($allrounds, function (round $round) {
            return in_array($round->get_current_stage_name(), [constants::STAGE_ARCHIVED, constants::STAGE_REVISION]);
        });
        $lastround = reset($completedrounds) ?: reset($allrounds);
        // Emulate data as the last round data but with id=0 since this is not a real round.
        $data = (object)(array)$lastround->data;
        $data->id = 0;
        $obj = new static($data);
        $obj->cm = $lastround->get_cm();
        $obj->kahoodle = $lastround->get_kahoodle();
        $obj->allrounds = $allrounds;
        return $obj;
    }

    /**
     * Create statistics instance from course module ID
     *
     * @param int $cmid The course module ID
     * @return self
     */
    public static function create_from_cm_id(int $cmid) {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'kahoodle');
        return self::create_from_kahoodle_id($cm->instance, null, $cm);
    }

    /**
     * Create statistics instance from a participant ID
     *
     * @param int $participantid The participant ID
     * @param int $roundid Returns the round ID that the participant belongs to
     * @return self
     */
    public static function create_for_participant_id(int $participantid, int &$roundid) {
        global $DB;
        $record = $DB->get_record_sql(
            "SELECT k.*, p.roundid
            FROM {kahoodle_participants} p
            JOIN {kahoodle_rounds} r ON p.roundid = r.id
            JOIN {kahoodle} k ON r.kahoodleid = k.id
            WHERE p.id = ?",
            [$participantid],
            MUST_EXIST
        );
        $roundid = $record->roundid;
        unset($record->roundid);
        return self::create_from_kahoodle_id(0, $record);
    }

    /**
     * Create statistics instance from a round ID
     *
     * @param int $roundid The round ID
     * @return self
     */
    public static function create_for_round_id(int $roundid) {
        global $DB;
        $record = $DB->get_record_sql(
            "SELECT k.*
            FROM {kahoodle_rounds} r
            JOIN {kahoodle} k ON r.kahoodleid = k.id
            WHERE r.id = ?",
            [$roundid],
            MUST_EXIST
        );
        return self::create_from_kahoodle_id(0, $record);
    }

    /**
     * All rounds
     *
     * @return round[] Array of round entities indexed by their IDs, ordered by non-archived first, then by timecreated DESC
     */
    public function get_all_rounds(): array {
        return $this->allrounds;
    }

    /**
     * Get the most recent round
     *
     * @return round
     */
    public function get_last_round(): round {
        return reset($this->allrounds);
    }

    /**
     * Check if the current user can join the last round
     *
     * @return bool
     */
    public function can_i_join_last_round(): bool {
        $round = $this->get_last_round();
        if (
            $round->is_in_progress()
                && $round->is_participant() === null
                && has_capability('mod/kahoodle:participate', $this->get_context())
        ) {
            // The last round is in progress and the current user has capability but is not yet a participant.
            if ($this->kahoodle->allowrepeat || $this->kahoodle->identitymode === constants::IDENTITYMODE_ANONYMOUS) {
                return true;
            }
            // Otherwise user can only join if they have not participated before.
            $pastparticipations = $this->get_my_past_participations();
            return empty($pastparticipations);
        }
        return false;
    }

    /** @var participant[]|null */
    protected ?array $pastparticipations = null;

    /**
     * Get the current user's participations in previous rounds
     *
     * @return participant[]
     */
    public function get_my_past_participations() {
        global $USER;
        if ($this->pastparticipations === null) {
            $this->pastparticipations = participant::load_round_participants(
                $this,
                ' AND p.userid = :userid',
                ['userid' => $USER->id]
            );
        }
        return $this->pastparticipations;
    }

    /**
     * Get all stages for this round in order
     *
     * For all-round statistics we do not show the lobby stage and leaders after each question
     *
     * @param bool $ispreview Whether this is for preview mode (true) or live game (false)
     * @return round_stage[] Array of round_stage objects in order
     */
    public function get_all_stages(bool $ispreview = false): array {
        $stages = parent::get_all_stages($ispreview);
        // Return all stages except lobby and leaders.
        return array_values(array_filter($stages, function ($stage) {
            return !in_array($stage->get_stage_name(), [constants::STAGE_LOBBY, constants::STAGE_LEADERS]);
        }));
    }

    /**
     * Get ordered questions for all-rounds playback
     *
     * Returns questions ordered by sortorder from the last round, with questions
     * not in the last round appended at the end.
     *
     * @return round_question[]
     */
    protected function load_all_questions(): array {
        global $DB;

        $completedrounds = array_filter($this->allrounds, function (round $round) {
            return in_array($round->get_current_stage_name(), [constants::STAGE_ARCHIVED, constants::STAGE_REVISION]);
        });
        $lastround = reset($completedrounds) ?: reset($this->allrounds);

        // Get all questions for this kahoodle with their latest version.
        $fields = array_merge(
            ["null AS id", "null AS roundid", "qv.id AS questionversionid"],
            array_map(fn($field) => "rq.$field", constants::FIELDS_ROUND_QUESTION),
            ["qv.questionid", "qv.version"],
            array_map(fn($field) => "qv.$field", constants::FIELDS_QUESTION_VERSION),
            ["q.kahoodleid", "q.questiontype"],
            ['k.questionformat']
        );
        $sql = "SELECT " . implode(', ', $fields) . "
                  FROM {kahoodle_questions} q
                  JOIN {kahoodle} k ON k.id = q.kahoodleid
                  JOIN {kahoodle_question_versions} qv ON qv.questionid = q.id AND qv.islast = 1
             LEFT JOIN {kahoodle_round_questions} rq ON rq.questionversionid = qv.id AND rq.roundid = :lastroundid
                 WHERE q.kahoodleid = :kahoodleid
              ORDER BY CASE WHEN rq.sortorder IS NULL THEN 1 ELSE 0 END, rq.sortorder ASC, q.id ASC";

        $records = $DB->get_recordset_sql($sql, [
            'kahoodleid' => $this->get_kahoodleid(),
            'lastroundid' => $lastround?->get_id() ?: 0,
        ]);

        $questions = [];
        $sortorder = 1;
        foreach ($records as $record) {
            $roundquestion = null;
            $record->sortorder = $sortorder++;
            $roundquestion = round_question::create_from_partial_record($record, $this);
            $questions[] = $roundquestion;
        }

        return $questions;
    }

    /**
     * Get rankings up to the question with the given question number
     *
     * @param int $questionnumber
     * @return rank[] Array of rank objects indexed by participant ID
     */
    public function get_question_rankings(int $questionnumber): array {
        global $DB;
        if ($questionnumber != $this->get_questions_count()) {
            // For statistics we can only calculate total rankings.
            return [];
        }
        if (isset($this->questionrankings[$questionnumber])) {
            return $this->questionrankings[$questionnumber];
        }

        $scores = [];
        $participants = $this->get_all_participants();
        foreach ($participants as $participant) {
            $scores[$participant->get_id()] = $participant->get_total_score();
        }

        $this->questionrankings[$questionnumber] = $this->get_rankings_int($scores);
        return $this->questionrankings[$questionnumber];
    }
}
