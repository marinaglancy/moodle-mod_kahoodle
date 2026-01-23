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
 * View Kahoodle instance
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$k = optional_param('k', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('kahoodle', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('kahoodle', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('kahoodle', ['id' => $k], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('kahoodle', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$round = \mod_kahoodle\questions::get_last_round($moduleinstance->id);

// Handle actions.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'start') {
    require_sesskey();
    require_capability('mod/kahoodle:control', $context);

    // Start the game.
    \mod_kahoodle\local\game\progress::start_game($round);

    // Redirect to remove action from URL (prevents re-triggering on refresh).
    redirect(new moodle_url('/mod/kahoodle/view.php', ['id' => $cm->id]));
}

if ($action === 'finish') {
    require_sesskey();
    require_capability('mod/kahoodle:control', $context);

    // Finish the game (archive the round).
    \mod_kahoodle\local\game\progress::finish_game($round);

    // Redirect to remove action from URL.
    redirect(new moodle_url('/mod/kahoodle/view.php', ['id' => $cm->id]));
}

\mod_kahoodle\event\course_module_viewed::create_from_record($moduleinstance, $cm, $course)->trigger();

$PAGE->set_url('/mod/kahoodle/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));

// Only load realtime and JS when user can control and game is in progress.
if (has_capability('mod/kahoodle:control', $context) && $round->is_in_progress()) {
    $channel = new \tool_realtime\channel($context, 'mod_kahoodle', 'game', $round->get_id());
    $channel->subscribe();

    // Initialize the game controller JS module.
    $PAGE->requires->js_call_amd('mod_kahoodle/gamecontroller', 'init', [
        $round->get_id(),
        $context->id,
    ]);
}

$landing = new \mod_kahoodle\output\landing($round);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('mod_kahoodle/landing', $landing->export_for_template($OUTPUT));

echo $OUTPUT->footer();
