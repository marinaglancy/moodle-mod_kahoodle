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
 * Renderer for Kahoodle
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Outputs a screen reader only inline text.
     *
     * @param string|null $contents The content of the badge
     * @param string $badgestyle The style of the badge (info/primary/secondary/success/danger/warning)
     * @param string $title An optional title of the badge
     * @return string the HTML to output.
     */
    public function badge(
        ?string $contents,
        string $badgestyle = 'primary',
        string $title = '',
    ): string {
        global $CFG;
        if ($contents === null || trim($contents) === '') {
            return '';
        }
        $validstyles = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];
        if (!in_array($badgestyle, $validstyles, true)) {
            $badgestyle = 'primary';
        }
        if ($CFG->branch >= 500) {
            $classes = 'badge rounded-pill text-bg-' . $badgestyle;
        } else {
            $classes = 'badge badge-' . $badgestyle;
        }
        $attrs = $title ? ['title' => $title] : [];
        return \html_writer::span($contents, $classes, $attrs);
    }
}
