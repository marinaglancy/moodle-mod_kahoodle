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

// Course module id.
$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('kahoodle', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$moduleinstance = $DB->get_record('kahoodle', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/kahoodle:manage_questions', $context);

$PAGE->set_url('/mod/kahoodle/questions.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('questions', 'mod_kahoodle'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->activityheader->disable();

$round = \mod_kahoodle\questions::get_last_round($moduleinstance->id);

// Build question types for JavaScript.
$questiontypes = \mod_kahoodle\questions::get_question_types();
$questiontypesjs = [];
foreach ($questiontypes as $questiontype) {
    $questiontypesjs[] = [
        'type' => $questiontype->get_type(),
        'name' => $questiontype->get_display_name(),
    ];
}

$PAGE->requires->js_call_amd('mod_kahoodle/questions', 'init', [$round->get_id(), $questiontypesjs]);

echo $OUTPUT->header();

echo html_writer::start_div('', ['data-region' => 'mod_kahoodle-questions']);

// Add question dropdown button.
$dropdownitems = [];
foreach ($questiontypes as $questiontype) {
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
    ]
);
echo html_writer::tag('div', implode('', $dropdownitems), ['class' => 'dropdown-menu']);
echo html_writer::end_div();

$report = \core_reportbuilder\system_report_factory::create(
    \mod_kahoodle\reportbuilder\local\systemreports\questions::class,
    $context,
    'mod_kahoodle',
    '',
    0,
    ['roundid' => $round->get_id()]
);

echo $report->output();

echo html_writer::end_div();

echo $OUTPUT->footer();
