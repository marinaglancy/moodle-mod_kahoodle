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

        // Verify default values for plugin-specific fields.
        $record = $DB->get_record('kahoodle', ['id' => $mod->id], '*', MUST_EXIST);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_ALLOW_REPEAT, $record->allowrepeat);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_LOBBY_DURATION, $record->lobbyduration);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_QUESTION_PREVIEW_DURATION, $record->questionpreviewduration);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_QUESTION_DURATION, $record->questionduration);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_QUESTION_RESULTS_DURATION, $record->questionresultsduration);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_MAX_POINTS, $record->defaultmaxpoints);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_MIN_POINTS, $record->defaultminpoints);

        // Delete module.
        course_delete_module($cm->id);
        $this->assertEquals(0, $DB->count_records('kahoodle', ['id' => $mod->id]));
        $this->assertEquals(0, $DB->count_records('course_modules', ['id' => $cm->id]));
    }

    /**
     * Test creating module with custom field values
     *
     * @covers ::kahoodle_add_instance
     * @return void
     */
    public function test_create_with_custom_values(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module('kahoodle', [
            'course' => $course->id,
            'allowrepeat' => 1,
            'lobbyduration' => 120,
            'questionpreviewduration' => 10,
            'questionduration' => 45,
            'questionresultsduration' => 15,
            'defaultmaxpoints' => 2000,
            'defaultminpoints' => 750,
        ]);

        // Verify custom values were saved.
        $record = $DB->get_record('kahoodle', ['id' => $mod->id], '*', MUST_EXIST);
        $this->assertEquals(1, $record->allowrepeat);
        $this->assertEquals(120, $record->lobbyduration);
        $this->assertEquals(10, $record->questionpreviewduration);
        $this->assertEquals(45, $record->questionduration);
        $this->assertEquals(15, $record->questionresultsduration);
        $this->assertEquals(2000, $record->defaultmaxpoints);
        $this->assertEquals(750, $record->defaultminpoints);
    }

    /**
     * Test updating module instance
     *
     * @covers ::kahoodle_update_instance
     * @return void
     */
    public function test_update_module(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('kahoodle', $mod->id);

        // Prepare data for update.
        $moduleinfo = new \stdClass();
        $moduleinfo->coursemodule = $cm->id;
        $moduleinfo->course = $course->id;
        $moduleinfo->modulename = 'kahoodle';
        $moduleinfo->instance = $mod->id;
        $moduleinfo->name = $mod->name;
        $moduleinfo->introeditor = [
            'text' => $mod->intro,
            'format' => $mod->introformat,
            'itemid' => 0,
        ];
        $moduleinfo->visible = $cm->visible;
        $moduleinfo->section = $cm->section;
        $moduleinfo->allowrepeat = 1;
        $moduleinfo->lobbyduration = 180;
        $moduleinfo->defaultmaxpoints = 1500;

        // Update using the proper Moodle API.
        update_moduleinfo($cm, $moduleinfo, $course);

        // Verify the updates.
        $record = $DB->get_record('kahoodle', ['id' => $mod->id], '*', MUST_EXIST);
        $this->assertEquals(1, $record->allowrepeat);
        $this->assertEquals(180, $record->lobbyduration);
        $this->assertEquals(1500, $record->defaultmaxpoints);
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

        // Create a module with custom values.
        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module(
            'kahoodle',
            [
                'course' => $course->id,
                'name' => 'My test module',
                'allowrepeat' => 1,
                'lobbyduration' => 90,
                'questionpreviewduration' => 8,
                'questionduration' => 40,
                'questionresultsduration' => 12,
                'defaultmaxpoints' => 1500,
                'defaultminpoints' => 600,
            ]
        );
        $cm = get_coursemodule_from_instance('kahoodle', $mod->id);

        // Call duplicate_module - it will backup and restore this module.
        $cmnew = duplicate_module($course, $cm);

        $this->assertNotNull($cmnew);
        $this->assertGreaterThan($cm->id, $cmnew->id);
        $this->assertGreaterThan($mod->id, $cmnew->instance);
        $this->assertEquals('kahoodle', $cmnew->modname);

        // Verify all fields were backed up and restored correctly.
        $newrecord = $DB->get_record('kahoodle', ['id' => $cmnew->instance], '*', MUST_EXIST);
        $this->assertEquals('My test module (copy)', $newrecord->name);
        $this->assertEquals(1, $newrecord->allowrepeat);
        $this->assertEquals(90, $newrecord->lobbyduration);
        $this->assertEquals(8, $newrecord->questionpreviewduration);
        $this->assertEquals(40, $newrecord->questionduration);
        $this->assertEquals(12, $newrecord->questionresultsduration);
        $this->assertEquals(1500, $newrecord->defaultmaxpoints);
        $this->assertEquals(600, $newrecord->defaultminpoints);
    }
}
