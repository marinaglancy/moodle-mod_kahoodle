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

use mod_kahoodle\api;
use mod_kahoodle\constants;
use mod_kahoodle\questions;
use moodle_url;
use renderable;
use stdClass;
use templatable;

/**
 * Output class for the all rounds results page (mod/view/results.php)
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results implements renderable, templatable {
    /** @var stdClass The kahoodle activity record */
    protected stdClass $kahoodle;
    /** @var \cm_info The course module */
    protected \cm_info $cm;

    /**
     * Constructor
     *
     * @param stdClass $kahoodle The kahoodle activity record
     * @param \cm_info $cm The course module
     */
    public function __construct(stdClass $kahoodle, \cm_info $cm) {
        $this->kahoodle = $kahoodle;
        $this->cm = $cm;
    }

    /**
     * Export this data for use in a Mustache template
     *
     * @param \renderer_base $output
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output): stdClass {
        global $DB;

        $context = \context_module::instance($this->cm->id);
        $canmanagequestions = has_capability('mod/kahoodle:manage_questions', $context);

        $data = new stdClass();
        $data->rounds = [];

        // Get all rounds for this kahoodle, ordered by: preparation first, then by timecreated DESC.
        $rounds = api::get_all_rounds($this->kahoodle->id, 0, $this->kahoodle, $this->cm);

        foreach ($rounds as $round) {
            $rounddata = new stdClass();
            $rounddata->id = $round->get_id();
            // Use inplace editable for the round name.
            $rounddata->name = $output->render($round->get_name_inplace_editable());

            // Determine status flags based on current stage.
            $stage = $round->get_current_stage_name();
            $rounddata->ispreparation = ($stage === constants::STAGE_PREPARATION);
            $rounddata->iscompleted = ($stage === constants::STAGE_ARCHIVED);
            $rounddata->isrevision = ($stage === constants::STAGE_REVISION);
            $rounddata->isinprogress = !$rounddata->ispreparation && !$rounddata->iscompleted && !$rounddata->isrevision;

            // Set status string.
            if ($rounddata->ispreparation) {
                $rounddata->status = get_string('results_status_preparation', 'mod_kahoodle');
            } else if ($rounddata->isinprogress) {
                $rounddata->status = get_string('results_status_inprogress', 'mod_kahoodle');
            } else if ($rounddata->isrevision) {
                $rounddata->status = get_string('results_status_revision', 'mod_kahoodle');
            } else {
                $rounddata->status = get_string('results_status_completed', 'mod_kahoodle');
            }

            // Show date/time fields for in progress, revision, and completed rounds.
            $rounddata->showdatefields = !$rounddata->ispreparation;
            if ($rounddata->showdatefields) {
                $timestarted = $round->get_timestarted();
                $rounddata->date = $timestarted ? userdate($timestarted, get_string('strftimedaydate')) : '-';
                $rounddata->lobbyopened = $timestarted ? userdate($timestarted, get_string('strftimetime')) : '-';
            }

            // Show full stats for revision and completed rounds.
            $rounddata->showfullstats = $rounddata->isrevision || $rounddata->iscompleted;
            if ($rounddata->showfullstats) {
                // Get total round duration.
                $duration = $round->get_duration();
                $rounddata->duration = $duration !== null ? format_time($duration) : '-';

                // Get participant statistics.
                $stats = $DB->get_record_sql(
                    "SELECT COUNT(*) as participantcount,
                            COALESCE(AVG(totalscore), 0) as averagescore,
                            COALESCE(MAX(totalscore), 0) as maxscore
                       FROM {kahoodle_participants}
                      WHERE roundid = ?",
                    [$round->get_id()]
                );

                $rounddata->participantcount = (int)$stats->participantcount;
                $rounddata->averagescore = round((float)$stats->averagescore);
                $rounddata->maxscore = (int)$stats->maxscore;

                // Placeholder for completed count (to be implemented later).
                $rounddata->completedcount = 0;

                // Links (non-functional for now, will be implemented later).
                $rounddata->participantsurl = (new moodle_url(
                    '/mod/kahoodle/results.php',
                    ['roundid' => $round->get_id(), 'view' => 'participants']
                ))->out(false);
                $rounddata->statisticsurl = (new moodle_url(
                    '/mod/kahoodle/results.php',
                    ['roundid' => $round->get_id(), 'view' => 'statistics']
                ))->out(false);
            }

            // Edit questions link for users with manage_questions capability.
            if ($canmanagequestions) {
                $rounddata->editquestionsurl = (new moodle_url(
                    '/mod/kahoodle/questions.php',
                    ['roundid' => $round->get_id()]
                ))->out(false);
            }

            $data->rounds[] = $rounddata;
        }

        return $data;
    }
}
