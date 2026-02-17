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

use mod_kahoodle\local\entities\statistics;

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);
$roundid = optional_param('roundid', 0, PARAM_INT);
$participantid = optional_param('participantid', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHANUMEXT);

$PAGE->set_url('/mod/kahoodle/results.php', array_filter([
    'id' => $id,
    'roundid' => $roundid,
    'participantid' => $participantid,
    'view' => $view,
]));

if ($participantid && $view === 'details') {
    // Get round from participant.
    $statistics = statistics::create_for_participant_id($participantid, $roundid);
    $round = $statistics->get_all_rounds()[$roundid];
} else if ($roundid && ($view === 'participants' || $view === 'statistics')) {
    $statistics = statistics::create_for_round_id($roundid);
    $round = $statistics->get_all_rounds()[$roundid];
} else if ($id) {
    $statistics = statistics::create_from_cm_id($id);
} else if ($view === 'allparticipants' || $view === 'allstatistics') {
    throw new \moodle_exception('missingparam', '', '', 'id');
} else {
    throw new \moodle_exception('missingparam', '', '', 'id/roundid/participantid');
}
$cm = $statistics->get_cm();
require_login($cm->course, true, $cm);
require_capability('mod/kahoodle:viewresults', $PAGE->context);

$PAGE->set_title(get_string('results', 'mod_kahoodle'));
$PAGE->set_heading(format_string($PAGE->course->fullname));
$PAGE->activityheader->disable();

echo $OUTPUT->header();

// Handle different views. Some views are provided by tool_kahoodleplus.
$rendered = component_class_callback(
    '\\tool_kahoodleplus\\main',
    'results_page_hook',
    [$view, $round ?? $statistics, $participantid]
);
// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
if ($rendered) {
    // Rendered by tool_kahoodleplus.
} else if ($view === 'allparticipants') {
    // Show participants report for all rounds.

    // Back button.
    $backurl = new moodle_url('/mod/kahoodle/results.php', ['id' => $cm->id]);
    echo html_writer::div(
        html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary mb-3']),
        'mb-3'
    );

    // Heading.
    echo $OUTPUT->heading(get_string('allroundsparticipants', 'mod_kahoodle'), 3);

    $report = \core_reportbuilder\system_report_factory::create(
        \mod_kahoodle\reportbuilder\local\systemreports\all_rounds_participants::class,
        $PAGE->context,
        'mod_kahoodle',
        '',
        0,
        ['kahoodleid' => $PAGE->activityrecord->id]
    );

    echo $report->output();
} else {
    // Show the default rounds list.
    $resultspage = new \mod_kahoodle\output\results($statistics);
    echo $OUTPUT->render($resultspage);
}

echo $OUTPUT->footer();
