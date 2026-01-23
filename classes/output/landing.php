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
    /** @var stdClass The course module record */
    protected stdClass $cm;
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

        // Get capabilities.
        $cancontrol = has_capability('mod/kahoodle:control', $this->context);
        $canmanagequestions = has_capability('mod/kahoodle:manage_questions', $this->context);
        $canparticipate = has_capability('mod/kahoodle:participate', $this->context);

        // Get round stage and question count.
        $stage = $this->get_stage();
        $questioncount = $this->round->get_questions_count();
        $hasquestions = $questioncount > 0;

        // Initialize all section flags to false.
        $data->showcontrolpreparation = false;
        $data->showcontrolinprogress = false;
        $data->showmanagequestions = false;
        $data->showwaitingtostart = false;
        $data->showinprogress = false;
        $data->showfinished = false;

        $isinprogress = $this->is_round_in_progress($stage);

        // Determine which section to show based on stage and capabilities.
        if ($stage === constants::STAGE_PREPARATION) {
            // Round is in preparation stage.
            if ($cancontrol) {
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
            if ($cancontrol) {
                // Facilitator view - show Resume and Finish buttons.
                $data->showcontrolinprogress = true;
                $data->finishurl = (new moodle_url(
                    '/mod/kahoodle/view.php',
                    ['id' => $this->cm->id, 'action' => 'finish', 'sesskey' => sesskey()]
                ))->out(false);
            } else if ($canparticipate) {
                // Participant view - show Join/Resume buttons.
                $data->showinprogress = true;
                $isparticipating = $this->is_user_participating();
                $data->showjoinbutton = !$isparticipating;
                $data->showresumebutton = $isparticipating;
                $data->joinurl = (new moodle_url(
                    '/mod/kahoodle/view.php',
                    ['id' => $this->cm->id, 'action' => 'join']
                ))->out(false);
                $data->resumeurl = (new moodle_url(
                    '/mod/kahoodle/view.php',
                    ['id' => $this->cm->id, 'action' => 'resume']
                ))->out(false);
            }
        } else if ($stage === constants::STAGE_ARCHIVED) {
            // Round is finished.
            $data->showfinished = true;
            $data->resultsurl = (new moodle_url(
                '/mod/kahoodle/view.php',
                ['id' => $this->cm->id, 'action' => 'results']
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
     * Get the current stage of the round
     *
     * @return string
     */
    protected function get_stage(): string {
        global $DB;
        // Fetch fresh stage from database to ensure it's current.
        return $DB->get_field('kahoodle_rounds', 'currentstage', ['id' => $this->round->get_id()]);
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

    /**
     * Check if the current user is already participating in this round
     *
     * @return bool
     */
    protected function is_user_participating(): bool {
        global $DB, $USER;
        return $DB->record_exists('kahoodle_participants', [
            'roundid' => $this->round->get_id(),
            'userid' => $USER->id,
        ]);
    }
}
