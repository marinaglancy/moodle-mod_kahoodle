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
    $roundid = (int)$DB->get_field('kahoodle_participants', 'roundid', ['id' => $participantid], MUST_EXIST);
    $round = \mod_kahoodle\local\entities\round::create_from_id($roundid);
    $cm = $round->get_cm();
} else if ($roundid && ($view === 'participants' || $view === 'statistics')) {
    $round = \mod_kahoodle\local\entities\round::create_from_id($roundid);
    $cm = $round->get_cm();
} else if ($id) {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'kahoodle');
} else if ($view === 'allparticipants' || $view === 'allstatistics') {
    throw new \moodle_exception('missingparam', '', '', 'id');
} else {
    throw new \moodle_exception('missingparam', '', '', 'id/roundid/participantid');
}
require_login(isset($course) ? $course : $cm->course, true, $cm);
require_capability('mod/kahoodle:viewresults', $PAGE->context);

$PAGE->set_title(get_string('results', 'mod_kahoodle'));
$PAGE->set_heading(format_string($PAGE->course->fullname));
$PAGE->activityheader->disable();

echo $OUTPUT->header();

// Handle different views.
if (!empty($participantid) && $view === 'details') {
    // Show participant answers report.
    /** @var \mod_kahoodle\reportbuilder\local\systemreports\participant_answers $report */
    $report = \core_reportbuilder\system_report_factory::create(
        \mod_kahoodle\reportbuilder\local\systemreports\participant_answers::class,
        $PAGE->context,
        'mod_kahoodle',
        '',
        0,
        ['participantid' => $participantid]
    );

    // Get participant data for header.
    $participant = $report->get_participant();

    // Back button to participants list.
    $backurl = new moodle_url('/mod/kahoodle/results.php', ['roundid' => $round->get_id(), 'view' => 'participants']);
    echo html_writer::div(
        html_writer::link($backurl, get_string('back') . ': ' . $round->get_display_name(), ['class' => 'btn btn-secondary mb-3']),
        'mb-3'
    );

    // Participant info header.
    echo html_writer::start_div('participant-details-header d-flex align-items-center mb-4 p-3 bg-light rounded');

    // Participant avatar and display name.
    $avatarurl = $participant->get_avatar_url();
    echo html_writer::img($avatarurl->out(false), '', ['class' => 'rounded-circle mr-3', 'width' => 64, 'height' => 64]);
    echo html_writer::start_div('participant-info');
    echo html_writer::tag('h4', s($participant->get_display_name()), ['class' => 'mb-1']);

    // User info (if available).
    if ($participant->get_user_id()) {
        $user = $participant->get_user_record();
        $profileurl = new moodle_url('/user/profile.php', ['id' => $user->id]);
        $userpic = $OUTPUT->user_picture($user, ['size' => 24, 'link' => false, 'class' => 'mr-1']);
        echo html_writer::div(
            html_writer::link($profileurl, $userpic . fullname($user)),
            'text-muted small'
        );
    }
    echo html_writer::end_div();

    // Total score.
    echo html_writer::div(
        html_writer::tag('span', get_string('score', 'mod_kahoodle') . ': ', ['class' => 'text-muted']) .
        html_writer::tag('strong', number_format($participant->get_total_score())),
        'ml-auto h5 mb-0'
    );

    echo html_writer::end_div();

    // Heading.
    echo $OUTPUT->heading(get_string('participantanswers', 'mod_kahoodle'), 3);

    echo $report->output();
} else if (!empty($roundid) && $view === 'participants') {
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
} else if (!empty($roundid) && $view === 'statistics') {
    // Show statistics report for this round.

    // Back button.
    $backurl = new moodle_url('/mod/kahoodle/results.php', ['id' => $cm->id]);
    echo html_writer::div(
        html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary mb-3']),
        'mb-3'
    );

    // Round name as heading.
    echo $OUTPUT->heading($round->get_display_name() . ' - ' . get_string('statistics', 'mod_kahoodle'), 3);

    // Show total participants count.
    $participantscount = $round->get_participants_count();
    echo html_writer::div(
        get_string('totalparticipants', 'mod_kahoodle') . ': ' . $participantscount,
        'text-muted mb-3'
    );

    $report = \core_reportbuilder\system_report_factory::create(
        \mod_kahoodle\reportbuilder\local\systemreports\statistics::class,
        $PAGE->context,
        'mod_kahoodle',
        '',
        0,
        ['roundid' => $round->get_id()]
    );

    echo $report->output();
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
} else if ($view === 'allstatistics') {
    // Show statistics report for all rounds.

    // Back button.
    $backurl = new moodle_url('/mod/kahoodle/results.php', ['id' => $cm->id]);
    echo html_writer::div(
        html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary mb-3']),
        'mb-3'
    );

    // Heading.
    echo $OUTPUT->heading(get_string('allroundsstatistics', 'mod_kahoodle'), 3);

    $report = \core_reportbuilder\system_report_factory::create(
        \mod_kahoodle\reportbuilder\local\systemreports\all_rounds_statistics::class,
        $PAGE->context,
        'mod_kahoodle',
        '',
        0,
        ['kahoodleid' => $PAGE->activityrecord->id]
    );

    echo $report->output();
} else {
    // Show the default rounds list.
    $resultspage = new \mod_kahoodle\output\results($PAGE->activityrecord, $cm);
    echo $OUTPUT->render($resultspage);
}

echo $OUTPUT->footer();
