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
use mod_kahoodle\task\auto_archive_round;

/**
 * Structure step to restore one Kahoodle activity
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_kahoodle_activity_structure_step extends restore_activity_structure_step {
    /** @var int[] New IDs of all restored rounds */
    protected array $restoredroundids = [];

    /** @var array|null New ID and sort key of the round to keep when restoring without user data */
    protected ?array $roundtokeep = null;

    /** @var int[] New IDs of the restored rounds that were in progress when the backup was made */
    protected array $roundsinprogress = [];

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
     * all rounds are restored here, and then {@see self::remove_extra_rounds()} deletes all of them
     * except the one that would have been backed up without user data.
     *
     * @param array $data
     * @return void
     */
    protected function process_kahoodle_round($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $userinfo = $this->get_setting_value('userinfo');

        // Rounds with a lower sort key come first, in the same order as in the backup without user data:
        // the round in preparation first, then the newest one.
        $sortkey = [
            $data->currentstage === constants::STAGE_PREPARATION ? 0 : 1,
            -(int)$data->timecreated,
            -(int)$oldid,
        ];
        $inprogress = !in_array($data->currentstage, [constants::STAGE_PREPARATION, constants::STAGE_ARCHIVED], true);

        $data->kahoodleid = $this->get_new_parentid('kahoodle');

        // When restoring without user data, reset round to preparation stage.
        if (!$userinfo) {
            $data->currentstage = constants::STAGE_PREPARATION;
            $data->currentquestion = null;
            $data->stagestarttime = null;
            $data->timestarted = null;
            $data->timecompleted = null;
        }

        $newitemid = $DB->insert_record('kahoodle_rounds', $data);
        $this->set_mapping('kahoodle_round', $oldid, $newitemid);

        $this->restoredroundids[] = $newitemid;
        if (!$userinfo && ($this->roundtokeep === null || $sortkey < $this->roundtokeep['sortkey'])) {
            $this->roundtokeep = ['id' => $newitemid, 'sortkey' => $sortkey];
        }
        if ($userinfo && $inprogress) {
            $this->roundsinprogress[] = $newitemid;
        }
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

        if (!$this->get_setting_value('userinfo')) {
            $this->remove_extra_rounds();
        }

        // Nobody will finish the rounds that were in progress when the backup was made, archive them automatically.
        foreach ($this->roundsinprogress as $roundid) {
            auto_archive_round::schedule(round::create_from_id($roundid));
        }
    }

    /**
     * When a backup made with user data is restored without user data, delete the extra rounds
     *
     * Only keep the round that the backup without user data would include, and delete the questions
     * and question versions that are not used in it.
     */
    protected function remove_extra_rounds(): void {
        global $DB;

        $roundids = array_diff($this->restoredroundids, [$this->roundtokeep['id'] ?? 0]);
        if (!$roundids) {
            return;
        }
        $DB->delete_records_list('kahoodle_round_questions', 'roundid', $roundids);
        $DB->delete_records_list('kahoodle_rounds', 'id', $roundids);

        // Delete question versions that are not used in the remaining round, and their images.
        $kahoodleid = $this->get_new_parentid('kahoodle');
        $versionids = $DB->get_fieldset_sql(
            "SELECT qv.id
               FROM {kahoodle_question_versions} qv
               JOIN {kahoodle_questions} q ON q.id = qv.questionid
          LEFT JOIN {kahoodle_round_questions} rq ON rq.questionversionid = qv.id
              WHERE q.kahoodleid = ? AND rq.id IS NULL",
            [$kahoodleid]
        );
        if ($versionids) {
            $fs = get_file_storage();
            foreach ($versionids as $versionid) {
                $fs->delete_area_files(
                    $this->task->get_contextid(),
                    'mod_kahoodle',
                    constants::FILEAREA_QUESTION_IMAGE,
                    $versionid
                );
            }
            $DB->delete_records_list('kahoodle_question_versions', 'id', $versionids);
        }

        // Delete questions without versions.
        $questionids = $DB->get_fieldset_sql(
            "SELECT q.id
               FROM {kahoodle_questions} q
          LEFT JOIN {kahoodle_question_versions} qv ON qv.questionid = q.id
              WHERE q.kahoodleid = ? AND qv.id IS NULL",
            [$kahoodleid]
        );
        if ($questionids) {
            $DB->delete_records_list('kahoodle_questions', 'id', $questionids);
        }

        // Each remaining question now has one version, used in the remaining round, make sure it is marked as the last.
        $DB->execute(
            "UPDATE {kahoodle_question_versions}
                SET islast = 1
              WHERE islast = 0
                AND questionid IN (SELECT id FROM {kahoodle_questions} WHERE kahoodleid = ?)",
            [$kahoodleid]
        );
    }
}
