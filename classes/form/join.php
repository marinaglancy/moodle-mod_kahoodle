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
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;
        /** @var round $round */
        $round = $this->_customdata['round'];
        $context = $round->guess_context();

        $mform->addElement('hidden', 'id', $round->get_cm()->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'join');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('html', '<p>' . get_string('landing_join_message', 'mod_kahoodle') . '</p>');

        $buttonlabel = has_capability('mod/kahoodle:facilitate', $context)
            ? get_string('join_as_participant', 'mod_kahoodle')
            : get_string('join', 'mod_kahoodle');
        $this->add_action_buttons(false, $buttonlabel);
    }
}
