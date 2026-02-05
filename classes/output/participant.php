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
use mod_kahoodle\local\entities\participant as participant_entity;
use mod_kahoodle\local\entities\rank;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\round_stage;
use mod_kahoodle\local\game\responses;

/**
 * Output class for the participant view playing kahoodle round
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participant implements \renderable, \templatable {
    /** @var participant_entity */
    protected participant_entity $participant;

    /** @var round The round (set during export) */
    protected round $round;

    /** @var round_stage The current stage (set during export) */
    protected round_stage $stage;

    /** @var rank The participant's rank (set during export) */
    protected rank $rank;

    /**
     * Constructor
     *
     * @param participant_entity $participant The participant
     */
    public function __construct(participant_entity $participant) {
        $this->participant = $participant;
    }

    /**
     * Export this data for use in a Mustache template
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        $this->round = $this->participant->get_round();
        $this->stage = $this->round->get_current_stage();
        $this->rank = $this->round->get_rankings()[$this->participant->get_id()]
            ?? rank::create_empty($this->participant);

        $this->ensure_page_setup();

        $data = $this->get_common_data();
        $stagename = $this->stage->get_stage_name();

        // Add stage-specific templatedata.
        switch ($stagename) {
            case constants::STAGE_LOBBY:
                $data['templatedata'] += $this->get_lobby_data();
                break;

            case constants::STAGE_QUESTION_PREVIEW:
            case constants::STAGE_QUESTION:
            case constants::STAGE_QUESTION_RESULTS:
            case constants::STAGE_LEADERS:
                $data['templatedata'] += $this->get_question_data();
                break;

            case constants::STAGE_REVISION:
                $data['templatedata'] += $this->get_revision_data();
                break;
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
     * @return string
     */
    protected function get_template(): string {
        $stagename = $this->stage->get_stage_name();

        if (
            in_array($stagename, [
            constants::STAGE_QUESTION_PREVIEW,
            constants::STAGE_QUESTION,
            constants::STAGE_QUESTION_RESULTS,
            ], true)
        ) {
            $questiontype = $this->stage->get_round_question()->get_question_type();
            return $questiontype->get_template('participant', $stagename);
        }

        return 'mod_kahoodle/participant/' . $stagename;
    }

    /**
     * Get common data for all stages
     *
     * @return array
     */
    protected function get_common_data(): array {
        return [
            'stagesignature' => $this->stage->get_stage_signature(),
            'duration' => 0, // No auto-advance for participants.
            'template' => $this->get_template(),
            'templatedata' => [
                'quiztitle' => $this->round->get_kahoodle_name(),
                'avatarurl' => $this->participant->get_avatar_url()->out(false),
                'displayname' => $this->participant->get_display_name(),
                'totalscore' => $this->rank->score,
            ],
        ];
    }

    /**
     * Get data for the lobby stage
     *
     * @return array Template data additions
     */
    protected function get_lobby_data(): array {
        return [
            'caneditavatar' => false, // TODO: Implement avatar editing.
        ];
    }

    /**
     * Get data for question stages (preview, question, results)
     *
     * @return array Template data additions
     */
    protected function get_question_data(): array {
        global $CFG;

        $stagename = $this->stage->get_stage_name();
        $roundquestion = $this->stage->get_round_question();
        $questiontype = $roundquestion->get_question_type();

        // Get question type template data.
        $typedata = $questiontype->export_template_data_participant(
            $this->participant,
            $roundquestion,
            $stagename
        );

        $data = [
            'sortorder' => $this->stage->get_question_number(),
            'questiontype' => $questiontype->get_type(),
            'typedata' => json_encode($typedata),
        ];

        // Check for existing response (not for preview stage).
        $response = null;
        if ($stagename !== constants::STAGE_QUESTION_PREVIEW) {
            $response = responses::get_response($this->participant, $roundquestion);
            $data['answered'] = $response !== null;
        }

        // Question stage with answer already submitted: show waiting screen.
        if ($stagename === constants::STAGE_QUESTION && $response !== null) {
            $imgidx = rand(1, 23);
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            // Mdlcode assume: $msgidx ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15'].
            $msgidx = rand(1, 15);
            $data['waitingmessage'] = get_string('waitingmessage' . $msgidx, 'mod_kahoodle');
            $data['waitingimage'] = $CFG->wwwroot . '/mod/kahoodle/pix/waiting/' . $imgidx . '.svg';
        }

        // Results and leaders stages: add answer feedback.
        if ($stagename === constants::STAGE_QUESTION_RESULTS || $stagename === constants::STAGE_LEADERS) {
            if ($response) {
                $data['iscorrect'] = (bool)$response->iscorrect;
                $data['points'] = (int)$response->points;
            } else {
                $data['timeup'] = true;
            }
            // Leaders stage also shows rank information.
            if ($stagename === constants::STAGE_LEADERS) {
                $data += $this->rank->get_data_for_question_results();
            }
        }

        return $data;
    }

    /**
     * Get data for the revision stage (final leaderboard)
     *
     * @return array Template data additions
     */
    protected function get_revision_data(): array {
        $elapsed = $this->round->get_current_stage_elapsed_time();
        $podiumduration = 60; // It is actually less but this is a fallback if reveal events do not work for some reason.
        $autoreveal = ($elapsed > $podiumduration || !$this->round->is_podium_shown()) ? 0 : ($podiumduration - $elapsed);
        return $this->rank->get_data_for_revision() + [
            'autorevealin' => $autoreveal,
        ];
    }
}
