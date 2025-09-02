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

namespace mod_kahoodle;

/**
 * Tests for Kahoodle
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {

    /**
     * Test create and delete module
     *
     * @covers ::kahoodle_add_instance
     * @covers ::kahoodle_delete_instance
     * @return void
     */
    public function test_create_delete_module(): void {
        global $DB;
        $this->resetAfterTest();

        // Disable recycle bin so we are testing module deletion and not backup.
        set_config('coursebinenable', 0, 'tool_recyclebin');

        // Create an instance of a module.
        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $mod->id);

        // Assert it was created.
        $this->assertNotEmpty(\context_module::instance($mod->cmid));
        $this->assertEquals($mod->id, $cm->instance);
        $this->assertEquals('kahoodle', $cm->modname);
        $this->assertEquals(1, $DB->count_records('kahoodle', ['id' => $mod->id]));
        $this->assertEquals(1, $DB->count_records('course_modules', ['id' => $cm->id]));

        // Delete module.
        course_delete_module($cm->id);
        $this->assertEquals(0, $DB->count_records('kahoodle', ['id' => $mod->id]));
        $this->assertEquals(0, $DB->count_records('course_modules', ['id' => $cm->id]));
    }

    /**
     * Test module backup and restore by duplicating it
     *
     * @covers \backup_kahoodle_activity_structure_step
     * @covers \restore_kahoodle_activity_structure_step
     * @return void
     */
    public function test_backup_restore(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Createa a module.
        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module('kahoodle',
            ['course' => $course->id, 'name' => 'My test module']);
        $cm = get_coursemodule_from_instance('kahoodle', $mod->id);

        // Call duplicate_module - it will backup and restore this module.
        $cmnew = duplicate_module($course, $cm);

        $this->assertNotNull($cmnew);
        $this->assertGreaterThan($cm->id, $cmnew->id);
        $this->assertGreaterThan($mod->id, $cmnew->instance);
        $this->assertEquals('kahoodle', $cmnew->modname);

        $name = $DB->get_field('kahoodle', 'name', ['id' => $cmnew->instance]);
        $this->assertEquals('My test module (copy)', $name);
        // TODO: check other fields and related tables.
    }
}
