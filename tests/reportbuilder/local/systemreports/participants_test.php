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

namespace mod_kahoodle\reportbuilder\local\systemreports;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/helper.php');

use mod_kahoodle\constants;

/**
 * Tests for the participants system report
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\reportbuilder\local\systemreports\participants
 * @covers     \mod_kahoodle\reportbuilder\local\entities\participant
 */
final class participants_test extends \advanced_testcase {
    use helper;

    /**
     * Test the participants system report renders and returns content.
     */
    public function test_participants_report(): void {
        $this->resetAfterTest();
        $data = $this->create_dataset();
        $this->setUser($data['teacher']);
        $this->setup_page($data['cm']);

        $context = \context_module::instance($data['cm']->id);

        $report = \core_reportbuilder\system_report_factory::create(
            participants::class,
            $context,
            'mod_kahoodle',
            '',
            0,
            ['roundid' => $data['round']->get_id()]
        );
        $content = $report->output();
        $this->assertNotEmpty($content);
    }

    /**
     * Test the participants report renders correctly in anonymous mode.
     */
    public function test_participants_report_anonymous(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Create kahoodle in anonymous mode.
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', [
            'course' => $course->id,
            'identitymode' => constants::IDENTITYMODE_ANONYMOUS,
        ]);
        $cm = get_coursemodule_from_instance('kahoodle', $kahoodle->id);

        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');

        $generator->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Anon Q1',
            'questionconfig' => "A\n*B\nC",
        ]);

        $round = \mod_kahoodle\local\game\questions::get_last_round($kahoodle->id);

        // Create anonymous participants directly (userid is null, participantcode is set).
        $p1 = $DB->insert_record('kahoodle_participants', (object) [
            'roundid' => $round->get_id(),
            'userid' => null,
            'participantcode' => 'abc123',
            'displayname' => 'Anonymous1',
            'totalscore' => 300,
            'timecreated' => time(),
        ]);

        // Set round to archived.
        $DB->update_record('kahoodle_rounds', (object) [
            'id' => $round->get_id(),
            'currentstage' => constants::STAGE_ARCHIVED,
            'timestarted' => time() - 3600,
            'timecompleted' => time(),
            'stagestarttime' => time(),
        ]);

        $this->setUser($teacher);
        $this->setup_page($cm);

        $context = \context_module::instance($cm->id);

        $report = \core_reportbuilder\system_report_factory::create(
            participants::class,
            $context,
            'mod_kahoodle',
            '',
            0,
            ['roundid' => $round->get_id()]
        );
        $content = $report->output();
        $this->assertNotEmpty($content);
    }
}
