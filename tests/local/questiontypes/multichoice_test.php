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

namespace mod_kahoodle\local\questiontypes;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\participant;
use mod_kahoodle\local\entities\round_question;
use mod_kahoodle\local\game\questions;

/**
 * Tests for multichoice question type and base class sanitization
 *
 * @covers     \mod_kahoodle\local\questiontypes\multichoice
 * @covers     \mod_kahoodle\local\questiontypes\base
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class multichoice_test extends \advanced_testcase {
    /**
     * Get the Kahoodle plugin generator
     *
     * @return \mod_kahoodle_generator
     */
    protected function get_generator(): \mod_kahoodle_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_kahoodle');
    }

    /**
     * Create a kahoodle with a question and return the round_question entity
     *
     * @param string $config Question config string
     * @return round_question
     */
    protected function create_question_with_config(string $config): round_question {
        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);
        return $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Test question',
            'questionconfig' => $config,
        ]);
    }

    /**
     * Test sanitize_question_config_data with valid configuration
     */
    public function test_sanitize_valid_config(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("Apple\n*Banana\nCherry");

        // The config should be sanitized and stored.
        $data = $rq->get_data();
        $this->assertStringContainsString('*Banana', $data->questionconfig);
        $this->assertStringContainsString('Apple', $data->questionconfig);
        $this->assertStringContainsString('Cherry', $data->questionconfig);
    }

    /**
     * Test sanitize rejects fewer than 2 options
     */
    public function test_sanitize_too_few_options(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Test',
            'questionconfig' => "*OnlyOne",
        ]);
    }

    /**
     * Test sanitize rejects more than 8 options
     */
    public function test_sanitize_too_many_options(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        $options = array_map(fn($i) => "Option $i", range(1, 9));
        $options[0] = '*' . $options[0];

        $this->expectException(\moodle_exception::class);
        $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Test',
            'questionconfig' => implode("\n", $options),
        ]);
    }

    /**
     * Test sanitize rejects no correct option
     */
    public function test_sanitize_no_correct_option(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Test',
            'questionconfig' => "A\nB\nC",
        ]);
    }

    /**
     * Test sanitize rejects multiple correct options
     */
    public function test_sanitize_multiple_correct_options(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $kahoodle = $this->getDataGenerator()->create_module('kahoodle', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        $this->get_generator()->create_question([
            'kahoodleid' => $kahoodle->id,
            'questiontext' => 'Test',
            'questionconfig' => "*A\n*B\nC",
        ]);
    }

    /**
     * Test sanitize with empty config on existing question unsets the field
     */
    public function test_sanitize_empty_config_on_update(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $data = (object)['questionconfig' => ''];
        // On existing question (has ID), empty config should be unset.
        $mc->sanitize_question_config_data($rq, $data);
        $this->assertObjectNotHasProperty('questionconfig', $data);
    }

    /**
     * Test export_template_data returns empty for preview stage
     */
    public function test_export_template_data_preview_stage(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $result = $mc->export_template_data($rq, constants::STAGE_QUESTION_PREVIEW);
        $this->assertEmpty($result);
    }

    /**
     * Test export_template_data for question stage
     */
    public function test_export_template_data_question_stage(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("Apple\n*Banana\nCherry");

        $mc = new multichoice();
        $result = $mc->export_template_data($rq, constants::STAGE_QUESTION);

        $this->assertCount(3, $result['options']);
        $this->assertEquals(3, $result['optioncount']);
        $this->assertFalse($result['manyoptions']);
        $this->assertEquals('A', $result['options'][0]['letter']);
        $this->assertEquals('Apple', $result['options'][0]['text']);
        // In question stage, iscorrect should NOT be present.
        $this->assertArrayNotHasKey('iscorrect', $result['options'][0]);
    }

    /**
     * Test export_template_data for results stage
     */
    public function test_export_template_data_results_stage(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("Apple\n*Banana\nCherry");

        $mc = new multichoice();
        $result = $mc->export_template_data($rq, constants::STAGE_QUESTION_RESULTS);

        $this->assertCount(3, $result['options']);
        // In results stage, iscorrect and count should be present.
        $this->assertArrayHasKey('iscorrect', $result['options'][0]);
        $this->assertFalse($result['options'][0]['iscorrect']);
        $this->assertTrue($result['options'][1]['iscorrect']);
        $this->assertArrayHasKey('count', $result['options'][0]);
        $this->assertArrayHasKey('heightpercent', $result['options'][0]);
    }

    /**
     * Test export_template_data with mock results
     */
    public function test_export_template_data_results_mock(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $result = $mc->export_template_data($rq, constants::STAGE_QUESTION_RESULTS, true);

        // Mock results should populate answer counts.
        $this->assertCount(3, $result['options']);
        foreach ($result['options'] as $option) {
            $this->assertArrayHasKey('count', $option);
        }
    }

    /**
     * Test manyoptions flag for 5+ options
     */
    public function test_export_template_data_many_options(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\nB\nC\nD\n*E");

        $mc = new multichoice();
        $result = $mc->export_template_data($rq, constants::STAGE_QUESTION);

        $this->assertTrue($result['manyoptions']);
        $this->assertEquals(5, $result['optioncount']);
    }

    /**
     * Test export_template_data_participant returns empty for preview stage
     */
    public function test_export_template_data_participant_preview(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        // Create a participant.
        $user = $this->getDataGenerator()->create_user();
        $round = $rq->get_round();
        $participantid = $this->get_generator()->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $user->id,
        ]);
        $participant = $round->get_participant_by_id($participantid);

        $mc = new multichoice();
        $result = $mc->export_template_data_participant($participant, $rq, constants::STAGE_QUESTION_PREVIEW);
        $this->assertEmpty($result);
    }

    /**
     * Test export_template_data_participant for question stage (letters only, no text)
     */
    public function test_export_template_data_participant_question(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("Apple\n*Banana\nCherry");

        $user = $this->getDataGenerator()->create_user();
        $round = $rq->get_round();
        $participantid = $this->get_generator()->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $user->id,
        ]);
        $participant = $round->get_participant_by_id($participantid);

        $mc = new multichoice();
        $result = $mc->export_template_data_participant($participant, $rq, constants::STAGE_QUESTION);

        $this->assertCount(3, $result['options']);
        $this->assertEquals('A', $result['options'][0]['letter']);
        // Participant options should NOT contain text (only letters).
        $this->assertArrayNotHasKey('text', $result['options'][0]);
    }

    /**
     * Test validate_answer with correct option
     */
    public function test_validate_answer_correct(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $this->assertTrue($mc->validate_answer($rq, '2'));
    }

    /**
     * Test validate_answer with incorrect option
     */
    public function test_validate_answer_incorrect(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $this->assertFalse($mc->validate_answer($rq, '1'));
        $this->assertFalse($mc->validate_answer($rq, '3'));
    }

    /**
     * Test validate_answer with invalid option number
     */
    public function test_validate_answer_invalid(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $this->assertNull($mc->validate_answer($rq, '0'));
        $this->assertNull($mc->validate_answer($rq, '4'));
        $this->assertNull($mc->validate_answer($rq, '-1'));
    }

    /**
     * Test format_response with valid option number
     */
    public function test_format_response_valid(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("Apple\n*Banana\nCherry");

        $mc = new multichoice();
        $this->assertEquals('Apple', $mc->format_response('1', $rq));
        $this->assertEquals('Banana', $mc->format_response('2', $rq));
        $this->assertEquals('Cherry', $mc->format_response('3', $rq));
    }

    /**
     * Test format_response with null input
     */
    public function test_format_response_null(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $this->assertNull($mc->format_response(null, $rq));
    }

    /**
     * Test format_response with invalid option number
     */
    public function test_format_response_invalid(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $this->assertNull($mc->format_response('0', $rq));
        $this->assertNull($mc->format_response('4', $rq));
    }

    /**
     * Create a question with a response so that edit restrictions apply
     *
     * @param string $config Question config string
     * @return round_question
     */
    protected function create_question_with_response(string $config): round_question {
        $rq = $this->create_question_with_config($config);
        $user = $this->getDataGenerator()->create_user();
        $round = $rq->get_round();
        $participantid = $this->get_generator()->create_participant([
            'roundid' => $round->get_id(),
            'userid' => $user->id,
        ]);
        $this->get_generator()->create_response([
            'participantid' => $participantid,
            'roundquestionid' => $rq->get_id(),
        ]);
        return $rq;
    }

    /**
     * Test sanitize allows compatible changes when question has responses
     */
    public function test_sanitize_with_responses_compatible(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_response("Apple\n*Banana\nCherry");

        $mc = new multichoice();
        // Same option count, same correct position - just text changed.
        $data = (object)['questionconfig' => "Red\n*Blue\nGreen"];
        $mc->sanitize_question_config_data($rq, $data);
        $this->assertStringContainsString('*Blue', $data->questionconfig);
    }

    /**
     * Test sanitize rejects changed option count when question has responses
     */
    public function test_sanitize_with_responses_option_count_changed(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_response("A\n*B\nC");

        $mc = new multichoice();
        $data = (object)['questionconfig' => "A\n*B"];
        $this->expectException(\moodle_exception::class);
        $mc->sanitize_question_config_data($rq, $data);
    }

    /**
     * Test sanitize rejects changed correct position when question has responses
     */
    public function test_sanitize_with_responses_correct_position_changed(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_response("A\n*B\nC");

        $mc = new multichoice();
        $data = (object)['questionconfig' => "*A\nB\nC"];
        $this->expectException(\moodle_exception::class);
        $mc->sanitize_question_config_data($rq, $data);
    }

    /**
     * Test sanitize allows option count change when question has no responses
     */
    public function test_sanitize_without_responses_option_count_changed(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $data = (object)['questionconfig' => "A\n*B"];
        $mc->sanitize_question_config_data($rq, $data);
        $this->assertStringContainsString('*B', $data->questionconfig);
    }

    /**
     * Test question_form_validation with valid data
     */
    public function test_question_form_validation_valid(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $errors = $mc->question_form_validation($rq, ['questionconfig' => "X\n*Y\nZ"], []);
        $this->assertEmpty($errors);
    }

    /**
     * Test question_form_validation with invalid data
     */
    public function test_question_form_validation_invalid(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $errors = $mc->question_form_validation($rq, ['questionconfig' => "OnlyOne"], []);
        $this->assertArrayHasKey('questionconfig', $errors);
    }

    /**
     * Test base sanitize_data strips unknown fields
     */
    public function test_base_sanitize_strips_unknown_fields(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $data = (object)[
            'questiontext' => 'Updated text',
            'questionconfig' => "X\n*Y\nZ",
            'unknownfield' => 'should be removed',
            'anotherfield' => 123,
        ];
        $mc->sanitize_data($rq, $data);
        $this->assertObjectNotHasProperty('unknownfield', $data);
        $this->assertObjectNotHasProperty('anotherfield', $data);
        $this->assertObjectHasProperty('questiontext', $data);
    }

    /**
     * Test base sanitize_data throws on maxpoints less than minpoints
     */
    public function test_base_sanitize_validates_points(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $data = (object)[
            'questionconfig' => "A\n*B\nC",
            'maxpoints' => 100,
            'minpoints' => 500,
        ];
        $this->expectException(\moodle_exception::class);
        $mc->sanitize_data($rq, $data);
    }

    /**
     * Test base sanitize_data throws on negative durations
     */
    public function test_base_sanitize_negative_durations(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $data = (object)[
            'questionconfig' => "A\n*B\nC",
            'questionduration' => -10,
        ];
        $this->expectException(\moodle_exception::class);
        $mc->sanitize_data($rq, $data);
    }

    /**
     * Test base sanitize_data unsets questionduration of zero (use default)
     */
    public function test_base_sanitize_zero_duration_unsets(): void {
        $this->resetAfterTest();
        $rq = $this->create_question_with_config("A\n*B\nC");

        $mc = new multichoice();
        $data = (object)[
            'questionconfig' => "A\n*B\nC",
            'questionduration' => 0,
        ];
        $mc->sanitize_data($rq, $data);
        $this->assertObjectNotHasProperty('questionduration', $data);
    }

    /**
     * Test get_type returns the correct type identifier
     */
    public function test_get_type(): void {
        $mc = new multichoice();
        $this->assertEquals('multichoice', $mc->get_type());
    }

    /**
     * Test get_display_name returns a non-empty string
     */
    public function test_get_display_name(): void {
        $mc = new multichoice();
        $this->assertNotEmpty($mc->get_display_name());
    }

    /**
     * Test get_template returns valid template paths
     */
    public function test_get_template(): void {
        $mc = new multichoice();

        // These specific templates exist for multichoice.
        $template = $mc->get_template('facilitator', 'question');
        $this->assertStringContainsString('mod_kahoodle', $template);

        $template = $mc->get_template('facilitator', 'results');
        $this->assertStringContainsString('mod_kahoodle', $template);

        $template = $mc->get_template('participant', 'question');
        $this->assertStringContainsString('mod_kahoodle', $template);
    }
}
