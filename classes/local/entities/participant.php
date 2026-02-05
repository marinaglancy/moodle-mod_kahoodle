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
     * Get the round entity
     *
     * @return round
     */
    public function get_round(): round {
        return $this->round;
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

        $userfields = \core_user\fields::for_userpic()->with_name();
        ['selects' => $userfieldssql, 'joins' => $userfieldsjoin, 'params' => $userfieldsparams,
        'mappings' => $mappings] =
                (array)$userfields->get_sql('u', true, 'user_');

        $participants = $DB->get_records_sql(
            'SELECT p.* ' . $userfieldssql . '
            FROM {kahoodle_participants} p
            LEFT JOIN {user} u ON u.deleted = 0 AND u.id = p.userid ' . $userfieldsjoin . '
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
            if (empty($user->id)) {
                // User deleted, remove userid from participant record too.
                $participant->userid = null;
            }
            $result[$participant->id] = new self($round, $participant, $user);
        }
        return $result;
    }

    public static function from_partial_record(stdClass $record, ?round $round = null): self {
        $round = $round ?? round::create_from_object((object)[
            'id' => $record->roundid,
            'kahoodleid' => $record->kahoodleid,
        ]);
        $userdata = (object)[
            'id' => $record->user_id ?? null,
        ];
        $participantdata = (object)[
            'id' => $record->id,
            'roundid' => $round->get_id(),
            'userid' => $userdata->id,
            'displayname' => $record->displayname,
            'avatar' => $record->avatar ?? null,
        ];
        return new self($round, $participantdata, $userdata);
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
     * Get user id. If user is deleted, returns null.
     *
     * @return int|null
     */
    public function get_user_id(): ?int {
        return empty($this->userdata->id) ? null : (int)$this->userdata->id;
    }

    /**
     * Get user record. If user is deleted, returns empty user record.
     *
     * @return stdClass
     */
    public function get_user_record(): stdClass {
        return $this->userdata;
    }

    /**
     * Get display name
     *
     * @return string
     */
    public function get_display_name(): string {
        return s($this->participantdata->displayname) ?: fullname($this->userdata);
    }

    /**
     * Get avatar URL from the stored avatar file.
     *
     * Returns the pluginfile URL for the participant's saved avatar.
     * If no avatar is stored, returns the default user picture URL.
     *
     * @return moodle_url
     */
    public function get_avatar_url(): moodle_url {
        global $OUTPUT;
        $avatar = $this->participantdata->avatar ?? null;
        if ($avatar) {
            $context = $this->round->guess_context();
            return moodle_url::make_pluginfile_url(
                $context->id,
                'mod_kahoodle',
                \mod_kahoodle\constants::FILEAREA_AVATAR,
                $this->get_id(),
                '/',
                $avatar
            );
        }
        return $OUTPUT->image_url('u/f3');
    }

    /**
     * Get the user's profile picture URL (from Moodle core, not the stored avatar).
     *
     * @param int $size Picture size. Recommended values: 16, 35, 64, 100, 120.
     * @return moodle_url
     */
    public function get_profile_picture_url(int $size = 35): moodle_url {
        global $PAGE;
        $picture = \core_user::get_profile_picture(
            $this->userdata,
            $this->round->get_context(),
            ['size' => $size]
        );
        return $picture->get_url($PAGE);
    }

    /**
     * Get total score
     *
     * @return int
     */
    public function get_total_score(): int {
        return (int)$this->participantdata->totalscore;
    }

    /**
     * Get participant final rank (null if round is not finished and final rank not assigned)
     *
     * @return int|null
     */
    public function get_final_rank(): ?int {
        $rank = $this->participantdata->finalrank;
        return $rank === null ? null : (int)$rank;
    }
}
