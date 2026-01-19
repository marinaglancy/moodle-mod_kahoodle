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

use core\exception\moodle_exception;
use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\entities\round_question;

/**
 * Class questions
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions {
    /**
     * Static cache for question type instances
     * @var null|array
     */
    protected static ?array $questiontypescache = null;

    /**
     * Get available question types
     *
     * @return \mod_kahoodle\local\questiontypes\base[] Array of question type instances
     */
    public static function get_question_types(): array {
        if (self::$questiontypescache === null) {
            $types = [
                new \mod_kahoodle\local\questiontypes\multichoice(),
                // Future question types can be added here.
            ];
            self::$questiontypescache = array_combine(
                array_map(fn($qt) => $qt->get_type(), $types),
                $types
            );
        }
        return array_values(self::$questiontypescache);
    }

    /**
     * Get a question type instance by its type property.
     *
     * @param string $type The type string (from base::get_type())
     * @return \mod_kahoodle\local\questiontypes\base|null The question type instance or null if not found
     */
    public static function get_question_type_instance(string $type): ?\mod_kahoodle\local\questiontypes\base {
        self::get_question_types(); // Ensure cache is populated.
        return self::$questiontypescache[$type] ?? null;
    }

    /**
     * Get the default question type instance
     *
     * @return \mod_kahoodle\local\questiontypes\base
     */
    public static function get_default_question_type(): \mod_kahoodle\local\questiontypes\base {
        $types = self::get_question_types();
        return reset($types);
    }

    /**
     * Get a question type instance by its type property, or return default if not found.
     *
     * @param string $type
     * @param bool $showdebugging show debugging message if not found
     * @return \mod_kahoodle\local\questiontypes\base
     */
    public static function get_question_type_instance_or_default(
        string $type,
        bool $showdebugging = true
    ): \mod_kahoodle\local\questiontypes\base {
        $questiontype = self::get_question_type_instance($type);
        if ($questiontype === null) {
            $questiontype = self::get_default_question_type();
            if ($showdebugging) {
                debugging("Unknown question type '" . s($type) . "' requested, using '" .
                    s($questiontype->get_type()) . "' as default.");
            }
        }
        return $questiontype;
    }

    /**
     * Get the last round for a Kahoodle activity
     *
     * Returns the most recent round, creating one if none exist.
     *
     * @param int $kahoodleid The Kahoodle activity ID
     * @return round Round entity
     */
    public static function get_last_round(int $kahoodleid): round {
        global $DB;

        // Get all rounds for this kahoodle, ordered by creation time (newest first).
        // In the same query we validate that kahoodle itself exists.
        $order = 'CASE WHEN currentstage = :preparation THEN 0 ELSE 1 END, timecreated DESC, id DESC';
        $rounds = $DB->get_records_sql(
            "SELECT r.* from {kahoodle} k
            LEFT JOIN {kahoodle_rounds} r ON r.kahoodleid = k.id
            WHERE k.id = :kahoodleid
            ORDER BY $order",
            ['kahoodleid' => $kahoodleid, 'preparation' => constants::STAGE_PREPARATION],
            0,
            1
        );
        if (empty($rounds)) {
            // Kahoodle does not exist. Throw exception.
            $DB->get_record('kahoodle', ['id' => $kahoodleid], '*', MUST_EXIST);
        }

        $round = reset($rounds);
        if (empty($round->id)) {
            // No rounds yet, create one.
            $record = new \stdClass();
            $record->kahoodleid = $kahoodleid;
            $record->name = 'Round 1'; // TODO do we need a name field?
            $record->currentstage = constants::STAGE_PREPARATION;
            $record->currentquestion = null;
            $record->stagestarttime = null;

            // Get default lobby duration from kahoodle instance.
            $kahoodle = $DB->get_record('kahoodle', ['id' => $kahoodleid], 'lobbyduration', MUST_EXIST);
            $record->lobbyduration = $kahoodle->lobbyduration;

            $record->timecreated = time();
            $record->timestarted = null;
            $record->timecompleted = null;
            $record->timemodified = time();

            $record->id = $DB->insert_record('kahoodle_rounds', $record);
            return round::create_from_object($record);
        }

        // Get the last (most recent) round.
        return round::create_from_object($round);
    }

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
        $lastround = self::get_last_round($kahoodleid);

        // Return the round ID only if it is editable.
        if ($lastround->is_editable()) {
            return $lastround->get_id();
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
     *   - questionconfig: Type-specific configuration (optional)
     *   - questionpreviewduration: Preview duration override (optional)
     *   - questionduration: Question duration override (optional)
     *   - questionresultsduration: Results duration override (optional)
     *   - maxpoints: Maximum points override (optional)
     *   - minpoints: Minimum points override (optional)
     *   - imagedraftitemid: Draft item ID for question images (optional)
     * @return round_question The question entity
     * @throws \moodle_exception If no editable round exists
     */
    public static function add_question(\stdClass $questiondata): round_question {
        global $DB;

        $kahoodleid = $questiondata->kahoodleid;

        // Get editable round ID, throw exception if there is no editable round.
        $round = self::get_last_round($kahoodleid);
        if (!$round->is_editable()) {
            throw new \moodle_exception('noeditableround', 'mod_kahoodle');
        }
        $roundquestionobj = round_question::new_for_round_and_type($round, $questiondata->questiontype ?? null);
        $defaultdata = $roundquestionobj->get_data();
        $roundquestionobj->get_question_type()->sanitize_data($roundquestionobj, $questiondata);

        $time = time();

        // Create new question.
        $question = new \stdClass();
        $question->kahoodleid = $kahoodleid;
        $question->questiontype = $defaultdata->questiontype; // Normalised value.
        $question->timecreated = $time;

        $questionid = $DB->insert_record('kahoodle_questions', $question);

        // Create first version of the question.
        $version = new \stdClass();
        $version->questionid = $questionid;
        $version->version = 1;
        foreach (constants::FIELDS_QUESTION_VERSION as $field) {
            if (property_exists($questiondata, $field)) {
                $version->$field = $questiondata->$field;
            } else {
                $version->$field = $defaultdata->$field;
            }
        }
        $version->timecreated = $time;
        $version->timemodified = $time;

        $versionid = $DB->insert_record('kahoodle_question_versions', $version);

        // Handle file uploads if draft item ID is provided.
        if (!empty($questiondata->imagedraftitemid)) {
            $context = $round->get_context();

            if ($roundquestionobj->get_data()->questionformat == constants::QUESTIONFORMAT_RICHTEXT) {
                // For rich text, save files and rewrite @@PLUGINFILE@@ URLs in text.
                $questiontext = file_save_draft_area_files(
                    $questiondata->imagedraftitemid,
                    $context->id,
                    'mod_kahoodle',
                    constants::FILEAREA_QUESTION_IMAGE,
                    $versionid,
                    ['subdirs' => false, 'maxfiles' => EDITOR_UNLIMITED_FILES],
                    $version->questiontext
                );
                // Update the version record with rewritten text.
                $DB->set_field('kahoodle_question_versions', 'questiontext', $questiontext, ['id' => $versionid]);
            } else {
                // For plain text, just save the image file(s).
                file_save_draft_area_files(
                    $questiondata->imagedraftitemid,
                    $context->id,
                    'mod_kahoodle',
                    constants::FILEAREA_QUESTION_IMAGE,
                    $versionid,
                    ['subdirs' => false, 'maxfiles' => 1]
                );
            }
        }

        // Get the next sort order for this round.
        $maxroundsortorder = $DB->get_field(
            'kahoodle_round_questions',
            'MAX(sortorder)',
            ['roundid' => $round->get_id()]
        );
        $roundsortorder = $maxroundsortorder ? $maxroundsortorder + 1 : 1;

        // Link the question version to the editable round.
        $roundquestion = new \stdClass();
        $roundquestion->roundid = $round->get_id();
        $roundquestion->questionversionid = $versionid;
        $roundquestion->sortorder = $roundsortorder;
        foreach (constants::FIELDS_ROUND_QUESTION as $field) {
            if (property_exists($questiondata, $field)) {
                $roundquestion->$field = $questiondata->$field;
            } else {
                $roundquestion->$field = $defaultdata->$field;
            }
        }
        // Timestamps.
        $roundquestion->timecreated = $time;
        $roundquestion->timemodified = $time;

        $id = $DB->insert_record('kahoodle_round_questions', $roundquestion);

        return round_question::create_from_round_question_id($id);
    }

    /**
     * Edit a question in the editable round
     *
     * Updates question content and/or behavior data. For content changes, creates a new
     * version if the current version is used in non-editable rounds.
     *
     * @param round_question $roundquestion The round question entity to edit
     * @param \stdClass $questiondata Question data including:
     *   - questiontext: Question text (optional)
     *   - questiontextformat: Text format (optional)
     *   - questionconfig: Type-specific configuration (optional)
     *   - questionpreviewduration: Preview duration override (optional)
     *   - questionduration: Question duration override (optional)
     *   - questionresultsduration: Results duration override (optional)
     *   - maxpoints: Maximum points override (optional)
     *   - minpoints: Minimum points override (optional)
     * @return void
     * @throws \moodle_exception If no editable round exists or question not found
     */
    public static function edit_question(round_question $roundquestion, \stdClass $questiondata): void {
        global $DB;

        if (!$roundquestion->get_id()) {
            throw new \moodle_exception('questionnotfound', 'mod_kahoodle');
        }

        $questionid = $roundquestion->get_data()->questionid;
        $round = $roundquestion->get_round();
        $roundquestion->get_question_type()->sanitize_data($roundquestion, $questiondata);

        if (!$round->is_editable()) {
            // TODO at the moment we only support editing questions in editable rounds.
            throw new \moodle_exception('noeditableround', 'mod_kahoodle');
        }

        $contentchanges = [];
        $content = [];
        foreach (constants::FIELDS_QUESTION_VERSION as $field) {
            if (property_exists($questiondata, $field) && $questiondata->$field !== $roundquestion->get_data()->$field) {
                $contentchanges[$field] = $questiondata->$field;
                $content[$field] = $questiondata->$field;
            } else {
                $content[$field] = $roundquestion->get_data()->$field;
            }
        }

        $behaviorchanges = [];
        foreach (constants::FIELDS_ROUND_QUESTION as $field) {
            if (property_exists($questiondata, $field) && $questiondata->$field !== $roundquestion->get_data()->$field) {
                $behaviorchanges[$field] = $questiondata->$field;
            }
        }

        // Update behavior data in kahoodle_round_questions table.
        if ($behaviorchanges) {
            $behaviorchanges['id'] = $roundquestion->get_id();
            $DB->update_record('kahoodle_round_questions', $behaviorchanges);
        }

        // Handle content changes.
        if ($contentchanges || !empty($questiondata->imagedraftitemid)) {
            // Check if this version is already used in any non-editable rounds.
            $sql = "SELECT COUNT(rq.id)
                      FROM {kahoodle_round_questions} rq
                      JOIN {kahoodle_rounds} r ON r.id = rq.roundid
                     WHERE rq.questionversionid = :versionid
                       AND (r.id != :roundid OR r.timestarted IS NOT NULL)";
            $usedinotherrounds = $DB->count_records_sql($sql, [
                'versionid' => $roundquestion->get_data()->questionversionid,
                'roundid' => $roundquestion->get_round()->get_id(),
            ]);

            $versionid = $roundquestion->get_data()->questionversionid;

            if ($usedinotherrounds > 0) {
                // Create a new version.
                // TODO do we really need the 'version' field? What is it used for? Consider removing it.
                $lastversion = (int)$DB->get_field_sql(
                    'SELECT MAX(version) AS version FROM {kahoodle_question_versions} WHERE questionid = ?',
                    [$questionid]
                );
                $content['version'] = $lastversion + 1;
                $content['questionid'] = $questionid;
                $content['timecreated'] = $content['timemodified'] = time();

                $versionid = $DB->insert_record('kahoodle_question_versions', $content);

                // Update the round question to use the new version.
                $DB->set_field(
                    'kahoodle_round_questions',
                    'questionversionid',
                    $versionid,
                    ['id' => $roundquestion->get_id()]
                );
            } else if ($contentchanges) {
                // Edit the existing version.
                $contentchanges['id'] = $roundquestion->get_data()->questionversionid;
                $contentchanges['timemodified'] = time();
                $DB->update_record('kahoodle_question_versions', $contentchanges);
            }

            // Handle file uploads if draft item ID is provided.
            if (!empty($questiondata->imagedraftitemid)) {
                $context = $round->get_context();

                if ($roundquestion->get_data()->questionformat == constants::QUESTIONFORMAT_RICHTEXT) {
                    // For rich text, save files and rewrite @@PLUGINFILE@@ URLs in text.
                    $questiontext = file_save_draft_area_files(
                        $questiondata->imagedraftitemid,
                        $context->id,
                        'mod_kahoodle',
                        constants::FILEAREA_QUESTION_IMAGE,
                        $versionid,
                        ['subdirs' => false, 'maxfiles' => EDITOR_UNLIMITED_FILES],
                        $content['questiontext']
                    );
                    // Update the version record with rewritten text.
                    $DB->set_field('kahoodle_question_versions', 'questiontext', $questiontext, ['id' => $versionid]);
                } else {
                    // For plain text, just save the image file(s).
                    file_save_draft_area_files(
                        $questiondata->imagedraftitemid,
                        $context->id,
                        'mod_kahoodle',
                        constants::FILEAREA_QUESTION_IMAGE,
                        $versionid,
                        ['subdirs' => false, 'maxfiles' => 1]
                    );
                }
            }
        }
    }

    /**
     * Delete a question from the editable round
     *
     * Removes question from editable round. If the question version is used in non-editable rounds,
     * only removes the link. Otherwise also deletes the version and question if it's the only version.
     *
     * @param round_question $roundquestion The round question entity to delete
     * @return void
     * @throws \moodle_exception If no editable round exists or question not found
     */
    public static function delete_question(round_question $roundquestion): void {
        global $DB;

        // Get editable round ID.
        $round = $roundquestion->get_round();
        if (!$round->is_editable()) {
            throw new \moodle_exception('noeditableround', 'mod_kahoodle');
        }

        // Always delete the link between the question and the editable round.
        $DB->delete_records('kahoodle_round_questions', ['id' => $roundquestion->get_id()]);

        // Fix sortorder for the round.
        self::fix_round_sortorder($round->get_id());

        // Check if this version is used in any non-editable rounds.
        $questionversionid = $roundquestion->get_data()->questionversionid;
        $usedinotherrounds = $DB->count_records('kahoodle_round_questions', ['questionversionid' => $questionversionid]);

        if ($usedinotherrounds == 0) {
            // This version is not used in any started rounds, we can delete it.
            $DB->delete_records('kahoodle_question_versions', ['id' => $questionversionid]);
            // Check if this was the only version of the question.
            $questionid = $roundquestion->get_question_id();
            $remainingversions = $DB->count_records('kahoodle_question_versions', ['questionid' => $questionid]);
            if ($remainingversions == 0) {
                // Delete the question itself.
                $DB->delete_records('kahoodle_questions', ['id' => $questionid]);
            }
        }
    }

    /**
     * Fix sortorder for questions in a round
     *
     * Renumbers questions sequentially (1, 2, 3, ...) based on their current order.
     *
     * @param int $roundid The round ID
     * @return void
     */
    protected static function fix_round_sortorder(int $roundid): void {
        global $DB;

        $questions = $DB->get_records(
            'kahoodle_round_questions',
            ['roundid' => $roundid],
            'sortorder ASC',
            'id, sortorder'
        );
        $sortorder = 1;
        foreach ($questions as $question) {
            if ($question->sortorder != $sortorder) {
                $DB->set_field('kahoodle_round_questions', 'sortorder', $sortorder, ['id' => $question->id]);
            }
            $sortorder++;
        }
    }
}
