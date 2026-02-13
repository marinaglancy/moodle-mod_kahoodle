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

/**
 * Behat data generator for mod_kahoodle.
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_kahoodle_generator extends behat_generator_base {
    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'questions' => [
                'singular' => 'question',
                'datagenerator' => 'question',
                'required' => ['kahoodle', 'questiontext'],
                'switchids' => ['kahoodle' => 'kahoodleid'],
            ],
            'participants' => [
                'singular' => 'participant',
                'datagenerator' => 'participant',
                'required' => ['kahoodle', 'user'],
                'switchids' => [],
            ],
            'responses' => [
                'singular' => 'response',
                'datagenerator' => 'response',
                'required' => ['kahoodle', 'user', 'question'],
                'switchids' => [],
            ],
        ];
    }

    /**
     * Get the kahoodle id using an activity idnumber.
     *
     * @param string $idnumber
     * @return int The kahoodle id
     */
    protected function get_kahoodle_id(string $idnumber): int {
        $cm = $this->get_cm_by_activity_name('kahoodle', $idnumber);

        return $cm->instance;
    }

    /**
     * Preprocess question data.
     *
     * Converts literal \n in questionconfig to actual newlines.
     *
     * @param array $data Raw data.
     * @return array Processed data.
     */
    protected function preprocess_question(array $data): array {
        if (isset($data['questionconfig'])) {
            // Convert literal \n to actual newlines.
            $data['questionconfig'] = str_replace('\n', "\n", $data['questionconfig']);
        }
        return $data;
    }

    /**
     * Preprocess participant data.
     *
     * Resolves kahoodle activity name to round ID and username to user ID.
     *
     * @param array $data Raw data.
     * @return array Processed data.
     */
    protected function preprocess_participant(array $data): array {
        global $DB;

        $cm = $this->get_cm_by_activity_name('kahoodle', $data['kahoodle']);
        $round = \mod_kahoodle\local\game\questions::get_last_round($cm->instance);
        $data['roundid'] = $round->get_id();

        $data['userid'] = $DB->get_field('user', 'id', ['username' => $data['user']], MUST_EXIST);

        unset($data['kahoodle'], $data['user']);
        return $data;
    }

    /**
     * Preprocess response data.
     *
     * Resolves kahoodle/user/question to participantid and roundquestionid.
     * The 'question' field can be either the question text or a sort order number.
     *
     * @param array $data Raw data.
     * @return array Processed data.
     */
    protected function preprocess_response(array $data): array {
        global $DB;

        $cm = $this->get_cm_by_activity_name('kahoodle', $data['kahoodle']);
        $round = \mod_kahoodle\local\game\questions::get_last_round($cm->instance);

        // Find participant by user and round.
        $userid = $DB->get_field('user', 'id', ['username' => $data['user']], MUST_EXIST);
        $data['participantid'] = $DB->get_field(
            'kahoodle_participants',
            'id',
            ['roundid' => $round->get_id(), 'userid' => $userid],
            MUST_EXIST
        );

        // Find round question by sort order (numeric) or question text.
        $question = $data['question'];
        $questions = \mod_kahoodle\local\entities\round_question::get_all_questions_for_round($round);
        foreach ($questions as $rq) {
            $rqdata = $rq->get_data();
            if (is_numeric($question) && (int)$rqdata->sortorder === (int)$question) {
                $data['roundquestionid'] = $rq->get_id();
                break;
            } else if (!is_numeric($question) && $rqdata->questiontext === $question) {
                $data['roundquestionid'] = $rq->get_id();
                break;
            }
        }
        if (empty($data['roundquestionid'])) {
            throw new \Exception("Question '{$question}' not found in round");
        }

        unset($data['kahoodle'], $data['user'], $data['question']);
        return $data;
    }

    /**
     * Get the module data generator.
     *
     * @return mod_kahoodle_generator Kahoodle data generator.
     */
    protected function get_data_generator(): mod_kahoodle_generator {
        return $this->componentdatagenerator;
    }
}
