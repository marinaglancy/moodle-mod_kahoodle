<?php
// This file is part of Moodle - http://moodle.org/
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
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = time();

    $id = $DB->insert_record('kahoodle', $moduleinstance);
    $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
    \core_completion\api::update_completion_date_event($moduleinstance->coursemodule,
        'kahoodle', $id, $completiontimeexpected);
    return $id;
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
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    $DB->update_record('kahoodle', $moduleinstance);

    $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
    \core_completion\api::update_completion_date_event($moduleinstance->coursemodule, 'kahoodle',
      $moduleinstance->id, $completiontimeexpected);

    return true;
}

/**
 * Removes an instance of the Kahoodle from the database.
 *
 * @param int $id Id of the module instance
 * @return bool True if successful, false otherwise
 */
function kahoodle_delete_instance($id) {
    global $DB;

    $record = $DB->get_record('kahoodle', ['id' => $id]);
    if (!$record) {
        return false;
    }

    // Delete all calendar events.
    $events = $DB->get_records('event', ['modulename' => 'kahoodle', 'instance' => $record->id]);
    foreach ($events as $event) {
        calendar_event::load($event)->delete();
    }

    // Delete the instance.
    $DB->delete_records('kahoodle', ['id' => $id]);

    return true;
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
