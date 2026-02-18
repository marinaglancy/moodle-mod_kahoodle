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
import KahoodleEvents from 'mod_kahoodle/events';

const SELECTORS = {
    OVERLAY: '.mod_kahoodle-overlay',
    CONTROL_CLOSE: '[data-action="close"]',
    // Landing page buttons.
    LANDING_RESUME: '[data-action="resume-participant"]',
    REVISION_CONTENT: '.mod_kahoodle-participant-revision-content',
    // Avatar picker.
    EDIT_AVATAR: '[data-action="editavatar"]',
    AVATAR_PICKER: '[data-region="avatar-picker"]',
    AVATAR_GRID: '[data-region="avatar-grid"]',
    CLOSE_PICKER: '[data-action="closepicker"]',
    SHOW_MORE: '[data-action="showmore"]',
    AVATAR_CANDIDATE: '[data-action="selectavatar"]',
    LOBBY_AVATAR_IMG: '.mod_kahoodle-participant-avatar-img',
};

// Current participant state.
let participantState = {
    roundId: null,
    participantId: null,
    overlayContainer: null,
    currentStageData: null,
};

// Wake lock sentinel for keeping screen on during gameplay.
let wakeLockSentinel = null;

// Touch start Y position for swipe-up fullscreen gesture detection.
let touchStartY = null;

// Whether closeOverlay was triggered by a popstate (back button) event.
let closingFromPopstate = false;

/**
 * Initialize the participant module
 *
 * This module is loaded when the user is a participant and game is in progress.
 * It immediately fetches the current stage and displays the waiting overlay.
 *
 * @param {number} roundId The round ID
 * @param {number} participantId The participant ID
 */
export const init = (roundId, participantId) => {
    participantState.roundId = roundId;
    participantState.participantId = participantId;

    // Subscribe to realtime events.
    PubSub.subscribe(RealTimeEvents.EVENT, handleRealtimeEvent);
    PubSub.subscribe(RealTimeEvents.CONNECTION_LOST, handleConnectionLost);

    // Set up event listeners for landing page buttons.
    document.addEventListener('click', handleLandingPageClick);

    // Listen for answer events from question type templates.
    PubSub.subscribe(KahoodleEvents.ANSWER, handleAnswerEvent);

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
        await sendToServer({
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
        const response = await sendToServer({action: 'get_participant_state'});

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
 * Send a request to the server via the realtime API
 *
 * @param {Object} payload Request data
 * @returns {Object} The parsed stage data
 */
const sendToServer = async(payload) => {
    const response = await RealTimeApi.sendToServer('mod_kahoodle', {
        roundid: participantState.roundId,
        ...payload
    });
    if (response && response.response) {
        return JSON.parse(response.response);
    }
    return response;
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
        // Mdlcode-disable-next-line cannot-parse-template
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
        height: 100vh;
        height: 100dvh;
        z-index: 9999;
    `;
    document.body.appendChild(participantState.overlayContainer);

    // Lock body scrolling while overlay is open.
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';

    // Add event listeners for controls.
    participantState.overlayContainer.addEventListener('click', handleOverlayControls);

    // Prevent touch scrolling from bleeding through to the body.
    participantState.overlayContainer.addEventListener('touchmove', handleTouchMove, {passive: false});

    // Detect swipe-up gesture to enter fullscreen (hides mobile URL bar).
    participantState.overlayContainer.addEventListener('touchstart', handleTouchStart, {passive: true});
    participantState.overlayContainer.addEventListener('touchend', handleTouchEnd, {passive: true});

    // Add keyboard navigation.
    document.addEventListener('keydown', handleKeyboard);

    // Keep screen on during gameplay.
    requestWakeLock();
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // Push history state so the back button closes the overlay instead of navigating away.
    history.pushState({kahoodleOverlay: true}, '');
    window.addEventListener('popstate', handlePopstate);
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
 * Check whether an element is inside a scrollable container within the overlay.
 *
 * @param {HTMLElement} element The element to check
 * @returns {boolean} True if the element is inside a scrollable container
 */
const isInsideScrollable = (element) => {
    let target = element;
    while (target && target !== participantState.overlayContainer) {
        const style = window.getComputedStyle(target);
        const overflowY = style.getPropertyValue('overflow-y');
        if ((overflowY === 'auto' || overflowY === 'scroll') && target.scrollHeight > target.clientHeight) {
            return true;
        }
        target = target.parentElement;
    }
    return false;
};

/**
 * Prevent touch scrolling from bleeding through to the page body.
 *
 * Allows scrolling within scrollable child elements (e.g. avatar picker grid)
 * but prevents the body from scrolling behind the overlay.
 *
 * @param {TouchEvent} e The touch event
 */
const handleTouchMove = (e) => {
    if (!isInsideScrollable(e.target)) {
        e.preventDefault();
    }
};

/**
 * Record touch start position for swipe-up fullscreen gesture detection.
 *
 * @param {TouchEvent} e The touch event
 */
const handleTouchStart = (e) => {
    touchStartY = e.touches[0].clientY;
};

/**
 * Detect swipe-up gesture and enter fullscreen mode.
 *
 * When the user swipes up on a non-scrollable part of the overlay,
 * request fullscreen to hide the mobile browser URL bar.
 *
 * @param {TouchEvent} e The touch event
 */
const handleTouchEnd = (e) => {
    if (touchStartY === null || document.fullscreenElement) {
        touchStartY = null;
        return;
    }
    const touchEndY = e.changedTouches[0].clientY;
    const swipeDistance = touchStartY - touchEndY;
    touchStartY = null;

    // Swipe up outside scrollable children (e.g. avatar picker) — enter fullscreen.
    if (swipeDistance > 50 && !isInsideScrollable(e.target)) {
        enterFullscreen();
    }
};

/**
 * Request fullscreen mode on the overlay container.
 *
 * Silently does nothing if the API is unavailable or the request fails.
 */
const enterFullscreen = () => {
    const el = document.documentElement;
    const request = el.requestFullscreen || el.webkitRequestFullscreen;
    if (request) {
        // Extend viewport into the system status bar area.
        const viewport = document.querySelector('meta[name="viewport"]');
        if (viewport && !viewport.content.includes('viewport-fit')) {
            viewport.dataset.kahoodleOriginal = viewport.content;
            viewport.content += ', viewport-fit=cover';
        }

        request.call(el).catch(() => {
            // Fullscreen not available.
        });
    }
};

/**
 * Request a screen wake lock to prevent the device screen from turning off.
 *
 * Silently does nothing if the API is unavailable or the request fails.
 */
const requestWakeLock = async() => {
    if (!('wakeLock' in navigator)) {
        return;
    }
    try {
        wakeLockSentinel = await navigator.wakeLock.request('screen');
        wakeLockSentinel.addEventListener('release', () => {
            wakeLockSentinel = null;
        });
    } catch (e) {
        wakeLockSentinel = null;
    }
};

/**
 * Release the screen wake lock if one is held.
 */
const releaseWakeLock = async() => {
    if (wakeLockSentinel) {
        try {
            await wakeLockSentinel.release();
        } catch (e) {
            // Already released.
        }
        wakeLockSentinel = null;
    }
};

/**
 * Re-acquire wake lock when the page becomes visible again.
 *
 * The browser automatically releases wake locks when a tab is hidden.
 */
const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible' && participantState.overlayContainer) {
        requestWakeLock();
    }
};

/**
 * Handle browser back button / back gesture.
 *
 * Closes the overlay instead of navigating to the previous page.
 */
const handlePopstate = () => {
    if (participantState.overlayContainer) {
        closingFromPopstate = true;
        closeOverlay();
        closingFromPopstate = false;
    }
};

/**
 * Close the overlay and clean up
 */
const closeOverlay = () => {
    // Exit fullscreen if active.
    if (document.fullscreenElement) {
        // Restore viewport meta tag.
        const viewport = document.querySelector('meta[name="viewport"]');
        if (viewport && viewport.dataset.kahoodleOriginal) {
            viewport.content = viewport.dataset.kahoodleOriginal;
            delete viewport.dataset.kahoodleOriginal;
        }

        document.exitFullscreen().catch(() => {
            // Already exited.
        });
    }

    if (participantState.overlayContainer) {
        participantState.overlayContainer.removeEventListener('touchmove', handleTouchMove);
        participantState.overlayContainer.removeEventListener('touchstart', handleTouchStart);
        participantState.overlayContainer.removeEventListener('touchend', handleTouchEnd);
        participantState.overlayContainer.remove();
        participantState.overlayContainer = null;
    }

    // Restore body scrolling.
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';

    // Remove keyboard event listener.
    document.removeEventListener('keydown', handleKeyboard);

    // Release wake lock and stop re-acquisition.
    releaseWakeLock();
    document.removeEventListener('visibilitychange', handleVisibilityChange);

    // Clean up history state.
    window.removeEventListener('popstate', handlePopstate);
    if (!closingFromPopstate) {
        // Programmatic close — remove the history entry we pushed.
        history.back();
    }

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

/**
 * Initialize avatar picker click handlers
 *
 * Called from the lobby template's {{#js}} block to register
 * click listeners for avatar editing controls. Uses the lobby
 * stage element as the listener target to avoid duplicate registration.
 */
export const initAvatarPicker = () => {
    const stage = document.querySelector('.mod_kahoodle-participant-content[data-stage="lobby"]');
    if (!stage || stage.dataset.avatarPickerInit) {
        return;
    }
    stage.dataset.avatarPickerInit = '1';
    stage.addEventListener('click', handleAvatarPickerClick);
};

/**
 * Handle avatar picker click events
 *
 * @param {Event} e The click event
 */
const handleAvatarPickerClick = (e) => {
    const editAvatarBtn = e.target.closest(SELECTORS.EDIT_AVATAR);
    if (editAvatarBtn) {
        e.preventDefault();
        openAvatarPicker();
        return;
    }

    const closePickerBtn = e.target.closest(SELECTORS.CLOSE_PICKER);
    if (closePickerBtn) {
        e.preventDefault();
        closeAvatarPicker();
        return;
    }

    const showMoreBtn = e.target.closest(SELECTORS.SHOW_MORE);
    if (showMoreBtn) {
        e.preventDefault();
        loadAvatarCandidates(true);
        return;
    }

    const candidateBtn = e.target.closest(SELECTORS.AVATAR_CANDIDATE);
    if (candidateBtn) {
        e.preventDefault();
        selectAvatar(candidateBtn.dataset.filename);
    }
};

/**
 * Open the avatar picker overlay and load initial candidates
 */
const openAvatarPicker = () => {
    const picker = document.querySelector(SELECTORS.AVATAR_PICKER);
    if (!picker) {
        return;
    }
    picker.style.display = '';
    loadAvatarCandidates(false);
};

/**
 * Close the avatar picker overlay
 */
const closeAvatarPicker = () => {
    const picker = document.querySelector(SELECTORS.AVATAR_PICKER);
    if (picker) {
        picker.style.display = 'none';
    }
};

/**
 * Load avatar candidates from the server and append to the grid
 *
 * @param {boolean} onlynew If true, only fetch new candidates (for "Show more")
 */
const loadAvatarCandidates = async(onlynew) => {
    const showMoreBtn = document.querySelector(SELECTORS.SHOW_MORE);
    if (showMoreBtn) {
        showMoreBtn.disabled = true;
    }

    try {
        const payload = {action: 'get_avatar_candidates'};
        if (onlynew) {
            payload.onlynew = true;
        }
        const response = await sendToServer(payload);

        const grid = document.querySelector(SELECTORS.AVATAR_GRID);
        if (!grid) {
            return;
        }

        // On initial load, clear the grid since the server returns all existing candidates.
        if (!onlynew) {
            grid.innerHTML = '';
        }

        // Append candidates to the grid.
        (response.candidates || []).forEach(candidate => {
            const btn = document.createElement('button');
            btn.className = 'mod_kahoodle-avatar-picker-item';
            btn.dataset.action = 'selectavatar';
            btn.dataset.filename = candidate.filename;
            const img = document.createElement('img');
            img.src = candidate.url;
            img.alt = '';
            img.className = 'mod_kahoodle-avatar-picker-img';
            btn.appendChild(img);
            grid.appendChild(btn);
        });

        // Show/hide the "Show more" button.
        if (showMoreBtn) {
            showMoreBtn.style.display = response.hasmore ? '' : 'none';
            showMoreBtn.disabled = false;
        }
    } catch (error) {
        window.console.error('Failed to load avatar candidates', error);
        if (showMoreBtn) {
            showMoreBtn.disabled = false;
        }
    }
};

/**
 * Select an avatar candidate and apply it
 *
 * @param {string} filename The filename of the chosen candidate
 */
const selectAvatar = async(filename) => {
    try {
        const response = await sendToServer({
            action: 'change_avatar',
            filename: filename,
        });

        // Update the lobby avatar image with the new URL.
        if (response.avatarurl) {
            const avatarImg = document.querySelector(SELECTORS.LOBBY_AVATAR_IMG);
            if (avatarImg) {
                avatarImg.src = response.avatarurl;
            }
        }

        // Close the picker.
        closeAvatarPicker();

        // Remove the edit button so the user cannot change avatar again.
        const editBtn = document.querySelector(SELECTORS.EDIT_AVATAR);
        if (editBtn) {
            editBtn.remove();
        }
    } catch (error) {
        window.console.error('Failed to change avatar', error);
    }
};
