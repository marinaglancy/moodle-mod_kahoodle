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

/**
 * Output class for the facilitator view managing kahoodle round
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class facilitator implements \renderable, \templatable {
    /** @var round The round */
    protected round $round;

    /** @var round_stage The current stage (set during export) */
    protected round_stage $stage;

    /** @var \renderer_base The renderer (set during export) */
    protected \renderer_base $output;

    /**
     * Constructor
     *
     * @param round $round The round
     */
    public function __construct(round $round) {
        $this->round = $round;
    }

    /**
     * Export this data for use in a Mustache template
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        $this->output = $output;
        $this->stage = $this->round->get_current_stage();
        $this->ensure_page_setup();

        $data = $this->get_common_data();

        // Add stage-specific templatedata.
        switch ($this->stage->get_stage_name()) {
            case constants::STAGE_LOBBY:
                $data['templatedata'] += $this->get_lobby_data();
                break;

            case constants::STAGE_QUESTION_PREVIEW:
            case constants::STAGE_QUESTION:
            case constants::STAGE_QUESTION_RESULTS:
                $data['templatedata'] += $this->get_question_data();
                break;

            case constants::STAGE_LEADERS:
                $data['templatedata'] += $this->get_leaderboard_data();
                break;

            case constants::STAGE_REVISION:
                $data['templatedata'] += $this->get_revision_data();
                break;

            case constants::STAGE_ARCHIVED:
                // No additional data needed.
                break;

            default:
                throw new \moodle_exception('invalidstage', 'mod_kahoodle');
        }

        return $data;
    }

    /**
     * Ensure PAGE is set up for rendering (needed when called from realtime callback)
     */
    protected function ensure_page_setup(): void {
        global $PAGE;
        if (!$PAGE->has_set_url()) {
            $PAGE->set_url($this->round->get_url());
            $PAGE->set_context($this->round->get_context());
        }
    }

    /**
     * Get the template name for the current stage
     *
     * @return string|null
     */
    protected function get_template(): ?string {
        $stagename = $this->stage->get_stage_name();

        if ($this->stage->is_question_stage()) {
            $questiontype = $this->stage->get_round_question()->get_question_type();
            return $questiontype->get_template('facilitator', $stagename);
        }

        $templates = [
            // Mdlcode uses-next-line: template 'mod_kahoodle/facilitator/lobby'.
            constants::STAGE_LOBBY => 'mod_kahoodle/facilitator/lobby',
            // Mdlcode uses-next-line: template 'mod_kahoodle/facilitator/revision'.
            constants::STAGE_REVISION => 'mod_kahoodle/facilitator/revision',
            constants::STAGE_ARCHIVED => null,
        ];

        return $templates[$stagename] ?? null;
    }

    /**
     * Get common data for all stages
     *
     * @return array
     */
    protected function get_common_data(): array {
        global $CFG;

        return [
            'stagesignature' => $this->stage->get_stage_signature(),
            'template' => $this->get_template(),
            'duration' => $this->stage->get_duration(),
            'templatedata' => [
                'quiztitle' => $this->round->get_kahoodle_name(),
                'sortorder' => $this->stage->get_question_number() ?: '',
                'totalquestions' => $this->round->get_questions_count(),
                'cancontrol' => true,
                'isedit' => false,
                'backgroundurl' => $CFG->wwwroot . '/mod/kahoodle/pix/classroom-bg.jpg',
            ],
        ];
    }

    /**
     * Get data for the lobby stage
     *
     * @return array Template data additions
     */
    protected function get_lobby_data(): array {
        // Get participants list.
        $participants = $this->round->get_all_participants();
        $participantcount = count($participants);

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
        // TODO use core_qrcode class to generate QR code, save it in filestorage and serve from there.

        return [
            'participantcount' => $participantcount,
            'participants' => $participantdata,
            'qrcodeurl' => $qrcode,
            'joincode' => '', // TODO when join codes are implemented.
        ];
    }

    /**
     * Get data for question stages (preview, question, results)
     *
     * @return array Template data additions
     */
    protected function get_question_data(): array {
        // Create output class with real results data.
        $outputclass = new roundquestion($this->stage->get_round_question(), $this->stage->get_stage_name(), false);
        $templatedata = $outputclass->export_for_template($this->output);

        // Convert to array and return as additions.
        // Note: Some fields like quiztitle, sortorder, totalquestions, cancontrol, isedit
        // are already in common data, but roundquestion also provides them. This is the
        // duplication that will be cleaned up later with template modifications.
        return (array)$templatedata;
    }

    /**
     * Get leaderboard data for leaders or revision stage
     *
     * @param bool $isrevision Whether this is for the revision stage (false - leaders stage after a question)
     * @return array Template data additions
     */
    protected function get_leaderboard_data(bool $isrevision = false): array {

        $leaderranks = $this->round->get_leaders();
        $leaders = [];
        foreach ($leaderranks as $rank) {
            $rankmoved = $rank->get_rank_movement_status();
            $leaders[] = [
                'displayname' => $rank->participant->get_display_name(),
                'avatarurl' => $rank->participant->get_avatar_url()->out(false),
                'score' => $rank->score,
                'rank' => $rank->get_rank_as_range(),
                'isup' => $rankmoved < 0,
                'isdown' => $rankmoved > 0,
            ];
        }

        // TODO use language string and different motivational messages.
        if ($isrevision) {
            $statusmessage = "Thanks for participating!";
        } else {
            $statusmessage = "Great job everyone! Get ready for the next question...";
        }

        return [
            'leaders' => $leaders,
            'statusmessage' => $statusmessage,
        ];
    }

    /**
     * Get data for the revision stage (including podium)
     *
     * @return array Template data additions
     */
    protected function get_revision_data(): array {
        $templatedata = $this->get_leaderboard_data(true);
        $templatedata['isrevision'] = true;

        // TODO if there was more than 30 seconds since the beginning of the revision stage,
        // skip the podium display.

        // Prepare data for the podium.
        $podiumranks = $this->round->get_podium_ranks();
        if (!$this->round->is_podium_shown()) {
            // Not enough participants for podium.
            $templatedata['skippodium'] = true;
            return $templatedata;
        }

        foreach ($podiumranks as $position => $ranks) {
            $istie = count($ranks) > 1;
            $hasmore = count($ranks) > 4;
            $displayedranks = $hasmore ? array_slice($ranks, 0, 3) : $ranks;
            $templatedata['rank' . $position] = [
                'istie' => $istie,
                'totalscore' => $ranks[0]->score,
                'hasmore' => $hasmore,
                'winners' => array_values(array_map(function ($rank) {
                    return [
                        'displayname' => $rank->participant->get_display_name(),
                        'avatarurl' => $rank->participant->get_avatar_url()->out(false),
                    ];
                }, $displayedranks)),
            ];
        }

        return $templatedata;
    }
}
