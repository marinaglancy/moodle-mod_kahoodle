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

namespace mod_kahoodle\reportbuilder\local\systemreports;

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use lang_string;
use mod_kahoodle\constants;
use mod_kahoodle\questions;

/**
 * All rounds question statistics system report
 *
 * Shows aggregated question statistics from all completed rounds for a kahoodle activity.
 * Each question appears once with totals across all rounds.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class all_rounds_statistics extends system_report {
    /** @var int|null Cached kahoodle ID */
    protected ?int $kahoodleid = null;

    /** @var int|null Total participants across all completed rounds */
    protected ?int $totalparticipants = null;

    /**
     * Get the kahoodle ID for this report
     *
     * @return int
     */
    protected function get_kahoodleid(): int {
        if ($this->kahoodleid === null) {
            $this->kahoodleid = $this->get_parameter('kahoodleid', 0, PARAM_INT);
        }
        return $this->kahoodleid;
    }

    /**
     * Get total participants across all completed rounds
     *
     * @return int
     */
    protected function get_total_participants(): int {
        global $DB;

        if ($this->totalparticipants === null) {
            $sql = "SELECT COUNT(*)
                      FROM {kahoodle_participants} p
                      JOIN {kahoodle_rounds} r ON r.id = p.roundid
                     WHERE r.kahoodleid = :kahoodleid
                       AND r.currentstage IN (:stagerevision, :stagearchived)";
            $this->totalparticipants = (int)$DB->count_records_sql($sql, [
                'kahoodleid' => $this->get_kahoodleid(),
                'stagerevision' => constants::STAGE_REVISION,
                'stagearchived' => constants::STAGE_ARCHIVED,
            ]);
        }
        return $this->totalparticipants;
    }

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        // Use kahoodle_questions as the main table since we want one row per question.
        $this->set_main_table('kahoodle_questions', 'kq');

        // Filter to only questions that have been used in completed rounds for this kahoodle.
        $paramkahoodleid = database::generate_param_name();
        $paramstagerevision = database::generate_param_name();
        $paramstagearchived = database::generate_param_name();
        $this->add_base_condition_sql(
            "kq.kahoodleid = :{$paramkahoodleid} AND EXISTS (
                SELECT 1 FROM {kahoodle_round_questions} krq
                JOIN {kahoodle_question_versions} kqv ON kqv.id = krq.questionversionid
                JOIN {kahoodle_rounds} kr ON kr.id = krq.roundid
                WHERE kqv.questionid = kq.id
                  AND kr.currentstage IN (:{$paramstagerevision}, :{$paramstagearchived})
            )",
            [
                $paramkahoodleid => $this->get_kahoodleid(),
                $paramstagerevision => constants::STAGE_REVISION,
                $paramstagearchived => constants::STAGE_ARCHIVED,
            ]
        );

        // Add base fields.
        $this->add_base_fields("kq.id, kq.questiontype");

        // Add columns.
        $this->add_columns();

        // Add filters.
        $this->add_filters();

        // Set initial sort order.
        $this->set_initial_sort_column('question:questiontext', SORT_ASC);

        // Set downloadable.
        $this->set_downloadable(true, get_string('allroundsstatistics', 'mod_kahoodle'));
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('mod/kahoodle:viewresults', $this->get_context());
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @return void
     */
    protected function add_columns(): void {
        $totalparticipants = $this->get_total_participants();

        // Question type column.
        $this->add_column((new column(
            'questiontype',
            new lang_string('questiontype', 'mod_kahoodle'),
            'question'
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_field("kq.questiontype")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $value): string {
                if ($value === null) {
                    return '';
                }
                $type = questions::get_question_type_instance($value);
                return $type ? $type->get_display_name() : s($value);
            }));

        // Question text column - get the latest version's text.
        $this->add_column((new column(
            'questiontext',
            new lang_string('questiontext', 'mod_kahoodle'),
            'question'
        ))
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("(SELECT kqv.questiontext FROM {kahoodle_question_versions} kqv
                WHERE kqv.questionid = kq.id ORDER BY kqv.version DESC LIMIT 1)", 'questiontext')
            ->set_is_sortable(false));

        // Total responses column - sum across all completed rounds.
        $stagerevision = constants::STAGE_REVISION;
        $stagearchived = constants::STAGE_ARCHIVED;
        $this->add_column((new column(
            'totalresponses',
            new lang_string('totalresponses', 'mod_kahoodle'),
            'question'
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(*)
                FROM {kahoodle_responses} r
                JOIN {kahoodle_round_questions} krq ON krq.id = r.roundquestionid
                JOIN {kahoodle_question_versions} kqv ON kqv.id = krq.questionversionid
                JOIN {kahoodle_rounds} kr ON kr.id = krq.roundid
                WHERE kqv.questionid = kq.id
                  AND kr.currentstage IN ('{$stagerevision}', '{$stagearchived}')
            )", 'totalresponses')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? (string)$value : '0';
            }));

        // Correct responses column - sum across all completed rounds.
        $this->add_column((new column(
            'correctresponses',
            new lang_string('correctresponses', 'mod_kahoodle'),
            'question'
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(*)
                FROM {kahoodle_responses} r
                JOIN {kahoodle_round_questions} krq ON krq.id = r.roundquestionid
                JOIN {kahoodle_question_versions} kqv ON kqv.id = krq.questionversionid
                JOIN {kahoodle_rounds} kr ON kr.id = krq.roundid
                WHERE kqv.questionid = kq.id
                  AND kr.currentstage IN ('{$stagerevision}', '{$stagearchived}')
                  AND r.iscorrect = 1
            )", 'correctresponses')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                return $value !== null ? (string)$value : '0';
            }));

        // Average score column - total points / total participants.
        $this->add_column((new column(
            'averagescore',
            new lang_string('results_averagescore', 'mod_kahoodle'),
            'question'
        ))
            ->set_type(column::TYPE_FLOAT)
            ->add_field("(SELECT COALESCE(SUM(r.points), 0)
                FROM {kahoodle_responses} r
                JOIN {kahoodle_round_questions} krq ON krq.id = r.roundquestionid
                JOIN {kahoodle_question_versions} kqv ON kqv.id = krq.questionversionid
                JOIN {kahoodle_rounds} kr ON kr.id = krq.roundid
                WHERE kqv.questionid = kq.id
                  AND kr.currentstage IN ('{$stagerevision}', '{$stagearchived}')
            )", 'totalpoints')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, \stdClass $row) use ($totalparticipants): string {
                $totalpoints = (int)($row->totalpoints ?? 0);
                if ($totalparticipants === 0) {
                    return '-';
                }
                $average = $totalpoints / $totalparticipants;
                return number_format($average, 1);
            }));
    }

    /**
     * Adds the filters we want to display in the report
     *
     * @return void
     */
    protected function add_filters(): void {
        // Question type filter.
        $this->add_filter(new \core_reportbuilder\local\report\filter(
            \core_reportbuilder\local\filters\text::class,
            'questiontype',
            new lang_string('questiontype', 'mod_kahoodle'),
            'question',
            "kq.questiontype"
        ));
    }
}
