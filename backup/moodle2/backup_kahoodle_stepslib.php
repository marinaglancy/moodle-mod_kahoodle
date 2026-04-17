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
 * Provides all the settings and steps to perform one complete backup of the activity
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_kahoodle_activity_structure_step extends backup_activity_structure_step {
    /**
     * Backup structure
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define the structure for the kahoodle table.
        $kahoodle = new backup_nested_element(
            'kahoodle',
            ['id'],
            [
                'name',
                'intro',
                'introformat',
                'questionformat',
                'identitymode',
                'allowrepeat',
                'lobbyduration',
                'questionpreviewduration',
                'questionduration',
                'questionresultsduration',
                'maxpoints',
                'minpoints',
                'timemodified',
            ]
        );

        // Questions (always backed up).
        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'kahoodleid',
            'questiontype',
            'timecreated',
        ]);

        // Question versions (always backed up).
        $questionversions = new backup_nested_element('question_versions');
        $questionversion = new backup_nested_element('question_version', ['id'], array_merge(
            [
                'questionid',
                'version',
            ],
            \mod_kahoodle\constants::FIELDS_QUESTION_VERSION,
            [
                'timecreated',
                'timemodified',
                'islast',
            ]
        ));

        // Rounds.
        $rounds = new backup_nested_element('rounds');
        $round = new backup_nested_element('round', ['id'], [
            'kahoodleid',
            'name',
            'currentstage',
            'currentquestion',
            'stagestarttime',
            'timecreated',
            'timestarted',
            'timecompleted',
            'timemodified',
        ]);

        // Round questions (always backed up).
        $roundquestions = new backup_nested_element('round_questions');
        $roundquestion = new backup_nested_element('round_question', ['id'], array_merge(
            [
                'roundid',
                'questionversionid',
                'sortorder',
            ],
            \mod_kahoodle\constants::FIELDS_ROUND_QUESTION,
            [
                'timecreated',
                'timemodified',
            ]
        ));

        // Participants (user data only).
        $participants = new backup_nested_element('participants');
        $participant = new backup_nested_element('participant', ['id'], [
            'roundid',
            'userid',
            'participantcode',
            'displayname',
            'avatar',
            'totalscore',
            'finalrank',
            'timecreated',
        ]);

        // Responses (user data only).
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], [
            'participantid',
            'roundquestionid',
            'response',
            'iscorrect',
            'points',
            'responsetime',
            'timecreated',
        ]);

        // Build the tree.
        $kahoodle->add_child($questions);
        $questions->add_child($question);

        $question->add_child($questionversions);
        $questionversions->add_child($questionversion);

        $kahoodle->add_child($rounds);
        $rounds->add_child($round);

        $round->add_child($roundquestions);
        $roundquestions->add_child($roundquestion);

        $round->add_child($participants);
        $participants->add_child($participant);

        $participant->add_child($responses);
        $responses->add_child($response);

        // Define sources.
        $kahoodle->set_source_table('kahoodle', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            // With user data: backup all questions and all rounds.
            $question->set_source_table('kahoodle_questions', ['kahoodleid' => backup::VAR_PARENTID], 'id ASC');
            $questionversion->set_source_table('kahoodle_question_versions', ['questionid' => backup::VAR_PARENTID], 'id ASC');
            $round->set_source_table('kahoodle_rounds', ['kahoodleid' => backup::VAR_PARENTID], 'id ASC');
            $roundquestion->set_source_table('kahoodle_round_questions', ['roundid' => backup::VAR_PARENTID], 'id ASC');
            $participant->set_source_table('kahoodle_participants', ['roundid' => backup::VAR_PARENTID], 'id ASC');
            $response->set_source_table('kahoodle_responses', ['participantid' => backup::VAR_PARENTID], 'id ASC');
        } else {
            global $DB;

            // MSSQL uses "SELECT TOP N" instead of "LIMIT N". Moodle supports MySQL,
            // MariaDB, PostgreSQL and MSSQL; all except MSSQL accept LIMIT.
            $ismssql = ($DB->get_dbfamily() === 'mssql');
            $top = $ismssql ? 'TOP 1 ' : '';
            $limit = $ismssql ? '' : ' LIMIT 1';

            // Without user data: backup only questions that belong to the last round,
            // with only their latest versions, and only the last round itself.
            $question->set_source_sql(
                "SELECT DISTINCT q.*
                   FROM {kahoodle_questions} q
                   JOIN {kahoodle_question_versions} qv ON qv.questionid = q.id
                   JOIN {kahoodle_round_questions} rq ON rq.questionversionid = qv.id
                   JOIN {kahoodle_rounds} r ON r.id = rq.roundid
                  WHERE q.kahoodleid = ?
                    AND r.id = (
                        SELECT {$top}r2.id FROM {kahoodle_rounds} r2
                         WHERE r2.kahoodleid = q.kahoodleid
                         ORDER BY CASE WHEN r2.currentstage = 'preparation' THEN 0 ELSE 1 END,
                                  r2.timecreated DESC, r2.id DESC
                         {$limit}
                    )
                  ORDER BY q.id ASC",
                [backup::VAR_PARENTID]
            );

            // Only the latest version of each question.
            $questionversion->set_source_sql(
                "SELECT qv.*
                   FROM {kahoodle_question_versions} qv
                  WHERE qv.questionid = ?
                    AND qv.islast = 1
                  ORDER BY qv.id ASC",
                [backup::VAR_PARENTID]
            );

            // Only the last round.
            $round->set_source_sql(
                "SELECT {$top}r.*
                   FROM {kahoodle_rounds} r
                  WHERE r.kahoodleid = ?
                  ORDER BY CASE WHEN r.currentstage = 'preparation' THEN 0 ELSE 1 END,
                           r.timecreated DESC, r.id DESC
                  {$limit}",
                [backup::VAR_PARENTID]
            );

            $roundquestion->set_source_table('kahoodle_round_questions', ['roundid' => backup::VAR_PARENTID], 'id ASC');

            // No participants or responses without user data.
        }

        // Define id annotations.
        $participant->annotate_ids('user', 'userid');

        // Define file annotations.
        $kahoodle->annotate_files('mod_kahoodle', 'intro', null);
        $questionversion->annotate_files('mod_kahoodle', 'questionimage', 'id');
        $participant->annotate_files('mod_kahoodle', 'avatar', 'id');

        // Return the root element (kahoodle), wrapped into standard activity structure.
        return $this->prepare_activity_structure($kahoodle);
    }
}
