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

namespace mod_kahoodle\local\entities;

use moodle_url;
use stdClass;

/**
 * Class participant
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participant {
    /**
     * Constructor
     *
     * @param round $round The round entity
     * @param stdClass $participantdata The participant record data
     * @param stdClass $userdata The user record data
     */
    protected function __construct(
        /** @var round The round entity */
        protected round $round,
        /** @var stdClass The participant record data */
        protected stdClass $participantdata,
        /** @var stdClass The user record data */
        protected stdClass $userdata
    ) {
    }

    /**
     * Load participants for a round
     *
     * @param round $round The round entity
     * @param string $extraquery Additional SQL query to append
     * @param array $extraparams Parameters for the additional query
     * @return participant[] Array of participant objects indexed by participant id
     */
    public static function load_round_participants(round $round, string $extraquery = '', array $extraparams = []): array {
        global $DB;

        $userfields = \core_user\fields::for_userpic()->with_name()->excluding('email');
        ['selects' => $userfieldssql, 'joins' => $userfieldsjoin, 'params' => $userfieldsparams,
        'mappings' => $mappings] =
                (array)$userfields->get_sql('u', true, 'user_');

        $participants = $DB->get_records_sql(
            'SELECT p.* ' . $userfieldssql . '
            FROM {kahoodle_participants} p
            JOIN {user} u ON u.id = p.userid ' . $userfieldsjoin . '
            WHERE p.roundid = :roundid ' . $extraquery . '
            ORDER BY p.timecreated DESC',
            ['roundid' => $round->get_id()] + $extraparams + $userfieldsparams
        );

        $result = [];
        foreach ($participants as $record) {
            $user = new stdClass();
            $participant = new stdClass();
            foreach ($record as $field => $value) {
                if (preg_match('/^user_/', $field, $matches)) {
                    $user->{substr($field, 5)} = $value;
                } else {
                    $participant->{$field} = $value;
                }
            }
            $result[$participant->id] = new self($round, $participant, $user);
        }
        return $result;
    }

    /**
     * Get participant id
     *
     * @return int
     */
    public function get_id(): int {
        return (int)$this->participantdata->id;
    }

    /**
     * Get user id
     *
     * @return int
     */
    public function get_user_id(): int {
        return (int)$this->userdata->id;
    }

    /**
     * Get display name
     *
     * @return string
     */
    public function get_display_name(): string {
        return $this->participantdata->displayname ?: fullname($this->userdata);
    }

    /**
     * Get avatar URL
     *
     * @param int $size Avatar size. Recommended values (supporting user initials too): 16, 35, 64 and 100.
     * @return moodle_url
     */
    public function get_avatar_url(int $size = 35): moodle_url {
        global $PAGE;
        $picture = \core_user::get_profile_picture(
            $this->userdata,
            $this->round->get_context(),
            ['size' => $size]
        );
        return $picture->get_url($PAGE);
    }
}
