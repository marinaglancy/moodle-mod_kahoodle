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

namespace mod_kahoodle\form;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\game\questions;

/**
 * Tests for the join form
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kahoodle\form\join
 */
final class join_test extends \advanced_testcase {
    /**
     * Create a kahoodle activity with the given identity mode and log in as a student
     *
     * @param int $identitymode
     * @return round
     */
    protected function create_round(int $identitymode): round {
        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', [
            'course' => $course->id,
            'identitymode' => $identitymode,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        return questions::get_last_round($kahoodle->id);
    }

    /**
     * Submit the join form with the given data
     *
     * @param round $round
     * @param array $data
     * @return join
     */
    protected function submit_form(round $round, array $data): join {
        join::mock_submit(['id' => $round->get_cm()->id, 'action' => 'join'] + $data);
        return new join(new \moodle_url('/mod/kahoodle/view.php'), ['round' => $round]);
    }

    /**
     * Data provider for test_validation
     *
     * @return array
     */
    public static function validation_provider(): array {
        return [
            'alias, empty' => [constants::IDENTITYMODE_ALIAS, [], null],
            'alias, spaces only' => [constants::IDENTITYMODE_ALIAS, ['displayname' => '   '], null],
            'alias, too long' => [constants::IDENTITYMODE_ALIAS, ['displayname' => str_repeat('a', 21)], null],
            'alias, valid' => [constants::IDENTITYMODE_ALIAS, ['displayname' => ' Nick '], 'Nick'],
            'alias, 7 CJK characters' => [constants::IDENTITYMODE_ALIAS, ['displayname' => '東京タワー好き'], '東京タワー好き'],
            'alias, 20 CJK characters' => [
                constants::IDENTITYMODE_ALIAS, ['displayname' => str_repeat('漢', 20)], str_repeat('漢', 20),
            ],
            'alias, 21 CJK characters' => [constants::IDENTITYMODE_ALIAS, ['displayname' => str_repeat('漢', 21)], null],
            'anonymous, spaces only' => [constants::IDENTITYMODE_ANONYMOUS, ['displayname' => '   '], null],
            'anonymous, 7 CJK characters' => [
                constants::IDENTITYMODE_ANONYMOUS, ['displayname' => '東京タワー好き'], '東京タワー好き',
            ],
            'optional, alias with spaces only' => [
                constants::IDENTITYMODE_OPTIONAL, ['identitychoice' => 'alias', 'displayname' => '   '], null,
            ],
            'optional, alias with 7 CJK characters' => [
                constants::IDENTITYMODE_OPTIONAL, ['identitychoice' => 'alias', 'displayname' => '東京タワー好き'], '東京タワー好き',
            ],
        ];
    }

    /**
     * Test server-side validation of the nickname
     *
     * @dataProvider validation_provider
     * @param int $identitymode
     * @param array $data submitted data
     * @param string|null $expected expected display name, or null if the form must not validate
     */
    public function test_validation(int $identitymode, array $data, ?string $expected): void {
        $this->resetAfterTest();
        $round = $this->create_round($identitymode);

        $form = $this->submit_form($round, $data);
        $formdata = $form->get_data();

        if ($expected === null) {
            $this->assertNull($formdata);
        } else {
            $this->assertNotNull($formdata);
            $this->assertEquals($expected, trim($formdata->displayname));
        }
    }
}
