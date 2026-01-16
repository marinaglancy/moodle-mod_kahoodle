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
use core_form\dynamic_form;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\round_question;
use moodle_url;

/**
 * Dynamic form for adding or editing a round question
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question extends dynamic_form {
    /** @var round_question|null Cached round question data */
    protected ?round_question $roundquestiondata = null;

    /**
     * Get round question data for add or edit mode
     *
     * @return round_question
     */
    protected function get_round_question_data(): round_question {
        if ($this->roundquestiondata !== null) {
            return $this->roundquestiondata;
        }

        $roundquestionid = $this->optional_param('roundquestionid', 0, PARAM_INT);
        $roundid = $this->optional_param('roundid', 0, PARAM_INT);
        $questiontype = $this->optional_param('questiontype', '', PARAM_ALPHANUMEXT);

        if ($roundquestionid) {
            // Edit mode - load existing round question.
            $roundquestion = round_question::create_from_round_question_id($roundquestionid);
        } else {
            // Add mode - load round by ID.
            $round = round::create_from_id($roundid);
            $roundquestion = round_question::new_for_round_and_type($round, $questiontype ?: null);
        }

        $this->roundquestiondata = $roundquestion;
        return $this->roundquestiondata;
    }

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;

        $roundquestion = $this->get_round_question_data();
        $round = $roundquestion->get_round();
        $questiontype = $roundquestion->get_data()->questiontype;

        // Hidden fields.
        $mform->addElement('hidden', 'roundquestionid', $roundquestion->get_id());
        $mform->setType('roundquestionid', PARAM_INT);

        $mform->addElement('hidden', 'roundid', $round->get_id());
        $mform->setType('roundid', PARAM_INT);

        // Question type is passed as parameter and stored as hidden field.
        $mform->addElement('hidden', 'questiontype', $questiontype);
        $mform->setType('questiontype', PARAM_ALPHANUMEXT);

        // Question text.
        $mform->addElement('editor', 'questiontext_editor', get_string('questiontext', 'mod_kahoodle'), null, [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true,
            'context' => $round->get_context(),
        ]);
        $mform->setType('questiontext_editor', PARAM_RAW);
        $mform->addRule('questiontext_editor', get_string('required'), 'required', null, 'client');

        $roundquestion->get_question_type()->question_form_definition($roundquestion, $mform);

        // Question behavior overrides.
        $mform->addElement('header', 'behaviorheader', get_string('questionbehavior', 'mod_kahoodle'));

        $kahoodle = $round->get_kahoodle();

        $group = [];
        $group[] = $mform->createElement('text', 'maxpoints', '', ['size' => '10']);
        $group[] = $mform->createElement(
            'static',
            'maxpoints_default',
            '',
            get_string('defaultvalue', 'mod_kahoodle', $kahoodle->defaultmaxpoints)
        );
        $mform->addGroup($group, 'maxpointsgroup', get_string('defaultmaxpoints', 'mod_kahoodle'), ' ', false);
        $mform->setType('maxpoints', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('maxpointsgroup', 'defaultmaxpoints', 'mod_kahoodle');
        $mform->addGroupRule('maxpointsgroup', ['maxpoints' => [[null, 'numeric', null, 'client']]]);

        $group = [];
        $group[] = $mform->createElement('text', 'minpoints', '', ['size' => '10']);
        $group[] = $mform->createElement(
            'static',
            'minpoints_default',
            '',
            get_string('defaultvalue', 'mod_kahoodle', $kahoodle->defaultminpoints)
        );
        $mform->addGroup($group, 'minpointsgroup', get_string('defaultminpoints', 'mod_kahoodle'), ' ', false);
        $mform->setType('minpoints', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('minpointsgroup', 'defaultminpoints', 'mod_kahoodle');
        $mform->addGroupRule('minpointsgroup', ['minpoints' => [[null, 'numeric', null, 'client']]]);

        $group = [];
        $group[] = $mform->createElement('text', 'questionpreviewduration', '', ['size' => '10']);
        $group[] = $mform->createElement(
            'static',
            'questionpreviewduration_default',
            '',
            get_string('defaultvalue', 'mod_kahoodle', $kahoodle->questionpreviewduration)
        );
        $mform->addGroup(
            $group,
            'questionpreviewdurationgroup',
            get_string('questionpreviewduration', 'mod_kahoodle'),
            ' ',
            false
        );
        $mform->setType('questionpreviewduration', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('questionpreviewdurationgroup', 'questionpreviewduration', 'mod_kahoodle');
        $mform->addGroupRule('questionpreviewdurationgroup', ['questionpreviewduration' => [[null, 'numeric', null, 'client']]]);

        $group = [];
        $group[] = $mform->createElement('text', 'questionduration', '', ['size' => '10']);
        $group[] = $mform->createElement(
            'static',
            'questionduration_default',
            '',
            get_string('defaultvalue', 'mod_kahoodle', $kahoodle->questionduration)
        );
        $mform->addGroup($group, 'questiondurationgroup', get_string('questionduration', 'mod_kahoodle'), ' ', false);
        $mform->setType('questionduration', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('questiondurationgroup', 'questionduration', 'mod_kahoodle');
        $mform->addGroupRule('questiondurationgroup', ['questionduration' => [[null, 'numeric', null, 'client']]]);

        $group = [];
        $group[] = $mform->createElement('text', 'questionresultsduration', '', ['size' => '10']);
        $group[] = $mform->createElement(
            'static',
            'questionresultsduration_default',
            '',
            get_string('defaultvalue', 'mod_kahoodle', $kahoodle->questionresultsduration)
        );
        $mform->addGroup(
            $group,
            'questionresultsdurationgroup',
            get_string('questionresultsduration', 'mod_kahoodle'),
            ' ',
            false
        );
        $mform->setType('questionresultsduration', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('questionresultsdurationgroup', 'questionresultsduration', 'mod_kahoodle');
        $mform->addGroupRule('questionresultsdurationgroup', ['questionresultsduration' => [[null, 'numeric', null, 'client']]]);
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $roundquestion = $this->get_round_question_data();
        $round = $roundquestion->get_round();
        $kahoodle = $round->get_kahoodle();

        // Helper to get effective value (submitted or default).
        $getvalue = function (string $field, string $defaultfield) use ($data, $kahoodle): int {
            return strlen("" . $data[$field]) ? (int)$data[$field] : (int)$kahoodle->$defaultfield;
        };

        // Validate non-negative values.
        $numericfields = [
            'maxpoints' => 'maxpointsgroup',
            'minpoints' => 'minpointsgroup',
            'questionpreviewduration' => 'questionpreviewdurationgroup',
            'questionduration' => 'questiondurationgroup',
            'questionresultsduration' => 'questionresultsdurationgroup',
        ];

        foreach ($numericfields as $field => $groupname) {
            if (strlen("" . $data[$field]) && (int)$data[$field] < 0) {
                $errors[$groupname] = get_string('error_nonnegative', 'mod_kahoodle');
            }
        }

        // Validate maxpoints >= minpoints (considering defaults).
        $maxpoints = $getvalue('maxpoints', 'defaultmaxpoints');
        $minpoints = $getvalue('minpoints', 'defaultminpoints');

        if ($maxpoints < $minpoints) {
            $errors['maxpointsgroup'] = get_string('error_maxpoints_less_than_minpoints', 'mod_kahoodle');
        }

        $errors += $roundquestion->get_question_type()->question_form_validation($roundquestion, $data, $files);

        return $errors;
    }

    /**
     * Returns context for dynamic submission
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return $this->get_round_question_data()->get_round()->get_context();
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
        $data = $this->get_data();
        $roundquestion = $this->get_round_question_data();
        $round = $roundquestion->get_round();

        // Prepare question data.
        $questiondata = new \stdClass();
        $questiondata->kahoodleid = $round->get_kahoodleid();
        $questiondata->questiontext = $data->questiontext_editor['text'];
        $questiondata->questiontextformat = $data->questiontext_editor['format'];
        $questiondata->questionconfig = !empty($data->questionconfig) ? $data->questionconfig : null;

        // Behavior overrides (null if empty to use defaults).
        $questiondata->maxpoints = strlen("" . $data->maxpoints) ? (int)$data->maxpoints : null;
        $questiondata->minpoints = strlen("" . $data->minpoints) ? (int)$data->minpoints : null;
        $questiondata->questionpreviewduration = strlen("" . $data->questionpreviewduration) ?
            (int)$data->questionpreviewduration : null;
        $questiondata->questionduration = strlen("" . $data->questionduration) ?
            (int)$data->questionduration : null;
        $questiondata->questionresultsduration = strlen("" . $data->questionresultsduration) ?
            (int)$data->questionresultsduration : null;

        if (!$roundquestion->get_id()) {
            // Add mode.
            $questiondata->questiontype = $data->questiontype;
            $roundquestion = \mod_kahoodle\questions::add_question($questiondata);
            return ['questionid' => $roundquestion->get_question_id(), 'action' => 'add'];
        } else {
            // Edit mode.
            \mod_kahoodle\questions::edit_question($roundquestion, $questiondata);
            return ['questionid' => $roundquestion->get_question_id(), 'action' => 'edit'];
        }
    }

    /**
     * Set data for dynamic submission
     */
    public function set_data_for_dynamic_submission(): void {
        $roundquestion = $this->get_round_question_data();
        $round = $roundquestion->get_round();
        $version = $roundquestion->get_data();

        $data = [
            'roundquestionid' => $roundquestion->get_id(),
            'roundid' => $round->get_id(),
            'questiontype' => $version->questiontype,
        ];

        $data['questiontext_editor'] = [
            'text' => $version->questiontext,
            'format' => $version->questiontextformat,
        ];
        $data['questionconfig'] = $version->questionconfig;
        $data['maxpoints'] = $version->maxpoints;
        $data['minpoints'] = $version->minpoints;
        $data['questionpreviewduration'] = $version->questionpreviewduration;
        $data['questionduration'] = $version->questionduration;
        $data['questionresultsduration'] = $version->questionresultsduration;

        $this->set_data($data);
    }

    /**
     * Returns url for dynamic submission
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $roundquestion = $this->get_round_question_data();
        $round = $roundquestion->get_round();
        return new moodle_url('/mod/kahoodle/questions.php', [
            'roundid' => $round->get_id(),
            'roundquestionid' => $roundquestion ? $roundquestion->get_id() : 0,
            'questiontype' => $roundquestion->get_data()->questiontype,
        ]);
    }
}
