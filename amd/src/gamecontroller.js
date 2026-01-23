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
 * Game controller JavaScript module for teacher interface
 *
 * Handles the real-time game flow including:
 * - Displaying the full-screen overlay with game stages
 * - Managing autoplay with timer, pause/resume
 * - Sending realtime events to progress through stages
 *
 * This module is only loaded when the game is in progress.
 *
 * @module     mod_kahoodle/gamecontroller
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as PubSub from 'core/pubsub';
import RealTimeEvents from 'tool_realtime/events';
import * as RealTimeApi from 'tool_realtime/api';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';

const SELECTORS = {
    OVERLAY: '.mod_kahoodle-overlay',
    CONTROL_NEXT: '[data-action="next"]',
    CONTROL_CLOSE: '[data-action="close"]',
    CONTROL_PAUSE: '[data-action="pause"]',
    CONTROL_RESUME: '[data-action="resume"]',
    PROGRESS_FILL: '.mod_kahoodle-progress-fill',
    // Landing page buttons.
    LANDING_RESUME: '[data-action="resume-game"]',
    LANDING_FINISH: '[data-action="finish-game"]',
};

// Current game state.
let gameState = {
    roundId: null,
    contextId: null,
    overlayContainer: null,
    currentStageData: null,
    lastRenderedStageKey: null,
    autoplayEnabled: true,
    autoplayTimerId: null,
    autoplayStartTime: null,
    autoplayElapsed: 0,
};

/**
 * Initialize the game controller
 *
 * This module is only loaded when the game is in progress, so it immediately
 * fetches the current stage and displays the game overlay.
 *
 * @param {number} roundId The round ID
 * @param {number} contextId The context ID
 */
export const init = (roundId, contextId) => {
    gameState.roundId = roundId;
    gameState.contextId = contextId;

    // Subscribe to realtime events.
    PubSub.subscribe(RealTimeEvents.EVENT, handleRealtimeEvent);
    PubSub.subscribe(RealTimeEvents.CONNECTION_LOST, handleConnectionLost);

    // Set up event listeners for landing page buttons.
    document.addEventListener('click', handleLandingPageClick);

    // Fetch current stage and display the game overlay.
    fetchCurrentStage();
};

/**
 * Handle click events on the landing page buttons
 *
 * @param {Event} e The click event
 */
const handleLandingPageClick = async(e) => {
    const resumeButton = e.target.closest(SELECTORS.LANDING_RESUME);
    if (resumeButton) {
        e.preventDefault();
        // Re-open the game overlay.
        fetchCurrentStage();
        return;
    }

    const finishButton = e.target.closest(SELECTORS.LANDING_FINISH);
    if (finishButton) {
        e.preventDefault();
        // Show confirmation and redirect to finish URL.
        const finishUrl = finishButton.dataset.finishurl;
        Notification.confirm(
            await getString('finishgame', 'mod_kahoodle'),
            await getString('finishgame_confirm', 'mod_kahoodle'),
            await getString('yes', 'core'),
            await getString('no', 'core'),
            () => {
                window.location.href = finishUrl;
            }
        );
    }
};

/**
 * Fetch the current stage data from the server and show it
 */
const fetchCurrentStage = async() => {
    try {
        const channel = getChannel();
        const rawResponse = await RealTimeApi.sendToServer(channel, {action: 'get_current'});
        const response = parseRealtimeResponse(rawResponse);

        if (response.error) {
            Notification.exception({message: response.error});
            return;
        }

        if (response.template) {
            await showStage(response);
        }
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Parse the response from the realtime API
 *
 * The tool_realtime_request web service returns {response: JSON_STRING},
 * so we need to parse the inner JSON string.
 *
 * @param {Object} response The response from RealTimeApi.sendToServer
 * @returns {Object} The parsed stage data
 */
const parseRealtimeResponse = (response) => {
    if (response && response.response) {
        return JSON.parse(response.response);
    }
    return response;
};

/**
 * Get the realtime channel configuration
 *
 * @returns {Object} Channel configuration
 */
const getChannel = () => {
    return {
        contextid: gameState.contextId,
        component: 'mod_kahoodle',
        area: 'game',
        itemid: gameState.roundId,
    };
};

/**
 * Handle realtime events from the server
 *
 * @param {Object} eventData The event data
 */
const handleRealtimeEvent = (eventData) => {
    const {component, area, itemid, payload} = eventData;

    // Verify this event is for our game.
    if (component !== 'mod_kahoodle' || area !== 'game' || parseInt(itemid) !== gameState.roundId) {
        return;
    }

    // Show the new stage.
    showStage(payload);
};

/**
 * Handle connection lost events
 *
 * @param {Object} e The error event
 */
const handleConnectionLost = (e) => {
    // Pause autoplay when connection is lost.
    pauseAutoplay();
    Notification.addNotification({
        message: 'Connection lost. Please check your network.',
        type: 'error',
    });
    window.console.error('Realtime connection lost', e);
};

/**
 * Process template data to decode typedata and add type boolean
 *
 * @param {Object} templatedata The template data from the server
 * @returns {Object} Processed template data ready for rendering
 */
const processTemplateData = (templatedata) => {
    const processed = {...templatedata};

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
 * Show a game stage in the overlay
 *
 * @param {Object} stageData The stage data from the server
 */
const showStage = async(stageData) => {
    // Create a unique key for this stage to avoid re-rendering the same stage.
    const stageKey = `${stageData.stage}-${stageData.currentquestion}`;

    // Skip if we're already showing this stage (prevents double render from response + realtime event).
    if (stageKey === gameState.lastRenderedStageKey) {
        return;
    }

    gameState.currentStageData = stageData;
    gameState.lastRenderedStageKey = stageKey;

    // If stage is archived, close the overlay.
    if (stageData.stage === 'archived' || !stageData.template) {
        closeOverlay();
        // Reload the page to show updated landing state.
        window.location.reload();
        return;
    }

    try {
        // Process template data (decode typedata, add type booleans).
        const templatedata = processTemplateData(stageData.templatedata);

        // Render the template.
        const html = await Templates.render(stageData.template, templatedata);

        // Create or update the overlay container.
        if (!gameState.overlayContainer) {
            createOverlayContainer();
        }

        gameState.overlayContainer.innerHTML = html;

        // Reset elapsed time for the new stage.
        gameState.autoplayElapsed = 0;
        gameState.autoplayEnabled = true;

        // Start autoplay.
        startAutoplay();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Create the overlay container element
 */
const createOverlayContainer = () => {
    gameState.overlayContainer = document.createElement('div');
    gameState.overlayContainer.className = 'mod_kahoodle-game-container';
    gameState.overlayContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
    `;
    document.body.appendChild(gameState.overlayContainer);

    // Add event listeners for controls.
    gameState.overlayContainer.addEventListener('click', handleOverlayControls);

    // Add keyboard navigation.
    document.addEventListener('keydown', handleKeyboard);
};

/**
 * Handle control button clicks in the overlay
 *
 * @param {Event} e The click event
 */
const handleOverlayControls = (e) => {
    const nextButton = e.target.closest(SELECTORS.CONTROL_NEXT);
    if (nextButton) {
        e.preventDefault();
        advanceToNextStage();
        return;
    }

    const closeButton = e.target.closest(SELECTORS.CONTROL_CLOSE);
    if (closeButton) {
        e.preventDefault();
        closeOverlay();
        return;
    }

    const pauseButton = e.target.closest(SELECTORS.CONTROL_PAUSE);
    if (pauseButton) {
        e.preventDefault();
        pauseAutoplay();
        return;
    }

    const resumeButton = e.target.closest(SELECTORS.CONTROL_RESUME);
    if (resumeButton) {
        e.preventDefault();
        resumeAutoplay();
    }
};

/**
 * Handle keyboard events
 *
 * @param {KeyboardEvent} e The keyboard event
 */
const handleKeyboard = (e) => {
    if (!gameState.overlayContainer) {
        return;
    }

    switch (e.key) {
        case 'ArrowRight':
            e.preventDefault();
            advanceToNextStage();
            break;
        case 'Escape':
            e.preventDefault();
            closeOverlay();
            break;
        case ' ':
            e.preventDefault();
            if (gameState.autoplayEnabled) {
                pauseAutoplay();
            } else {
                resumeAutoplay();
            }
            break;
    }
};

/**
 * Advance to the next stage by sending the advance action to the server
 */
const advanceToNextStage = async() => {
    // Stop current autoplay.
    stopAutoplayTimer();

    try {
        const channel = getChannel();
        const rawResponse = await RealTimeApi.sendToServer(channel, {action: 'advance'});
        const response = parseRealtimeResponse(rawResponse);

        if (response.error) {
            Notification.exception({message: response.error});
            return;
        }

        // The server notifies all subscribers, but we can also use the response directly.
        await showStage(response);
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Start the autoplay countdown
 */
const startAutoplay = () => {
    // Stop any existing timer.
    stopAutoplayTimer();

    const stageData = gameState.currentStageData;
    if (!stageData || !stageData.duration) {
        return;
    }

    const overlay = gameState.overlayContainer?.querySelector(SELECTORS.OVERLAY);

    // Update paused class based on current state.
    if (overlay) {
        if (gameState.autoplayEnabled) {
            overlay.classList.remove('mod_kahoodle-progress-paused');
        } else {
            overlay.classList.add('mod_kahoodle-progress-paused');
        }
    }

    // Update progress bar to current position.
    updateProgressBar();

    // Only start timer if autoplay is enabled.
    if (!gameState.autoplayEnabled) {
        return;
    }

    const durationMs = stageData.duration * 1000;
    const remainingMs = durationMs - gameState.autoplayElapsed;

    if (remainingMs <= 0) {
        // Time is up, go to next stage.
        advanceToNextStage();
        return;
    }

    gameState.autoplayStartTime = Date.now();

    // Use requestAnimationFrame for smooth progress updates.
    const updateLoop = () => {
        if (!gameState.autoplayEnabled || !gameState.overlayContainer) {
            return;
        }

        const elapsed = gameState.autoplayElapsed + (Date.now() - gameState.autoplayStartTime);
        const progress = Math.min((elapsed / durationMs) * 100, 100);

        const progressFill = gameState.overlayContainer.querySelector(SELECTORS.PROGRESS_FILL);
        if (progressFill) {
            progressFill.style.width = progress + '%';
        }

        if (elapsed >= durationMs) {
            // Time is up, advance to next stage after a brief moment.
            gameState.autoplayTimerId = setTimeout(() => {
                advanceToNextStage();
            }, 50);
        } else {
            gameState.autoplayTimerId = requestAnimationFrame(updateLoop);
        }
    };

    gameState.autoplayTimerId = requestAnimationFrame(updateLoop);
};

/**
 * Stop the autoplay timer
 */
const stopAutoplayTimer = () => {
    if (gameState.autoplayTimerId) {
        cancelAnimationFrame(gameState.autoplayTimerId);
        clearTimeout(gameState.autoplayTimerId);
        gameState.autoplayTimerId = null;
    }
};

/**
 * Update the progress bar to reflect current elapsed time
 */
const updateProgressBar = () => {
    const stageData = gameState.currentStageData;
    if (!stageData || !stageData.duration || !gameState.overlayContainer) {
        return;
    }

    const durationMs = stageData.duration * 1000;
    const progress = Math.min((gameState.autoplayElapsed / durationMs) * 100, 100);

    const progressFill = gameState.overlayContainer.querySelector(SELECTORS.PROGRESS_FILL);
    if (progressFill) {
        progressFill.style.width = progress + '%';
    }
};

/**
 * Pause the autoplay countdown
 */
const pauseAutoplay = () => {
    if (!gameState.autoplayEnabled) {
        return;
    }

    // Stop the timer first.
    stopAutoplayTimer();

    // Calculate and store elapsed time.
    if (gameState.autoplayStartTime) {
        gameState.autoplayElapsed += Date.now() - gameState.autoplayStartTime;
        gameState.autoplayStartTime = null;
    }

    gameState.autoplayEnabled = false;

    // Update progress bar to exact position.
    updateProgressBar();

    // Add paused class to overlay.
    const overlay = gameState.overlayContainer?.querySelector(SELECTORS.OVERLAY);
    if (overlay) {
        overlay.classList.add('mod_kahoodle-progress-paused');
    }
};

/**
 * Resume the autoplay countdown
 */
const resumeAutoplay = () => {
    if (gameState.autoplayEnabled) {
        return;
    }

    gameState.autoplayEnabled = true;

    // Remove paused class from overlay.
    const overlay = gameState.overlayContainer?.querySelector(SELECTORS.OVERLAY);
    if (overlay) {
        overlay.classList.remove('mod_kahoodle-progress-paused');
    }

    // Restart the timer.
    startAutoplay();
};

/**
 * Close the overlay and clean up
 */
const closeOverlay = () => {
    // Stop autoplay timer.
    stopAutoplayTimer();

    if (gameState.overlayContainer) {
        gameState.overlayContainer.remove();
        gameState.overlayContainer = null;
    }

    // Remove keyboard event listener.
    document.removeEventListener('keydown', handleKeyboard);

    // Reset state (but keep roundId and contextId).
    gameState.currentStageData = null;
    gameState.lastRenderedStageKey = null;
    gameState.autoplayEnabled = true;
    gameState.autoplayTimerId = null;
    gameState.autoplayStartTime = null;
    gameState.autoplayElapsed = 0;
};
