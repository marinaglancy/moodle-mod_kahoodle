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
use mod_kahoodle\local\game\questions;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_kahoodle\external\prepare_round::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_kahoodle\external\get_rounds::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_kahoodle\external\get_round_results::class)]
/**
 * Tests for the web service functions about Kahoodle rounds
 *
 * @covers     \mod_kahoodle\external\prepare_round
 * @covers     \mod_kahoodle\external\get_rounds
 * @covers     \mod_kahoodle\external\get_round_results
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rounds_test extends \advanced_testcase {
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
     * A played round with two questions and three participants
     *
     * @param int $identitymode
     * @return array [kahoodle, roundid, course]
     */
    private function played_round(int $identitymode = constants::IDENTITYMODE_REALNAME): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module(
            'kahoodle',
            ['course' => $course->id, 'identitymode' => $identitymode]
        );
        /** @var \mod_kahoodle_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
        $round = questions::get_last_round($kahoodle->id);
        $q1 = $generator->create_question(['kahoodleid' => $kahoodle->id], $round);
        $q2 = $generator->create_question(['kahoodleid' => $kahoodle->id], $round);
        $DB->update_record('kahoodle_rounds', (object)['id' => $round->get_id(), 'name' => 'Monday',
            'currentstage' => constants::STAGE_ARCHIVED, 'timestarted' => time() - 600, 'timecompleted' => time() - 60]);
        foreach ([['Anna', 1800, 1, [1, 1]], ['Ben', 900, 2, [1, 0]], ['Cleo', 0, 3, [0]]] as [$name, $score, $rank, $answers]) {
            $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => $name]);
            $participantid = $generator->create_participant(['roundid' => $round->get_id(), 'userid' => $user->id,
                'displayname' => $name . ' alias', 'totalscore' => $score, 'finalrank' => $rank]);
            foreach ($answers as $i => $iscorrect) {
                $generator->create_response(['participantid' => $participantid,
                    'roundquestionid' => [$q1, $q2][$i]->get_id(), 'iscorrect' => $iscorrect]);
            }
        }
        return [$kahoodle, $round->get_id(), $course];
    }

    /**
     * A finished round can be followed by a new round with the same questions; a round that is not
     * finished cannot
     */
    public function test_prepare_round(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$kahoodle, $roundid] = $this->played_round();

        $newroundid = self::call('mod_kahoodle_prepare_round', ['cmid' => $kahoodle->cmid])['roundid'];
        $this->assertNotEquals($roundid, $newroundid);
        $questions = self::call('mod_kahoodle_get_questions', ['cmid' => $kahoodle->cmid]);
        $this->assertSame($newroundid, $questions['roundid']);
        $this->assertTrue($questions['roundeditable']);
        $this->assertCount(2, $questions['questions']);

        try {
            self::call('mod_kahoodle_prepare_round', ['cmid' => $kahoodle->cmid]);
            $this->fail('A round was prepared while one is in preparation');
        } catch (\moodle_exception $e) {
            $this->assertSame('cannotprepareround', $e->debuginfo);
        }
    }

    /**
     * Rounds are listed with their totals, and a round's results list its participants by rank
     */
    public function test_get_rounds_and_results(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$kahoodle, $roundid] = $this->played_round();

        $rounds = self::call('mod_kahoodle_get_rounds', ['cmid' => $kahoodle->cmid])['rounds'];
        $this->assertCount(1, $rounds);
        $this->assertSame('completed', $rounds[0]['status']);
        $this->assertSame(3, $rounds[0]['participantcount']);
        $this->assertSame(900, $rounds[0]['averagescore']);
        $this->assertSame(1800, $rounds[0]['maxscore']);
        $this->assertSame(2, $rounds[0]['questioncount']);

        $results = self::call('mod_kahoodle_get_round_results', ['roundid' => $roundid]);
        $this->assertSame('Monday', $results['name']);
        $this->assertSame(['Anna alias', 'Ben alias', 'Cleo alias'], array_column($results['participants'], 'displayname'));
        $this->assertSame([1, 2, 3], array_column($results['participants'], 'rank'));
        $this->assertSame([2, 1, 0], array_column($results['participants'], 'correctanswers'));
        $this->assertSame([2, 2, 1], array_column($results['participants'], 'questionsanswered'));
        $this->assertStringStartsWith('Anna', $results['participants'][0]['userfullname']);
    }

    /**
     * A fully anonymous activity never reveals who the participants are
     */
    public function test_results_of_an_anonymous_activity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $roundid] = $this->played_round(constants::IDENTITYMODE_ANONYMOUS);

        $results = self::call('mod_kahoodle_get_round_results', ['roundid' => $roundid]);
        $this->assertSame(['', '', ''], array_column($results['participants'], 'userfullname'));
    }

    /**
     * A student cannot see the results or prepare a round
     */
    public function test_requires_capability(): void {
        $this->resetAfterTest();
        [$kahoodle, $roundid, $course] = $this->played_round();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        foreach (
            [
            ['mod_kahoodle_get_rounds', ['cmid' => $kahoodle->cmid]],
            ['mod_kahoodle_get_round_results', ['roundid' => $roundid]],
            ['mod_kahoodle_prepare_round', ['cmid' => $kahoodle->cmid]],
            ] as [$name, $args]
        ) {
            try {
                self::call($name, $args);
                $this->fail("$name worked for a student");
            } catch (\moodle_exception $e) {
                $this->assertSame('nopermissions', $e->debuginfo, $name);
            }
        }
    }
}
