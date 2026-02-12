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
use mod_kahoodle\local\entities\round_stage;
use mod_kahoodle\local\entities\statistics;

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
    /** @var round|statistics Round for single-round mode */
    protected round $round;

    /**
     * Constructor
     *
     * @param round $round Round for single-round mode or statistics for all-rounds mode
     */
    public function __construct(round $round) {
        $this->round = $round;
    }

    /**
     * Export all playback stages
     *
     * @param \renderer_base $output
     * @return array With keys: quiztitle, totalquestions, stages[]
     */
    public function export_all_stages(\renderer_base $output): array {
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
     * Get the template name for a stage
     *
     * @param round_stage $stage
     * @return string|null
     */
    protected function get_template_for_stage(round_stage $stage): ?string {
        // TODO this repeats facilitator::get_template().
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
        // TODO this basically repeats facilitator::get_lobby_data().
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
     * @param round $round
     * @param int $questionnumber 1-based question number
     * @param int $maxnumber Maximum number of leaders to return
     * @return array Template data for leaderboard template
     */
    public static function get_leaders_data_for_round(
        round $round,
        int $questionnumber,
        int $maxnumber = 5
    ): array {
        // This basically duplicates facilitator::get_leaderboard_data().
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
            'statusmessage' => get_string('status_leaders_stage', 'kahoodle'),
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
        // TODO a lot of code repetition with facilitator::get_leaderboard_data()
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
            'statusmessage' => get_string('status_revision_stage', 'kahoodle'),
            'isrevision' => true,
            'skippodium' => true,
        ];
    }
}
