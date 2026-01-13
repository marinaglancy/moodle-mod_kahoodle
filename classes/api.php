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
}
