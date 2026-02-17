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
use mod_kahoodle\local\entities\rank;
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
                $data['templatedata'] += self::get_lobby_data($this->round);
                break;

            case constants::STAGE_QUESTION_PREVIEW:
            case constants::STAGE_QUESTION:
            case constants::STAGE_QUESTION_RESULTS:
                $data['templatedata'] += self::get_question_stage_data($this->stage, $this->output);
                break;

            case constants::STAGE_LEADERS:
                $data['templatedata'] += self::get_leaderboard_data($this->round->get_leaders());
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
     * Get the template name for a stage
     *
     * @param round_stage $stage
     * @return string|null
     */
    public static function get_template_for_stage(round_stage $stage): ?string {
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
            'template' => self::get_template_for_stage($this->stage),
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
     * @param round $round
     * @return array Template data additions
     */
    public static function get_lobby_data(round $round): array {
        // Get participants list.
        $participants = $round->get_all_participants();
        $participantcount = count($participants);

        $participantdata = [];
        foreach ($participants as $participant) {
            $participantdata[] = [
                'participantid' => $participant->get_id(),
                'displayname' => $participant->get_display_name(),
                'avatarurl' => $participant->get_avatar_url()->out(false),
            ];
        }

        $qrcodeurl = $round->get_qrcode_url()->out(false);

        $lobbysize = match (true) {
            $participantcount <= constants::LOBBYSIZE_XL_MAX => 'xl',
            $participantcount <= constants::LOBBYSIZE_L_MAX => 'l',
            $participantcount <= constants::LOBBYSIZE_M_MAX => 'm',
            default => 's',
        };

        return [
            'participantcount' => $participantcount,
            'participants' => $participantdata,
            'qrcodeurl' => $qrcodeurl,
            'joincode' => '', // TODO when join codes are implemented.
            'lobbysize' => $lobbysize,
        ];
    }

    /**
     * Get data for question stages (preview, question, results)
     *
     * @param round_stage $stage
     * @param \renderer_base $output
     * @return array Template data additions
     */
    public static function get_question_stage_data(round_stage $stage, \renderer_base $output): array {
        $outputclass = new roundquestion($stage->get_round_question(), $stage->get_stage_name(), false);
        return (array)$outputclass->export_for_template($output);
    }

    /**
     * Format leader rank objects into template data for the leaderboard
     *
     * @param rank[] $leaderranks Array of rank objects with prevquestionrank set
     * @param bool $isrevision Whether this is for the revision stage
     * @return array Template data additions
     */
    public static function get_leaderboard_data(array $leaderranks, bool $isrevision = false): array {
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

        if ($isrevision) {
            $statusmessage = get_string('status_revision_stage', 'kahoodle');
        } else {
            $statusmessage = get_string('status_leaders_stage', 'kahoodle');
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
        $templatedata = self::get_leaderboard_data($this->round->get_leaders(), true);
        $templatedata['isrevision'] = true;

        // If too much time has elapsed since revision started, skip the podium animation.
        $podiumranks = $this->round->get_podium_ranks();
        $elapsed = $this->round->get_current_stage_elapsed_time() ?? 0;
        if (!$this->round->is_podium_shown() || $elapsed > constants::MAX_RANK_REVEAL_DELAY) {
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
