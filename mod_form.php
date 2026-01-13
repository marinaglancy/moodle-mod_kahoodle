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

        // Allow repeat participation.
        $mform->addElement(
            'advcheckbox',
            'allowrepeat',
            get_string('allowrepeat', 'mod_kahoodle'),
            get_string('allowrepeat_help', 'mod_kahoodle')
        );
        $mform->setDefault('allowrepeat', 0);

        // Timing settings.
        $mform->addElement(
            'duration',
            'lobbyduration',
            get_string('lobbyduration', 'mod_kahoodle'),
            ['optional' => false]
        );
        $mform->setDefault('lobbyduration', 300);
        $mform->addHelpButton('lobbyduration', 'lobbyduration', 'mod_kahoodle');

        $mform->addElement(
            'duration',
            'questionpreviewduration',
            get_string('questionpreviewduration', 'mod_kahoodle'),
            ['optional' => false]
        );
        $mform->setDefault('questionpreviewduration', 10);
        $mform->addHelpButton('questionpreviewduration', 'questionpreviewduration', 'mod_kahoodle');

        $mform->addElement(
            'duration',
            'questionduration',
            get_string('questionduration', 'mod_kahoodle'),
            ['optional' => false]
        );
        $mform->setDefault('questionduration', 30);
        $mform->addHelpButton('questionduration', 'questionduration', 'mod_kahoodle');

        $mform->addElement(
            'duration',
            'questionresultsduration',
            get_string('questionresultsduration', 'mod_kahoodle'),
            ['optional' => false]
        );
        $mform->setDefault('questionresultsduration', 10);
        $mform->addHelpButton('questionresultsduration', 'questionresultsduration', 'mod_kahoodle');

        // Points settings.
        $mform->addElement('text', 'defaultmaxpoints', get_string('defaultmaxpoints', 'mod_kahoodle'), ['size' => '10']);
        $mform->setType('defaultmaxpoints', PARAM_INT);
        $mform->setDefault('defaultmaxpoints', 1000);
        $mform->addRule('defaultmaxpoints', null, 'required', null, 'client');
        $mform->addRule('defaultmaxpoints', null, 'numeric', null, 'client');
        $mform->addHelpButton('defaultmaxpoints', 'defaultmaxpoints', 'mod_kahoodle');

        $mform->addElement('text', 'defaultminpoints', get_string('defaultminpoints', 'mod_kahoodle'), ['size' => '10']);
        $mform->setType('defaultminpoints', PARAM_INT);
        $mform->setDefault('defaultminpoints', 500);
        $mform->addRule('defaultminpoints', null, 'required', null, 'client');
        $mform->addRule('defaultminpoints', null, 'numeric', null, 'client');
        $mform->addHelpButton('defaultminpoints', 'defaultminpoints', 'mod_kahoodle');

        // Other standard elements that are displayed in their own fieldsets.
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}
