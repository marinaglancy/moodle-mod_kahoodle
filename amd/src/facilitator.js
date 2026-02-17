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
 * @module     mod_kahoodle/facilitator
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as PubSub from 'core/pubsub';
import RealTimeEvents from 'tool_realtime/events';
import * as RealTimeApi from 'tool_realtime/api';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';
import KahoodleEvents from 'mod_kahoodle/events';
import * as Player from 'mod_kahoodle/player';

const SELECTORS = {
    // Landing page buttons.
    LANDING_RESUME: '[data-action="resume-game"]',
    LANDING_FINISH: '[data-action="finish-game"]',
    // Lobby-specific selectors for partial updates.
    LOBBY_PARTICIPANTS_LIST: '.mod_kahoodle-participants-list',
    LOBBY_COUNT_NUMBER: '.mod_kahoodle-count-number',
    LOBBY_MAIN: '.mod_kahoodle-lobby-main',
};

// Current game state.
let gameState = {
    roundId: null,
    currentStageData: null,
};

// Player state (from player.js).
let playerState = null;

/**
 * Initialize the game controller
 *
 * This module is only loaded when the game is in progress, so it immediately
 * fetches the current stage and displays the game overlay.
 *
 * @param {number} roundId The round ID
 */
export const init = (roundId) => {
    gameState.roundId = roundId;

    // Subscribe to realtime events.
    PubSub.subscribe(RealTimeEvents.EVENT, handleRealtimeEvent);
    PubSub.subscribe(RealTimeEvents.CONNECTION_LOST, handleConnectionLost);

    // Set up event listeners for landing page buttons.
    document.addEventListener('click', handleLandingPageClick);

    // Listen for reveal events from animation.
    PubSub.subscribe(KahoodleEvents.REVEAL_RANK, handleRevealEvent);

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
        const response = await sendToServer({action: 'get_current'});

        if (response.template && response.stagesignature) {
            await showStage(response);
        }
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Send a request to the server via the realtime API
 *
 * @param {Object} payload Request data
 * @returns {Promise<Object>} The parsed response data
 */
const sendToServer = (payload) => {
    return RealTimeApi.sendToServer('mod_kahoodle', {
        roundid: gameState.roundId,
        ...payload
    });
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
    if (payload.stagesignature) {
        showStage(payload);
    }
};

/**
 * Handle connection lost events
 */
const handleConnectionLost = () => {
    // Pause autoplay when connection is lost.
    if (playerState) {
        Player.pause(playerState);
    }
    Notification.addNotification({
        message: 'Connection lost. Please refresh the page.',
        type: 'error',
    });
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
    const container = playerState ? Player.getContainer(playerState) : null;
    if (!container) {
        return false;
    }

    const currentParticipantsList = container.querySelector(SELECTORS.LOBBY_PARTICIPANTS_LIST);
    const currentCountNumber = container.querySelector(SELECTORS.LOBBY_COUNT_NUMBER);

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

    // Sync lobby size class.
    const currentMain = container.querySelector(SELECTORS.LOBBY_MAIN);
    const newMain = tempContainer.querySelector(SELECTORS.LOBBY_MAIN);
    if (currentMain && newMain) {
        const sizeRe = /mod_kahoodle-lobby-size-\w+/;
        const newSize = newMain.className.match(sizeRe)?.[0];
        const curSize = currentMain.className.match(sizeRe)?.[0];
        if (newSize && newSize !== curSize) {
            if (curSize) {
                currentMain.classList.remove(curSize);
            }
            currentMain.classList.add(newSize);
        }
    }

    return true;
};

/**
 * Show a game stage in the overlay
 *
 * @param {Object} stageData The stage data from the server
 */
const showStage = async(stageData) => {
    const stageChanged = !gameState.currentStageData ||
        gameState.currentStageData.stagesignature !== stageData.stagesignature;

    gameState.currentStageData = stageData;

    // If stage is archived, close the overlay.
    if (stageData.stagesignature === 'archived' || !stageData.template) {
        closeOverlay();
        // Reload the page to show updated landing state.
        window.location.reload();
        return;
    }

    try {
        // Process template data (decode typedata, add type booleans).
        const templatedata = processTemplateData(stageData.templatedata);

        // Create player if needed.
        if (!playerState) {
            playerState = Player.create({
                containerClass: 'mod_kahoodle-game-container',
                onNext: () => advanceToNextStage(),
                onBack: null,
                onClose: () => closeOverlay(),
            });
        }

        // For lobby stage without stage change, only update dynamic parts to avoid flickering.
        if (!stageChanged && stageData.stagesignature === 'lobby') {
            if (await updateLobbyPartial(stageData.template, templatedata)) {
                return;
            }
        }

        if (stageChanged) {
            // New stage: render template and start autoplay via player.
            await Player.showStage(playerState, stageData.template, templatedata, stageData.duration);
        } else {
            // Same stage re-render (e.g. reconnect): render without resetting autoplay.
            const container = Player.getContainer(playerState);
            const {html, js} = await Templates.renderForPromise(stageData.template, templatedata);
            Templates.replaceNodeContents(container, html, js);
        }
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Advance to the next stage by sending the advance action to the server
 */
const advanceToNextStage = async() => {
    // Stop current autoplay to prevent double-advance.
    if (playerState) {
        Player.stopAutoplay(playerState);
    }

    try {
        await sendToServer({
            action: 'advance',
            // Also send the current stage to avoid race conditions, double facilitation, and jumping over stages.
            currentstage: gameState.currentStageData.stagesignature,
        });
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Close the overlay and clean up
 */
const closeOverlay = () => {
    if (playerState) {
        Player.close(playerState);
        playerState = null;
    }

    // Reset state (but keep roundId).
    gameState.currentStageData = null;
};

/**
 * Handle reveal events from animation
 *
 * @param {string} data What was revealed ('rank1', 'rank2', 'rank3', or 'all')
 */
const handleRevealEvent = async(data) => {
    try {
        await sendToServer({
            action: 'reveal_rank',
            data
        });
        // Server will send stage update via channel notification if successful.
    } catch (error) {
        // Silent failure.
    }
};
