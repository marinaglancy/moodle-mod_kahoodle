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

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\round_question;
/**
 * Data generator class
 *
 * @package    mod_kahoodle
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_kahoodle_generator extends testing_module_generator {
    /**
     * Creates an instance of the module for testing purposes.
     *
     * Module type will be taken from the class name.
     *
     * @param array|stdClass $record data for module being generated. Requires 'course' key
     *     (an id or the full object). Also can have any fields from add module form.
     * @param null|array $options general options for course module, can be merged into $record
     * @return stdClass record from module-defined table with additional field
     *     cmid (corresponding id in course_modules table)
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;

        // Set default values for plugin-specific fields if not provided.
        if (!isset($record->allowrepeat)) {
            $record->allowrepeat = \mod_kahoodle\constants::DEFAULT_ALLOW_REPEAT;
        }
        if (!isset($record->lobbyduration)) {
            $record->lobbyduration = \mod_kahoodle\constants::DEFAULT_LOBBY_DURATION;
        }
        if (!isset($record->questionpreviewduration)) {
            $record->questionpreviewduration = \mod_kahoodle\constants::DEFAULT_QUESTION_PREVIEW_DURATION;
        }
        if (!isset($record->questionduration)) {
            $record->questionduration = \mod_kahoodle\constants::DEFAULT_QUESTION_DURATION;
        }
        if (!isset($record->questionresultsduration)) {
            $record->questionresultsduration = \mod_kahoodle\constants::DEFAULT_QUESTION_RESULTS_DURATION;
        }
        if (!isset($record->maxpoints)) {
            $record->maxpoints = \mod_kahoodle\constants::DEFAULT_MAX_POINTS;
        }
        if (!isset($record->minpoints)) {
            $record->minpoints = \mod_kahoodle\constants::DEFAULT_MIN_POINTS;
        }
        if (!isset($record->questionformat)) {
            $record->questionformat = \mod_kahoodle\constants::QUESTIONFORMAT_PLAIN;
        }

        $instance = parent::create_instance($record, (array)$options);

        return $instance;
    }

    /**
     * Creates a question for a Kahoodle instance for testing purposes.
     *
     * @param array|stdClass $record data for question being generated. Requires 'kahoodleid' key.
     *     Optional fields:
     *     - questiontype: Type of question (default: 'multichoice')
     *     - questiontext: Question text (default: 'Sample question')
     *     - questionconfig: Type-specific configuration (may be required)
     *     - questionpreviewduration: Preview duration override (default: null)
     *     - questionduration: Question duration override (default: null)
     *     - questionresultsduration: Results duration override (default: null)
     *     - maxpoints: Maximum points override (default: null)
     *     - minpoints: Minimum points override (default: null)
     *     - image: If truthy, creates a test image file for the question (default: false)
     * @return round_question The question entity
     */
    public function create_question($record): round_question {
        global $DB;
        static $counter = 1;

        $record = (object)(array)$record;

        if (empty($record->kahoodleid)) {
            throw new coding_exception('kahoodleid must be specified when creating a question');
        }

        if (!isset($record->questiontext)) {
            $record->questiontext = 'Sample question ' . $counter++;
        }
        if (($record->questiontype ?? 'multichoice') === 'multichoice' && empty($record->questionconfig)) {
            $record->questionconfig = "Option 1\n*Option 2\nOption 3";
        }

        // Extract image flag before passing to API (not a real question field).
        $createimage = !empty($record->image);
        unset($record->image);

        // Use the questions API to add the question.
        $rq = \mod_kahoodle\questions::add_question($record);

        // Store a test image file for the question if requested.
        if ($createimage) {
            $this->create_question_image($rq);
        }

        return $rq;
    }

    /**
     * Creates a test image file for a question in the questionimage file area.
     *
     * @param round_question $rq The round question entity
     */
    protected function create_question_image(round_question $rq): void {
        $fs = get_file_storage();
        $context = $rq->get_round()->get_context();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_kahoodle',
            'filearea' => constants::FILEAREA_QUESTION_IMAGE,
            'itemid' => $rq->get_data()->questionversionid,
            'filepath' => '/',
            'filename' => 'testimage.png',
        ];
        // Create a minimal 1x1 pixel PNG.
        $image = imagecreate(1, 1);
        imagecolorallocate($image, 255, 0, 0);
        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);
        $fs->create_file_from_string($filerecord, $content);
    }

    /**
     * Creates a participant for a round for testing purposes.
     *
     * @param array|stdClass $record data for participant being generated. Requires 'roundid' and 'userid' keys.
     *     Optional fields:
     *     - displayname: Display name (default: 'User {userid}')
     *     - totalscore: Total score (default: 0)
     *     - timecreated: Time created (default: current time)
     * @return int The participant ID
     */
    public function create_participant($record): int {
        global $DB;

        $record = (object)(array)$record;

        if (empty($record->roundid)) {
            throw new coding_exception('roundid must be specified when creating a participant');
        }
        if (empty($record->userid)) {
            throw new coding_exception('userid must be specified when creating a participant');
        }

        if (!isset($record->displayname)) {
            $record->displayname = 'User ' . $record->userid;
        }
        if (!isset($record->totalscore)) {
            $record->totalscore = 0;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }

        return $DB->insert_record('kahoodle_participants', $record);
    }

    /**
     * Creates a response for a participant for testing purposes.
     *
     * @param array|stdClass $record data for response being generated. Requires 'participantid' and 'roundquestionid' keys.
     *     Optional fields:
     *     - response: The response string (default: '1')
     *     - iscorrect: Whether correct (default: 1)
     *     - points: Points earned (default: 100)
     *     - responsetime: Response time in seconds (default: 5.0)
     *     - timecreated: Time created (default: current time)
     * @return int The response ID
     */
    public function create_response($record): int {
        global $DB;

        $record = (object)(array)$record;

        if (empty($record->participantid)) {
            throw new coding_exception('participantid must be specified when creating a response');
        }
        if (empty($record->roundquestionid)) {
            throw new coding_exception('roundquestionid must be specified when creating a response');
        }

        if (!isset($record->response)) {
            $record->response = '1';
        }
        if (!isset($record->iscorrect)) {
            $record->iscorrect = 1;
        }
        if (!isset($record->points)) {
            $record->points = 100;
        }
        if (!isset($record->responsetime)) {
            $record->responsetime = 5.0;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }

        return $DB->insert_record('kahoodle_responses', $record);
    }

    /**
     * Creates a round for a Kahoodle instance for testing purposes.
     *
     * Unlike create_question() (which auto-creates a round), this method creates
     * a round directly with explicit properties. Useful for multi-round scenarios
     * and for creating rounds in specific stages.
     *
     * @param array|stdClass $record data for round being generated. Requires 'kahoodleid' key.
     *     Optional fields:
     *     - name: Round name (default: '')
     *     - currentstage: Stage constant (default: 'preparation')
     *     - currentquestion: Current question number (default: 0)
     *     - timestarted: Timestamp (default: null)
     *     - timecompleted: Timestamp (default: null)
     *     - stagestarttime: Timestamp (default: null)
     *     - timecreated: Timestamp (default: current time)
     * @return int The round ID
     */
    public function create_round($record): int {
        global $DB;

        $record = (object)(array)$record;

        if (empty($record->kahoodleid)) {
            throw new coding_exception('kahoodleid must be specified when creating a round');
        }

        if (!isset($record->name)) {
            $record->name = '';
        }
        if (!isset($record->currentstage)) {
            $record->currentstage = constants::STAGE_PREPARATION;
        }
        if (!isset($record->currentquestion)) {
            $record->currentquestion = 0;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }

        return $DB->insert_record('kahoodle_rounds', $record);
    }
}
