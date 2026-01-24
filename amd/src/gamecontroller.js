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
    // Lobby-specific selectors for partial updates.
    LOBBY_PARTICIPANTS_LIST: '.mod_kahoodle-participants-list',
    LOBBY_COUNT_NUMBER: '.mod_kahoodle-count-number',
    COUNTDOWN_TIME: '.mod_kahoodle-countdown-time',
};

// Current game state.
let gameState = {
    roundId: null,
    contextId: null,
    overlayContainer: null,
    currentStageData: null,
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

        if (response.template && response.stage) {
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
        area: 'facilitator',
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
    if (component !== 'mod_kahoodle' || area !== 'facilitator' || parseInt(itemid) !== gameState.roundId) {
        return;
    }

    // Show the new stage.
    if (payload.template && payload.stage) {
        showStage(payload);
    }
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
 * Sync participants list by comparing data-participantid attributes
 *
 * This performs a smart DOM diff to minimize changes:
 * - Removes participants that are no longer present
 * - Adds new participants
 * - Updates existing participants if their content changed
 *
 * @param {HTMLElement} currentList The current participants list element
 * @param {HTMLElement} newList The new participants list element (from rendered template)
 */
const syncParticipantsList = (currentList, newList) => {
    const currentChildren = Array.from(currentList.children);
    const newChildren = Array.from(newList.children);

    // Build maps by participantid for quick lookup.
    const currentMap = new Map(currentChildren.filter(c => c.dataset.participantid).map(c => [c.dataset.participantid, c]));
    const newids = newChildren.map(c => c.dataset.participantid).filter(id => id);

    // Remove participants that are no longer present.
    currentChildren.filter(c => !newids.includes(c.dataset.participantid)).forEach(c => c.remove());

    // Add or update participants in the correct order.
    let currentIndex = 0;
    newChildren.forEach(newChild => {
        const existingChild = currentMap.get(newChild.dataset.participantid);

        if (existingChild) {
            // Update existing participant if content changed.
            if (existingChild.innerHTML !== newChild.innerHTML) {
                existingChild.innerHTML = newChild.innerHTML;
            }
            // Move to correct position if needed.
            const currentAtIndex = currentList.children[currentIndex];
            if (currentAtIndex !== existingChild) {
                currentList.insertBefore(existingChild, currentAtIndex);
            }
        } else {
            // Insert new participant at the correct position.
            const referenceNode = currentList.children[currentIndex] || null;
            currentList.insertBefore(newChild.cloneNode(true), referenceNode);
        }
        currentIndex++;
    });
};

/**
 * Update only the dynamic parts of the lobby (participants list and count)
 *
 * This prevents the whole overlay from re-rendering when participants join/leave,
 * which would cause visual flickering.
 *
 * @param {string} template The template name
 * @param {Object} templatedata The processed template data
 * @returns {Promise<boolean>} True if partial update was performed, false otherwise
 */
const updateLobbyPartial = async(template, templatedata) => {
    if (!gameState.overlayContainer) {
        return false;
    }

    const currentParticipantsList = gameState.overlayContainer.querySelector(SELECTORS.LOBBY_PARTICIPANTS_LIST);
    const currentCountNumber = gameState.overlayContainer.querySelector(SELECTORS.LOBBY_COUNT_NUMBER);

    if (!currentParticipantsList || !currentCountNumber) {
        return false;
    }

    // Render the full template to get the new HTML.
    const html = await Templates.render(template, templatedata);

    // Parse the rendered HTML to extract the updated parts.
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = html;

    const newParticipantsList = tempContainer.querySelector(SELECTORS.LOBBY_PARTICIPANTS_LIST);
    const newCountNumber = tempContainer.querySelector(SELECTORS.LOBBY_COUNT_NUMBER);

    if (!newParticipantsList || !newCountNumber) {
        return false;
    }

    // Sync participants list with minimal DOM changes.
    syncParticipantsList(currentParticipantsList, newParticipantsList);

    // Update count.
    currentCountNumber.textContent = newCountNumber.textContent;

    return true;
};

/**
 * Show a game stage in the overlay
 *
 * @param {Object} stageData The stage data from the server
 */
const showStage = async(stageData) => {

    const stageChanged = !gameState.currentStageData ||
        gameState.currentStageData.stage !== stageData.stage ||
        gameState.currentStageData.currentquestion !== stageData.currentquestion;

    gameState.currentStageData = stageData;

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

        // For lobby stage without stage change, only update dynamic parts to avoid flickering.
        if (!stageChanged && stageData.stage === 'lobby') {
            if (await updateLobbyPartial(stageData.template, templatedata)) {
                return;
            }
        }

        // Render the full template.
        const html = await Templates.render(stageData.template, templatedata);

        // Create or update the overlay container.
        if (!gameState.overlayContainer) {
            createOverlayContainer();
        }

        gameState.overlayContainer.innerHTML = html;

        if (stageChanged) {
            // Reset elapsed time for the new stage.
            gameState.autoplayElapsed = 0;
            gameState.autoplayEnabled = true;

            // Start autoplay.
            startAutoplay();
        }
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
        const rawResponse = await RealTimeApi.sendToServer(channel, {
            action: 'advance',
            // Also send the current stage to avoid race conditions, double facilitation, and jumping over stages.
            currentstage: gameState.currentStageData.stage,
            currentquestion: gameState.currentStageData.currentquestion
        });
        const response = parseRealtimeResponse(rawResponse);

        if (response.error) {
            Notification.exception({message: response.error});
            return;
        }
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
        const remaining = durationMs - elapsed;
        const progress = Math.min((elapsed / durationMs) * 100, 100);

        const progressFill = gameState.overlayContainer.querySelector(SELECTORS.PROGRESS_FILL);
        if (progressFill) {
            progressFill.style.width = progress + '%';
        }

        const countdownTime = gameState.overlayContainer.querySelector(SELECTORS.COUNTDOWN_TIME);
        if (countdownTime) {
            countdownTime.textContent = formatCountdown(remaining);
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
 * Format milliseconds as M:SS countdown string
 *
 * @param {number} ms Milliseconds remaining
 * @returns {string} Formatted time string (e.g., "4:30")
 */
const formatCountdown = (ms) => {
    const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
};

/**
 * Update the progress bar and countdown time to reflect current elapsed time
 */
const updateProgressBar = () => {
    const stageData = gameState.currentStageData;
    if (!stageData || !stageData.duration || !gameState.overlayContainer) {
        return;
    }

    const durationMs = stageData.duration * 1000;
    const remainingMs = durationMs - gameState.autoplayElapsed;
    const progress = Math.min((gameState.autoplayElapsed / durationMs) * 100, 100);

    const progressFill = gameState.overlayContainer.querySelector(SELECTORS.PROGRESS_FILL);
    if (progressFill) {
        progressFill.style.width = progress + '%';
    }

    const countdownTime = gameState.overlayContainer.querySelector(SELECTORS.COUNTDOWN_TIME);
    if (countdownTime) {
        countdownTime.textContent = formatCountdown(remainingMs);
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
    gameState.autoplayEnabled = true;
    gameState.autoplayTimerId = null;
    gameState.autoplayStartTime = null;
    gameState.autoplayElapsed = 0;
};
