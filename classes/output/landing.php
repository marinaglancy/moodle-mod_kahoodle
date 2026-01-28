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
use moodle_url;
use renderable;
use stdClass;
use templatable;

/**
 * Output class for the landing page
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class landing implements renderable, templatable {
    /** @var round The round entity */
    protected round $round;
    /** @var stdClass The kahoodle activity record */
    protected stdClass $kahoodle;
    /** @var \cm_info The course module */
    protected \cm_info $cm;
    /** @var \context_module The context */
    protected \context_module $context;

    /**
     * Constructor
     *
     * @param round $round The round entity
     */
    public function __construct(round $round) {
        $this->round = $round;
        $this->kahoodle = $round->get_kahoodle();
        $this->cm = $round->get_cm();
        $this->context = $round->get_context();
    }

    /**
     * Export this data for use in a Mustache template
     *
     * @param \renderer_base $output
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output): stdClass {
        $data = new stdClass();

        // Get capabilities and participant status using round methods.
        $isfacilitator = $this->round->is_facilitator();
        $canmanagequestions = has_capability('mod/kahoodle:manage_questions', $this->context);
        $canparticipate = has_capability('mod/kahoodle:participate', $this->context);
        $isparticipating = $this->round->is_participant() !== null;

        // Get round stage and question count.
        $stage = $this->round->get_current_stage_name();
        $questioncount = $this->round->get_questions_count();
        $hasquestions = $questioncount > 0;

        // Initialize all section flags to false.
        $data->showcontrolpreparation = false;
        $data->showfacilitatorcontrols = false;
        $data->showparticipantcontrols = false;
        $data->showjoinoption = false;
        $data->showmanagequestions = false;
        $data->showwaitingtostart = false;
        $data->showfinished = false;

        $isinprogress = $this->is_round_in_progress($stage);

        // Determine which section to show based on stage and capabilities.
        if ($stage === constants::STAGE_PREPARATION) {
            // Round is in preparation stage.
            if ($isfacilitator) {
                $data->showcontrolpreparation = true;
                $data->startgamebuttondisabled = !$hasquestions;
                $data->starturl = (new moodle_url(
                    '/mod/kahoodle/view.php',
                    ['id' => $this->cm->id, 'action' => 'start', 'sesskey' => sesskey()]
                ))->out(false);
            } else if ($canparticipate) {
                $data->showwaitingtostart = true;
            }
        } else if ($isinprogress) {
            // Round is in progress (lobby through revision).
            // Participation takes precedence over facilitation - once joined, user acts as participant.
            if ($isparticipating) {
                // User is a participant - show participant controls only.
                $data->showparticipantcontrols = true;
                if (has_capability('mod/kahoodle:facilitate', $this->context)) {
                    // Only facilitators can leave the round, so they can take over control.
                    $data->leaveurl = (new moodle_url(
                        '/mod/kahoodle/view.php',
                        ['id' => $this->cm->id, 'action' => 'leave', 'sesskey' => sesskey()]
                    ))->out(false);
                }
            } else {
                // User is not a participant.
                if ($isfacilitator) {
                    // Show facilitator controls.
                    $data->showfacilitatorcontrols = true;
                    $data->finishurl = (new moodle_url(
                        '/mod/kahoodle/view.php',
                        ['id' => $this->cm->id, 'action' => 'finish', 'sesskey' => sesskey()]
                    ))->out(false);
                }
                if ($canparticipate) {
                    // Show join option (for users with participate capability who haven't joined yet).
                    // This includes users with both capabilities.
                    $data->showjoinoption = true;
                    $data->joinurl = (new moodle_url(
                        '/mod/kahoodle/view.php',
                        ['id' => $this->cm->id, 'action' => 'join', 'sesskey' => sesskey()]
                    ))->out(false);
                }
            }
        } else if ($stage === constants::STAGE_ARCHIVED) {
            // Round is finished.
            $data->showfinished = true;
            $data->resultsurl = (new moodle_url(
                '/mod/kahoodle/results.php',
                ['id' => $this->cm->id]
            ))->out(false);
        }

        // Show manage questions section if user can manage questions and game is not in progress.
        if ($canmanagequestions && !$isinprogress) {
            $data->showmanagequestions = true;
            $data->managequestionsurl = (new moodle_url(
                '/mod/kahoodle/questions.php',
                ['id' => $this->cm->id]
            ))->out(false);
        }

        return $data;
    }

    /**
     * Check if the round is in progress (between lobby and revision stages)
     *
     * @param string $stage The current stage
     * @return bool
     */
    protected function is_round_in_progress(string $stage): bool {
        return $stage != constants::STAGE_PREPARATION && $stage != constants::STAGE_ARCHIVED;
    }
}
