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

namespace mod_kahoodle\output;

/**
 * Output class for the facilitator view managing kahoodle round
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class facilitator implements \renderable, \templatable {
    /**
     * Constructor
     *
     * @param \mod_kahoodle\local\entities\round $round The round
     */
    public function __construct(
        /** @var \mod_kahoodle\local\entities\round */
        protected \mod_kahoodle\local\entities\round $round
    ) {
    }

    /**
     * Export this data for use in a Mustache template
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        $stage = $this->round->get_current_stage();
        return $stage->export_data_for_facilitators();
    }
}
