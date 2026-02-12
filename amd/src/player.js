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
 * Shared stage player module
 *
 * Manages the full-screen overlay, autoplay timer with progress bar,
 * control buttons (next/back/pause/resume/close), and keyboard navigation.
 *
 * Used by facilitator.js (live game), questions.js (preview), and playback.js.
 *
 * @module     mod_kahoodle/player
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';

const SELECTORS = {
    OVERLAY: '.mod_kahoodle-overlay',
    CONTROL_NEXT: '[data-action="next"]',
    CONTROL_BACK: '[data-action="back"]',
    CONTROL_CLOSE: '[data-action="close"]',
    CONTROL_PAUSE: '[data-action="pause"]',
    CONTROL_RESUME: '[data-action="resume"]',
    PROGRESS_FILL: '.mod_kahoodle-progress-fill',
    COUNTDOWN_TIME: '.mod_kahoodle-countdown-time',
};

/**
 * Create and initialise a stage player.
 *
 * @param {Object} config
 * @param {string} config.containerClass - CSS class for the overlay container
 * @param {Function} config.onNext - Called when Next is triggered (button/keyboard/autoplay expiry)
 * @param {Function|null} [config.onBack] - Called when Back is triggered. Null to disable.
 * @param {Function} config.onClose - Called when Close is triggered (button/keyboard)
 * @returns {Object} Player state object (pass to other exported functions)
 */
export const create = (config) => {
    const state = {
        overlayContainer: null,
        autoplayEnabled: true,
        autoplayTimerId: null,
        autoplayStartTime: null,
        autoplayElapsed: 0,
        currentDuration: 0,
        config,
        // Store bound handlers so we can remove them on close.
        _handleControls: null,
        _handleKeyboard: null,
    };

    createContainer(state);
    return state;
};

/**
 * Show a stage in the player. Renders the template into the container and starts autoplay.
 *
 * @param {Object} state - Player state from create()
 * @param {string} template - Mustache template name
 * @param {Object} templatedata - Template context data
 * @param {number} duration - Stage duration in seconds (0 = no autoplay)
 */
export const showStage = async(state, template, templatedata, duration) => {
    if (!state.overlayContainer) {
        return;
    }

    // Render the template and execute embedded JS.
    const {html, js} = await Templates.renderForPromise(template, templatedata);
    Templates.replaceNodeContents(state.overlayContainer, html, js);

    // Reset autoplay for the new stage.
    state.autoplayElapsed = 0;
    state.autoplayEnabled = true;
    state.currentDuration = duration;

    startAutoplay(state);
};

/**
 * Pause autoplay.
 *
 * @param {Object} state - Player state
 */
export const pause = (state) => {
    if (!state.autoplayEnabled) {
        return;
    }

    stopAutoplayTimer(state);

    // Store elapsed time.
    if (state.autoplayStartTime) {
        state.autoplayElapsed += Date.now() - state.autoplayStartTime;
        state.autoplayStartTime = null;
    }

    state.autoplayEnabled = false;

    updateProgressBar(state);

    // Add paused class to overlay.
    const overlay = state.overlayContainer?.querySelector(SELECTORS.OVERLAY);
    if (overlay) {
        overlay.classList.add('mod_kahoodle-progress-paused');
    }
};

/**
 * Resume autoplay.
 *
 * @param {Object} state - Player state
 */
export const resume = (state) => {
    if (state.autoplayEnabled) {
        return;
    }

    state.autoplayEnabled = true;

    // Remove paused class from overlay.
    const overlay = state.overlayContainer?.querySelector(SELECTORS.OVERLAY);
    if (overlay) {
        overlay.classList.remove('mod_kahoodle-progress-paused');
    }

    startAutoplay(state);
};

/**
 * Stop the autoplay timer without changing the pause state.
 *
 * Use this when advancing to a new stage externally (e.g. server-driven advance)
 * to prevent the old timer from firing onNext again.
 *
 * @param {Object} state - Player state
 */
export const stopAutoplay = (state) => {
    stopAutoplayTimer(state);
};

/**
 * Close the player and clean up DOM + event listeners.
 *
 * @param {Object} state - Player state
 */
export const close = (state) => {
    stopAutoplayTimer(state);

    if (state.overlayContainer) {
        state.overlayContainer.remove();
        state.overlayContainer = null;
    }

    if (state._handleKeyboard) {
        document.removeEventListener('keydown', state._handleKeyboard);
        state._handleKeyboard = null;
    }

    // Reset autoplay state.
    state.autoplayEnabled = true;
    state.autoplayTimerId = null;
    state.autoplayStartTime = null;
    state.autoplayElapsed = 0;
    state.currentDuration = 0;
};

/**
 * Get the overlay container element (for callers that need direct DOM access, e.g. lobby partial updates).
 *
 * @param {Object} state - Player state
 * @returns {HTMLElement|null}
 */
export const getContainer = (state) => state.overlayContainer;

// --- Internal functions ---

/**
 * Create the overlay container element and attach event listeners.
 *
 * @param {Object} state - Player state
 */
const createContainer = (state) => {
    state.overlayContainer = document.createElement('div');
    state.overlayContainer.className = state.config.containerClass;
    state.overlayContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
    `;
    document.body.appendChild(state.overlayContainer);

    // Bind and store handlers for cleanup.
    state._handleControls = (e) => handleControls(state, e);
    state._handleKeyboard = (e) => handleKeyboard(state, e);

    state.overlayContainer.addEventListener('click', state._handleControls);
    document.addEventListener('keydown', state._handleKeyboard);
};

/**
 * Handle control button clicks.
 *
 * @param {Object} state - Player state
 * @param {Event} e - Click event
 */
const handleControls = (state, e) => {
    const nextButton = e.target.closest(SELECTORS.CONTROL_NEXT);
    if (nextButton) {
        e.preventDefault();
        state.config.onNext();
        return;
    }

    if (state.config.onBack) {
        const backButton = e.target.closest(SELECTORS.CONTROL_BACK);
        if (backButton) {
            e.preventDefault();
            state.config.onBack();
            return;
        }
    }

    const closeButton = e.target.closest(SELECTORS.CONTROL_CLOSE);
    if (closeButton) {
        e.preventDefault();
        state.config.onClose();
        return;
    }

    const pauseButton = e.target.closest(SELECTORS.CONTROL_PAUSE);
    if (pauseButton) {
        e.preventDefault();
        pause(state);
        return;
    }

    const resumeButton = e.target.closest(SELECTORS.CONTROL_RESUME);
    if (resumeButton) {
        e.preventDefault();
        resume(state);
    }
};

/**
 * Handle keyboard events.
 *
 * @param {Object} state - Player state
 * @param {KeyboardEvent} e - Keyboard event
 */
const handleKeyboard = (state, e) => {
    if (!state.overlayContainer) {
        return;
    }

    switch (e.key) {
        case 'ArrowRight':
            e.preventDefault();
            state.config.onNext();
            break;
        case 'ArrowLeft':
            if (state.config.onBack) {
                e.preventDefault();
                state.config.onBack();
            }
            break;
        case 'Escape':
            e.preventDefault();
            state.config.onClose();
            break;
        case ' ':
            e.preventDefault();
            if (state.autoplayEnabled) {
                pause(state);
            } else {
                resume(state);
            }
            break;
    }
};

/**
 * Start the autoplay countdown.
 *
 * @param {Object} state - Player state
 */
const startAutoplay = (state) => {
    stopAutoplayTimer(state);

    if (!state.currentDuration) {
        return;
    }

    const overlay = state.overlayContainer?.querySelector(SELECTORS.OVERLAY);

    // Update paused class based on current state.
    if (overlay) {
        if (state.autoplayEnabled) {
            overlay.classList.remove('mod_kahoodle-progress-paused');
        } else {
            overlay.classList.add('mod_kahoodle-progress-paused');
        }
    }

    updateProgressBar(state);

    if (!state.autoplayEnabled) {
        return;
    }

    const durationMs = state.currentDuration * 1000;
    const remainingMs = durationMs - state.autoplayElapsed;

    if (remainingMs <= 0) {
        state.config.onNext();
        return;
    }

    state.autoplayStartTime = Date.now();

    const updateLoop = () => {
        if (!state.autoplayEnabled || !state.overlayContainer) {
            return;
        }

        const elapsed = state.autoplayElapsed + (Date.now() - state.autoplayStartTime);
        const remaining = durationMs - elapsed;
        const progress = Math.min((elapsed / durationMs) * 100, 100);

        const progressFill = state.overlayContainer.querySelector(SELECTORS.PROGRESS_FILL);
        if (progressFill) {
            progressFill.style.width = progress + '%';
        }

        const countdownTime = state.overlayContainer.querySelector(SELECTORS.COUNTDOWN_TIME);
        if (countdownTime) {
            countdownTime.textContent = formatCountdown(remaining);
        }

        if (elapsed >= durationMs) {
            state.autoplayTimerId = setTimeout(() => {
                state.config.onNext();
            }, 50);
        } else {
            state.autoplayTimerId = requestAnimationFrame(updateLoop);
        }
    };

    state.autoplayTimerId = requestAnimationFrame(updateLoop);
};

/**
 * Stop the autoplay timer.
 *
 * @param {Object} state - Player state
 */
const stopAutoplayTimer = (state) => {
    if (state.autoplayTimerId) {
        cancelAnimationFrame(state.autoplayTimerId);
        clearTimeout(state.autoplayTimerId);
        state.autoplayTimerId = null;
    }
};

/**
 * Update the progress bar and countdown to reflect current elapsed time.
 *
 * @param {Object} state - Player state
 */
const updateProgressBar = (state) => {
    if (!state.currentDuration || !state.overlayContainer) {
        return;
    }

    const durationMs = state.currentDuration * 1000;
    const remainingMs = durationMs - state.autoplayElapsed;
    const progress = Math.min((state.autoplayElapsed / durationMs) * 100, 100);

    const progressFill = state.overlayContainer.querySelector(SELECTORS.PROGRESS_FILL);
    if (progressFill) {
        progressFill.style.width = progress + '%';
    }

    const countdownTime = state.overlayContainer.querySelector(SELECTORS.COUNTDOWN_TIME);
    if (countdownTime) {
        countdownTime.textContent = formatCountdown(remainingMs);
    }
};

/**
 * Format milliseconds as M:SS countdown string.
 *
 * @param {number} ms - Milliseconds remaining
 * @returns {string} Formatted time string (e.g. "1:30")
 */
const formatCountdown = (ms) => {
    const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
};
