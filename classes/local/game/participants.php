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
use mod_kahoodle\local\entities\participant;
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
     * @param string|null $displayname The display name chosen by the participant
     * @throws \dml_exception If database operation fails
     */
    public static function join_round(round $round, ?string $displayname = null): void {
        global $DB, $USER;

        // Check if user is already a participant.
        if ($round->is_participant()) {
            return;
        }

        // Determine display name based on identity mode.
        $kahoodle = $round->get_kahoodle();
        $identitymode = (int)($kahoodle->identitymode ?? constants::DEFAULT_IDENTITY_MODE);
        if ($identitymode === constants::IDENTITYMODE_REALNAME || $displayname === null || trim($displayname) === '') {
            $displayname = fullname($USER);
        } else {
            $displayname = substr(trim($displayname), 0, constants::DISPLAYNAME_MAXLENGTH);
        }

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

        // Save avatar: use profile picture for real name mode (or optional mode with real identity), random avatar otherwise.
        $userealidentity = $identitymode === constants::IDENTITYMODE_REALNAME
            || ($identitymode === constants::IDENTITYMODE_OPTIONAL
                && ($displayname === null || trim($displayname) === ''));
        if ($userealidentity) {
            $avatar = self::save_profile_picture_to_avatar($round, (int)$participant->id);
        } else {
            $avatar = self::save_random_avatar($round, (int)$participant->id);
        }
        if ($avatar !== null) {
            $DB->set_field('kahoodle_participants', 'avatar', $avatar, ['id' => $participant->id]);
            $participant->avatar = $avatar;
        }

        $round->clear_participant_cache();

        // Trigger participant joined event.
        $event = \mod_kahoodle\event\participant_joined::create_from_participant(
            participant::from_partial_record($participant, $round)
        );
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
     * This method is only called when a person who can facilitate joins as a participant and then
     * leaves in order to become a facilitator. The anonymous identity mode is not applicable here.
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
     * Save a random avatar from the admin-uploaded allavatars file area.
     *
     * Picks a random image from the mod_kahoodle/allavatars file area (system context,
     * may contain subfolders) and copies it to the participant's avatar file area.
     *
     * @param round $round The round the participant belongs to
     * @param int $participantid The participant record ID
     * @return string The filename of the saved avatar.
     */
    public static function save_random_avatar(round $round, int $participantid): string {
        $fs = get_file_storage();
        $context = $round->get_context();

        // Get all image files from the allavatars file area.
        $files = self::get_allavatars_images();

        // Delete any existing avatar files for this participant.
        $fs->delete_area_files($context->id, 'mod_kahoodle', constants::FILEAREA_AVATAR, $participantid);

        if (empty($files)) {
            // No admin-uploaded avatars — generate a unique geopattern.
            global $OUTPUT;
            $svg = $OUTPUT->get_generated_svg_for_id($participantid);
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_kahoodle',
                'filearea' => constants::FILEAREA_AVATAR,
                'itemid' => $participantid,
                'filepath' => '/',
                'filename' => 'geopattern.svg',
            ], $svg);
            return 'geopattern.svg';
        }

        // Pick a random file.
        $randomfile = $files[array_rand($files)];

        // Copy it to the participant's avatar area.
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_kahoodle',
            'filearea' => constants::FILEAREA_AVATAR,
            'itemid' => $participantid,
            'filepath' => '/',
            'filename' => $randomfile->get_filename(),
        ];

        $fs->create_file_from_storedfile($filerecord, $randomfile);
        return $randomfile->get_filename();
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

    /**
     * Get avatar candidate files for a participant to choose from.
     *
     * When $onlynew is false (default, used on initial picker open): if the participant
     * already has stored candidates, returns all of them. Otherwise generates up to 8 new ones.
     * When $onlynew is true (used by "Show more"): always generates new candidates.
     *
     * @param participant $participant The participant
     * @param bool $onlynew If true, only generate new candidates (for "Show more")
     * @return array With keys 'candidates' (array of ['filename', 'url']) and 'hasmore' (bool)
     * @throws \moodle_exception If participant cannot change avatar
     */
    public static function get_avatar_candidates(participant $participant, bool $onlynew = false): array {
        if (!$participant->can_change_avatar()) {
            throw new \moodle_exception('error_cannotchangeavatar', 'mod_kahoodle');
        }

        $fs = get_file_storage();
        $round = $participant->get_round();
        $context = $round->guess_context();
        $participantid = $participant->get_id();

        // Get existing candidates already stored for this participant.
        $existingcandidates = $fs->get_directory_files(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_AVATAR,
            $participantid,
            '/candidates/',
            false,
            false
        );
        $existingcount = count($existingcandidates);

        // On initial open, return existing candidates if available.
        if (!$onlynew && $existingcount > 0) {
            $candidates = [];
            foreach ($existingcandidates as $file) {
                $url = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_kahoodle',
                    constants::FILEAREA_AVATAR,
                    $participantid,
                    '/candidates/',
                    $file->get_filename()
                );
                $candidates[] = [
                    'filename' => $file->get_filename(),
                    'url' => $url->out(false),
                ];
            }
            $hasmore = self::has_more_candidates($fs, $context, $participantid, $existingcandidates);
            return ['candidates' => $candidates, 'hasmore' => $hasmore, 'count' => $existingcount];
        }

        // Get all image files from the allavatars file area.
        $allfiles = self::get_allavatars_images();

        if (empty($allfiles)) {
            return ['candidates' => [], 'hasmore' => false];
        }

        // Check if the max candidate limit has been reached.
        if ($existingcount >= constants::MAX_AVATAR_CANDIDATES) {
            return ['candidates' => [], 'hasmore' => false];
        }

        // Build a set of content hashes to exclude (current avatar + existing candidates).
        $excludedhashes = [];
        $currentfiles = $fs->get_directory_files(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_AVATAR,
            $participantid,
            '/',
            false,
            false
        );
        foreach ($currentfiles as $file) {
            $excludedhashes[$file->get_contenthash()] = true;
        }
        foreach ($existingcandidates as $file) {
            $excludedhashes[$file->get_contenthash()] = true;
        }

        // Filter out already-used files.
        $eligible = array_values(array_filter($allfiles, function ($file) use ($excludedhashes) {
            return !isset($excludedhashes[$file->get_contenthash()]);
        }));

        if (empty($eligible)) {
            return ['candidates' => [], 'hasmore' => false];
        }

        // Pick up to 8 random candidates, respecting the max limit.
        $maxnew = min(8, constants::MAX_AVATAR_CANDIDATES - $existingcount);
        $count = min($maxnew, count($eligible));
        $keys = (array)array_rand($eligible, $count);

        // Build set of existing candidate filenames for collision detection.
        $existingnames = [];
        foreach ($existingcandidates as $file) {
            $existingnames[$file->get_filename()] = true;
        }

        // Copy selected files to /candidates/ subfolder.
        $candidates = [];
        $addednames = [];
        foreach ($keys as $key) {
            $sourcefile = $eligible[$key];
            $candidatefilename = $sourcefile->get_filename();

            // Handle filename collisions with existing candidates or within this batch.
            if (isset($existingnames[$candidatefilename]) || isset($addednames[$candidatefilename])) {
                $pathinfo = pathinfo($candidatefilename);
                $ext = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '.png';
                $candidatefilename = $pathinfo['filename'] . '_' . $key . $ext;
            }

            $filerecord = [
                'contextid' => $context->id,
                'component' => 'mod_kahoodle',
                'filearea' => constants::FILEAREA_AVATAR,
                'itemid' => $participantid,
                'filepath' => '/candidates/',
                'filename' => $candidatefilename,
            ];

            $fs->create_file_from_storedfile($filerecord, $sourcefile);
            $addednames[$candidatefilename] = true;

            $url = \moodle_url::make_pluginfile_url(
                $context->id,
                'mod_kahoodle',
                constants::FILEAREA_AVATAR,
                $participantid,
                '/candidates/',
                $candidatefilename
            );

            $candidates[] = [
                'filename' => $candidatefilename,
                'url' => $url->out(false),
            ];
        }

        // Determine if more candidates can still be requested.
        $newtotal = $existingcount + count($candidates);
        $remainingeligible = count($eligible) - count($candidates);
        $hasmore = $newtotal < constants::MAX_AVATAR_CANDIDATES && $remainingeligible > 0;

        return ['candidates' => $candidates, 'hasmore' => $hasmore];
    }

    /**
     * Change a participant's avatar to one of the stored candidates.
     *
     * Validates that the requested filename exists in the /candidates/ subfolder,
     * replaces the current avatar with the selected candidate, and deletes
     * all candidate files.
     *
     * @param participant $participant The participant
     * @param string $filename The filename of the chosen candidate
     * @return string The pluginfile URL of the new avatar
     * @throws \moodle_exception If participant cannot change avatar or filename is invalid
     */
    public static function change_avatar(participant $participant, string $filename): string {
        global $DB;

        if (!$participant->can_change_avatar()) {
            throw new \moodle_exception('error_cannotchangeavatar', 'mod_kahoodle');
        }

        $fs = get_file_storage();
        $round = $participant->get_round();
        $context = $round->guess_context();
        $participantid = $participant->get_id();

        // Validate the chosen file exists in candidates.
        $chosenfile = $fs->get_file(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_AVATAR,
            $participantid,
            '/candidates/',
            $filename
        );
        if (!$chosenfile || $chosenfile->is_directory()) {
            throw new \moodle_exception('error_invalidavatarcandidate', 'mod_kahoodle');
        }

        // Delete the current avatar at /.
        $currentfiles = $fs->get_directory_files(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_AVATAR,
            $participantid,
            '/',
            false,
            false
        );
        foreach ($currentfiles as $file) {
            $file->delete();
        }

        // Copy the chosen candidate to /.
        $fs->create_file_from_storedfile([
            'contextid' => $context->id,
            'component' => 'mod_kahoodle',
            'filearea' => constants::FILEAREA_AVATAR,
            'itemid' => $participantid,
            'filepath' => '/',
            'filename' => $chosenfile->get_filename(),
        ], $chosenfile);

        // Update the participant record with the new avatar filename.
        $DB->set_field(
            'kahoodle_participants',
            'avatar',
            $chosenfile->get_filename(),
            ['id' => $participantid]
        );

        // Delete all candidates.
        $allcandidates = $fs->get_directory_files(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_AVATAR,
            $participantid,
            '/candidates/',
            false,
            false
        );
        foreach ($allcandidates as $file) {
            $file->delete();
        }

        $round->clear_participant_cache();

        // Return the pluginfile URL for the new avatar.
        return \moodle_url::make_pluginfile_url(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_AVATAR,
            $participantid,
            '/',
            $chosenfile->get_filename()
        )->out(false);
    }

    /**
     * Delete all candidate avatar files for all participants in a round.
     *
     * Called when the lobby stage ends to ensure no leftover candidate files remain.
     *
     * @param round $round The round whose participants' candidates should be cleaned up
     */
    public static function cleanup_avatar_candidates(round $round): void {
        $fs = get_file_storage();
        $context = $round->guess_context();

        foreach ($round->get_all_participants() as $participant) {
            $candidates = $fs->get_directory_files(
                $context->id,
                'mod_kahoodle',
                constants::FILEAREA_AVATAR,
                $participant->get_id(),
                '/candidates/',
                false,
                false
            );
            foreach ($candidates as $file) {
                $file->delete();
            }
        }
    }

    /**
     * Check whether more avatar candidates can be generated for a participant.
     *
     * @param \file_storage $fs File storage instance
     * @param \context $context The module context
     * @param int $participantid The participant ID
     * @param \stored_file[] $existingcandidates Already stored candidate files
     * @return bool
     */
    private static function has_more_candidates(
        \file_storage $fs,
        \context $context,
        int $participantid,
        array $existingcandidates
    ): bool {
        $existingcount = count($existingcandidates);
        if ($existingcount >= constants::MAX_AVATAR_CANDIDATES) {
            return false;
        }

        // Build excluded hashes from current avatar and existing candidates.
        $excludedhashes = [];
        $currentfiles = $fs->get_directory_files(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_AVATAR,
            $participantid,
            '/',
            false,
            false
        );
        foreach ($currentfiles as $file) {
            $excludedhashes[$file->get_contenthash()] = true;
        }
        foreach ($existingcandidates as $file) {
            $excludedhashes[$file->get_contenthash()] = true;
        }

        $allfiles = self::get_allavatars_images();

        foreach ($allfiles as $file) {
            if (!isset($excludedhashes[$file->get_contenthash()])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all image files from the allavatars file area.
     *
     * Filters out non-image files (e.g. text files, PDFs) that may have been
     * accidentally uploaded to the avatar pool.
     *
     * @return \stored_file[]
     */
    private static function get_allavatars_images(): array {
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $files = $fs->get_area_files(
            $syscontext->id,
            'mod_kahoodle',
            'allavatars',
            0,
            'filepath, filename',
            false
        );
        return array_values(array_filter($files, function (\stored_file $file) {
            return file_mimetype_in_typegroup($file->get_mimetype(), ['web_image']);
        }));
    }
}
