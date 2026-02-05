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

namespace mod_kahoodle\local\game;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;

/**
 * Participant management for Kahoodle rounds
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants {
    /**
     * Join a round as a participant
     *
     * Creates a participant record for the current user in the given round.
     * If the round is in the lobby stage, notifies facilitators with the updated participant list.
     *
     * @param round $round The round to join
     * @throws \dml_exception If database operation fails
     */
    public static function join_round(round $round): void {
        global $DB, $USER;

        // Check if user is already a participant.
        if ($round->is_participant()) {
            return;
        }

        // Get user's display name.
        $displayname = fullname($USER);

        // Create participant record.
        $participant = (object)[
            'roundid' => $round->get_id(),
            'userid' => $USER->id,
            'displayname' => $displayname,
            'avatar' => null,
            'totalscore' => 0,
            'finalrank' => null,
            'timecreated' => time(),
        ];

        $participant->id = $DB->insert_record('kahoodle_participants', $participant);

        // Save the user's profile picture as the participant avatar.
        $avatar = self::save_profile_picture_to_avatar($round, (int)$participant->id);
        if ($avatar) {
            $DB->set_field('kahoodle_participants', 'avatar', $avatar, ['id' => $participant->id]);
        }

        $round->clear_participant_cache();

        // Trigger participant joined event.
        $event = \mod_kahoodle\event\participant_joined::create([
            'objectid' => $participant->id,
            'context' => $round->get_context(),
            'other' => [
                'roundid' => $round->get_id(),
                'kahoodleid' => $round->get_kahoodleid(),
            ],
        ]);
        $event->trigger();

        // If round is in lobby stage, notify facilitators with updated stage data.
        if ($round->get_current_stage_name() === constants::STAGE_LOBBY) {
            realtime_channels::notify_facilitators_stage_changed($round);
        }
    }

    /**
     * Leave a round as a participant
     *
     * Removes the participant record for the current user in the given round.
     * Also deletes any responses the participant may have submitted.
     * If the round is in the lobby stage, notifies facilitators with the updated participant list.
     *
     * @param round $round The round to leave
     */
    public static function leave_round(round $round): void {
        global $DB;

        $participant = $round->is_participant();
        if (!$participant) {
            return;
        }

        // Delete any responses from this participant.
        $DB->delete_records('kahoodle_responses', ['participantid' => $participant->get_id()]);

        // Trigger participant left event before deletion.
        $event = \mod_kahoodle\event\participant_left::create([
            'objectid' => $participant->get_id(),
            'context' => $round->get_context(),
            'other' => [
                'roundid' => $round->get_id(),
                'kahoodleid' => $round->get_kahoodleid(),
            ],
        ]);
        $event->trigger();

        // Delete the participant record.
        $DB->delete_records('kahoodle_participants', ['id' => $participant->get_id()]);
        $round->clear_participant_cache();

        // If round is in lobby stage, notify facilitators with updated stage data.
        if ($round->get_current_stage_name() === constants::STAGE_LOBBY) {
            realtime_channels::notify_facilitators_stage_changed($round);
        }
    }

    /**
     * Save the current user's profile picture to the participant's avatar file area.
     *
     * Retrieves the profile picture (f3, 120px) and stores it in
     * mod_kahoodle/avatar/{participantid}. If the user has an uploaded picture,
     * it is copied from file storage. Otherwise (gravatar or generated), the
     * URL is downloaded and saved.
     *
     * @param round $round The round the participant belongs to
     * @param int $participantid The participant record ID
     * @return string|null The filename of the saved avatar, or null if no picture could be saved.
     */
    public static function save_profile_picture_to_avatar(round $round, int $participantid): ?string {
        global $USER, $PAGE;

        $fs = get_file_storage();
        $context = $round->get_context();

        // Delete any existing avatar files for this participant.
        $fs->delete_area_files($context->id, 'mod_kahoodle', 'avatar', $participantid);

        // Try to get the uploaded profile picture from file storage (f3 = 120px).
        $usercontext = \context_user::instance($USER->id, IGNORE_MISSING);
        $file = null;
        if ($usercontext) {
            $file = $fs->get_file($usercontext->id, 'user', 'icon', 0, '/', 'f3.png');
            if (!$file) {
                $file = $fs->get_file($usercontext->id, 'user', 'icon', 0, '/', 'f3.jpg');
            }
        }

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_kahoodle',
            'filearea' => 'avatar',
            'itemid' => $participantid,
            'filepath' => '/',
        ];

        if ($file) {
            // User has an uploaded picture - copy it from file storage.
            $filerecord['filename'] = $file->get_filename();
            $fs->create_file_from_storedfile($filerecord, $file);
            return $file->get_filename();
        }

        // No uploaded picture - download from the profile picture URL (gravatar/generated).
        $picture = \core_user::get_profile_picture($USER, $context, ['size' => 120]);
        $url = $picture->get_url($PAGE)->out(false);
        $response = download_file_content($url, null, null, true);
        if ($response->status == 200 && !empty($response->results)) {
            $mimetype = $response->headers['Content-Type'] ?? 'image/png';
            // Strip any charset or parameters from the Content-Type header.
            $mimetype = trim(explode(';', $mimetype)[0]);
            $ext = match ($mimetype) {
                'image/jpeg' => 'jpg',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                default => 'png',
            };
            $filerecord['filename'] = "profile.$ext";
            $fs->create_file_from_string($filerecord, $response->results);
            return "profile.$ext";
        }

        return null;
    }
}
