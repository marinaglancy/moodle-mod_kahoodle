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

namespace mod_kahoodle\output;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\round_question;
use mod_kahoodle\local\entities\round_stage;

/**
 * Output class for building playback stages
 *
 * Provides all stages with template-ready data for replaying completed rounds
 * from statistics reports. Supports single-round and all-rounds modes.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class playback {
    /** @var round|null Round for single-round mode */
    protected ?round $round;

    /** @var int|null Kahoodle ID for all-rounds mode */
    protected ?int $kahoodleid;

    /**
     * Constructor
     *
     * @param round|null $round Round for single-round mode
     * @param int|null $kahoodleid Kahoodle ID for all-rounds mode
     */
    public function __construct(?round $round = null, ?int $kahoodleid = null) {
        $this->round = $round;
        $this->kahoodleid = $kahoodleid;
    }

    /**
     * Export all playback stages
     *
     * @param \renderer_base $output
     * @return array With keys: quiztitle, totalquestions, stages[]
     */
    public function export_all_stages(\renderer_base $output): array {
        if ($this->round !== null) {
            return $this->export_single_round_stages($output);
        }
        return $this->export_all_rounds_stages($output);
    }

    /**
     * Export stages for a single round
     *
     * @param \renderer_base $output
     * @return array
     */
    protected function export_single_round_stages(\renderer_base $output): array {
        global $CFG;

        $allstages = $this->round->get_all_stages(false);
        $totalquestions = $this->round->get_questions_count();
        $stages = [];

        foreach ($allstages as $stage) {
            $stagename = $stage->get_stage_name();

            // Skip archived.
            if ($stagename === constants::STAGE_ARCHIVED) {
                continue;
            }

            $template = $this->get_template_for_stage($stage);
            if ($template === null) {
                continue;
            }

            // Common template data for all stages.
            $templatedata = [
                'quiztitle' => $this->round->get_kahoodle_name(),
                'sortorder' => $stage->get_question_number() ?: '',
                'totalquestions' => $totalquestions,
                'cancontrol' => true,
                'isedit' => false,
                'isplayback' => true,
                'backgroundurl' => $CFG->wwwroot . '/mod/kahoodle/pix/classroom-bg.jpg',
            ];

            // Add stage-specific data.
            switch ($stagename) {
                case constants::STAGE_LOBBY:
                    $templatedata += $this->get_lobby_data();
                    break;

                case constants::STAGE_QUESTION_PREVIEW:
                case constants::STAGE_QUESTION:
                case constants::STAGE_QUESTION_RESULTS:
                    $templatedata += $this->get_question_stage_data($stage, $output);
                    break;

                case constants::STAGE_LEADERS:
                    $templatedata += $this->get_leaders_data_for_question(
                        $stage->get_question_number()
                    );
                    break;

                case constants::STAGE_REVISION:
                    $templatedata += $this->get_revision_data();
                    break;
            }

            $stages[] = [
                'stagesignature' => $stage->get_stage_signature(),
                'template' => $template,
                'duration' => $stage->get_duration(),
                'templatedata' => json_encode($templatedata),
            ];
        }

        return [
            'quiztitle' => $this->round->get_kahoodle_name(),
            'totalquestions' => $totalquestions,
            'stages' => $stages,
        ];
    }

    /**
     * Export stages for all rounds (aggregated)
     *
     * @param \renderer_base $output
     * @return array
     */
    protected function export_all_rounds_stages(\renderer_base $output): array {
        global $DB, $CFG;

        $lastround = \mod_kahoodle\questions::get_last_round($this->kahoodleid);
        $kahoodle = $lastround->get_kahoodle();

        // Get all completed round IDs.
        $completedrounds = $DB->get_records_select(
            'kahoodle_rounds',
            'kahoodleid = :kahoodleid AND currentstage IN (:revision, :archived)',
            [
                'kahoodleid' => $this->kahoodleid,
                'revision' => constants::STAGE_REVISION,
                'archived' => constants::STAGE_ARCHIVED,
            ],
            'timecreated ASC',
            'id'
        );
        $completedroundids = array_keys($completedrounds);

        if (empty($completedroundids)) {
            return [
                'quiztitle' => $kahoodle->name,
                'totalquestions' => 0,
                'stages' => [],
            ];
        }

        // Get ordered question list (same logic as all_rounds_statistics report).
        $questions = $this->get_ordered_questions_for_all_rounds($lastround, $completedroundids);
        $totalquestions = count($questions);
        $stages = [];

        // Build question stages for each question.
        $sortorder = 0;
        foreach ($questions as $questiondata) {
            $sortorder++;

            $commondata = [
                'quiztitle' => $kahoodle->name,
                'sortorder' => $sortorder,
                'totalquestions' => $totalquestions,
                'cancontrol' => true,
                'isedit' => false,
                'isplayback' => true,
                'backgroundurl' => $CFG->wwwroot . '/mod/kahoodle/pix/classroom-bg.jpg',
            ];

            // Use the round_question from the last round if available, otherwise create stages with defaults.
            $roundquestion = $questiondata['roundquestion'];
            $questiontype = $roundquestion ? $roundquestion->get_question_type() : null;

            // Preview stage.
            $previewduration = $roundquestion
                ? $roundquestion->get_stage_duration(constants::STAGE_QUESTION_PREVIEW)
                : (int)$kahoodle->questionpreviewduration;
            if ($previewduration > 0 && $roundquestion) {
                $outputclass = new roundquestion($roundquestion, constants::STAGE_QUESTION_PREVIEW, false);
                $templatedata = $commondata + (array)$outputclass->export_for_template($output);
                $template = $questiontype->get_template('facilitator', constants::STAGE_QUESTION_PREVIEW);
                $stages[] = [
                    'stagesignature' => constants::STAGE_QUESTION_PREVIEW . '-' . $sortorder,
                    'template' => $template,
                    'duration' => $previewduration,
                    'templatedata' => json_encode($templatedata),
                ];
            }

            // Question stage.
            if ($roundquestion) {
                $questionduration = $roundquestion->get_stage_duration(constants::STAGE_QUESTION);
                $outputclass = new roundquestion($roundquestion, constants::STAGE_QUESTION, false);
                $templatedata = $commondata + (array)$outputclass->export_for_template($output);
                $template = $questiontype->get_template('facilitator', constants::STAGE_QUESTION);
                $stages[] = [
                    'stagesignature' => constants::STAGE_QUESTION . '-' . $sortorder,
                    'template' => $template,
                    'duration' => $questionduration,
                    'templatedata' => json_encode($templatedata),
                ];
            }

            // Results stage with aggregated response counts.
            $resultsduration = $roundquestion
                ? $roundquestion->get_stage_duration(constants::STAGE_QUESTION_RESULTS)
                : (int)$kahoodle->questionresultsduration;
            if ($resultsduration > 0 && $roundquestion) {
                $outputclass = new roundquestion($roundquestion, constants::STAGE_QUESTION_RESULTS, false);
                $templatedata = $commondata + (array)$outputclass->export_for_template($output);

                // Override typedata with aggregated data from the question type.
                $templatedata['typedata'] = json_encode($questiontype->export_template_data_all_rounds(
                    $roundquestion,
                    constants::STAGE_QUESTION_RESULTS,
                    $questiondata['questionid'],
                    $completedroundids
                ));

                $template = $questiontype->get_template('facilitator', constants::STAGE_QUESTION_RESULTS);
                $stages[] = [
                    'stagesignature' => constants::STAGE_QUESTION_RESULTS . '-' . $sortorder,
                    'template' => $template,
                    'duration' => $resultsduration,
                    'templatedata' => json_encode($templatedata),
                ];
            }
        }

        // Final revision: top 5 participants by totalscore across all completed rounds.
        if (!empty($stages)) {
            $commondata = [
                'quiztitle' => $kahoodle->name,
                'sortorder' => '',
                'totalquestions' => $totalquestions,
                'cancontrol' => true,
                'isedit' => false,
                'isplayback' => true,
                'backgroundurl' => $CFG->wwwroot . '/mod/kahoodle/pix/classroom-bg.jpg',
            ];
            $templatedata = $commondata + $this->get_all_rounds_revision_data($completedroundids);

            // Mdlcode uses-next-line: template 'mod_kahoodle/facilitator/revision'.
            $stages[] = [
                'stagesignature' => constants::STAGE_REVISION,
                'template' => 'mod_kahoodle/facilitator/revision',
                'duration' => constants::DEFAULT_REVISION_DURATION,
                'templatedata' => json_encode($templatedata),
            ];
        }

        return [
            'quiztitle' => $kahoodle->name,
            'totalquestions' => $totalquestions,
            'stages' => $stages,
        ];
    }

    /**
     * Get the template name for a stage
     *
     * @param round_stage $stage
     * @return string|null
     */
    protected function get_template_for_stage(round_stage $stage): ?string {
        $stagename = $stage->get_stage_name();

        if ($stage->is_question_stage()) {
            $questiontype = $stage->get_round_question()->get_question_type();
            return $questiontype->get_template('facilitator', $stagename);
        }

        $templates = [
            // Mdlcode uses-next-line: template 'mod_kahoodle/facilitator/lobby'.
            constants::STAGE_LOBBY => 'mod_kahoodle/facilitator/lobby',
            // Mdlcode uses-next-line: template 'mod_kahoodle/facilitator/revision'.
            constants::STAGE_REVISION => 'mod_kahoodle/facilitator/revision',
        ];

        return $templates[$stagename] ?? null;
    }

    /**
     * Get lobby data for a single round
     *
     * @return array Template data additions
     */
    protected function get_lobby_data(): array {
        $participants = $this->round->get_all_participants();
        $participantdata = [];
        foreach ($participants as $participant) {
            $participantdata[] = [
                'participantid' => $participant->get_id(),
                'displayname' => $participant->get_display_name(),
                'avatarurl' => $participant->get_avatar_url()->out(false),
            ];
        }

        $url = $this->round->get_url()->out(false);
        $qrcode = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($url);

        return [
            'participantcount' => count($participants),
            'participants' => $participantdata,
            'qrcodeurl' => $qrcode,
            'joincode' => '',
        ];
    }

    /**
     * Get data for question stages (preview, question, results) using roundquestion output
     *
     * @param round_stage $stage
     * @param \renderer_base $output
     * @return array Template data additions
     */
    protected function get_question_stage_data(round_stage $stage, \renderer_base $output): array {
        $outputclass = new roundquestion(
            $stage->get_round_question(),
            $stage->get_stage_name(),
            false
        );
        return (array)$outputclass->export_for_template($output);
    }

    /**
     * Get leaderboard data for a specific question number
     *
     * Builds the leaders array with rank movement (up/down) by comparing
     * rankings at question N with rankings at question N-1.
     *
     * @param int $questionnumber 1-based question number
     * @param int $maxnumber Maximum number of leaders to return
     * @return array Template data for leaderboard template
     */
    public static function get_leaders_data_for_round(
        round $round,
        int $questionnumber,
        int $maxnumber = 5
    ): array {
        $rankings = $round->get_question_rankings($questionnumber);
        $prevrankings = $questionnumber > 1 ? $round->get_question_rankings($questionnumber - 1) : [];

        $leaders = count($rankings) > $maxnumber ? array_slice($rankings, 0, $maxnumber, true) : $rankings;

        $leaderdata = [];
        foreach ($leaders as $participantid => $rank) {
            $prevrank = $prevrankings[$participantid] ?? null;
            $rankmoved = 0;
            if ($prevrank !== null) {
                $rankmoved = $rank->minrank - $prevrank->minrank;
            }
            $leaderdata[] = [
                'displayname' => $rank->participant->get_display_name(),
                'avatarurl' => $rank->participant->get_avatar_url()->out(false),
                'score' => $rank->score,
                'rank' => $rank->get_rank_as_range(),
                'isup' => $rankmoved < 0,
                'isdown' => $rankmoved > 0,
            ];
        }

        return [
            'leaders' => $leaderdata,
            'statusmessage' => "Great job everyone! Get ready for the next question...",
        ];
    }

    /**
     * Get leaders data for a question in single-round mode
     *
     * @param int $questionnumber 1-based question number
     * @return array Template data additions
     */
    protected function get_leaders_data_for_question(int $questionnumber): array {
        return self::get_leaders_data_for_round($this->round, $questionnumber);
    }

    /**
     * Get revision data for single round (final leaderboard, no podium)
     *
     * @return array Template data additions
     */
    protected function get_revision_data(): array {
        $rankings = $this->round->get_rankings();
        $totalquestions = $this->round->get_questions_count();
        $prevrankings = $totalquestions > 1 ? $this->round->get_question_rankings($totalquestions - 1) : [];

        $leaders = count($rankings) > 5 ? array_slice($rankings, 0, 5, true) : $rankings;

        $leaderdata = [];
        foreach ($leaders as $participantid => $rank) {
            $prevrank = $prevrankings[$participantid] ?? null;
            $rankmoved = 0;
            if ($prevrank !== null) {
                $rankmoved = $rank->minrank - $prevrank->minrank;
            }
            $leaderdata[] = [
                'displayname' => $rank->participant->get_display_name(),
                'avatarurl' => $rank->participant->get_avatar_url()->out(false),
                'score' => $rank->score,
                'rank' => $rank->get_rank_as_range(),
                'isup' => $rankmoved < 0,
                'isdown' => $rankmoved > 0,
            ];
        }

        return [
            'leaders' => $leaderdata,
            'statusmessage' => "Thanks for participating!",
            'isrevision' => true,
            'skippodium' => true,
        ];
    }

    /**
     * Get ordered questions for all-rounds playback
     *
     * Returns questions ordered by sortorder from the last round, with questions
     * not in the last round appended at the end.
     *
     * @param round $lastround The last round
     * @param int[] $completedroundids IDs of completed rounds
     * @return array[] Each element has: questionid, roundquestion (round_question|null from last round)
     */
    protected function get_ordered_questions_for_all_rounds(round $lastround, array $completedroundids): array {
        global $DB;

        $kahoodleid = $lastround->get_kahoodle()->id;

        // Get all questions for this kahoodle with their latest version.
        $sql = "SELECT q.id AS questionid, qv.id AS versionid, rq.id AS roundquestionid, rq.sortorder
                  FROM {kahoodle_questions} q
                  JOIN {kahoodle_question_versions} qv ON qv.questionid = q.id AND qv.islast = 1
             LEFT JOIN {kahoodle_round_questions} rq ON rq.questionversionid = qv.id AND rq.roundid = :lastroundid
                 WHERE q.kahoodleid = :kahoodleid
              ORDER BY rq.sortorder ASC, q.id ASC";

        $records = $DB->get_records_sql($sql, [
            'kahoodleid' => $kahoodleid,
            'lastroundid' => $lastround->get_id(),
        ]);

        $questions = [];
        foreach ($records as $record) {
            $roundquestion = null;
            if ($record->roundquestionid) {
                $roundquestion = round_question::create_from_round_question_id((int)$record->roundquestionid);
            }
            $questions[] = [
                'questionid' => (int)$record->questionid,
                'roundquestion' => $roundquestion,
            ];
        }

        return $questions;
    }

    /**
     * Get revision data for all-rounds mode (top 5 participants by totalscore)
     *
     * @param int[] $completedroundids IDs of completed rounds
     * @return array Template data additions
     */
    protected function get_all_rounds_revision_data(array $completedroundids): array {
        global $DB, $OUTPUT;

        if (empty($completedroundids)) {
            return [
                'leaders' => [],
                'statusmessage' => "Thanks for participating!",
                'isrevision' => true,
                'skippodium' => true,
            ];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($completedroundids, SQL_PARAMS_NAMED);

        // Get top 5 participants by totalscore across all completed rounds.
        $sql = "SELECT p.id, p.displayname, p.avatar, p.totalscore, p.roundid
                  FROM {kahoodle_participants} p
                 WHERE p.roundid {$insql}
              ORDER BY p.totalscore DESC, p.id ASC";

        $records = $DB->get_records_sql($sql, $inparams, 0, 5);

        // Get context for avatar URLs (all rounds belong to the same kahoodle/cm).
        $context = null;
        if (!empty($records)) {
            $firstrecord = reset($records);
            $firstround = round::create_from_id((int)$firstrecord->roundid);
            $context = $firstround->get_context();
        }

        $defaultavatarurl = $OUTPUT->image_url('u/f3')->out(false);

        $leaderdata = [];
        $ranknum = 0;
        $lastscoregroup = null;
        $positioninscore = 0;
        foreach ($records as $record) {
            $positioninscore++;
            if ($lastscoregroup !== (int)$record->totalscore) {
                $ranknum = $positioninscore;
                $lastscoregroup = (int)$record->totalscore;
            }

            // Build avatar URL directly from record data.
            $avatarurl = $defaultavatarurl;
            if (!empty($record->avatar) && $context) {
                $avatarurl = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_kahoodle',
                    constants::FILEAREA_AVATAR,
                    (int)$record->id,
                    '/',
                    $record->avatar
                )->out(false);
            }

            $leaderdata[] = [
                'displayname' => s($record->displayname),
                'avatarurl' => $avatarurl,
                'score' => (int)$record->totalscore,
                'rank' => (string)$ranknum,
                'isup' => false,
                'isdown' => false,
            ];
        }

        return [
            'leaders' => $leaderdata,
            'statusmessage' => "Thanks for participating!",
            'isrevision' => true,
            'skippodium' => true,
        ];
    }
}
