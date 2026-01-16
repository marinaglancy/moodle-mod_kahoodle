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
        if (!strlen(trim("" . ($data->questionconfig ?? '')))) {
            $options = [];
        } else {
            $options = preg_split('/\r\n|\r|\n/', $data->questionconfig, -1, PREG_SPLIT_NO_EMPTY);
        }

        $options = array_filter(array_map(fn($o) => trim("" . $o), $options), fn($o) => strlen($o) > 0);
        if (empty($options) && $roundquestion->get_id()) {
            // For updates we can just unset the field and not update it.
            unset($data->questionconfig);
            return;
        }

        if (count($options) < 2 || count($options) > 8) {
            throw new \moodle_exception('multichoice_needtwooptions', 'mod_kahoodle');
        }
        $correctoptions = array_filter($options, fn($o) => str_starts_with($o, '*'));
        if (empty($correctoptions) || count($correctoptions) > 1) {
            throw new \moodle_exception('multichoice_needonecorrectoption', 'mod_kahoodle');
        }

        // TODO (later) if round is not editable, the numer of options and the correct option cannot be changed.

        $options = array_map(fn($o) => clean_param($o, PARAM_TEXT), $options);

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
}
