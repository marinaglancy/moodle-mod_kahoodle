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
 * Structure step to restore one Kahoodle activity
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_kahoodle_activity_structure_step extends restore_activity_structure_step {
    /** @var int|null The ID of the last round in the backup (highest timecreated or preparation stage). */
    protected ?int $lastroundoldid = null;

    /**
     * Structure step to restore one kahoodle activity
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('kahoodle', '/activity/kahoodle');
        $paths[] = new restore_path_element('kahoodle_question', '/activity/kahoodle/questions/question');
        $paths[] = new restore_path_element(
            'kahoodle_question_version',
            '/activity/kahoodle/questions/question/question_versions/question_version'
        );
        $paths[] = new restore_path_element('kahoodle_round', '/activity/kahoodle/rounds/round');
        $paths[] = new restore_path_element(
            'kahoodle_round_question',
            '/activity/kahoodle/rounds/round/round_questions/round_question'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'kahoodle_participant',
                '/activity/kahoodle/rounds/round/participants/participant'
            );
            $paths[] = new restore_path_element(
                'kahoodle_response',
                '/activity/kahoodle/rounds/round/participants/participant/responses/response'
            );
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process a kahoodle restore
     *
     * @param array $data
     * @return void
     */
    protected function process_kahoodle($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Insert the kahoodle record.
        $newitemid = $DB->insert_record('kahoodle', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process a question restore
     *
     * @param array $data
     * @return void
     */
    protected function process_kahoodle_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->kahoodleid = $this->get_new_parentid('kahoodle');

        $newitemid = $DB->insert_record('kahoodle_questions', $data);
        $this->set_mapping('kahoodle_question', $oldid, $newitemid);
    }

    /**
     * Process a question version restore
     *
     * @param array $data
     * @return void
     */
    protected function process_kahoodle_question_version($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->questionid = $this->get_new_parentid('kahoodle_question');

        $newitemid = $DB->insert_record('kahoodle_question_versions', $data);
        $this->set_mapping('kahoodle_question_version', $oldid, $newitemid, true);
    }

    /**
     * Process a round restore.
     *
     * When the backup was made with user data but we are restoring without user data,
     * only restore the last round (the one that would have been backed up without user data).
     *
     * @param array $data
     * @return void
     */
    protected function process_kahoodle_round($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Track the last round (first round processed is the one with highest priority
        // based on the backup ordering).
        if ($this->lastroundoldid === null) {
            $this->lastroundoldid = $oldid;
        }

        $data->kahoodleid = $this->get_new_parentid('kahoodle');

        $newitemid = $DB->insert_record('kahoodle_rounds', $data);
        $this->set_mapping('kahoodle_round', $oldid, $newitemid);
    }

    /**
     * Process a round question restore
     *
     * @param array $data
     * @return void
     */
    protected function process_kahoodle_round_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->roundid = $this->get_new_parentid('kahoodle_round');
        $data->questionversionid = $this->get_mappingid('kahoodle_question_version', $data->questionversionid);

        $newitemid = $DB->insert_record('kahoodle_round_questions', $data);
        $this->set_mapping('kahoodle_round_question', $oldid, $newitemid);
    }

    /**
     * Process a participant restore
     *
     * @param array $data
     * @return void
     */
    protected function process_kahoodle_participant($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->roundid = $this->get_new_parentid('kahoodle_round');
        if ($data->userid) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }

        $newitemid = $DB->insert_record('kahoodle_participants', $data);
        $this->set_mapping('kahoodle_participant', $oldid, $newitemid, true);
    }

    /**
     * Process a response restore
     *
     * @param array $data
     * @return void
     */
    protected function process_kahoodle_response($data) {
        global $DB;

        $data = (object)$data;

        $data->participantid = $this->get_new_parentid('kahoodle_participant');
        $data->roundquestionid = $this->get_mappingid('kahoodle_round_question', $data->roundquestionid);

        $DB->insert_record('kahoodle_responses', $data);
    }

    /**
     * Actions to be executed after the restore is completed
     */
    protected function after_execute() {
        // Add kahoodle related files.
        $this->add_related_files('mod_kahoodle', 'intro', null);
        $this->add_related_files('mod_kahoodle', 'questionimage', 'kahoodle_question_version');
        $this->add_related_files('mod_kahoodle', 'avatar', 'kahoodle_participant');
    }
}
