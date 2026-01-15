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

namespace mod_kahoodle\form;

use context;
use context_module;
use core_form\dynamic_form;
use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;
use moodle_url;

/**
 * Dynamic form for adding or editing a round question
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question extends dynamic_form {
    /** @var array|null Cached round question data */
    protected ?array $roundquestiondata = null;

    /**
     * Get round question data for add or edit mode
     *
     * @return array [roundquestion, round, kahoodle, cm] - roundquestion is null for add mode
     */
    protected function get_round_question_data(): array {
        global $DB;

        if ($this->roundquestiondata !== null) {
            return $this->roundquestiondata;
        }

        $roundquestionid = $this->optional_param('roundquestionid', 0, PARAM_INT);
        $roundid = $this->optional_param('roundid', 0, PARAM_INT);
        $roundquestion = null;

        if ($roundquestionid) {
            // Edit mode - load existing round question.
            $roundquestion = $DB->get_record('kahoodle_round_questions', ['id' => $roundquestionid], '*', MUST_EXIST);
            $roundid = $roundquestion->roundid;
        }

        $round = round::create_from_id($roundid);
        $kahoodle = $DB->get_record('kahoodle', ['id' => $round->get_kahoodleid()], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id, 0, false, MUST_EXIST);

        $this->roundquestiondata = [$roundquestion, $round, $kahoodle, $cm];
        return $this->roundquestiondata;
    }

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;

        [$roundquestion, $round, $kahoodle, $cm] = $this->get_round_question_data();

        // Hidden fields.
        $mform->addElement('hidden', 'roundquestionid', $roundquestion ? $roundquestion->id : 0);
        $mform->setType('roundquestionid', PARAM_INT);

        $mform->addElement('hidden', 'roundid', $round->get_id());
        $mform->setType('roundid', PARAM_INT);

        // Question type (only for add mode).
        if (!$roundquestion) {
            $questiontypes = [
                constants::QUESTION_TYPE_MULTICHOICE => get_string('questiontype_multichoice', 'mod_kahoodle'),
            ];
            $mform->addElement('select', 'questiontype', get_string('questiontype', 'mod_kahoodle'), $questiontypes);
            $mform->setDefault('questiontype', constants::QUESTION_TYPE_MULTICHOICE);
        }

        // Question text.
        $mform->addElement('editor', 'questiontext_editor', get_string('questiontext', 'mod_kahoodle'), null, [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true,
            'context' => context_module::instance($cm->id),
        ]);
        $mform->setType('questiontext_editor', PARAM_RAW);
        $mform->addRule('questiontext_editor', get_string('required'), 'required', null, 'client');

        // Answers configuration (JSON for now, can be expanded later).
        $mform->addElement('textarea', 'answersconfig', get_string('answersconfig', 'mod_kahoodle'), [
            'rows' => 5,
            'cols' => 50,
        ]);
        $mform->setType('answersconfig', PARAM_RAW);

        // Question behavior overrides.
        $mform->addElement('header', 'behaviorheader', get_string('questionbehavior', 'mod_kahoodle'));

        $mform->addElement('text', 'maxpoints', get_string('defaultmaxpoints', 'mod_kahoodle'));
        $mform->setType('maxpoints', PARAM_INT);
        $mform->addHelpButton('maxpoints', 'defaultmaxpoints', 'mod_kahoodle');

        $mform->addElement('text', 'minpoints', get_string('defaultminpoints', 'mod_kahoodle'));
        $mform->setType('minpoints', PARAM_INT);
        $mform->addHelpButton('minpoints', 'defaultminpoints', 'mod_kahoodle');

        $mform->addElement('text', 'questionpreviewduration', get_string('questionpreviewduration', 'mod_kahoodle'));
        $mform->setType('questionpreviewduration', PARAM_INT);
        $mform->addHelpButton('questionpreviewduration', 'questionpreviewduration', 'mod_kahoodle');

        $mform->addElement('text', 'questionduration', get_string('questionduration', 'mod_kahoodle'));
        $mform->setType('questionduration', PARAM_INT);
        $mform->addHelpButton('questionduration', 'questionduration', 'mod_kahoodle');

        $mform->addElement('text', 'questionresultsduration', get_string('questionresultsduration', 'mod_kahoodle'));
        $mform->setType('questionresultsduration', PARAM_INT);
        $mform->addHelpButton('questionresultsduration', 'questionresultsduration', 'mod_kahoodle');
    }

    /**
     * Returns context for dynamic submission
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        [, , , $cm] = $this->get_round_question_data();
        return context_module::instance($cm->id);
    }

    /**
     * Check access for dynamic submission
     */
    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        require_capability('mod/kahoodle:manage_questions', $context);
    }

    /**
     * Process dynamic submission
     *
     * @return array
     */
    public function process_dynamic_submission(): array {
        global $DB;

        $data = $this->get_data();
        [$roundquestion, $round] = $this->get_round_question_data();

        // Prepare question data.
        $questiondata = new \stdClass();
        $questiondata->kahoodleid = $round->get_kahoodleid();
        $questiondata->questiontext = $data->questiontext_editor['text'];
        $questiondata->questiontextformat = $data->questiontext_editor['format'];
        $questiondata->answersconfig = !empty($data->answersconfig) ? $data->answersconfig : null;

        // Behavior overrides (null if empty to use defaults).
        $questiondata->maxpoints = !empty($data->maxpoints) ? $data->maxpoints : null;
        $questiondata->minpoints = !empty($data->minpoints) ? $data->minpoints : null;
        $questiondata->questionpreviewduration = !empty($data->questionpreviewduration) ? $data->questionpreviewduration : null;
        $questiondata->questionduration = !empty($data->questionduration) ? $data->questionduration : null;
        $questiondata->questionresultsduration = !empty($data->questionresultsduration) ? $data->questionresultsduration : null;

        if (!$roundquestion) {
            // Add mode.
            $questiondata->questiontype = $data->questiontype;
            $questionid = \mod_kahoodle\questions::add_question($questiondata);
            return ['questionid' => $questionid, 'action' => 'add'];
        } else {
            // Edit mode - get the question ID from the round question.
            $version = $DB->get_record('kahoodle_question_versions', ['id' => $roundquestion->questionversionid], '*', MUST_EXIST);
            $questiondata->id = $version->questionid;
            \mod_kahoodle\questions::edit_question($questiondata);
            return ['questionid' => $version->questionid, 'action' => 'edit'];
        }
    }

    /**
     * Set data for dynamic submission
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        [$roundquestion, $round] = $this->get_round_question_data();

        $data = [
            'roundquestionid' => $roundquestion ? $roundquestion->id : 0,
            'roundid' => $round->get_id(),
        ];

        if ($roundquestion) {
            // Edit mode - load existing data.
            $version = $DB->get_record('kahoodle_question_versions', ['id' => $roundquestion->questionversionid], '*', MUST_EXIST);

            $data['questiontext_editor'] = [
                'text' => $version->questiontext,
                'format' => $version->questiontextformat,
            ];
            $data['answersconfig'] = $version->answersconfig;
            $data['maxpoints'] = $roundquestion->maxpoints;
            $data['minpoints'] = $roundquestion->minpoints;
            $data['questionpreviewduration'] = $roundquestion->questionpreviewduration;
            $data['questionduration'] = $roundquestion->questionduration;
            $data['questionresultsduration'] = $roundquestion->questionresultsduration;
        }

        $this->set_data($data);
    }

    /**
     * Returns url for dynamic submission
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        [, , , $cm] = $this->get_round_question_data();
        return new moodle_url('/mod/kahoodle/questions.php', ['id' => $cm->id]);
    }
}
