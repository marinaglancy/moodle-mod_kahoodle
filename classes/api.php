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

use mod_kahoodle\local\entities\round;

/**
 * API class for Kahoodle instance management
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /**
     * Create a new Kahoodle instance
     *
     * @param \stdClass $moduleinstance Instance data
     * @param int|null $coursemoduleid Optional course module ID for completion event
     * @return int New instance ID
     */
    public static function create_instance(\stdClass $moduleinstance, ?int $coursemoduleid = null): int {
        global $DB;

        $moduleinstance->timecreated = time();
        $moduleinstance->timemodified = time();

        $id = $DB->insert_record('kahoodle', $moduleinstance);

        // Update completion date event if course module ID is provided.
        if ($coursemoduleid) {
            $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
            \core_completion\api::update_completion_date_event(
                $coursemoduleid,
                'kahoodle',
                $id,
                $completiontimeexpected
            );
        }

        return $id;
    }

    /**
     * Update an existing Kahoodle instance
     *
     * @param \stdClass $moduleinstance Instance data (must include id field)
     * @param int|null $coursemoduleid Optional course module ID for completion event
     * @return bool True on success
     */
    public static function update_instance(\stdClass $moduleinstance, ?int $coursemoduleid = null): bool {
        global $DB;

        $moduleinstance->timemodified = time();

        $DB->update_record('kahoodle', $moduleinstance);

        // Update completion date event if course module ID is provided.
        if ($coursemoduleid) {
            $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
            \core_completion\api::update_completion_date_event(
                $coursemoduleid,
                'kahoodle',
                $moduleinstance->id,
                $completiontimeexpected
            );
        }

        return true;
    }

    /**
     * Delete a Kahoodle instance
     *
     * @param int $id Instance ID
     * @return bool True on success, false if instance not found
     */
    public static function delete_instance(int $id): bool {
        global $DB;

        $record = $DB->get_record('kahoodle', ['id' => $id]);
        if (!$record) {
            return false;
        }

        // Delete all calendar events.
        $events = $DB->get_records('event', ['modulename' => 'kahoodle', 'instance' => $record->id]);
        foreach ($events as $event) {
            \calendar_event::load($event)->delete();
        }

        // Get all rounds for this instance.
        $rounds = $DB->get_records('kahoodle_rounds', ['kahoodleid' => $id], '', 'id');
        if ($rounds) {
            $roundids = array_keys($rounds);
            [$insql, $params] = $DB->get_in_or_equal($roundids);

            // Get all participants for these rounds.
            $participants = $DB->get_records_select('kahoodle_participants', "roundid $insql", $params, '', 'id');
            if ($participants) {
                $participantids = array_keys($participants);
                [$pinsql, $pparams] = $DB->get_in_or_equal($participantids);

                // Delete all responses for these participants.
                $DB->delete_records_select('kahoodle_responses', "participantid $pinsql", $pparams);
            }

            // Delete all participants for these rounds.
            $DB->delete_records_select('kahoodle_participants', "roundid $insql", $params);

            // Delete all round questions for these rounds.
            $DB->delete_records_select('kahoodle_round_questions', "roundid $insql", $params);

            // Delete all rounds.
            $DB->delete_records_select('kahoodle_rounds', "id $insql", $params);
        }

        // Get all questions for this instance.
        $questions = $DB->get_records('kahoodle_questions', ['kahoodleid' => $id], '', 'id');
        if ($questions) {
            $questionids = array_keys($questions);
            [$qinsql, $qparams] = $DB->get_in_or_equal($questionids);

            // Delete all question versions.
            $DB->delete_records_select('kahoodle_question_versions', "questionid $qinsql", $qparams);

            // Delete all questions.
            $DB->delete_records_select('kahoodle_questions', "id $qinsql", $qparams);
        }

        // Delete the instance.
        $DB->delete_records('kahoodle', ['id' => $id]);

        return true;
    }

    /**
     * Get all rounds for a Kahoodle activity, creates a new one if none exist
     *
     * @param int $kahoodleid The Kahoodle activity ID
     * @param int $limit
     * @param \stdClass|null $kahoodle Optional Kahoodle activity record, if known
     * @param \cm_info|null $cm Optional course module, if known
     * @return round[] Array of round entities indexed by their IDs, ordered by non-archived first, then by timecreated DESC
     */
    public static function get_all_rounds(
        int $kahoodleid,
        int $limit = 0,
        ?\stdClass $kahoodle = null,
        ?\cm_info $cm = null
    ): array {
        global $DB;

        // Get all rounds for this kahoodle, ordered by creation time (newest first).
        // In the same query we validate that kahoodle itself exists.
        $order = '
            CASE WHEN currentstage = :preparation THEN 0 ELSE CASE WHEN currentstage <> :archived THEN 1 ELSE 2 END END,
            timecreated DESC,
            id DESC';
        $rounds = $DB->get_records_sql(
            "SELECT r.* from {kahoodle} k
            LEFT JOIN {kahoodle_rounds} r ON r.kahoodleid = k.id
            WHERE k.id = :kahoodleid
            ORDER BY $order",
            [
                'kahoodleid' => $kahoodleid,
                'preparation' => constants::STAGE_PREPARATION,
                'archived' => constants::STAGE_ARCHIVED,
            ],
            0,
            $limit
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
            $record->name = get_string('roundname', 'mod_kahoodle', 1);
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
            $rounds = [$record->id => $record];
        }

        return array_map(function ($record) use ($kahoodle, $cm) {
            return round::create_from_object($record, $kahoodle, $cm);
        }, $rounds);
    }
}
