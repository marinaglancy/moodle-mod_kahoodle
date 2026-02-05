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

use mod_kahoodle\constants;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Form for adding and editing Kahoodle instances
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_kahoodle_mod_form extends moodleform_mod {
    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // General fieldset.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        if (!empty($this->_features->introeditor)) {
            // Description element that is usually added to the General fieldset.
            $this->standard_intro_elements();
        }

        // Kahoodle settings.
        $mform->addElement('header', 'kahoodlesettings', get_string('kahoodlesettings', 'mod_kahoodle'));

        // Question format.
        $mform->addElement(
            'select',
            'questionformat',
            get_string('questionformat', 'mod_kahoodle'),
            [
                constants::QUESTIONFORMAT_PLAIN => get_string('questionformat_plain', 'mod_kahoodle'),
                constants::QUESTIONFORMAT_RICHTEXT => get_string('questionformat_richtext', 'mod_kahoodle'),
            ]
        );
        $mform->setDefault('questionformat', constants::QUESTIONFORMAT_PLAIN);
        $mform->addHelpButton('questionformat', 'questionformat', 'mod_kahoodle');

        // Participant identity mode.
        $mform->addElement(
            'select',
            'identitymode',
            get_string('identitymode', 'mod_kahoodle'),
            [
                constants::IDENTITYMODE_REALNAME => get_string('identitymode_realname', 'mod_kahoodle'),
                constants::IDENTITYMODE_OPTIONAL => get_string('identitymode_optional', 'mod_kahoodle'),
                constants::IDENTITYMODE_ALIAS => get_string('identitymode_alias', 'mod_kahoodle'),
                constants::IDENTITYMODE_ANONYMOUS => get_string('identitymode_anonymous', 'mod_kahoodle'),
            ]
        );
        $mform->setDefault('identitymode', constants::DEFAULT_IDENTITY_MODE);
        $mform->addHelpButton('identitymode', 'identitymode', 'mod_kahoodle');

        // Allow repeat participation.
        $mform->addElement(
            'advcheckbox',
            'allowrepeat',
            get_string('allowrepeat', 'mod_kahoodle'),
            get_string('allowrepeat_help', 'mod_kahoodle')
        );
        $mform->setDefault('allowrepeat', constants::DEFAULT_ALLOW_REPEAT);

        // Timing settings.
        $mform->addElement(
            'duration',
            'lobbyduration',
            get_string('lobbyduration', 'mod_kahoodle'),
            ['optional' => false, 'units' => [60, 1]]
        );
        $mform->setDefault('lobbyduration', constants::DEFAULT_LOBBY_DURATION);
        $mform->addHelpButton('lobbyduration', 'lobbyduration', 'mod_kahoodle');

        $mform->addElement(
            'text',
            'questionpreviewduration',
            get_string('questionpreviewduration', 'mod_kahoodle'),
            ['size' => '10']
        );
        $mform->setType('questionpreviewduration', PARAM_INT);
        $mform->setDefault('questionpreviewduration', constants::DEFAULT_QUESTION_PREVIEW_DURATION);
        $mform->addRule('questionpreviewduration', null, 'required', null, 'client');
        $mform->addRule('questionpreviewduration', null, 'numeric', null, 'client');
        $mform->addHelpButton('questionpreviewduration', 'questionpreviewduration', 'mod_kahoodle');

        $mform->addElement(
            'text',
            'questionduration',
            get_string('questionduration', 'mod_kahoodle'),
            ['size' => '10']
        );
        $mform->setType('questionduration', PARAM_INT);
        $mform->setDefault('questionduration', constants::DEFAULT_QUESTION_DURATION);
        $mform->addRule('questionduration', null, 'required', null, 'client');
        $mform->addRule('questionduration', null, 'numeric', null, 'client');
        $mform->addHelpButton('questionduration', 'questionduration', 'mod_kahoodle');

        $mform->addElement(
            'text',
            'questionresultsduration',
            get_string('questionresultsduration', 'mod_kahoodle'),
            ['size' => '10']
        );
        $mform->setType('questionresultsduration', PARAM_INT);
        $mform->setDefault('questionresultsduration', constants::DEFAULT_QUESTION_RESULTS_DURATION);
        $mform->addRule('questionresultsduration', null, 'required', null, 'client');
        $mform->addRule('questionresultsduration', null, 'numeric', null, 'client');
        $mform->addHelpButton('questionresultsduration', 'questionresultsduration', 'mod_kahoodle');

        // Points settings.
        $mform->addElement('text', 'maxpoints', get_string('maxpoints', 'mod_kahoodle'), ['size' => '10']);
        $mform->setType('maxpoints', PARAM_INT);
        $mform->setDefault('maxpoints', constants::DEFAULT_MAX_POINTS);
        $mform->addRule('maxpoints', null, 'required', null, 'client');
        $mform->addRule('maxpoints', null, 'numeric', null, 'client');
        $mform->addHelpButton('maxpoints', 'maxpoints', 'mod_kahoodle');

        $mform->addElement('text', 'minpoints', get_string('minpoints', 'mod_kahoodle'), ['size' => '10']);
        $mform->setType('minpoints', PARAM_INT);
        $mform->setDefault('minpoints', constants::DEFAULT_MIN_POINTS);
        $mform->addRule('minpoints', null, 'required', null, 'client');
        $mform->addRule('minpoints', null, 'numeric', null, 'client');
        $mform->addHelpButton('minpoints', 'minpoints', 'mod_kahoodle');

        // Other standard elements that are displayed in their own fieldsets.
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
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

        // Validate non-negative values.
        $numericfields = [
            'lobbyduration',
            'questionpreviewduration',
            'questionduration',
            'questionresultsduration',
            'maxpoints',
            'minpoints',
        ];

        foreach ($numericfields as $field) {
            if (isset($data[$field]) && (int)$data[$field] < 0) {
                $errors[$field] = get_string('error_nonnegative', 'mod_kahoodle');
            }
        }

        // Validate maxpoints >= minpoints.
        if ((int)$data['maxpoints'] < (int)$data['minpoints']) {
            $errors['maxpoints'] = get_string('error_maxpoints_less_than_minpoints', 'mod_kahoodle');
        }

        return $errors;
    }
}
