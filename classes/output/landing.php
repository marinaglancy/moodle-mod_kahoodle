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
use mod_kahoodle\local\entities\statistics;
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
    /** @var statistics All game rounds */
    protected statistics $statistics;
    /** @var round Last round */
    protected round $round;
    /** @var stdClass The kahoodle activity record */
    protected stdClass $kahoodle;
    /** @var \cm_info The course module */
    protected \cm_info $cm;
    /** @var \context_module The context */
    protected \context_module $context;
    /** @var \moodleform|null The join form */
    protected ?\moodleform $joinform;

    /**
     * Constructor
     *
     * @param statistics $statistics game rounds
     * @param \moodleform|null $joinform The join form
     */
    public function __construct(statistics $statistics, ?\moodleform $joinform = null) {
        $this->statistics = $statistics;
        $this->round = $round = $statistics->get_last_round();
        $this->round = $round;
        $this->kahoodle = $round->get_kahoodle();
        $this->cm = $round->get_cm();
        $this->context = $round->get_context();
        $this->joinform = $joinform;
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
        $data->showwaitingtostart = false;
        $data->showfinished = false;

        $isinprogress = $this->round->is_in_progress();

        // Check if user has past participations and cannot rejoin (allowrepeat=0).
        $pastparticipations = $canparticipate ? $this->statistics->get_my_past_participations() : [];
        $haspastparticipation = !empty($pastparticipations);
        $cannotrejoin = $haspastparticipation && !$this->kahoodle->allowrepeat
            && $this->kahoodle->identitymode !== constants::IDENTITYMODE_ANONYMOUS;

        // Show section headers when the user has both capabilities and may see multiple sections.
        $canfacilitate = has_capability('mod/kahoodle:facilitate', $this->context);
        $data->showsectionheaders = $canfacilitate && $canparticipate;

        // Determine which section to show based on stage and capabilities.
        if ($stage === constants::STAGE_PREPARATION) {
            // Round is in preparation stage.
            if ($isfacilitator) {
                $data->showcontrolpreparation = true;
                $data->hasquestions = $hasquestions;
                $data->startgamebuttondisabled = !$hasquestions;
                $data->starturl = (new moodle_url(
                    '/mod/kahoodle/view.php',
                    ['id' => $this->cm->id, 'action' => 'start', 'sesskey' => sesskey()]
                ))->out(false);
                if ($canmanagequestions) {
                    $data->managequestionsurl = (new moodle_url(
                        '/mod/kahoodle/questions.php',
                        ['id' => $this->cm->id]
                    ))->out(false);
                }
            }
            if ($canparticipate && !$isfacilitator) {
                if ($cannotrejoin) {
                    // User participated before and cannot rejoin - show finished.
                    $data->showfinished = true;
                } else {
                    $data->showwaitingtostart = true;
                }
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
                    $autoarchivetime = $this->round->get_auto_archive_time();
                    if ($autoarchivetime) {
                        $remaining = $autoarchivetime - time();
                        $data->autoarchivenotice = get_string(
                            'landing_autoarchive_notice',
                            'mod_kahoodle',
                            $remaining > 0 ? format_time($remaining) : ("0 " . get_string('secs'))
                        );
                    }
                }
                if ($canparticipate && $cannotrejoin) {
                    // User participated before and cannot rejoin - show finished.
                    $data->showfinished = true;
                } else if ($canparticipate && $this->joinform) {
                    // Show join option (for users with participate capability who haven't joined yet).
                    // This includes users with both capabilities.
                    $data->showjoinoption = true;
                    $data->joinformhtml = $this->joinform->render();
                }
            }
        } else if ($stage === constants::STAGE_ARCHIVED) {
            // Round is finished.
            $data->showfinished = true;
            if (has_capability('mod/kahoodle:viewresults', $this->context)) {
                $data->resultsurl = (new moodle_url(
                    '/mod/kahoodle/results.php',
                    ['id' => $this->cm->id]
                ))->out(false);
            }
            if (has_capability('mod/kahoodle:facilitate', $this->context)) {
                $data->newroundurl = (new moodle_url(
                    '/mod/kahoodle/view.php',
                    ['id' => $this->cm->id, 'action' => 'newround', 'sesskey' => sesskey()]
                ))->out(false);
                $helpicon = new \help_icon('preparenewround', 'mod_kahoodle');
                $data->newroundhelpicon = $helpicon->export_for_template($output);
            }
        }

        // Add past participation score data when available.
        if ($haspastparticipation) {
            $data->haspastparticipations = true;
            $data->pastparticipations = [];
            foreach ($pastparticipations as $participation) {
                $timestarted = $participation->get_round()->get_timestarted();
                $data->pastparticipations[] = (object)[
                    'scoretext' => get_string('landing_past_score', 'mod_kahoodle', (object)[
                        'score' => $participation->get_total_score(),
                        'date' => $timestarted ? userdate($timestarted) : '',
                    ]),
                ];
            }
            // Show intro sentence when user can join a new round alongside past scores.
            if ($data->showjoinoption) {
                $data->pastscoreopener = get_string('landing_past_scores_joinintro', 'mod_kahoodle');
            }
        }

        return $data;
    }
}
