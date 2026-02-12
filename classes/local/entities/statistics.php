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

use mod_kahoodle\api;
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
     * @param int $kahoodleid
     * @throws \moodle_exception
     */
    public function __construct(int $kahoodleid) {
        $this->allrounds = api::get_all_rounds($kahoodleid);
        $completedrounds = array_filter($this->allrounds, function (round $round) {
            return in_array($round->get_current_stage_name(), [constants::STAGE_ARCHIVED, constants::STAGE_REVISION]);
        });
        if (empty($completedrounds)) {
            throw new \moodle_exception('error_nostatistics', 'mod_kahoodle');
        }
        $lastround = reset($completedrounds);
        // Emulate data as the last round data.
        $this->data = (object)(array)$lastround->data;
        $this->cm = $lastround->get_cm();
        $this->kahoodle = $lastround->get_kahoodle();
    }

    /**
     * Get all stages for this round in order
     *
     * For preview mode, returns only question stages (preview, question, results) for each question.
     * For live game mode, includes lobby at the start, leaders after each question, and revision at the end.
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

        // Get all questions for this kahoodle with their latest version.
        $fields = array_merge(
            ["null AS id", "null AS roundid", "qv.id AS questionversionid"],
            array_map(fn($field) => "rq.$field", constants::FIELDS_ROUND_QUESTION),
            ["null AS totalresponses"], // TODO potentially calculate total responses.
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
              ORDER BY rq.sortorder ASC, q.id ASC";

        $records = $DB->get_recordset_sql($sql, [
            'kahoodleid' => $this->get_kahoodleid(),
            'lastroundid' => $this->get_id(),
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
