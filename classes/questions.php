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

namespace mod_kahoodle;

/**
 * Class questions
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions {
    /**
     * Get the ID of the editable round for a Kahoodle activity
     *
     * Returns the ID of a round that can be edited (not yet started).
     * If no editable round exists, creates a new one.
     *
     * @param int $kahoodleid The Kahoodle activity ID
     * @return int|null Round ID if editable round exists/created, null if last round is started
     */
    public static function get_editable_round_id(int $kahoodleid): ?int {
        global $DB;

        // Get all rounds for this kahoodle, ordered by creation time (newest first).
        $rounds = $DB->get_records('kahoodle_rounds', ['kahoodleid' => $kahoodleid], 'timecreated DESC', '*', 0, 1);

        if (empty($rounds)) {
            // No rounds yet, create one.
            $round = new \stdClass();
            $round->kahoodleid = $kahoodleid;
            $round->name = 'Round 1';
            $round->currentstage = constants::STAGE_PREPARATION;
            $round->currentquestion = null;
            $round->stagestarttime = null;

            // Get default lobby duration from kahoodle instance.
            $kahoodle = $DB->get_record('kahoodle', ['id' => $kahoodleid], 'lobbyduration', MUST_EXIST);
            $round->lobbyduration = $kahoodle->lobbyduration;

            $round->timecreated = time();
            $round->timestarted = null;
            $round->timecompleted = null;
            $round->timemodified = time();

            $round->id = $DB->insert_record('kahoodle_rounds', $round);
            return $round->id;
        }

        // Get the last (most recent) round.
        $lastround = reset($rounds);

        // Return the round ID only if it has not been started yet.
        if ($lastround->currentstage === constants::STAGE_PREPARATION && empty($lastround->timestarted)) {
            return $lastround->id;
        }

        // Last round has been started, return null.
        return null;
    }

    /**
     * Add a new question to the editable round
     *
     * Creates a new question, its first version, and links it to the editable round.
     * Accepts both question content and behavior data.
     *
     * @param \stdClass $questiondata Question data including:
     *   - kahoodleid: Kahoodle activity ID (required)
     *   - questiontype: Question type (required)
     *   - questiontext: Question text (required)
     *   - questiontextformat: Text format (optional, default FORMAT_HTML)
     *   - questionconfig: JSON config for question-specific settings (optional)
     *   - answersconfig: JSON config for answers (optional)
     *   - questionpreviewduration: Preview duration override (optional)
     *   - questionduration: Question duration override (optional)
     *   - questionresultsduration: Results duration override (optional)
     *   - maxpoints: Maximum points override (optional)
     *   - minpoints: Minimum points override (optional)
     *   - imagedraftitemid: Draft item ID for question images (optional)
     * @return int The question ID
     * @throws \moodle_exception If no editable round exists
     */
    public static function add_question(\stdClass $questiondata): int {
        global $DB;

        $kahoodleid = $questiondata->kahoodleid;

        // Get editable round ID, throw exception if there is no editable round.
        $roundid = self::get_editable_round_id($kahoodleid);
        if ($roundid === null) {
            throw new \moodle_exception('noeditableround', 'mod_kahoodle');
        }

        $time = time();

        // Get the next sort order for this kahoodle.
        $maxsortorder = $DB->get_field('kahoodle_questions', 'MAX(sortorder)', ['kahoodleid' => $kahoodleid]);
        $sortorder = $maxsortorder ? $maxsortorder + 1 : 1;

        // Create new question.
        $question = new \stdClass();
        $question->kahoodleid = $kahoodleid;
        $question->questiontype = $questiondata->questiontype;
        $question->sortorder = $sortorder;
        $question->timecreated = $time;
        $question->timemodified = $time;

        $questionid = $DB->insert_record('kahoodle_questions', $question);

        // Create first version of the question.
        $version = new \stdClass();
        $version->questionid = $questionid;
        $version->version = 1;
        $version->questiontext = $questiondata->questiontext;
        $version->questiontextformat = $questiondata->questiontextformat ?? FORMAT_HTML;
        $version->questionconfig = $questiondata->questionconfig ?? null;
        $version->answersconfig = $questiondata->answersconfig ?? null;
        $version->timecreated = $time;

        $versionid = $DB->insert_record('kahoodle_question_versions', $version);

        // Handle file uploads if draft item ID is provided.
        if (!empty($questiondata->imagedraftitemid)) {
            // Get the module context.
            $cm = get_coursemodule_from_instance('kahoodle', $kahoodleid, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);

            // Save files from draft area to the question version.
            file_save_draft_area_files(
                $questiondata->imagedraftitemid,
                $context->id,
                'mod_kahoodle',
                constants::FILEAREA_QUESTION_IMAGE,
                $versionid,
                ['subdirs' => false, 'maxfiles' => 10]
            );
        }

        // Get the next sort order for this round.
        $maxroundsortorder = $DB->get_field('kahoodle_round_questions', 'MAX(sortorder)', ['roundid' => $roundid]);
        $roundsortorder = $maxroundsortorder ? $maxroundsortorder + 1 : 1;

        // Link the question version to the editable round.
        $roundquestion = new \stdClass();
        $roundquestion->roundid = $roundid;
        $roundquestion->questionversionid = $versionid;
        $roundquestion->sortorder = $roundsortorder;
        $roundquestion->questionpreviewduration = $questiondata->questionpreviewduration ?? null;
        $roundquestion->questionduration = $questiondata->questionduration ?? null;
        $roundquestion->questionresultsduration = $questiondata->questionresultsduration ?? null;
        $roundquestion->maxpoints = $questiondata->maxpoints ?? null;
        $roundquestion->minpoints = $questiondata->minpoints ?? null;
        $roundquestion->totalresponses = null;
        $roundquestion->answerdistribution = null;
        $roundquestion->timecreated = $time;

        $DB->insert_record('kahoodle_round_questions', $roundquestion);

        return $questionid;
    }

    /**
     * Edit a question in the editable round
     *
     * Updates question content and/or behavior data. For content changes, creates a new
     * version if the current version is used in non-editable rounds.
     *
     * @param \stdClass $questiondata Question data including:
     *   - id: Question ID (required)
     *   - questiontext: Question text (optional)
     *   - questiontextformat: Text format (optional)
     *   - questionconfig: JSON config for question-specific settings (optional)
     *   - answersconfig: JSON config for answers (optional)
     *   - questionpreviewduration: Preview duration override (optional)
     *   - questionduration: Question duration override (optional)
     *   - questionresultsduration: Results duration override (optional)
     *   - maxpoints: Maximum points override (optional)
     *   - minpoints: Minimum points override (optional)
     * @return void
     * @throws \moodle_exception If no editable round exists or question not found
     */
    public static function edit_question(\stdClass $questiondata): void {
        global $DB;

        $questionid = $questiondata->id;

        // Get the question record.
        $question = $DB->get_record('kahoodle_questions', ['id' => $questionid], '*', MUST_EXIST);

        // Get editable round ID.
        $editableroundid = self::get_editable_round_id($question->kahoodleid);
        if ($editableroundid === null) {
            throw new \moodle_exception('noeditableround', 'mod_kahoodle');
        }

        // Find the version of this question used in the editable round.
        $sql = "SELECT rq.*, qv.version
                  FROM {kahoodle_round_questions} rq
                  JOIN {kahoodle_question_versions} qv ON qv.id = rq.questionversionid
                 WHERE rq.roundid = :roundid
                   AND qv.questionid = :questionid";
        $roundquestion = $DB->get_record_sql($sql, ['roundid' => $editableroundid, 'questionid' => $questionid]);

        if (!$roundquestion) {
            throw new \moodle_exception('questionnotfound', 'mod_kahoodle');
        }

        $hascontentchanges = isset($questiondata->questiontext) || isset($questiondata->questiontextformat) ||
            isset($questiondata->questionconfig) || isset($questiondata->answersconfig);

        // Update behavior data in kahoodle_round_questions table.
        $updatedroundquestion = new \stdClass();
        $updatedroundquestion->id = $roundquestion->id;
        $needsroundupdate = false;

        if (isset($questiondata->questionpreviewduration)) {
            $updatedroundquestion->questionpreviewduration = $questiondata->questionpreviewduration;
            $needsroundupdate = true;
        }
        if (isset($questiondata->questionduration)) {
            $updatedroundquestion->questionduration = $questiondata->questionduration;
            $needsroundupdate = true;
        }
        if (isset($questiondata->questionresultsduration)) {
            $updatedroundquestion->questionresultsduration = $questiondata->questionresultsduration;
            $needsroundupdate = true;
        }
        if (isset($questiondata->maxpoints)) {
            $updatedroundquestion->maxpoints = $questiondata->maxpoints;
            $needsroundupdate = true;
        }
        if (isset($questiondata->minpoints)) {
            $updatedroundquestion->minpoints = $questiondata->minpoints;
            $needsroundupdate = true;
        }

        if ($needsroundupdate) {
            $DB->update_record('kahoodle_round_questions', $updatedroundquestion);
        }

        // Handle content changes.
        if ($hascontentchanges) {
            // Check if this version is already used in any non-editable rounds.
            $sql = "SELECT COUNT(rq.id)
                      FROM {kahoodle_round_questions} rq
                      JOIN {kahoodle_rounds} r ON r.id = rq.roundid
                     WHERE rq.questionversionid = :versionid
                       AND (r.currentstage != :stage OR r.timestarted IS NOT NULL)";
            $usedinstartedrounds = $DB->count_records_sql($sql, [
                'versionid' => $roundquestion->questionversionid,
                'stage' => constants::STAGE_PREPARATION,
            ]);

            if ($usedinstartedrounds > 0) {
                // Create a new version.
                $currentversion = $DB->get_record(
                    'kahoodle_question_versions',
                    ['id' => $roundquestion->questionversionid],
                    '*',
                    MUST_EXIST
                );

                $newversion = new \stdClass();
                $newversion->questionid = $questionid;
                $newversion->version = $roundquestion->version + 1;
                $newversion->questiontext = $questiondata->questiontext ?? $currentversion->questiontext;
                $newversion->questiontextformat = $questiondata->questiontextformat ?? $currentversion->questiontextformat;
                $newversion->questionconfig = $questiondata->questionconfig ?? $currentversion->questionconfig;
                $newversion->answersconfig = $questiondata->answersconfig ?? $currentversion->answersconfig;
                $newversion->timecreated = time();

                $newversionid = $DB->insert_record('kahoodle_question_versions', $newversion);

                // Update the round question to use the new version.
                $DB->set_field('kahoodle_round_questions', 'questionversionid', $newversionid, ['id' => $roundquestion->id]);
            } else {
                // Edit the existing version.
                $updatedversion = new \stdClass();
                $updatedversion->id = $roundquestion->questionversionid;

                if (isset($questiondata->questiontext)) {
                    $updatedversion->questiontext = $questiondata->questiontext;
                }
                if (isset($questiondata->questiontextformat)) {
                    $updatedversion->questiontextformat = $questiondata->questiontextformat;
                }
                if (isset($questiondata->questionconfig)) {
                    $updatedversion->questionconfig = $questiondata->questionconfig;
                }
                if (isset($questiondata->answersconfig)) {
                    $updatedversion->answersconfig = $questiondata->answersconfig;
                }

                $DB->update_record('kahoodle_question_versions', $updatedversion);
            }

            // Update question timemodified.
            $DB->set_field('kahoodle_questions', 'timemodified', time(), ['id' => $questionid]);
        }
    }

    /**
     * Delete a question from the editable round
     *
     * Removes question from editable round. If the question version is used in non-editable rounds,
     * only removes the link. Otherwise also deletes the version and question if it's the only version.
     *
     * @param int $questionid The question ID
     * @return void
     * @throws \moodle_exception If no editable round exists or question not found
     */
    public static function delete_question(int $questionid): void {
        global $DB;

        // Get the question record.
        $question = $DB->get_record('kahoodle_questions', ['id' => $questionid], '*', MUST_EXIST);

        // Get editable round ID.
        $editableroundid = self::get_editable_round_id($question->kahoodleid);
        if ($editableroundid === null) {
            throw new \moodle_exception('noeditableround', 'mod_kahoodle');
        }

        // Find the version of this question used in the editable round.
        $sql = "SELECT rq.*, qv.version, qv.id as versionid
                  FROM {kahoodle_round_questions} rq
                  JOIN {kahoodle_question_versions} qv ON qv.id = rq.questionversionid
                 WHERE rq.roundid = :roundid
                   AND qv.questionid = :questionid";
        $roundquestion = $DB->get_record_sql($sql, ['roundid' => $editableroundid, 'questionid' => $questionid]);

        if (!$roundquestion) {
            throw new \moodle_exception('questionnotfound', 'mod_kahoodle');
        }

        // Always delete the link between the question and the editable round.
        $DB->delete_records('kahoodle_round_questions', ['id' => $roundquestion->id]);

        // Check if this version is used in any non-editable rounds.
        $sql = "SELECT COUNT(rq.id)
                  FROM {kahoodle_round_questions} rq
                  JOIN {kahoodle_rounds} r ON r.id = rq.roundid
                 WHERE rq.questionversionid = :versionid
                   AND (r.currentstage != :stage OR r.timestarted IS NOT NULL)";
        $usedinstartedrounds = $DB->count_records_sql($sql, [
            'versionid' => $roundquestion->versionid,
            'stage' => constants::STAGE_PREPARATION,
        ]);

        if ($usedinstartedrounds == 0) {
            // This version is not used in any started rounds, we can delete it.
            // But first check if it's used in any other editable rounds.
            $usedineditable = $DB->count_records('kahoodle_round_questions', ['questionversionid' => $roundquestion->versionid]);

            if ($usedineditable == 0) {
                // Not used anywhere, delete the version.
                $DB->delete_records('kahoodle_question_versions', ['id' => $roundquestion->versionid]);

                // Check if this was the only version of the question.
                $remainingversions = $DB->count_records('kahoodle_question_versions', ['questionid' => $questionid]);
                if ($remainingversions == 0) {
                    // Delete the question itself.
                    $DB->delete_records('kahoodle_questions', ['id' => $questionid]);
                }
            }
        }
    }

    /**
     * Not implemented yet
     *
     * @return void
     */
    public static function edit_past_question() {
        // TODO placeholder.
        // Sometimes teachers may want to edit questions that are already used in non-editable rounds,
        // usually to fix typos before showing the results to students.
        // In this case it is not possible to edit the number or order of options, it is only allowed
        // to edit the text of the question, options and/or images.

        // It is also possible to change the question properties like maxpoints/minpoints.
        // editing past questions may need regrading.
    }
}
