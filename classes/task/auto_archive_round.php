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

namespace mod_kahoodle\task;

use mod_kahoodle\local\entities\round;
use mod_kahoodle\local\game\progress;

/**
 * Ad-hoc task to automatically archive a kahoodle round if it is still in progress
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_archive_round extends \core\task\adhoc_task {
    /**
     * Execute the task
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $roundid = $data->roundid;

        try {
            $round = round::create_from_id($roundid);
        } catch (\dml_missing_record_exception $e) {
            // Round has been deleted, nothing to do.
            return;
        }

        $autoarchivetime = $round->get_auto_archive_time();
        if ($autoarchivetime !== null && $autoarchivetime <= time()) {
            progress::finish_game($round);
        }
    }

    /**
     * Schedule this task to run after the given delay for the given round
     *
     * @param round $round
     */
    public static function schedule(round $round): void {
        global $USER;
        $autoarchivetime = $round->get_auto_archive_time();
        if ($autoarchivetime !== null && $autoarchivetime > time()) {
            $task = new self();
            $task->set_custom_data((object)['roundid' => $round->get_id()]);
            $task->set_next_run_time($autoarchivetime + 1);
            $task->set_userid($USER->id);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }
}
