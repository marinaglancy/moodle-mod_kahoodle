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
 * Tests for the statistics system report
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\reportbuilder\local\systemreports\statistics
 */
final class statistics_test extends \advanced_testcase {
    use helper;

    /**
     * Test the statistics system report renders and returns content.
     */
    public function test_statistics_report(): void {
        $this->resetAfterTest();
        $data = $this->create_dataset();
        $this->setUser($data['teacher']);
        $this->setup_page($data['cm']);

        $context = \context_module::instance($data['cm']->id);

        $report = \core_reportbuilder\system_report_factory::create(
            statistics::class,
            $context,
            'mod_kahoodle',
            '',
            0,
            ['roundid' => $data['round']->get_id()]
        );
        $content = $report->output();
        $this->assertNotEmpty($content);
    }
}
