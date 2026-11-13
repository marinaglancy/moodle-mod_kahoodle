<?php
// This file is part of Moodle - http://moodle.org/
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

namespace mod_kahoodle\external;

use core_external\external_api;
use core_external\external_multiple_structure;
use core_external\external_value;
use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\round_question;

/**
 * Parameter definitions and checks shared by the Kahoodle web service functions
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Load a Kahoodle course module by its id and validate its context
     *
     * @param int $cmid Course module id
     * @return array [cm_info, \context_module, \stdClass kahoodle record]
     */
    public static function validate_cm(int $cmid): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'kahoodle');
        $context = \context_module::instance($cm->id);
        external_api::validate_context($context);
        $kahoodle = $DB->get_record('kahoodle', ['id' => $cm->instance], '*', MUST_EXIST);
        return [$cm, $context, $kahoodle];
    }

    /**
     * Load the question with this id as it is in the last round of its activity, and validate the context
     *
     * @param int $questionid Question id
     * @return round_question
     */
    public static function validate_question(int $questionid): round_question {
        $roundquestion = round_question::create_from_question_id($questionid);
        external_api::validate_context($roundquestion->get_round()->get_context());
        return $roundquestion;
    }

    /**
     * A warning for one item of a batch that failed
     *
     * @param string $item What the item is, for example 'question'
     * @param int $itemid Its id, or its index in the batch
     * @param \Throwable $e
     * @return array In the external_warnings structure, whose warningcode allows letters and digits only
     */
    public static function warning(string $item, int $itemid, \Throwable $e): array {
        $code = $e instanceof \moodle_exception ? $e->errorcode : 'exception';
        return [
            'item' => $item,
            'itemid' => $itemid,
            'warningcode' => clean_param(str_replace('_', '', $code), PARAM_ALPHANUM),
            'message' => $e->getMessage(),
        ];
    }

    /**
     * Throw when the last round has started, so questions cannot be added, removed or moved
     *
     * @param round $round
     */
    public static function require_editable_round(round $round): void {
        if (!$round->is_fully_editable()) {
            throw new \moodle_exception('noeditableround_ws', 'mod_kahoodle');
        }
    }

    /**
     * Set the visibility fields of a module from the visible parameter (1, 0 or -1), as the module form does
     *
     * @param \stdClass $moduleinfo
     * @param int $visible
     */
    public static function apply_visibility(\stdClass $moduleinfo, int $visible): void {
        global $CFG;
        if ($visible === -1 && empty($CFG->allowstealth)) {
            throw new \invalid_parameter_exception('visible = -1 needs the site setting allowstealth');
        }
        $moduleinfo->visible = $visible === 0 ? 0 : 1;
        $moduleinfo->visibleoncoursepage = $visible === -1 ? 0 : 1;
    }

    /**
     * Description of the questionconfig parameter, listing the format of every installed question type
     *
     * @return string
     */
    public static function questionconfig_description(): string {
        $lines = [];
        foreach (\mod_kahoodle\local\game\questions::get_question_types() as $type) {
            $description = $type->get_config_description();
            if ($description !== '') {
                $lines[] = '- ' . $type->get_type() . ': ' . $description;
            }
        }
        return "Type-specific configuration. Its format depends on the question type:\n" . implode("\n", $lines);
    }

    /**
     * Description of the questiontype parameter, listing the installed question types
     *
     * @return string
     */
    public static function questiontype_description(): string {
        $types = array_map(fn($type) => $type->get_type(), \mod_kahoodle\local\game\questions::get_question_types());
        return 'Question type, one of: ' . implode(', ', $types) . '. Default: '
            . \mod_kahoodle\local\game\questions::get_default_question_type()->get_type() . '.';
    }

    /**
     * Definitions of the per-question timing and points overrides
     *
     * @param string $suffix Appended to every description, for example how to keep the current value
     * @param bool $toplevel Whether they are top-level parameters, which need a null default rather than
     *     VALUE_OPTIONAL
     * @return external_value[]
     */
    public static function question_override_parameters(string $suffix, bool $toplevel): array {
        $required = $toplevel ? VALUE_DEFAULT : VALUE_OPTIONAL;
        return [
            'questionpreviewduration' => new external_value(
                PARAM_INT,
                'Seconds the question is shown before answers open. 0 uses the activity default.' . $suffix,
                $required
            ),
            'questionduration' => new external_value(
                PARAM_INT,
                'Seconds participants have to answer. 0 uses the activity default.' . $suffix,
                $required
            ),
            'questionresultsduration' => new external_value(
                PARAM_INT,
                'Seconds the results of the question are shown. 0 uses the activity default.' . $suffix,
                $required
            ),
            'maxpoints' => new external_value(
                PARAM_INT,
                'Points for the fastest correct answer. 0 uses the activity default.' . $suffix,
                $required
            ),
            'minpoints' => new external_value(
                PARAM_INT,
                'Points for the slowest correct answer. 0 uses the activity default.' . $suffix,
                $required
            ),
        ];
    }

    /**
     * Turn the overrides of a web service call into the stored form: 0 means the activity default (NULL)
     *
     * @param array $params Validated parameters of one question
     * @return \stdClass The question data for the questions API
     */
    public static function question_data(array $params): \stdClass {
        $data = (object) $params;
        foreach (constants::FIELDS_ROUND_QUESTION as $field) {
            if (property_exists($data, $field) && empty($data->$field)) {
                $data->$field = null;
            }
        }
        return $data;
    }

    /**
     * Description of the activity settings shared by create_modules, update_module and pre_update
     *
     * @param bool $forupdate Whether the description is for update_module, where every default is null
     *     ("not given"): a top-level VALUE_OPTIONAL would shift the arguments core passes to execute()
     * @return external_value[]
     */
    public static function settings_parameters(bool $forupdate): array {
        $optional = VALUE_DEFAULT;
        $default = fn($value) => $forupdate ? null : $value;
        return [
            'questionformat' => new external_value(
                PARAM_INT,
                'How question text is written: 0 = plain text, at most ' . constants::QUESTIONTEXT_MAXLENGTH .
                ' characters, with an optional image shown under it; 1 = rich text (HTML) with embedded images.',
                $optional,
                $default(constants::QUESTIONFORMAT_PLAIN)
            ),
            'identitymode' => new external_value(
                PARAM_INT,
                'How participants appear on the leaderboard: 0 = real name, 1 = real name or an alias they choose, ' .
                '2 = an alias they must choose, 3 = fully anonymous.',
                $optional,
                $default(constants::DEFAULT_IDENTITY_MODE)
            ),
            'allowrepeat' => new external_value(
                PARAM_INT,
                'Whether a user may take part in more than one round: 1 = yes, 0 = no.',
                $optional,
                $default(constants::DEFAULT_ALLOW_REPEAT)
            ),
            'lobbyduration' => new external_value(
                PARAM_INT,
                'Seconds the lobby stays open for participants to join before the first question.',
                $optional,
                $default(constants::DEFAULT_LOBBY_DURATION)
            ),
            'questionpreviewduration' => new external_value(
                PARAM_INT,
                'Default seconds a question is shown before answers open.',
                $optional,
                $default(constants::DEFAULT_QUESTION_PREVIEW_DURATION)
            ),
            'questionduration' => new external_value(
                PARAM_INT,
                'Default seconds participants have to answer a question.',
                $optional,
                $default(constants::DEFAULT_QUESTION_DURATION)
            ),
            'questionresultsduration' => new external_value(
                PARAM_INT,
                'Default seconds the results of a question are shown.',
                $optional,
                $default(constants::DEFAULT_QUESTION_RESULTS_DURATION)
            ),
            'maxpoints' => new external_value(
                PARAM_INT,
                'Default points for the fastest correct answer.',
                $optional,
                $default(constants::DEFAULT_MAX_POINTS)
            ),
            'minpoints' => new external_value(
                PARAM_INT,
                'Default points for the slowest correct answer.',
                $optional,
                $default(constants::DEFAULT_MIN_POINTS)
            ),
        ];
    }

    /**
     * Description of the common module fields shared by create_modules and update_module
     *
     * @param bool $forupdate Whether the description is for update_module, where every default is null
     * @return \core_external\external_description[]
     */
    public static function common_parameters(bool $forupdate): array {
        $optional = VALUE_DEFAULT;
        $default = fn($value) => $forupdate ? null : $value;
        return [
            'intro' => new external_value(
                PARAM_RAW,
                'Description of the activity (HTML). Files from introdraftitemid can be embedded as ' .
                '@@PLUGINFILE@@/filename.ext.',
                $optional,
                $default('')
            ),
            'introformat' => new external_value(
                PARAM_INT,
                'Format of intro: 1 = HTML, 0 = Moodle auto-format, 2 = plain text, 4 = Markdown.',
                $optional,
                $default(FORMAT_HTML)
            ),
            'introdraftitemid' => new external_value(
                PARAM_INT,
                'Draft file area id with the files embedded in intro.' .
                ($forupdate ? ' It replaces the files the description has now.' : ''),
                $optional,
                $default(0)
            ),
            'visible' => new external_value(
                PARAM_INT,
                'Availability: 1 = shown on the course page, 0 = hidden, ' .
                '-1 = available but not shown on the course page (if the site allows it).',
                $optional,
                $default(1)
            ),
            'idnumber' => new external_value(PARAM_RAW, 'ID number of the activity.', $optional, $default('')),
            'lang' => new external_value(
                PARAM_LANG,
                'Language to force for the activity, for example "de". Empty for no forced language.',
                $optional,
                $default('')
            ),
            'tags' => new external_multiple_structure(
                new external_value(PARAM_TAG, 'Tag name'),
                'Tags of the activity.' . ($forupdate ? ' They replace the current tags.' : ''),
                $optional,
                $default([]),
                $forupdate ? NULL_ALLOWED : NULL_NOT_ALLOWED
            ),
        ];
    }
}
