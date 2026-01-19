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

    /** @var int Total number of questions in the round */
    protected int $totalquestions;

    /** @var bool Whether user can control the quiz */
    protected bool $cancontrol;

    /** @var bool Whether this is a preview mode */
    protected bool $ispreview;

    /** @var bool Whether to skip format_text (for web service usage) */
    protected bool $forwebservice;

    /**
     * Constructor
     *
     * @param round_question $roundquestion The round question entity
     * @param int $totalquestions Total number of questions in the round
     * @param bool $cancontrol Whether user can control the quiz
     * @param bool $ispreview Whether this is a preview mode
     * @param bool $forwebservice Whether this is for web service (skip format_text)
     */
    public function __construct(
        round_question $roundquestion,
        int $totalquestions,
        bool $cancontrol = false,
        bool $ispreview = false,
        bool $forwebservice = false
    ) {
        $this->roundquestion = $roundquestion;
        $this->totalquestions = $totalquestions;
        $this->cancontrol = $cancontrol;
        $this->ispreview = $ispreview;
        $this->forwebservice = $forwebservice;
    }

    /**
     * Export this data for use in a Mustache template
     *
     * @param \renderer_base $output
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output): stdClass {
        $data = $this->roundquestion->get_data();
        $round = $this->roundquestion->get_round();
        $kahoodle = $round->get_kahoodle();
        $context = $round->get_context();

        $templatedata = new stdClass();

        // Quiz title and question counter.
        $templatedata->quiztitle = format_string($kahoodle->name, true, ['context' => $context]);
        $templatedata->sortorder = $data->sortorder;
        $templatedata->totalquestions = $this->totalquestions;
        $templatedata->roundquestionid = $data->id;

        // Question text.
        $templatedata->questiontext = $this->format_question_text($context);

        // Question image.
        $imagedata = $this->get_image_data($context);
        $templatedata->hasimage = $imagedata['hasimage'];
        $templatedata->imageurl = $imagedata['imageurl'];
        $templatedata->imagealt = $imagedata['imagealt'];
        $templatedata->imagelandscape = $imagedata['imagelandscape'];

        // Answer options (for multichoice questions).
        $templatedata->options = $this->get_options();

        // Progress and control.
        $templatedata->progresspercent = 100; // Full progress for preview.
        $templatedata->cancontrol = $this->cancontrol;
        $templatedata->ispreview = $this->ispreview;
        $templatedata->ispaused = false;

        return $templatedata;
    }

    /**
     * Format the question text
     *
     * @param \context_module $context
     * @return string
     */
    protected function format_question_text(\context_module $context): string {
        $data = $this->roundquestion->get_data();
        $text = $data->questiontext ?? '';

        if ($data->questionformat == constants::QUESTIONFORMAT_RICHTEXT) {
            // Rewrite plugin file URLs for rich text.
            $text = file_rewrite_pluginfile_urls(
                $text,
                'pluginfile.php',
                $context->id,
                'mod_kahoodle',
                constants::FILEAREA_QUESTION_IMAGE,
                $data->questionversionid
            );

            if ($this->forwebservice) {
                // For web services, use external_format_text to avoid double escaping.
                [$text] = \core_external\util::format_text(
                    $text,
                    FORMAT_HTML,
                    $context,
                    'mod_kahoodle',
                    constants::FILEAREA_QUESTION_IMAGE,
                    $data->questionversionid
                );
                return $text;
            }

            return format_text($text, FORMAT_HTML, ['context' => $context]);
        }

        // Plain text format.
        if ($this->forwebservice) {
            [$text] = \core_external\util::format_text(
                $text,
                FORMAT_PLAIN,
                $context,
                'mod_kahoodle',
                constants::FILEAREA_QUESTION_IMAGE,
                $data->questionversionid
            );
            return $text;
        }

        return format_text($text, FORMAT_PLAIN, ['context' => $context]);
    }

    /**
     * Get image data for the question
     *
     * @param \context_module $context
     * @return array
     */
    protected function get_image_data(\context_module $context): array {
        $data = $this->roundquestion->get_data();
        $result = [
            'hasimage' => false,
            'imageurl' => '',
            'imagealt' => '',
            'imagelandscape' => false,
        ];

        // For rich text, images are embedded in the question text.
        if ($data->questionformat == constants::QUESTIONFORMAT_RICHTEXT) {
            return $result;
        }

        // For plain text, get the uploaded image file.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_QUESTION_IMAGE,
            $data->questionversionid,
            'filename',
            false
        );

        if (empty($files)) {
            return $result;
        }

        // Get the first image file.
        $file = reset($files);
        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_kahoodle',
            constants::FILEAREA_QUESTION_IMAGE,
            $data->questionversionid,
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

    /**
     * Get answer options for multichoice questions
     *
     * @return array
     */
    protected function get_options(): array {
        $data = $this->roundquestion->get_data();
        $options = [];

        $questionconfig = $data->questionconfig ?? '';
        if (empty($questionconfig)) {
            return $options;
        }

        $lines = preg_split('/\r\n|\r|\n/', $questionconfig, -1, PREG_SPLIT_NO_EMPTY);
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        foreach ($lines as $index => $line) {
            $text = trim($line);
            // Remove the asterisk marker for correct answer (don't show in preview).
            if (str_starts_with($text, '*')) {
                $text = substr($text, 1);
            }

            $options[] = [
                'optionnumber' => $index + 1,
                'letter' => $letters[$index] ?? (string)($index + 1),
                'text' => $text,
            ];
        }

        return $options;
    }
}
