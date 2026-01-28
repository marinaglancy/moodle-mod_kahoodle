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
 * Manage questions for Kahoodle instance
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$roundid = optional_param('roundid', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHANUMEXT);

$PAGE->set_url('/mod/kahoodle/questions.php', array_filter(['id' => $id, 'roundid' => $roundid]));

if ($roundid) {
    $round = \mod_kahoodle\local\entities\round::create_from_id($roundid);
    $cm = $round->get_cm();
} else if ($id) {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'kahoodle');
}
require_login(isset($course) ? $course : $cm->course, true, $cm);
require_capability('mod/kahoodle:manage_questions', $PAGE->context);

$PAGE->set_title(get_string('questions', 'mod_kahoodle'));
$PAGE->set_heading(format_string($PAGE->course->fullname));
$PAGE->activityheader->disable();

// Get all rounds for building navigation tabs.
$allrounds = \mod_kahoodle\api::get_all_rounds($cm->instance);

if (!isset($round)) {
    // Default to the first round (which is the editable one or most recent).
    $round = reset($allrounds);
}

// Check if this is the last round (for showing "Prepare new round" button).
$lastround = reset($allrounds);
$islastround = ($round->get_id() === $lastround->get_id());

// Build question types for JavaScript.
$questiontypesjs = [];
foreach (\mod_kahoodle\questions::get_question_types() as $questiontype) {
    $questiontypesjs[] = [
        'type' => $questiontype->get_type(),
        'name' => $questiontype->get_display_name(),
    ];
}

$PAGE->requires->js_call_amd('mod_kahoodle/questions', 'init', [$round->get_id(), $questiontypesjs]);

echo $OUTPUT->header();

echo html_writer::start_div('', ['data-region' => 'mod_kahoodle-questions']);

// Build round navigation selector.
$roundoptions = [];
foreach ($allrounds as $r) {
    $url = (new moodle_url('/mod/kahoodle/questions.php', ['roundid' => $r->get_id()]))->out(false);
    $name = $r->get_display_name();
    if (!$r->is_editable()) {
        $name .= ' (' . userdate($r->get_timestarted(), get_string('strftimedatetimeshort', 'langconfig')) . ')';
    }
    $roundoptions[$url] = $name;
}
$currenturl = (new moodle_url('/mod/kahoodle/questions.php', ['roundid' => $round->get_id()]))->out(false);
$selectmenu = new \core\output\select_menu('roundselector', $roundoptions, $currenturl);
$selectmenu->set_label(get_string('selectround', 'mod_kahoodle'), ['class' => 'sr-only']);
echo html_writer::div(
    $OUTPUT->render_from_template('core/tertiary_navigation_selector', $selectmenu->export_for_template($OUTPUT)),
    'tertiary-navigation mb-3'
);

// Display warning if round is not editable.
if (!$round->is_editable()) {
    echo $OUTPUT->notification(
        get_string('questions_roundnoteditable', 'mod_kahoodle'),
        \core\output\notification::NOTIFY_WARNING
    );

    // Show "Prepare new round" button if this is the last round, it's archived, and user can facilitate.
    if (
        $islastround &&
        $round->get_current_stage_name() === \mod_kahoodle\constants::STAGE_ARCHIVED &&
        has_capability('mod/kahoodle:facilitate', $PAGE->context)
    ) {
        $newroundurl = new moodle_url('/mod/kahoodle/view.php', [
            'id' => $cm->id,
            'action' => 'newround',
            'sesskey' => sesskey(),
        ]);
        echo html_writer::div(
            html_writer::link($newroundurl, get_string('preparenewround', 'mod_kahoodle'), ['class' => 'btn btn-primary']),
            'mb-3'
        );
    }
}

// Add question dropdown button.
$dropdownitems = [];
foreach (\mod_kahoodle\questions::get_question_types() as $questiontype) {
    $dropdownitems[] = html_writer::link(
        '#',
        $questiontype->get_display_name(),
        [
            'class' => 'dropdown-item',
            'data-action' => 'mod_kahoodle-add-question',
            'data-questiontype' => $questiontype->get_type(),
        ]
    );
}

echo html_writer::start_div('dropdown mb-3');
echo html_writer::tag(
    'button',
    get_string('addquestion', 'mod_kahoodle') . ' <span class="caret"></span>',
    [
        'class' => 'btn btn-primary dropdown-toggle',
        'type' => 'button',
        'data-toggle' => 'dropdown',
        'data-bs-toggle' => 'dropdown',
        'aria-haspopup' => 'true',
        'aria-expanded' => 'false',
        'disabled' => !$round->is_editable() ? 'disabled' : null,
    ]
);
echo html_writer::tag('div', implode('', $dropdownitems), ['class' => 'dropdown-menu']);
echo html_writer::end_div();

$report = \core_reportbuilder\system_report_factory::create(
    \mod_kahoodle\reportbuilder\local\systemreports\questions::class,
    $PAGE->context,
    'mod_kahoodle',
    '',
    0,
    ['roundid' => $round->get_id()]
);
$report->set_default_per_page(5000);

echo $report->output();

echo html_writer::end_div();

echo $OUTPUT->footer();
