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
 * Class multichoice
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class multichoice extends base {
    /**
     * Name of the question type
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('questiontype_multichoice', 'mod_kahoodle');
    }

    /**
     * Type-specific data sanitization
     *
     * @param round_question $roundquestion
     * @param \stdClass $data
     * @return void
     */
    public function sanitize_question_config_data(round_question $roundquestion, \stdClass $data): void {
        $options = $this->get_answers_options($data->questionconfig ?? null);
        if (empty($options) && $roundquestion->get_id()) {
            // For updates we can just unset the field and not update it.
            unset($data->questionconfig);
            return;
        }

        if (count($options) < 2 || count($options) > 8) {
            throw new \moodle_exception('multichoice_needtwooptions', 'mod_kahoodle');
        }
        $correctoptions = array_filter($options, fn($o) => $o['iscorrect']);
        if (empty($correctoptions) || count($correctoptions) > 1) {
            throw new \moodle_exception('multichoice_needonecorrectoption', 'mod_kahoodle');
        }

        // TODO (later) if round is not editable, the numer of options and the correct option cannot be changed.

        $options = array_map(fn($o) => ($o['iscorrect'] ? '*' : '') . clean_param($o['text'], PARAM_TEXT), $options);

        $data->questionconfig = join("\n", $options);
    }

    /**
     * Define question type specific form elements
     *
     * @param round_question $roundquestion
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function question_form_definition(round_question $roundquestion, \MoodleQuickForm $mform): void {
        $mform->addElement('textarea', 'questionconfig', get_string('multichoice_answers', 'mod_kahoodle'), [
            'rows' => 5,
            'cols' => 50,
        ]);
        $mform->setType('questionconfig', PARAM_RAW);
        $mform->addHelpButton('questionconfig', 'multichoice_answers', 'mod_kahoodle');
        $mform->addRule('questionconfig', get_string('required'), 'required', null, 'client');
    }

    /**
     * Question type specific form validation
     *
     * @param round_question $roundquestion
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function question_form_validation(round_question $roundquestion, array $data, array $files): array {
        $errors = [];

        try {
            $dataobj = (object)$data;
            $this->sanitize_question_config_data($roundquestion, $dataobj);
            if (empty($dataobj->questionconfig)) {
                $errors['questionconfig'] = get_string('required');
            }
        } catch (\moodle_exception $e) {
            $errors['questionconfig'] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Export question type specific data for templates
     *
     * @param round_question $roundquestion
     * @param string $stage (one of constants::STAGE_QUESTION_PREVIEW, constants::STAGE_QUESTION, constants::STAGE_QUESTION_RESULTS)
     * @param bool $mockresults Whether to generate mock results data (used when a teacher edits the questions)
     * @return array
     */
    public function export_template_data(round_question $roundquestion, string $stage, bool $mockresults = false): array {
        $options = [];

        if ($stage == constants::STAGE_QUESTION_PREVIEW) {
            // In preview stage, we do not show answers.
            return [];
        }

        $answers = $this->get_answers_options($roundquestion->get_data()->questionconfig);
        if (empty($answers)) {
            return [
                'options' => $options,
                'optioncount' => 0,
                'manyoptions' => false,
            ];
        }

        $letters = constants::MULTICHOICE_SYMBOLS;

        if ($stage == constants::STAGE_QUESTION_RESULTS) {
            $answerscount = $this->get_answers_count($roundquestion, $mockresults);
            $maxanswerscount = max(1, ...$answerscount);
        }

        foreach ($answers as $index => $answer) {
            $option = [
                'optionnumber' => $index + 1,
                'letter' => $letters[$index] ?? (string)($index + 1),
                'text' => $answer['text'],
            ];
            if ($stage == constants::STAGE_QUESTION_RESULTS) {
                $option['iscorrect'] = $answer['iscorrect'];
                $option['count'] = $answerscount[$index];
                $heightpercent = (int)round(100.0 * $answerscount[$index] / $maxanswerscount);
                $option['heightpercent'] = $heightpercent;
                $option['isshort'] = $heightpercent < 25;
            }
            $options[] = $option;
        }

        return [
            'options' => $options,
            'optioncount' => count($options),
            'manyoptions' => count($options) > 4,
        ];
    }

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
    public function export_template_data_participant(
        participant $participant,
        round_question $roundquestion,
        string $stage
    ): array {
        $options = [];

        if ($stage == constants::STAGE_QUESTION_PREVIEW) {
            // In preview stage, we do not show answers.
            return [];
        }

        $answers = $this->get_answers_options($roundquestion->get_data()->questionconfig);
        if (empty($answers)) {
            return [
                'options' => $options,
                'optioncount' => 0,
                'manyoptions' => false,
            ];
        }

        $letters = constants::MULTICHOICE_SYMBOLS;

        foreach ($answers as $index => $answer) {
            $option = [
                'optionnumber' => $index + 1,
                'letter' => $letters[$index] ?? (string)($index + 1),
            ];
            $options[] = $option;
        }

        return [
            'options' => $options,
            'optioncount' => count($options),
            'manyoptions' => count($options) > 4,
        ];
    }

    /**
     * Returns an array with all configured answers options
     *
     * @param string|null $questionconfig
     * @return array{iscorrect: bool, text: string[]}
     */
    protected function get_answers_options(?string $questionconfig): array {
        $lines = preg_split('/\r\n|\r|\n/', $questionconfig ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $lines = array_filter(array_map(fn($o) => trim("" . $o), $lines), fn($o) => strlen($o) > 0);
        $options = [];
        foreach ($lines as $line) {
            $text = trim($line);
            $iscorrect = str_starts_with($text, '*');
            $text = $iscorrect ? substr($text, 1) : $text;
            $options[] = [
                'text' => $text,
                'iscorrect' => $iscorrect,
            ];
        }
        return $options;
    }

    /**
     * Returns the number of participants answers for each answer option
     *
     * @param round_question $roundquestion
     * @param bool $mockresults
     * @return int[]
     */
    protected function get_answers_count(round_question $roundquestion, bool $mockresults = false): array {
        global $DB;

        $answers = $this->get_answers_options($roundquestion->get_data()->questionconfig);
        $answerscount = array_fill(0, count($answers), 0);

        if ($mockresults) {
            // Generate some mock results for teacher preview.
            for ($i = 0; $i < count($answers); $i++) {
                $answerscount[$i] = rand(0, 10);
            }
        } else {
            // Get actual response counts from database.
            $responses = $DB->get_records(
                'kahoodle_responses',
                ['roundquestionid' => $roundquestion->get_id()],
                '',
                'id, response'
            );

            foreach ($responses as $responserecord) {
                $optionnumber = (int)$responserecord->response;
                // Convert from 1-based to 0-based index.
                $index = $optionnumber - 1;
                if (isset($answerscount[$index])) {
                    $answerscount[$index]++;
                }
            }
        }

        return $answerscount;
    }

    /**
     * Validate a participant's answer for multichoice questions
     *
     * @param round_question $roundquestion The question
     * @param string $response The option number (1-based) as a string
     * @return bool|null True if correct, false if incorrect, null if invalid
     */
    public function validate_answer(round_question $roundquestion, string $response): ?bool {
        $optionnumber = (int)$response;
        $options = $this->get_answers_options($roundquestion->get_data()->questionconfig);

        // Invalid option number - return null to indicate invalid answer.
        if ($optionnumber < 1 || $optionnumber > count($options)) {
            return null;
        }

        // Return whether the selected option is correct.
        return $options[$optionnumber - 1]['iscorrect'];
    }

    /**
     * Format response for display in reports
     *
     * @param string|null $response
     * @param round_question $roundquestion
     * @return string|null
     */
    public function format_response(?string $response, round_question $roundquestion): ?string {
        if (!$response) {
            return null;
        }

        $optionnumber = (int)$response;
        $options = $this->get_answers_options($roundquestion->get_data()->questionconfig);

        // Invalid option number - return null to indicate no answer.
        if ($optionnumber < 1 || $optionnumber > count($options)) {
            return null;
        }

        return format_string($options[$optionnumber - 1]['text']);
    }
}
