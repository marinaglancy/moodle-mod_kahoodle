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

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round_question;
use moodle_url;
use renderable;
use stdClass;
use templatable;

/**
 * Output class for rendering round question data for templates
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roundquestion implements renderable, templatable {
    /** @var round_question The round question entity */
    protected round_question $roundquestion;
    /** @var string Current question stage (preview/question/results) */
    protected string $stage;
    /** @var bool Whether to generate mock results for preview */
    protected bool $mockresults;

    /**
     * Constructor
     *
     * @param round_question $roundquestion The round question entity
     * @param string $stage Current question stage (preview/question/results)
     * @param bool $mockresults Whether to generate mock results for preview
     */
    public function __construct(round_question $roundquestion, string $stage, bool $mockresults = false) {
        $this->roundquestion = $roundquestion;
        $this->stage = $stage;
        $this->mockresults = $mockresults;
    }

    /**
     * Export this data for use in a Mustache template
     *
     * @param \renderer_base $output
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output): stdClass {
        global $CFG;

        $templatedata = new stdClass();

        // Quiz title and question counter.
        $data = $this->roundquestion->get_data();
        $templatedata->sortorder = $data->sortorder;
        $templatedata->roundquestionid = $data->id;

        // Question text.
        $templatedata->questiontext = $this->roundquestion->display_question_text();
        $templatedata->questiontextcompact = $this->roundquestion->preview_question_text();

        // Question image.
        foreach ($this->get_image_data() as $key => $value) {
            $templatedata->$key = $value;
        }

        // Question type and type-specific data (JSON-encoded for JS to decode).
        $questiontype = $this->roundquestion->get_question_type();
        $templatedata->questiontype = $questiontype->get_type();
        $templatedata->typedata = json_encode($questiontype->export_template_data(
            $this->roundquestion,
            $this->stage,
            $this->mockresults
        ));

        // Background.
        $templatedata->backgroundurl = $CFG->wwwroot . '/mod/kahoodle/pix/classroom-bg.jpg';

        // Question stage information.
        $templatedata->stage = $this->stage;
        $template = 'mod_kahoodle/questiontypes/' . strtolower($templatedata->questiontype) .
            '/facilitator_' . $templatedata->stage;
        try {
            \core\output\mustache_template_finder::get_template_filepath($template);
        } catch (\moodle_exception $e) {
            // Template not found, will use fallback.
            $template = 'mod_kahoodle/facilitator/' . $templatedata->stage;
        }
        $templatedata->template = $template;
        $templatedata->duration = $this->roundquestion->get_stage_duration($this->stage);

        return $templatedata;
    }

    /**
     * Get image data for the question
     *
     * @return array
     */
    protected function get_image_data(): array {
        $result = [
            'hasimage' => false,
            'imageurl' => '',
            'imagealt' => '',
            'imagelandscape' => false,
        ];

        // For rich text, images are embedded in the question text.
        $questionformat = $this->roundquestion->get_data()->questionformat;
        if ($questionformat == constants::QUESTIONFORMAT_RICHTEXT) {
            return $result;
        }

        // For plain text, get the uploaded image file.
        $files = $this->roundquestion->get_question_files();

        if (empty($files)) {
            return $result;
        }

        // Get the first image file.
        $file = reset($files);
        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        $result['hasimage'] = true;
        $result['imageurl'] = $url->out();
        $result['imagealt'] = $file->get_filename();

        // Determine if image is landscape.
        $imageinfo = $file->get_imageinfo();
        if ($imageinfo && isset($imageinfo['width']) && isset($imageinfo['height'])) {
            $result['imagelandscape'] = $imageinfo['width'] > $imageinfo['height'];
        }

        return $result;
    }
}
