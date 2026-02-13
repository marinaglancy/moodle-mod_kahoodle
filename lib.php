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
            return MOD_PURPOSE_ASSESSMENT;
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
    return \mod_kahoodle\local\game\instance::create_instance($moduleinstance, $moduleinstance->coursemodule);
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
    return \mod_kahoodle\local\game\instance::update_instance($moduleinstance, $moduleinstance->coursemodule);
}

/**
 * Removes an instance of the Kahoodle from the database.
 *
 * @param int $id Id of the module instance
 * @return bool True if successful, false otherwise
 */
function kahoodle_delete_instance($id) {
    return \mod_kahoodle\local\game\instance::delete_instance($id);
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

    if (has_capability('mod/kahoodle:viewresults', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/kahoodle/results.php', ['id' => $PAGE->cm->id]);
        $node->add(
            get_string('results', 'mod_kahoodle'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'kahoodle_results',
            new pix_icon('i/report', '')
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
 */
function kahoodle_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return;
    }

    require_course_login($course, true, $cm);

    // Check the file area.
    $allowedareas = [
        \mod_kahoodle\constants::FILEAREA_QUESTION_IMAGE,
        \mod_kahoodle\constants::FILEAREA_AVATAR,
    ];
    if (!in_array($filearea, $allowedareas)) {
        // The intro file area is handled automatically.
        return;
    }

    // TODO check capabilities to view the file: for question image - facilitate, manage questions or view results
    // for avatar - anyone who can see the participant list (facilitate, manage questions, view results, participate).

    // The item ID is the question version ID (for question images) or participant ID (for avatars).
    $itemid = array_shift($args);
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/mod_kahoodle/{$filearea}/{$itemid}/{$relativepath}";

    $fs = get_file_storage();
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return;
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
 * Callback for inplace editable API
 *
 * @param string $itemtype The type of item being edited
 * @param int $itemid The ID of the item
 * @param string $newvalue The new value
 * @return \core\output\inplace_editable
 */
function mod_kahoodle_inplace_editable(string $itemtype, int $itemid, string $newvalue): \core\output\inplace_editable {
    if ($itemtype === 'roundname') {
        // Get the round record.
        $round = \mod_kahoodle\local\entities\round::create_from_id($itemid);

        // Validate context and capability.
        \core_external\external_api::validate_context($round->get_context());
        require_capability('mod/kahoodle:facilitate', $round->get_context());

        // Update the round name.
        return $round->update_name($newvalue);
    }

    throw new coding_exception('Unknown item type: ' . $itemtype);
}

/**
 * Callback for tool_realtime - handle events received from clients
 *
 * @param mixed $payload The event payload
 * @return array Response data
 */
function mod_kahoodle_realtime_event_received($payload): array {
    global $PAGE, $DB;

    $action = $payload['action'] ?? '';
    $roundid = clean_param($payload['roundid'] ?? 0, PARAM_INT);
    if (!$roundid) {
        return ['error' => 'Missing round ID'];
    }
    // Get the round entity.
    $round = \mod_kahoodle\local\entities\round::create_from_id($roundid);
    $context = $round->get_context();
    \core_external\external_api::validate_context($context);
    if (!$round->is_in_progress()) {
        return ['error' => 'Round is not in progress'];
    }

    if (in_array($action, ['advance', 'get_current', 'reveal_rank'])) {
        // Facilitator actions.

        require_capability('mod/kahoodle:facilitate', $context);

        switch ($action) {
            case 'advance':
                // Advance to the next stage.
                $currentstage = clean_param($payload['currentstage'] ?? '', PARAM_ALPHANUMEXT);
                \mod_kahoodle\local\game\progress::advance_to_next_stage($round, $currentstage);
                // Do not return anything, instead listen to the game channel for updates.
                return [];

            case 'get_current':
                // Get current stage data (used when resuming a game in progress).
                return (new \mod_kahoodle\output\facilitator($round))->export_for_template(
                    $PAGE->get_renderer('mod_kahoodle')
                );

            case 'reveal_rank':
                // During revision stage - reveal rank3, rank2, rank1, all.
                $data = clean_param($payload['data'] ?? '', PARAM_ALPHANUMEXT);
                \mod_kahoodle\local\game\realtime_channels::notify_participants_rank_revealed($round, $data);
                return [];
        }
    }

    if (in_array($action, ['get_participant_state', 'answer', 'get_avatar_candidates', 'change_avatar'])) {
        // Participant-specific actions.

        $participant = $round->is_participant();
        if (!$participant) {
            return ['error' => 'Participant not found'];
        }

        switch ($action) {
            case 'get_participant_state':
                // Get current stage data for participant view.
                return (new \mod_kahoodle\output\participant($participant))->export_for_template(
                    $PAGE->get_renderer('mod_kahoodle')
                );

            case 'answer':
                // Record participant's answer (validation and notification handled inside).
                $response = (string)($payload['response'] ?? '');
                $currentstage = clean_param($payload['currentstage'] ?? '', PARAM_ALPHANUMEXT);
                \mod_kahoodle\local\game\responses::record_answer($participant, $response, $currentstage);
                return [];

            case 'get_avatar_candidates':
                // Return candidate avatars for the participant to choose from.
                $onlynew = !empty($payload['onlynew']);
                return \mod_kahoodle\local\game\participants::get_avatar_candidates($participant, $onlynew);

            case 'change_avatar':
                // Change the participant's avatar to one of the stored candidates.
                $filename = clean_param($payload['filename'] ?? '', PARAM_FILE);
                if (empty($filename)) {
                    return ['error' => 'Missing filename'];
                }
                $avatarurl = \mod_kahoodle\local\game\participants::change_avatar($participant, $filename);
                // Notify facilitators so the lobby display refreshes with the new avatar.
                \mod_kahoodle\local\game\realtime_channels::notify_facilitators_stage_changed($round);
                return ['avatarurl' => $avatarurl];
        }
    }

    return ['error' => 'Invalid action'];
}
