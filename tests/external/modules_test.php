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

namespace mod_kahoodle\external;

use core_external\external_api;
use mod_kahoodle\constants;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_kahoodle\external\create_modules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_kahoodle\external\pre_update::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_kahoodle\external\update_module::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_kahoodle\external\helper::class)]
/**
 * Tests for the web service functions that create, read and change Kahoodle activities
 *
 * @covers     \mod_kahoodle\external\create_modules
 * @covers     \mod_kahoodle\external\pre_update
 * @covers     \mod_kahoodle\external\update_module
 * @covers     \mod_kahoodle\external\helper
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class modules_test extends \advanced_testcase {
    /**
     * Call a function the way a web service client does, through validation of parameters and results
     *
     * @param string $name Function name
     * @param array $args
     * @return mixed The cleaned result
     */
    private static function call(string $name, array $args): mixed {
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function($name, $args);
        if ($response['error']) {
            throw new \moodle_exception('error', 'error', '', null, json_encode($response['exception']));
        }
        return $response['data'];
    }

    /**
     * Several activities are created in one call; one that fails is reported without stopping the others
     */
    public function test_create_modules(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $result = self::call('mod_kahoodle_create_modules', [
            'courseid' => $course->id,
            'modules' => [
                ['name' => 'Quiz one', 'section' => 1, 'questionformat' => constants::QUESTIONFORMAT_RICHTEXT,
                    'tags' => ['games'], 'visible' => 0],
                ['name' => 'Quiz two', 'visible' => -1],
                ['name' => 'Quiz three', 'identitymode' => constants::IDENTITYMODE_ANONYMOUS, 'maxpoints' => 2000],
            ],
        ]);

        $this->assertSame([0, 2], array_column($result['modules'], 'index'));
        $this->assertCount(1, $result['warnings']);
        $this->assertSame(1, $result['warnings'][0]['itemid']);

        $first = $DB->get_record('kahoodle', ['id' => $result['modules'][0]['kahoodleid']]);
        $this->assertSame('Quiz one', $first->name);
        $this->assertEquals(constants::QUESTIONFORMAT_RICHTEXT, $first->questionformat);
        $cm = get_coursemodule_from_id('kahoodle', $result['modules'][0]['cmid']);
        $this->assertEquals(0, $cm->visible);
        $this->assertSame(['games'], array_values(\core_tag_tag::get_item_tags_array('core', 'course_modules', $cm->id)));

        $third = $DB->get_record('kahoodle', ['id' => $result['modules'][1]['kahoodleid']]);
        $this->assertEquals(constants::IDENTITYMODE_ANONYMOUS, $third->identitymode);
        $this->assertEquals(2000, $third->maxpoints);
        $this->assertEquals(constants::DEFAULT_MIN_POINTS, $third->minpoints);
    }

    /**
     * Available but not shown on the course page is stored the way the module form stores it
     */
    public function test_create_modules_stealth(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $result = self::call('mod_kahoodle_create_modules', [
            'courseid' => $course->id, 'modules' => [['name' => 'Hidden quiz', 'visible' => -1]]]);
        $this->assertCount(1, $result['warnings']);

        $CFG->allowstealth = 1;
        $result = self::call('mod_kahoodle_create_modules', [
            'courseid' => $course->id, 'modules' => [['name' => 'Hidden quiz', 'visible' => -1]]]);
        $cm = get_coursemodule_from_id('kahoodle', $result['modules'][0]['cmid']);
        $this->assertEquals(1, $cm->visible);
        $this->assertEquals(0, $cm->visibleoncoursepage);
    }

    /**
     * A student cannot create activities
     */
    public function test_create_modules_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = self::call('mod_kahoodle_create_modules', ['courseid' => $course->id, 'modules' => [['name' => 'X']]]);
        $this->assertSame([], $result['modules']);
        $this->assertSame('nopermissions', $result['warnings'][0]['warningcode']);
    }

    /**
     * pre_update returns the stored settings under the names update_module takes, and update_module
     * changes only what it is given
     */
    public function test_pre_update_and_update_module(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', [
            'course' => $course->id, 'name' => 'Before', 'intro' => '<p>Intro</p>', 'questionduration' => 25]);

        $before = self::call('mod_kahoodle_pre_update', ['cmid' => $kahoodle->cmid]);
        $this->assertSame('Before', $before['name']);
        $this->assertSame('<p>Intro</p>', $before['intro']);
        $this->assertSame(25, $before['questionduration']);
        $this->assertSame(1, $before['visible']);
        $this->assertTrue($before['lastroundeditable']);
        $this->assertSame(0, $before['questioncount']);

        self::call('mod_kahoodle_update_module', [
            'cmid' => $kahoodle->cmid, 'name' => 'After', 'maxpoints' => 3000, 'tags' => ['quiz']]);

        $after = self::call('mod_kahoodle_pre_update', ['cmid' => $kahoodle->cmid]);
        $this->assertSame('After', $after['name']);
        $this->assertSame(3000, $after['maxpoints']);
        $this->assertSame(['quiz'], $after['tags']);
        $unchanged = array_diff_key($before, ['name' => 1, 'maxpoints' => 1, 'tags' => 1]);
        $this->assertSame($unchanged, array_intersect_key($after, $unchanged));
    }

    /**
     * A new description keeps the files it embeds unless a draft area replaces them
     */
    public function test_update_module_keeps_intro_files(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftitemid, 'filepath' => '/', 'filename' => 'pic.png'], 'png');
        $result = self::call('mod_kahoodle_create_modules', ['courseid' => $course->id, 'modules' => [[
            'name' => 'With picture', 'intro' => '<img src="@@PLUGINFILE@@/pic.png">', 'introdraftitemid' => $draftitemid]]]);
        $cmid = $result['modules'][0]['cmid'];
        $context = \context_module::instance($cmid);
        $this->assertTrue(get_file_storage()->file_exists($context->id, 'mod_kahoodle', 'intro', 0, '/', 'pic.png'));

        self::call('mod_kahoodle_update_module', ['cmid' => $cmid, 'intro' => '<p>New</p><img src="@@PLUGINFILE@@/pic.png">']);

        $this->assertSame(
            '<p>New</p><img src="@@PLUGINFILE@@/pic.png">',
            self::call('mod_kahoodle_pre_update', ['cmid' => $cmid])['intro']
        );
        $this->assertTrue(get_file_storage()->file_exists($context->id, 'mod_kahoodle', 'intro', 0, '/', 'pic.png'));
    }

    /**
     * A student can neither read nor change the settings
     */
    public function test_update_module_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $this->expectException(\moodle_exception::class);
        self::call('mod_kahoodle_update_module', ['cmid' => $kahoodle->cmid, 'name' => 'Mine']);
    }
}
