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

namespace mod_kahoodle\event;

/**
 * Event course_module_viewed
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_viewed extends \core\event\course_module_viewed {
    /**
     * Init method.
     */
    protected function init() {
        parent::init();
        $this->data['objecttable'] = 'kahoodle';
    }

    /**
     * Creates an instance of event
     *
     * In the fully anonymous mode the event is anonymous, otherwise the order of the views in the logs
     * (for example, right after joining) would reveal which participant is which user.
     *
     * @param \stdClass $record
     * @param \cm_info|\stdClass $cm
     * @param \stdClass $course
     * @return course_module_viewed
     */
    public static function create_from_record($record, $cm, $course) {
        $isanonymous = (int)($record->identitymode ?? 0) === \mod_kahoodle\constants::IDENTITYMODE_ANONYMOUS;
        /** @var course_module_viewed $event */
        $event = self::create([
            'objectid' => $record->id,
            'context' => \context_module::instance($cm->id),
            'anonymous' => (int)$isanonymous,
        ]);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('kahoodle', $record);
        return $event;
    }
}
