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

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for joining a round as a participant
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class join extends \moodleform {
    /**
     * Get the round entity from custom data
     *
     * @return round
     * @throws \coding_exception
     */
    public function get_round(): round {
        if (!isset($this->_customdata['round']) || !($this->_customdata['round'] instanceof round)) {
            throw new \coding_exception('The join form requires a round entity in custom data');
        }
        return $this->_customdata['round'];
    }

    /**
     * Form definition
     */
    protected function definition() {
        global $USER;

        $mform = $this->_form;
        $round = $this->get_round();
        $context = $round->get_context();

        $mform->addElement('hidden', 'id', $round->get_cm()->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'join');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('html', '<p>' . get_string('landing_join_message', 'mod_kahoodle') . '</p>');

        $kahoodle = $round->get_kahoodle();
        $identitymode = (int)($kahoodle->identitymode ?? constants::DEFAULT_IDENTITY_MODE);

        if ($identitymode !== constants::IDENTITYMODE_REALNAME) {
            $maxlen = constants::DISPLAYNAME_MAXLENGTH;
            $mform->addElement(
                'text',
                'displayname',
                get_string('participantdisplayname_form', 'mod_kahoodle'),
                ['maxlength' => $maxlen, 'size' => $maxlen]
            );
            $mform->setType('displayname', PARAM_TEXT);
            $mform->addRule('displayname', null, 'required', null, 'client');
            $mform->addRule('displayname', get_string('maximumchars', '', $maxlen), 'maxlength', $maxlen, 'client');

            if ($identitymode === constants::IDENTITYMODE_OPTIONAL) {
                $mform->setDefault('displayname', fullname($USER));
            }
        }

        $buttonlabel = has_capability('mod/kahoodle:facilitate', $context)
            ? get_string('join_as_participant', 'mod_kahoodle')
            : get_string('join', 'mod_kahoodle');
        $this->add_action_buttons(false, $buttonlabel);
    }
}
