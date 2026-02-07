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

namespace mod_kahoodle\event;

/**
 * Event participant_joined
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participant_joined extends \core\event\base {
    /**
     * Set basic properties for the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'kahoodle_participants';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Create an instance
     *
     * @param \mod_kahoodle\local\entities\participant $participant
     * @return self
     */
    public static function create_from_participant(\mod_kahoodle\local\entities\participant $participant): self {
        $round = $participant->get_round();
        return self::create([
            'objectid' => $participant->get_id(),
            'context' => $round->get_context(),
            'relateduserid' => $participant->get_user_id(),
            'other' => [
                'roundid' => $round->get_id(),
                'kahoodleid' => $round->get_kahoodle()->id,
            ],
        ]);
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' joined round with id '{$this->other['roundid']}' " .
            "in kahoodle with id '{$this->other['kahoodleid']}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventparticipantjoined', 'mod_kahoodle');
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/kahoodle/view.php', ['id' => $this->contextinstanceid]);
    }
}
