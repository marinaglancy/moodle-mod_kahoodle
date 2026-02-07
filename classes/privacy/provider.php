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

namespace mod_kahoodle\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_kahoodle.
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table(
            'kahoodle_participants',
            [
                'userid' => 'privacy:metadata:kahoodle_participants:userid',
                'displayname' => 'privacy:metadata:kahoodle_participants:displayname',
                'totalscore' => 'privacy:metadata:kahoodle_participants:totalscore',
                'finalrank' => 'privacy:metadata:kahoodle_participants:finalrank',
                'timecreated' => 'privacy:metadata:kahoodle_participants:timecreated',
            ],
            'privacy:metadata:kahoodle_participants'
        );

        $items->add_database_table(
            'kahoodle_responses',
            [
                'response' => 'privacy:metadata:kahoodle_responses:response',
                'iscorrect' => 'privacy:metadata:kahoodle_responses:iscorrect',
                'points' => 'privacy:metadata:kahoodle_responses:points',
                'responsetime' => 'privacy:metadata:kahoodle_responses:responsetime',
                'timecreated' => 'privacy:metadata:kahoodle_responses:timecreated',
            ],
            'privacy:metadata:kahoodle_responses'
        );

        $items->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {kahoodle} k ON k.id = cm.instance
                  JOIN {kahoodle_rounds} r ON r.kahoodleid = k.id
                  JOIN {kahoodle_participants} p ON p.roundid = r.id
                 WHERE p.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'kahoodle',
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT p.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {kahoodle} k ON k.id = cm.instance
                  JOIN {kahoodle_rounds} r ON r.kahoodleid = k.id
                  JOIN {kahoodle_participants} p ON p.roundid = r.id
                 WHERE cm.id = :cmid
                   AND p.userid IS NOT NULL";

        $userlist->add_from_sql('userid', $sql, [
            'cmid' => $context->instanceid,
            'modname' => 'kahoodle',
        ]);
    }

    /**
     * Export personal data for the given approved_contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT p.id AS participantid,
                       p.displayname,
                       p.totalscore,
                       p.finalrank,
                       p.timecreated AS participanttimecreated,
                       r.name AS roundname,
                       cm.id AS cmid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {kahoodle} k ON k.id = cm.instance
                  JOIN {kahoodle_rounds} r ON r.kahoodleid = k.id
                  JOIN {kahoodle_participants} p ON p.roundid = r.id
                 WHERE ctx.id {$contextsql}
                   AND p.userid = :userid
              ORDER BY cm.id, r.id, p.id";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'kahoodle',
            'userid' => $user->id,
        ] + $contextparams;

        $lastcmid = null;
        $participations = [];

        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            if ($lastcmid !== null && $lastcmid != $record->cmid) {
                self::export_kahoodle_data($lastcmid, $participations, $user);
                $participations = [];
            }

            // Fetch responses for this participant.
            $responses = $DB->get_records_sql(
                "SELECT resp.response, resp.iscorrect, resp.points, resp.responsetime, resp.timecreated
                   FROM {kahoodle_responses} resp
                  WHERE resp.participantid = :participantid
                  ORDER BY resp.id",
                ['participantid' => $record->participantid]
            );

            $participation = [
                'round' => $record->roundname,
                'displayname' => $record->displayname,
                'totalscore' => $record->totalscore,
                'finalrank' => $record->finalrank,
                'timecreated' => transform::datetime($record->participanttimecreated),
                'responses' => [],
            ];

            foreach ($responses as $response) {
                $participation['responses'][] = [
                    'response' => $response->response,
                    'iscorrect' => transform::yesno($response->iscorrect),
                    'points' => $response->points,
                    'responsetime' => $response->responsetime,
                    'timecreated' => transform::datetime($response->timecreated),
                ];
            }

            $participations[] = $participation;
            $lastcmid = $record->cmid;
        }
        $records->close();

        if (!empty($participations)) {
            self::export_kahoodle_data($lastcmid, $participations, $user);
        }
    }

    /**
     * Export kahoodle data for a single activity.
     *
     * @param int $cmid The course module ID.
     * @param array $participations The participation data.
     * @param \stdClass $user The user record.
     */
    protected static function export_kahoodle_data(int $cmid, array $participations, \stdClass $user) {
        $context = \context_module::instance($cmid);
        $contextdata = helper::get_context_data($context, $user);
        $contextdata->participations = $participations;
        writer::with_context($context)->export_data([], $contextdata);
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('kahoodle', $context->instanceid);
        if (!$cm) {
            return;
        }

        // Get all participant IDs for this kahoodle.
        $participantids = $DB->get_fieldset_sql(
            "SELECT p.id
               FROM {kahoodle_participants} p
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = :kahoodleid",
            ['kahoodleid' => $cm->instance]
        );

        if ($participantids) {
            [$insql, $params] = $DB->get_in_or_equal($participantids);
            $DB->delete_records_select('kahoodle_responses', "participantid $insql", $params);
        }

        // Delete participants.
        $DB->execute(
            "DELETE FROM {kahoodle_participants}
              WHERE roundid IN (SELECT id FROM {kahoodle_rounds} WHERE kahoodleid = :kahoodleid)",
            ['kahoodleid' => $cm->instance]
        );

        // Delete avatar files.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_kahoodle', \mod_kahoodle\constants::FILEAREA_AVATAR);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('kahoodle', $context->instanceid);
            if (!$cm) {
                continue;
            }

            self::delete_user_data_in_instance($cm->instance, [$userid], $context);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('kahoodle', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        self::delete_user_data_in_instance($cm->instance, $userids, $context);
    }

    /**
     * Delete data for specific users in a kahoodle instance.
     *
     * @param int $kahoodleid The kahoodle instance ID.
     * @param array $userids The user IDs to delete data for.
     * @param \context_module $context The context.
     */
    protected static function delete_user_data_in_instance(int $kahoodleid, array $userids, \context_module $context) {
        global $DB;

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Get participant IDs for these users in this kahoodle.
        $participantids = $DB->get_fieldset_sql(
            "SELECT p.id
               FROM {kahoodle_participants} p
               JOIN {kahoodle_rounds} r ON r.id = p.roundid
              WHERE r.kahoodleid = :kahoodleid
                AND p.userid $usersql",
            ['kahoodleid' => $kahoodleid] + $userparams
        );

        if ($participantids) {
            [$pinsql, $pparams] = $DB->get_in_or_equal($participantids, SQL_PARAMS_NAMED);

            // Delete responses.
            $DB->delete_records_select('kahoodle_responses', "participantid $pinsql", $pparams);

            // Delete avatar files for each participant.
            $fs = get_file_storage();
            foreach ($participantids as $pid) {
                $fs->delete_area_files($context->id, 'mod_kahoodle', \mod_kahoodle\constants::FILEAREA_AVATAR, $pid);
            }

            // Delete participants.
            $DB->delete_records_select('kahoodle_participants', "id $pinsql", $pparams);
        }
    }
}
