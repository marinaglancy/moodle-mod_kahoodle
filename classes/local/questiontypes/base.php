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
     * Sanitizes the data before adding/editing a question.
     *
     * @param round_question $roundquestion
     * @param \stdClass $data
     * @return void
     */
    final public function sanitize_data(round_question $roundquestion, \stdClass $data): void {
        $allowedfields = array_merge(constants::FIELDS_QUESTION_VERSION, constants::FIELDS_ROUND_QUESTION);
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
            ? (int)$data->maxpoints : (int)$kahoodle->defaultmaxpoints;
        $minpoints = isset($data->minpoints) && $data->minpoints !== null
            ? (int)$data->minpoints : (int)$kahoodle->defaultminpoints;
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

        // Validate questiontextformat is a valid FORMAT_* constant.
        if (isset($data->questiontextformat) && $data->questiontextformat !== null) {
            $validformats = [(int)FORMAT_MOODLE, (int)FORMAT_HTML, (int)FORMAT_PLAIN, (int)FORMAT_MARKDOWN];
            if (!in_array((int)$data->questiontextformat, $validformats, true)) {
                unset($data->questiontextformat);
            }
        }

        // Clean questiontext if set.
        if (isset($data->questiontext) && $data->questiontext !== null) {
            $format = $data->questiontextformat ?? FORMAT_HTML;
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
}
