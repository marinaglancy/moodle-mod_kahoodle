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

/**
 * Tests for the participant answers system report
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\reportbuilder\local\systemreports\participant_answers
 * @covers     \mod_kahoodle\reportbuilder\local\entities\response
 */
final class participant_answers_test extends \advanced_testcase {
    use helper;

    /**
     * Test the participant answers system report renders and returns content.
     */
    public function test_participant_answers_report(): void {
        $this->resetAfterTest();
        $data = $this->create_dataset();
        $this->setUser($data['teacher']);
        $this->setup_page($data['cm']);

        $context = \context_module::instance($data['cm']->id);

        $report = \core_reportbuilder\system_report_factory::create(
            participant_answers::class,
            $context,
            'mod_kahoodle',
            '',
            0,
            ['participantid' => $data['p1']]
        );
        $content = $report->output();
        $this->assertNotEmpty($content);
    }
}
