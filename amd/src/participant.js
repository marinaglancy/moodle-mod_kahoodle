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
 * Participant JavaScript module for participant interface
 *
 * Handles the participant view during a Kahoodle round:
 * - Displaying the waiting overlay
 * - Subscribing to game and participant channels
 * - Handling stage change notifications
 *
 * This module is only loaded when the user is a participant and game is in progress.
 *
 * @module     mod_kahoodle/participant
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
    CONTROL_CLOSE: '[data-action="close"]',
    // Landing page buttons.
    LANDING_RESUME: '[data-action="resume-participant"]',
    REVISION_CONTENT: '.mod_kahoodle-participant-revision-content',
};

// Current participant state.
let participantState = {
    roundId: null,
    participantId: null,
    contextId: null,
    overlayContainer: null,
    currentStageData: null,
};

/**
 * Initialize the participant module
 *
 * This module is loaded when the user is a participant and game is in progress.
 * It immediately fetches the current stage and displays the waiting overlay.
 *
 * @param {number} roundId The round ID
 * @param {number} participantId The participant ID
 * @param {number} contextId The context ID
 */
export const init = (roundId, participantId, contextId) => {
    participantState.roundId = roundId;
    participantState.participantId = participantId;
    participantState.contextId = contextId;

    // Subscribe to realtime events.
    PubSub.subscribe(RealTimeEvents.EVENT, handleRealtimeEvent);
    PubSub.subscribe(RealTimeEvents.CONNECTION_LOST, handleConnectionLost);

    // Set up event listeners for landing page buttons.
    document.addEventListener('click', handleLandingPageClick);

    // Listen for answer events from question type templates.
    PubSub.subscribe('mod_kahoodle:answer', handleAnswerEvent);

    // Fetch current stage and display the participant overlay.
    fetchCurrentStage();
};

/**
 * Handle click events on the landing page buttons
 *
 * @param {Event} e The click event
 */
const handleLandingPageClick = (e) => {
    const resumeButton = e.target.closest(SELECTORS.LANDING_RESUME);
    if (resumeButton) {
        e.preventDefault();
        // Re-open the participant overlay.
        fetchCurrentStage();
    }
};

/**
 * Handle answer events from question type templates
 *
 * @param {string} response The answer response
 */
const handleAnswerEvent = async(response) => {
    try {
        const channel = getParticipantChannel();
        await RealTimeApi.sendToServer(channel, {
            action: 'answer',
            response: response,
            currentstage: participantState.currentStageData.stagesignature,
        });
        // Server will send stage update via channel notification if successful.
    } catch (error) {
        // Silent failure - server will ignore invalid submissions anyway.
        window.console.error('Failed to submit answer', error);
    }
};

/**
 * Fetch the current stage data from the server and show it
 */
const fetchCurrentStage = async() => {
    try {
        const channel = getParticipantChannel();
        const rawResponse = await RealTimeApi.sendToServer(channel, {action: 'get_current'});
        const response = parseRealtimeResponse(rawResponse);

        if (response.error) {
            Notification.exception({message: response.error});
            return;
        }

        if (response.template && response.stagesignature) {
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
 * Get the participant channel configuration
 *
 * @returns {Object} Channel configuration
 */
const getParticipantChannel = () => {
    return {
        contextid: participantState.contextId,
        component: 'mod_kahoodle',
        area: 'participant',
        itemid: participantState.participantId,
    };
};

/**
 * Handle realtime events from the server
 *
 * @param {Object} eventData The event data
 */
const handleRealtimeEvent = (eventData) => {
    const {component, area, itemid, payload} = eventData;

    const isMyEvent = (component === 'mod_kahoodle') &&
        ((area === 'participant' && parseInt(itemid) === participantState.participantId) ||
        (area === 'game' && parseInt(itemid) === participantState.roundId));

    // Verify this event is for our participant or for the game channel.
    if (!isMyEvent) {
        return;
    }

    // Handle game channel events.
    if (payload.stagesignature) {
        showStage(payload);
    }
    if (payload.action === 'reveal_rank') {
        revealRank();
    }
};

/**
 * Handle connection lost events
 */
const handleConnectionLost = () => {
    getString('error_connectionlost', 'kahoodle').then((message) => {
        return Notification.addNotification({
            message,
            type: 'error',
        });
    }).catch(() => null);
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
 * Show a stage in the participant overlay
 *
 * @param {Object} stageData The stage data from the server
 */
const showStage = async(stageData) => {
    participantState.currentStageData = stageData;

    // If stage is archived, close the overlay and reload the page.
    if (stageData.stagesignature === 'archived' || !stageData.template) {
        closeOverlay();
        window.location.reload();
        return;
    }

    try {
        // Process template data (decode typedata, add type booleans).
        const templatedata = processTemplateData(stageData.templatedata);

        // Create the overlay container if it doesn't exist.
        if (!participantState.overlayContainer) {
            createOverlayContainer();
        }

        // Render the template and execute embedded JS.
        const {html, js} = await Templates.renderForPromise(stageData.template, templatedata);
        Templates.replaceNodeContents(participantState.overlayContainer, html, js);

        if (stageData.stagesignature === 'revision') {
            // Special handling for revision stage.
            onRevisionStageStart();
        }
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Create the overlay container element
 */
const createOverlayContainer = () => {
    participantState.overlayContainer = document.createElement('div');
    participantState.overlayContainer.className = 'mod_kahoodle-participant-container';
    participantState.overlayContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
    `;
    document.body.appendChild(participantState.overlayContainer);

    // Add event listeners for controls.
    participantState.overlayContainer.addEventListener('click', handleOverlayControls);

    // Add keyboard navigation.
    document.addEventListener('keydown', handleKeyboard);
};

/**
 * Handle control button clicks in the overlay
 *
 * @param {Event} e The click event
 */
const handleOverlayControls = (e) => {
    const closeButton = e.target.closest(SELECTORS.CONTROL_CLOSE);
    if (closeButton) {
        e.preventDefault();
        closeOverlay();
    }
};

/**
 * Handle keyboard events
 *
 * @param {KeyboardEvent} e The keyboard event
 */
const handleKeyboard = (e) => {
    if (!participantState.overlayContainer) {
        return;
    }

    if (e.key === 'Escape') {
        e.preventDefault();
        closeOverlay();
    }
};

/**
 * Close the overlay and clean up
 */
const closeOverlay = () => {
    if (participantState.overlayContainer) {
        participantState.overlayContainer.remove();
        participantState.overlayContainer = null;
    }

    // Remove keyboard event listener.
    document.removeEventListener('keydown', handleKeyboard);

    // Reset state (but keep IDs).
    participantState.currentStageData = null;
};

/**
 * Executed when revision stage starts
 */
const onRevisionStageStart = () => {
    const el = document.querySelector(SELECTORS.REVISION_CONTENT);
    const autorevealin = el ? parseInt(el.dataset.autorevealin, 10) : 0;
    if (autorevealin > 0) {
        setTimeout(revealRank, autorevealin * 1000);
    } else {
        revealRank();
    }
};

/**
 * During revision stage hide suspense and reveal actual rank
 */
const revealRank = () => {
    const el = document.querySelector(SELECTORS.REVISION_CONTENT);
    if (!el) {
        return;
    }
    el.querySelectorAll('[data-region="suspense"]').forEach(element => {
        element.style.display = 'none';
    });
    el.querySelectorAll('[data-region="aftersuspense"]').forEach(element => {
        element.style.display = '';
    });
};
