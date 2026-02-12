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

namespace mod_kahoodle\local\questiontypes;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\participant;
use mod_kahoodle\local\entities\round_question;

/**
 * Class base
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Get the name of the question type
     *
     * @return string
     */
    public function get_type(): string {
        // Last part of the namespaced class name.
        $parts = explode('\\', static::class);
        return end($parts);
    }

    /**
     * Name of the question type
     *
     * @return string
     */
    abstract public function get_display_name(): string;

    /**
     * Returns the name of the template for this question type and role/stage (either type-specific or basic)
     *
     * @param string $role Role of the user (facilitator/participant)
     * @param string $stage Stage name (question preview/question/results)
     * @return string
     */
    public function get_template(string $role, string $stage): string {
        // Mdlcode uses: template 'mod_kahoodle/questiontypes/multichoice/facilitator_question'.
        // Mdlcode uses: template 'mod_kahoodle/questiontypes/multichoice/facilitator_results'.
        // Mdlcode uses: template 'mod_kahoodle/questiontypes/multichoice/participant_question'.
        $template = 'mod_kahoodle/questiontypes/' . $this->get_type() . '/' . $role . '_' . $stage;
        try {
            \core\output\mustache_template_finder::get_template_filepath($template);
            return $template;
        // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (\moodle_exception $e) {
            // Template not found, will use fallback.
        }
        // Mdlcode returns: template.
        // Mdlcode assume-optional: $this->get_type() ['multiplechoice'].
        // Mdlcode assume: $role ['facilitator', 'participant'].
        return 'mod_kahoodle/' . $role . '/' . $stage;
    }

    /**
     * Sanitizes the data before adding/editing a question.
     *
     * @param round_question $roundquestion
     * @param \stdClass $data
     * @return void
     */
    final public function sanitize_data(round_question $roundquestion, \stdClass $data): void {
        $allowedfields = array_merge(
            constants::FIELDS_QUESTION_VERSION,
            constants::FIELDS_ROUND_QUESTION,
            ['imagedraftitemid']
        );
        $datafields = array_keys(get_object_vars($data));
        foreach ($datafields as $field) {
            if (!in_array($field, $allowedfields, true)) {
                unset($data->{$field});
            }
        }

        // Get defaults from kahoodle activity.
        $kahoodle = $roundquestion->get_round()->get_kahoodle();

        // Validate maxpoints >= minpoints (considering defaults). If invalid, unset both.
        $maxpoints = isset($data->maxpoints) && $data->maxpoints !== null
            ? (int)$data->maxpoints : (int)$kahoodle->maxpoints;
        $minpoints = isset($data->minpoints) && $data->minpoints !== null
            ? (int)$data->minpoints : (int)$kahoodle->minpoints;
        if ($maxpoints < $minpoints) {
            unset($data->maxpoints, $data->minpoints);
        }

        // Ensure questionresultsduration and questionpreviewduration are non-negative.
        if (
            isset($data->questionresultsduration) && $data->questionresultsduration !== null
                && (int)$data->questionresultsduration < 0
        ) {
            $data->questionresultsduration = 0;
        }
        if (
            isset($data->questionpreviewduration) && $data->questionpreviewduration !== null
                && (int)$data->questionpreviewduration < 0
        ) {
            $data->questionpreviewduration = 0;
        }

        // Ensure questionduration is positive (unset if zero or negative).
        if (
            isset($data->questionduration) && $data->questionduration !== null
                && (int)$data->questionduration <= 0
        ) {
            unset($data->questionduration);
        }

        // Clean questiontext if set. Use format based on kahoodle's questionformat setting.
        if (isset($data->questiontext) && $data->questiontext !== null) {
            $format = ($kahoodle->questionformat == constants::QUESTIONFORMAT_RICHTEXT) ? FORMAT_HTML : FORMAT_MOODLE;
            $data->questiontext = clean_text($data->questiontext, $format);
        }

        $this->sanitize_question_config_data($roundquestion, $data);
    }

    /**
     * Type-specific data sanitization
     *
     * @param round_question $roundquestion
     * @param \stdClass $data
     * @return void
     */
    abstract public function sanitize_question_config_data(round_question $roundquestion, \stdClass $data): void;

    /**
     * Define question type specific form elements
     *
     * @param round_question $roundquestion
     * @param \MoodleQuickForm $mform
     * @return void
     */
    abstract public function question_form_definition(round_question $roundquestion, \MoodleQuickForm $mform): void;

    /**
     * Question type specific form validation
     *
     * @param round_question $roundquestion
     * @param array $data
     * @param array $files
     * @return array errors
     */
    abstract public function question_form_validation(round_question $roundquestion, array $data, array $files): array;

    /**
     * Export question type specific data for templates
     *
     * This method returns an array of data specific to this question type
     * that will be JSON-encoded and passed to the template via JavaScript.
     * The JS will decode it and merge with the main template data.
     *
     * @param round_question $roundquestion
     * @param string $stage (one of constants::STAGE_QUESTION_PREVIEW, constants::STAGE_QUESTION, constants::STAGE_QUESTION_RESULTS)
     * @param bool $mockresults Whether to generate mock results data (used when a teacher edits the questions)
     * @return array
     */
    abstract public function export_template_data(round_question $roundquestion, string $stage, bool $mockresults = false): array;

    /**
     * Export question type specific data aggregated across all completed rounds
     *
     * Used for all-rounds playback. Each question type implements its own
     * aggregation logic. For example, multichoice aggregates response counts
     * per option, text might aggregate text responses for a tagcloud, etc.
     *
     * @param round_question $roundquestion A round question (from the last round) for question config
     * @param string $stage One of constants::STAGE_QUESTION_PREVIEW, STAGE_QUESTION, STAGE_QUESTION_RESULTS
     * @param int $questionid The kahoodle_questions.id
     * @param int[] $completedroundids IDs of all completed rounds
     * @return array
     */
    abstract public function export_template_data_all_rounds(
        round_question $roundquestion,
        string $stage,
        int $questionid,
        array $completedroundids
    ): array;

    /**
     * Export question type specific data for templates
     *
     * This method returns an array of data specific to this question type
     * that will be JSON-encoded and passed to the template via JavaScript.
     * The JS will decode it and merge with the main template data.
     *
     * @param participant $participant The participant
     * @param round_question $roundquestion
     * @param string $stage (one of constants::STAGE_QUESTION_PREVIEW, constants::STAGE_QUESTION, constants::STAGE_QUESTION_RESULTS)
     * @return array
     */
    abstract public function export_template_data_participant(
        participant $participant,
        round_question $roundquestion,
        string $stage
    ): array;

    /**
     * Validate that proposed edit changes are compatible with existing responses
     *
     * Called when editing a question that already has responses. Returns an array of
     * error messages if the changes are not allowed. By default, all changes are allowed.
     *
     * @param round_question $roundquestion The current round question
     * @param \stdClass $newdata The proposed new data
     * @return string[] Array of error messages (empty if changes are allowed)
     */
    public function validate_edit_changes(round_question $roundquestion, \stdClass $newdata): array {
        return [];
    }

    /**
     * Validate a participant's answer
     *
     * @param round_question $roundquestion The question
     * @param string $response The participant's answer
     * @return bool|null True if correct, false if incorrect, null if invalid
     */
    abstract public function validate_answer(round_question $roundquestion, string $response): ?bool;

    /**
     * Format response for display in reports
     *
     * @param string|null $response
     * @param round_question $roundquestion
     * @return string|null
     */
    abstract public function format_response(?string $response, round_question $roundquestion): ?string;
}
