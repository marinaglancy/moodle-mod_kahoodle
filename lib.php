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

/**
 * Callback implementations for Kahoodle
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/mod}
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * List of features supported in module
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function kahoodle_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Add Kahoodle instance
 *
 * Given an object containing all the necessary data, (defined by the form in mod_form.php)
 * this function will create a new instance and return the id of the instance
 *
 * @param stdClass $moduleinstance form data
 * @param mod_kahoodle_mod_form $form the form
 * @return int new instance id
 */
function kahoodle_add_instance($moduleinstance, $form = null) {
    return \mod_kahoodle\api::create_instance($moduleinstance, $moduleinstance->coursemodule);
}

/**
 * Updates an instance of the Kahoodle in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param stdClass $moduleinstance An object from the form in mod_form.php
 * @param mod_kahoodle_mod_form $form The form
 * @return bool True if successful, false otherwis
 */
function kahoodle_update_instance($moduleinstance, $form = null) {
    $moduleinstance->id = $moduleinstance->instance;
    return \mod_kahoodle\api::update_instance($moduleinstance, $moduleinstance->coursemodule);
}

/**
 * Removes an instance of the Kahoodle from the database.
 *
 * @param int $id Id of the module instance
 * @return bool True if successful, false otherwise
 */
function kahoodle_delete_instance($id) {
    return \mod_kahoodle\api::delete_instance($id);
}

/**
 * Extend the settings navigation with the Kahoodle module items
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param navigation_node $node The navigation node to extend
 */
function kahoodle_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node) {
    global $PAGE;

    if (has_capability('mod/kahoodle:manage_questions', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/kahoodle/questions.php', ['id' => $PAGE->cm->id]);
        $node->add(
            get_string('questions', 'mod_kahoodle'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'kahoodle_questions',
            new pix_icon('i/questions', '')
        );
    }
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 */
function mod_kahoodle_check_updates_since(cm_info $cm, $from, $filter = []) {
    $updates = course_check_module_updates_since($cm, $from, ['content'], $filter);
    return $updates;
}

/**
 * Serves the kahoodle files.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param context $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function kahoodle_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    // Check the file area.
    if ($filearea !== \mod_kahoodle\constants::FILEAREA_QUESTION_IMAGE) {
        // The intro file area is handled automatically.
        return false;
    }

    // The item ID is the question version ID.
    $itemid = array_shift($args);
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/mod_kahoodle/{$filearea}/{$itemid}/{$relativepath}";

    $fs = get_file_storage();
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Font awesome icons mapping
 *
 * @return array
 */
function kahoodle_get_fontawesome_icon_map() {
    return [
        'mod_kahoodle:pause' => 'fa-pause',
        'mod_kahoodle:resume' => 'fa-play',
        'mod_kahoodle:next' => 'fa-forward-step',
        'mod_kahoodle:back' => 'fa-backward-step',
    ];
}

/**
 * Callback for tool_realtime - handle events received from clients
 *
 * @param \tool_realtime\channel $channel The realtime channel
 * @param mixed $payload The event payload
 * @return array Response data
 */
function mod_kahoodle_realtime_event_received(\tool_realtime\channel $channel, $payload): array {
    $props = $channel->get_properties();

    // Verify this is for our component and the game area.
    if ($props['component'] !== 'mod_kahoodle') {
        return ['error' => 'Invalid channel'];
    }

    if ($props['area'] == 'facilitator') {
        // Facilitator channel. If there are several facilitators, they share the same channel.
        // Itemid is the round id.

        $roundid = (int)$props['itemid'];
        $action = $payload['action'] ?? '';

        // Get the round entity.
        $round = \mod_kahoodle\local\entities\round::create_from_id($roundid);
        $context = $round->get_context();
        \core_external\external_api::validate_context($context);

        switch ($action) {
            case 'advance':
                // Advance to the next stage.
                $currentstage = clean_param($payload['currentstage'] ?? '', PARAM_ALPHANUMEXT);
                $currentquestion = clean_param($payload['currentquestion'] ?? 0, PARAM_INT);
                $stage = \mod_kahoodle\local\game\progress::advance_to_next_stage($round, $currentstage, $currentquestion);
                // Notify all subscribers on the game channel with the new stage data.
                $channel->notify($stage->export_data_for_facilitators());
                // Do not return anything, instead listen to the game channel for updates.
                return [];

            case 'get_current':
                // Get current stage data (used when resuming a game in progress).
                $currentstage = clean_param($payload['currentstage'] ?? '', PARAM_ALPHANUMEXT);
                $stage = $round->get_current_stage();
                return $stage->export_data_for_facilitators();

            default:
                return ['error' => 'Unknown action'];
        }
    }

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($props['area'] == 'game') {
        // Game channel, common notifications to all participants. Itemid is the round id.
        // TODO implement when participant view is ready.
        // Nobody can send events to this channel.
        // Examples of common notifications: stage changed to previewing or asking a question.
    }

    if ($props['area'] == 'participant') {
        // Participant-specific channel. Itemid is the participant id.
        // TODO: Participants can send events to this channel - changing avatar, answering a question.
        // TODO: Examples of participant-specific notifications:
        // stage changed to question results (showing result for this participant).

        $participantid = (int)$props['itemid'];
        $action = $payload['action'] ?? '';

        // Get the participant and round.
        global $DB;
        $participant = $DB->get_record('kahoodle_participants', ['id' => $participantid], '*', MUST_EXIST);
        $round = \mod_kahoodle\local\entities\round::create_from_id($participant->roundid);
        $context = $round->get_context();
        \core_external\external_api::validate_context($context);

        switch ($action) {
            case 'get_current':
                // Get current stage data for participant view.
                $stage = $round->get_current_stage();
                return $stage->export_data_for_participants();

            default:
                return ['error' => 'Unknown action'];
        }
    }

    return ['error' => 'Invalid channel'];
}
