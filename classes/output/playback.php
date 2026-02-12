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
    /** @var round Round for single-round mode or statistics for all-rounds mode */
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

            $template = facilitator::get_template_for_stage($stage);
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
                    $templatedata += facilitator::get_lobby_data($this->round);
                    break;

                case constants::STAGE_QUESTION_PREVIEW:
                case constants::STAGE_QUESTION:
                case constants::STAGE_QUESTION_RESULTS:
                    $templatedata += facilitator::get_question_stage_data($stage, $output);
                    $templatedata['questionid'] = $stage->get_round_question()->get_question_id();
                    break;

                case constants::STAGE_LEADERS:
                    $templatedata += facilitator::get_leaderboard_data(
                        $this->round->get_leaders($stage->get_question_number())
                    );
                    break;

                case constants::STAGE_REVISION:
                    $questionscount = $this->round->get_questions_count();
                    $templatedata += facilitator::get_leaderboard_data(
                        $this->round->get_leaders($questionscount),
                        true
                    );
                    $templatedata['isrevision'] = true;
                    $templatedata['skippodium'] = true;
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
}
