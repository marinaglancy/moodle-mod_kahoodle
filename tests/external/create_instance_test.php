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

namespace mod_kahoodle\external;

/**
 * Tests for create_instance web service
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\external\create_instance
 */
final class create_instance_test extends \advanced_testcase {
    /**
     * Test creating instance with minimal parameters
     *
     * @return void
     */
    public function test_create_instance_minimal(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $result = create_instance::execute([
            'courseid' => $course->id,
            'section' => 0,
            'name' => 'Test Kahoodle',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('coursemoduleid', $result);
        $this->assertArrayHasKey('instanceid', $result);
        $this->assertGreaterThan(0, $result['coursemoduleid']);
        $this->assertGreaterThan(0, $result['instanceid']);

        // Verify the instance was created in database.
        $instance = $DB->get_record('kahoodle', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertEquals('Test Kahoodle', $instance->name);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_IDENTITY_MODE, $instance->identitymode);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_ALLOW_REPEAT, $instance->allowrepeat);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_LOBBY_DURATION, $instance->lobbyduration);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_QUESTION_PREVIEW_DURATION, $instance->questionpreviewduration);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_QUESTION_DURATION, $instance->questionduration);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_QUESTION_RESULTS_DURATION, $instance->questionresultsduration);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_MAX_POINTS, $instance->maxpoints);
        $this->assertEquals(\mod_kahoodle\constants::DEFAULT_MIN_POINTS, $instance->minpoints);

        // Verify course module was created.
        $cm = $DB->get_record('course_modules', ['id' => $result['coursemoduleid']], '*', MUST_EXIST);
        $this->assertEquals($result['instanceid'], $cm->instance);
        $this->assertEquals($course->id, $cm->course);
    }

    /**
     * Test creating instance with all parameters
     *
     * @return void
     */
    public function test_create_instance_full(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $result = create_instance::execute([
            'courseid' => $course->id,
            'section' => 0,
            'name' => 'Custom Kahoodle',
            'intro' => 'This is a test description',
            'introformat' => FORMAT_HTML,
            'introdraftitemid' => 0,
            'visible' => 1,
            'idnumber' => 'kahoodle-001',
            'lang' => 'en',
            'tags' => ['quiz', 'game'],
            'identitymode' => 2,
            'allowrepeat' => 1,
            'lobbyduration' => 120,
            'questionpreviewduration' => 8,
            'questionduration' => 45,
            'questionresultsduration' => 15,
            'maxpoints' => 2000,
            'minpoints' => 750,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('coursemoduleid', $result);
        $this->assertArrayHasKey('instanceid', $result);

        // Verify custom values were saved.
        $instance = $DB->get_record('kahoodle', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertEquals('Custom Kahoodle', $instance->name);
        $this->assertEquals('This is a test description', $instance->intro);
        $this->assertEquals(FORMAT_HTML, $instance->introformat);
        $this->assertEquals(2, $instance->identitymode);
        $this->assertEquals(1, $instance->allowrepeat);
        $this->assertEquals(120, $instance->lobbyduration);
        $this->assertEquals(8, $instance->questionpreviewduration);
        $this->assertEquals(45, $instance->questionduration);
        $this->assertEquals(15, $instance->questionresultsduration);
        $this->assertEquals(2000, $instance->maxpoints);
        $this->assertEquals(750, $instance->minpoints);

        // Verify course module properties.
        $cm = $DB->get_record('course_modules', ['id' => $result['coursemoduleid']], '*', MUST_EXIST);
        $this->assertEquals('kahoodle-001', $cm->idnumber);

        // Verify tags were saved.
        $tags = \core_tag_tag::get_item_tags_array('core', 'course_modules', $result['coursemoduleid']);
        $this->assertContains('quiz', $tags);
        $this->assertContains('game', $tags);
    }

    /**
     * Test that capability check works
     *
     * @return void
     */
    public function test_create_instance_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        create_instance::execute([
            'courseid' => $course->id,
            'section' => 0,
            'name' => 'Test Kahoodle',
        ]);
    }

    /**
     * Test that invalid course ID is handled
     *
     * @return void
     */
    public function test_create_instance_invalid_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        create_instance::execute([
            'courseid' => 99999,
            'section' => 0,
            'name' => 'Test Kahoodle',
        ]);
    }
}
