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
 * Results page for Kahoodle instance
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);
$roundid = optional_param('roundid', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHANUMEXT);

$PAGE->set_url('/mod/kahoodle/results.php', array_filter(['id' => $id, 'roundid' => $roundid, 'view' => $view]));

if ($roundid) {
    $round = \mod_kahoodle\local\entities\round::create_from_id($roundid);
    $cm = $round->get_cm();
} else if ($id) {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'kahoodle');
}
require_login(isset($course) ? $course : $cm->course, true, $cm);
require_capability('mod/kahoodle:viewresults', $PAGE->context);

$PAGE->set_title(get_string('results', 'mod_kahoodle'));
$PAGE->set_heading(format_string($PAGE->course->fullname));
$PAGE->activityheader->disable();

echo $OUTPUT->header();

// If roundid and view are specified, show participants or statistics view instead of the rounds list.
if (!empty($roundid) && $view === 'participants') {
    // Show participants report for this round.

    // Back button.
    $backurl = new moodle_url('/mod/kahoodle/results.php', ['id' => $cm->id]);
    echo html_writer::div(
        html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary mb-3']),
        'mb-3'
    );

    // Round name as heading.
    echo $OUTPUT->heading($round->get_display_name() . ' - ' . get_string('participants', 'mod_kahoodle'), 3);

    $report = \core_reportbuilder\system_report_factory::create(
        \mod_kahoodle\reportbuilder\local\systemreports\participants::class,
        $PAGE->context,
        'mod_kahoodle',
        '',
        0,
        ['roundid' => $round->get_id()]
    );

    echo $report->output();
} else {
    // Show the default rounds list.
    $resultspage = new \mod_kahoodle\output\results($PAGE->activityrecord, $cm);
    echo $OUTPUT->render($resultspage);
}

echo $OUTPUT->footer();
