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

/**
 * Playback module for replaying completed Kahoodle rounds
 *
 * Handles playback of completed rounds from statistics reports.
 * Uses the shared player module for overlay, autoplay, and controls.
 *
 * @module     mod_kahoodle/playback
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {call as fetchMany} from 'core/ajax';
import * as Player from 'mod_kahoodle/player';

const SELECTORS = {
    PLAYBACK_BUTTON: '[data-action="mod_kahoodle-playback"]',
    PLAYBACK_ALL_BUTTON: '[data-action="mod_kahoodle-playback-all"]',
};

// Playback state.
let playbackState = null;
let playbackStages = [];
let playbackIndex = 0;

/**
 * Initialize the playback module
 *
 * Sets up event delegation for playback buttons on the statistics reports.
 */
export const init = () => {
    document.addEventListener('click', async(e) => {
        const playbackButton = e.target.closest(SELECTORS.PLAYBACK_BUTTON);
        if (playbackButton) {
            e.preventDefault();
            const roundId = parseInt(playbackButton.dataset.roundid || 0, 10);
            const kahoodleId = parseInt(playbackButton.dataset.kahoodleid || 0, 10);
            const questionNumber = parseInt(playbackButton.dataset.questionnumber || 0, 10);
            const questionId = parseInt(playbackButton.dataset.questionid || 0, 10);
            await openPlayback(roundId, kahoodleId, questionNumber, questionId);
            return;
        }

        const playAllButton = e.target.closest(SELECTORS.PLAYBACK_ALL_BUTTON);
        if (playAllButton) {
            e.preventDefault();
            const roundId = parseInt(playAllButton.dataset.roundid || 0, 10);
            const kahoodleId = parseInt(playAllButton.dataset.kahoodleid || 0, 10);
            await openPlayback(roundId, kahoodleId, 0);
        }
    });
};

/**
 * Open the playback overlay
 *
 * @param {number} roundId Round ID (for single-round playback)
 * @param {number} kahoodleId Kahoodle ID (for all-rounds playback)
 * @param {number} questionNumber Question number to start at (0 = start from beginning)
 * @param {number} questionId Question ID to start at (0 = not specified, used for all-rounds mode)
 */
const openPlayback = async(roundId, kahoodleId, questionNumber, questionId = 0) => {
    try {
        const result = await fetchMany([{
            methodname: 'mod_kahoodle_playback_stages',
            args: {roundid: roundId, kahoodleid: kahoodleId},
        }])[0];

        if (!result.stages || result.stages.length === 0) {
            return;
        }

        // Decode templatedata from JSON strings.
        playbackStages = result.stages.map(s => ({
            ...s,
            templatedata: JSON.parse(s.templatedata),
        }));

        // Find start index.
        playbackIndex = findStartIndex(playbackStages, questionNumber, questionId);

        playbackState = Player.create({
            containerClass: 'mod_kahoodle-playback-container',
            onNext: () => navigatePlayback(1),
            onBack: () => navigatePlayback(-1),
            onClose: () => closePlayback(),
        });

        await showCurrentPlaybackStage();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Find the stage index to start playback from
 *
 * @param {Array} stages Array of stage objects
 * @param {number} questionNumber Question number to find (0 = start from beginning)
 * @param {number} questionId Question ID to find (0 = not specified)
 * @returns {number} The index to start from
 */
const findStartIndex = (stages, questionNumber, questionId = 0) => {
    // Match by question ID (used for all-rounds mode where sortorder may be NULL).
    if (questionId) {
        const index = stages.findIndex(s =>
            s.stagesignature.startsWith('preview-') &&
            s.templatedata.questionid === questionId
        );
        if (index !== -1) {
            return index;
        }
    }

    if (!questionNumber) {
        return 0;
    }

    // Try to find the preview stage for this question number.
    let index = stages.findIndex(s => s.stagesignature === 'preview-' + questionNumber);
    if (index !== -1) {
        return index;
    }

    // Fall back to the question stage.
    index = stages.findIndex(s => s.stagesignature === 'question-' + questionNumber);
    if (index !== -1) {
        return index;
    }

    return 0;
};

/**
 * Show the current playback stage
 */
const showCurrentPlaybackStage = async() => {
    const stage = playbackStages[playbackIndex];
    if (!stage || !playbackState) {
        return;
    }

    const templatedata = processPlaybackData(stage.templatedata);
    await Player.showStage(playbackState, stage.template, templatedata, stage.duration);
};

/**
 * Process playback template data
 *
 * Decodes typedata JSON and adds typeis<type> boolean for Mustache conditionals.
 *
 * @param {Object} data Raw template data
 * @returns {Object} Processed template data ready for rendering
 */
const processPlaybackData = (data) => {
    const processed = {...data};

    // Decode the JSON-encoded type-specific data if present.
    if (processed.typedata && typeof processed.typedata === 'string') {
        processed.typedata = JSON.parse(processed.typedata);
    }

    // Add typeis<type> boolean for Mustache conditional rendering.
    if (processed.questiontype) {
        processed['typeis' + processed.questiontype] = true;
    }

    return processed;
};

/**
 * Navigate to the previous or next stage in playback
 *
 * @param {number} direction -1 for previous, 1 for next
 */
const navigatePlayback = async(direction) => {
    const newIndex = playbackIndex + direction;

    // Check bounds.
    if (newIndex < 0 || newIndex >= playbackStages.length) {
        return;
    }

    playbackIndex = newIndex;
    await showCurrentPlaybackStage();
};

/**
 * Close the playback overlay
 */
const closePlayback = () => {
    if (playbackState) {
        Player.close(playbackState);
        playbackState = null;
    }

    playbackStages = [];
    playbackIndex = 0;
};
