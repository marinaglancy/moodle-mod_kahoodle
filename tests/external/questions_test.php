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

/**
 * Tests for the web service functions that read and change Kahoodle questions
 *
 * @covers     \mod_kahoodle\external\create_questions
 * @covers     \mod_kahoodle\external\update_question
 * @covers     \mod_kahoodle\external\delete_questions
 * @covers     \mod_kahoodle\external\get_questions
 * @covers     \mod_kahoodle\external\question_pre_update
 * @covers     \mod_kahoodle\external\helper
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class questions_test extends \advanced_testcase {
    /** @var \stdClass The activity, with cmid */
    private \stdClass $kahoodle;

    /**
     * Create an activity and log in as its teacher
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
    }

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
            throw new \moodle_exception('error', 'error', '', null, $response['exception']->errorcode);
        }
        return $response['data'];
    }

    /**
     * Add three questions and return their ids in order
     *
     * @return int[]
     */
    private function add_three(): array {
        $result = self::call('mod_kahoodle_create_questions', ['cmid' => $this->kahoodle->cmid, 'questions' => [
            ['questiontext' => 'One?', 'questionconfig' => "*a\nb"],
            ['questiontext' => 'Two?', 'questionconfig' => "a\n*b"],
            ['questiontext' => 'Three?', 'questionconfig' => "*a\nb\nc", 'questionduration' => 30],
        ]]);
        return array_column($result['questions'], 'questionid');
    }

    /**
     * Mark the last round as played and archived
     */
    private function finish_round(): void {
        global $DB;
        $round = \mod_kahoodle\local\game\questions::get_last_round($this->kahoodle->id);
        $DB->update_record('kahoodle_rounds', (object)['id' => $round->get_id(),
            'currentstage' => constants::STAGE_ARCHIVED, 'timestarted' => time() - 600, 'timecompleted' => time() - 60]);
    }

    /**
     * Questions are added in order, one that fails is reported, and get_questions lists them
     */
    public function test_create_and_get_questions(): void {
        $result = self::call('mod_kahoodle_create_questions', ['cmid' => $this->kahoodle->cmid, 'questions' => [
            ['questiontext' => 'Capital of Italy?', 'questionconfig' => "Paris\n*Rome"],
            ['questiontext' => 'No answers?'],
            ['questiontext' => 'Two plus two?', 'questionconfig' => "3\n*4\n5", 'maxpoints' => 1500],
        ]]);
        $this->assertSame([0, 2], array_column($result['questions'], 'index'));
        $this->assertSame('multichoiceneedtwooptions', $result['warnings'][0]['warningcode']);

        $list = self::call('mod_kahoodle_get_questions', ['cmid' => $this->kahoodle->cmid]);
        $this->assertTrue($list['roundeditable']);
        $this->assertSame(['Capital of Italy?', 'Two plus two?'], array_column($list['questions'], 'questiontext'));
        $this->assertSame([1, 2], array_column($list['questions'], 'position'));
        $this->assertArrayNotHasKey('questionconfig', $list['questions'][0]);

        $details = self::call('mod_kahoodle_get_questions', ['cmid' => $this->kahoodle->cmid, 'includedetails' => true]);
        $this->assertSame("3\n*4\n5", $details['questions'][1]['questionconfig']);
        $this->assertSame(1500, $details['questions'][1]['maxpoints']);
        $this->assertSame(constants::DEFAULT_MAX_POINTS, $details['questions'][0]['maxpoints']);
        $this->assertFalse($details['questions'][0]['hasresponses']);
    }

    /**
     * New questions can be inserted at a position
     */
    public function test_create_questions_at_position(): void {
        $this->add_three();
        self::call('mod_kahoodle_create_questions', ['cmid' => $this->kahoodle->cmid, 'position' => 2, 'questions' => [
            ['questiontext' => 'New A?', 'questionconfig' => "*a\nb"],
            ['questiontext' => 'New B?', 'questionconfig' => "*a\nb"],
        ]]);
        $list = self::call('mod_kahoodle_get_questions', ['cmid' => $this->kahoodle->cmid]);
        $this->assertSame(['One?', 'New A?', 'New B?', 'Two?', 'Three?'], array_column($list['questions'], 'questiontext'));
    }

    /**
     * question_pre_update returns the stored values under the names update_question takes, and update_question
     * changes only what it is given, including the position
     */
    public function test_pre_update_and_update_question(): void {
        [$one, , $three] = $this->add_three();

        $before = self::call('mod_kahoodle_question_pre_update', ['questionids' => [$three]])['questions'][0];
        $this->assertSame('Three?', $before['questiontext']);
        $this->assertSame("*a\nb\nc", $before['questionconfig']);
        $this->assertSame(30, $before['questionduration']);
        $this->assertSame(0, $before['maxpoints']);
        $this->assertSame(3, $before['position']);

        self::call('mod_kahoodle_update_question', ['questionid' => $three, 'questiontext' => 'Third?', 'position' => 1]);
        self::call('mod_kahoodle_update_question', ['questionid' => $one, 'maxpoints' => 2000]);

        $after = self::call('mod_kahoodle_question_pre_update', ['questionids' => [$three, $one]])['questions'];
        $this->assertSame('Third?', $after[0]['questiontext']);
        $this->assertSame(1, $after[0]['position']);
        $this->assertSame("*a\nb\nc", $after[0]['questionconfig']);
        $this->assertSame(30, $after[0]['questionduration']);
        $this->assertSame(2000, $after[1]['maxpoints']);

        // A zero override goes back to the activity default.
        self::call('mod_kahoodle_update_question', ['questionid' => $three, 'questionduration' => 0]);
        $this->assertSame(0, self::call('mod_kahoodle_question_pre_update',
            ['questionids' => [$three]])['questions'][0]['questionduration']);
    }

    /**
     * Deleting reports a question that cannot be deleted and removes the others
     */
    public function test_delete_questions(): void {
        [$one, $two] = $this->add_three();
        $result = self::call('mod_kahoodle_delete_questions', ['questionids' => [$two, 999999]]);
        $this->assertSame([$two], $result['deleted']);
        $this->assertCount(1, $result['warnings']);
        $list = self::call('mod_kahoodle_get_questions', ['cmid' => $this->kahoodle->cmid]);
        $this->assertSame([$one], array_slice(array_column($list['questions'], 'questionid'), 0, 1));
        $this->assertCount(2, $list['questions']);
    }

    /**
     * After a round has started, questions can be changed but not added, removed or moved
     */
    public function test_started_round_allows_only_changes(): void {
        [$one, $two] = $this->add_three();
        $this->finish_round();

        try {
            self::call('mod_kahoodle_create_questions', ['cmid' => $this->kahoodle->cmid,
                'questions' => [['questiontext' => 'Late?', 'questionconfig' => "*a\nb"]]]);
            $this->fail('A question was added to a finished round');
        } catch (\moodle_exception $e) {
            $this->assertSame('noeditableround_ws', $e->debuginfo);
        }
        $this->assertSame('noeditableroundws',
            self::call('mod_kahoodle_delete_questions', ['questionids' => [$two]])['warnings'][0]['warningcode']);
        try {
            self::call('mod_kahoodle_update_question', ['questionid' => $two, 'position' => 1]);
            $this->fail('A question was moved in a finished round');
        } catch (\moodle_exception $e) {
            $this->assertSame('noeditableround_ws', $e->debuginfo);
        }

        self::call('mod_kahoodle_update_question', ['questionid' => $one, 'questiontext' => 'First, fixed?']);
        $list = self::call('mod_kahoodle_get_questions', ['cmid' => $this->kahoodle->cmid]);
        $this->assertFalse($list['roundeditable']);
        $this->assertSame('First, fixed?', $list['questions'][0]['questiontext']);
    }

    /**
     * The questionconfig description lists the format of each installed question type
     */
    public function test_questionconfig_description(): void {
        $description = helper::questionconfig_description();
        $this->assertStringContainsString('- multichoice: the answers, one per line', $description);
    }

    /**
     * A student can read and change no question
     */
    public function test_requires_capability(): void {
        global $DB;
        [$one] = $this->add_three();
        $course = $DB->get_record('course', ['id' => $this->kahoodle->course]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        foreach ([
            ['mod_kahoodle_get_questions', ['cmid' => $this->kahoodle->cmid]],
            ['mod_kahoodle_question_pre_update', ['questionids' => [$one]]],
            ['mod_kahoodle_update_question', ['questionid' => $one, 'questiontext' => 'Mine?']],
            ['mod_kahoodle_create_questions', ['cmid' => $this->kahoodle->cmid, 'questions' => []]],
        ] as [$name, $args]) {
            try {
                self::call($name, $args);
                $this->fail("$name worked for a student");
            } catch (\moodle_exception $e) {
                $this->assertSame('nopermissions', $e->debuginfo, $name);
            }
        }
        $this->assertSame('nopermissions',
            self::call('mod_kahoodle_delete_questions', ['questionids' => [$one]])['warnings'][0]['warningcode']);
    }
}
