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
 * Question management JavaScript module
 *
 * @module     mod_kahoodle/questions
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {dispatchEvent} from 'core/event_dispatcher';
import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';
import * as reportEvents from 'core_reportbuilder/local/events';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import Notification from 'core/notification';
import {call as fetchMany} from 'core/ajax';
import Templates from 'core/templates';

const SELECTORS = {
    QUESTIONS_REGION: '[data-region="mod_kahoodle-questions"]',
    ADD_QUESTION_BUTTON: '[data-action="mod_kahoodle-add-question"]',
    EDIT_QUESTION_BUTTON: '[data-action="mod_kahoodle-edit-question"]',
    DELETE_QUESTION_BUTTON: '[data-action="mod_kahoodle-delete-question"]',
    PREVIEW_QUESTION_BUTTON: '[data-action="mod_kahoodle-preview-question"]',
    PREVIEW_OVERLAY: '.mod_kahoodle-overlay',
    PREVIEW_CONTROL_BACK: '[data-action="back"]',
    PREVIEW_CONTROL_NEXT: '[data-action="next"]',
    PREVIEW_CONTROL_CLOSE: '[data-action="close"]',
    PREVIEW_CONTROL_PAUSE: '[data-action="pause"]',
    PREVIEW_CONTROL_RESUME: '[data-action="resume"]',
    PROGRESS_FILL: '.mod_kahoodle-progress-fill',
};

// Cache for preview questions data, keyed by roundId.
let previewCache = {};

// Current preview state.
let currentPreviewState = {
    roundId: null,
    questionstages: [],
    currentIndex: 0,
    overlayContainer: null,
    autoplayEnabled: true,
    autoplayTimerId: null,
    autoplayStartTime: null,
    autoplayElapsed: 0,
};

/**
 * Initialize the questions page
 *
 * @param {number} roundId The round ID
 * @param {Array} questionTypes Array of {type, name} objects for available question types
 */
export const init = (roundId, questionTypes) => {
    // Use event delegation for all buttons.
    const questionsRegion = document.querySelector(SELECTORS.QUESTIONS_REGION);
    if (questionsRegion) {
        questionsRegion.addEventListener('click', async(e) => {
            const addButton = e.target.closest(SELECTORS.ADD_QUESTION_BUTTON);
            if (addButton) {
                e.preventDefault();
                const questionType = addButton.dataset.questiontype;
                await openQuestionForm(roundId, 0, questionType, questionTypes);
                return;
            }

            const editButton = e.target.closest(SELECTORS.EDIT_QUESTION_BUTTON);
            if (editButton) {
                e.preventDefault();
                const roundQuestionId = editButton.dataset.roundquestionid;
                await openQuestionForm(roundId, parseInt(roundQuestionId, 10), null, questionTypes);
                return;
            }

            const deleteButton = e.target.closest(SELECTORS.DELETE_QUESTION_BUTTON);
            if (deleteButton) {
                e.preventDefault();
                const questionId = deleteButton.dataset.questionid;
                await deleteQuestion(parseInt(questionId, 10));
                return;
            }

            const previewButton = e.target.closest(SELECTORS.PREVIEW_QUESTION_BUTTON);
            if (previewButton) {
                e.preventDefault();
                const previewRoundId = parseInt(previewButton.dataset.roundid, 10);
                const roundQuestionId = parseInt(previewButton.dataset.roundquestionid, 10);
                await openPreview(previewRoundId, roundQuestionId);
            }
        });

        // Listen for report reload events to clear the cache.
        const reportElement = questionsRegion.querySelector(reportSelectors.regions.report);
        if (reportElement) {
            reportElement.addEventListener(reportEvents.tableReload, () => {
                // Clear the preview cache when report reloads.
                previewCache = {};
            });
        }
    }
};

/**
 * Open the question form modal
 *
 * @param {number} roundId The round ID
 * @param {number} roundQuestionId Optional round question ID for editing
 * @param {string|null} questionType Question type for add mode
 * @param {Array} questionTypes Array of {type, name} objects for available question types
 */
const openQuestionForm = async(roundId, roundQuestionId = 0, questionType = null, questionTypes = []) => {
    // Determine modal title.
    let title;
    if (roundQuestionId) {
        title = await getString('editquestion', 'mod_kahoodle');
    } else {
        // Find the question type name.
        const typeInfo = questionTypes.find(t => t.type === questionType);
        const typeName = typeInfo ? typeInfo.name : questionType;
        title = await getString('addquestiontype', 'mod_kahoodle', typeName);
    }

    const args = {
        roundid: roundId,
        roundquestionid: roundQuestionId,
    };

    // Pass question type for add mode.
    if (questionType) {
        args.questiontype = questionType;
    }

    const modalForm = new ModalForm({
        formClass: 'mod_kahoodle\\form\\question',
        args: args,
        modalConfig: {
            title: title,
        },
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        reloadQuestionsTable();
    });

    modalForm.show();
};

/**
 * Delete a question with confirmation
 *
 * @param {number} questionId The question ID to delete
 */
const deleteQuestion = async(questionId) => {
    const confirmMessage = await getString('deletequestionconfirm', 'mod_kahoodle');
    const confirmTitle = await getString('deletequestion', 'mod_kahoodle');

    Notification.confirm(
        confirmTitle,
        confirmMessage,
        await getString('delete', 'core'),
        await getString('cancel', 'core'),
        async() => {
            try {
                await fetchMany([{
                    methodname: 'mod_kahoodle_delete_question',
                    args: {questionid: questionId},
                }])[0];
                reloadQuestionsTable();
            } catch (error) {
                Notification.exception(error);
            }
        }
    );
};

/**
 * Reload the questions table
 */
const reloadQuestionsTable = () => {
    const questionsRegion = document.querySelector(SELECTORS.QUESTIONS_REGION);
    const reportElement = questionsRegion?.querySelector(reportSelectors.regions.report);
    if (reportElement) {
        dispatchEvent(reportEvents.tableReload, {preservePagination: true}, reportElement);
    }
};

/**
 * Process question data from web service for template use
 *
 * Decodes type-specific JSON data and adds typeis<type> boolean property.
 *
 * @param {Object} question Raw question data from web service
 * @param {Object} generalData General data to merge into question data
 * @returns {Object} Processed question data ready for template
 */
const processQuestionData = (question, generalData) => {
    // Decode the JSON-encoded type-specific data.
    const typeData = JSON.parse(question.typedata || '{}');

    // Store decoded type data under typedata to avoid naming conflicts.
    const processed = {...generalData, ...question, typedata: typeData};

    // Add typeis<type> boolean for Mustache conditional rendering.
    // E.g., questiontype "multichoice" becomes typeismultichoice: true.
    const typeName = question.questiontype;
    processed['typeis' + typeName] = true;

    return processed;
};

/**
 * Fetch preview questions from the web service
 *
 * @param {number} roundId The round ID
 * @returns {Promise<Array>} Array of question data
 */
const fetchPreviewQuestions = async(roundId) => {
    // Check cache first.
    if (previewCache[roundId]) {
        return previewCache[roundId];
    }

    const response = await fetchMany([{
        methodname: 'mod_kahoodle_preview_questions',
        args: {roundid: roundId},
    }])[0];

    // General data is everything in the reponse except questions (quiz name, number of questions, etc).
    const generalData = {...response};
    delete generalData.questionstages;

    // Process each question to decode typedata and add isType boolean.
    const processedQuestions = response.questionstages.map((q) => processQuestionData(q, generalData));

    // Cache the processed result.
    previewCache[roundId] = processedQuestions;
    return processedQuestions;
};

/**
 * Open the preview overlay
 *
 * @param {number} roundId The round ID
 * @param {number} roundQuestionId The round question ID to start preview from
 */
const openPreview = async(roundId, roundQuestionId) => {
    try {
        const questions = await fetchPreviewQuestions(roundId);

        if (!questions || questions.length === 0) {
            return;
        }

        // Find the index of the question to preview.
        let startIndex = questions.findIndex(q => q.roundquestionid === roundQuestionId);
        if (startIndex === -1) {
            startIndex = 0;
        }

        // Store current state.
        currentPreviewState = {
            roundId: roundId,
            questionstages: questions,
            currentIndex: startIndex,
            overlayContainer: null,
            autoplayEnabled: true,
            autoplayTimerId: null,
            autoplayStartTime: null,
            autoplayElapsed: 0,
        };

        // Create and show the overlay.
        await showPreviewOverlay();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Show the preview overlay for the current question
 */
const showPreviewOverlay = async() => {
    const question = currentPreviewState.questionstages[currentPreviewState.currentIndex];
    if (!question) {
        return;
    }

    // Render the template.
    const html = await Templates.render(question.template, question);

    // Create or update the overlay container.
    if (!currentPreviewState.overlayContainer) {
        currentPreviewState.overlayContainer = document.createElement('div');
        currentPreviewState.overlayContainer.className = 'mod_kahoodle-preview-container';
        currentPreviewState.overlayContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
        `;
        document.body.appendChild(currentPreviewState.overlayContainer);

        // Add event listeners for controls.
        currentPreviewState.overlayContainer.addEventListener('click', handlePreviewControls);

        // Add keyboard navigation.
        document.addEventListener('keydown', handlePreviewKeyboard);
    }

    currentPreviewState.overlayContainer.innerHTML = html;

    // Reset elapsed time for the new question.
    currentPreviewState.autoplayElapsed = 0;

    // Start or update autoplay based on current state.
    startAutoplay();
};

/**
 * Start or resume the autoplay countdown
 */
const startAutoplay = () => {
    // Stop any existing timer.
    stopAutoplayTimer();

    const question = currentPreviewState.questionstages[currentPreviewState.currentIndex];
    if (!question || !question.duration) {
        return;
    }

    const overlay = currentPreviewState.overlayContainer?.querySelector(SELECTORS.PREVIEW_OVERLAY);

    // Update paused class based on current state.
    if (overlay) {
        if (currentPreviewState.autoplayEnabled) {
            overlay.classList.remove('mod_kahoodle-progress-paused');
        } else {
            overlay.classList.add('mod_kahoodle-progress-paused');
        }
    }

    // Update progress bar to current position.
    updateProgressBar();

    // Only start timer if autoplay is enabled.
    if (!currentPreviewState.autoplayEnabled) {
        return;
    }

    const durationMs = question.duration * 1000;
    const remainingMs = durationMs - currentPreviewState.autoplayElapsed;

    if (remainingMs <= 0) {
        // Time is up, go to next question.
        navigatePreview(1);
        return;
    }

    currentPreviewState.autoplayStartTime = Date.now();

    // Use requestAnimationFrame for smooth progress updates.
    const updateLoop = () => {
        if (!currentPreviewState.autoplayEnabled || !currentPreviewState.overlayContainer) {
            return;
        }

        const elapsed = currentPreviewState.autoplayElapsed + (Date.now() - currentPreviewState.autoplayStartTime);
        const progress = Math.min((elapsed / durationMs) * 100, 100);

        const progressFill = currentPreviewState.overlayContainer.querySelector(SELECTORS.PROGRESS_FILL);
        if (progressFill) {
            progressFill.style.width = progress + '%';
        }

        if (elapsed >= durationMs) {
            // Time is up, set to 100% and go to next question after a brief moment.
            currentPreviewState.autoplayTimerId = setTimeout(() => {
                navigatePreview(1);
            }, 50);
        } else {
            currentPreviewState.autoplayTimerId = requestAnimationFrame(updateLoop);
        }
    };

    currentPreviewState.autoplayTimerId = requestAnimationFrame(updateLoop);
};

/**
 * Stop the autoplay timer
 */
const stopAutoplayTimer = () => {
    if (currentPreviewState.autoplayTimerId) {
        cancelAnimationFrame(currentPreviewState.autoplayTimerId);
        clearTimeout(currentPreviewState.autoplayTimerId);
        currentPreviewState.autoplayTimerId = null;
    }
};

/**
 * Update the progress bar to reflect current elapsed time
 */
const updateProgressBar = () => {
    const question = currentPreviewState.questionstages[currentPreviewState.currentIndex];
    if (!question || !question.duration || !currentPreviewState.overlayContainer) {
        return;
    }

    const durationMs = question.duration * 1000;
    const progress = Math.min((currentPreviewState.autoplayElapsed / durationMs) * 100, 100);

    const progressFill = currentPreviewState.overlayContainer.querySelector(SELECTORS.PROGRESS_FILL);
    if (progressFill) {
        progressFill.style.width = progress + '%';
    }
};

/**
 * Pause the autoplay countdown
 */
const pauseAutoplay = () => {
    if (!currentPreviewState.autoplayEnabled) {
        return;
    }

    // Stop the timer first to prevent further updates.
    stopAutoplayTimer();

    // Calculate and store elapsed time.
    if (currentPreviewState.autoplayStartTime) {
        currentPreviewState.autoplayElapsed += Date.now() - currentPreviewState.autoplayStartTime;
        currentPreviewState.autoplayStartTime = null;
    }

    currentPreviewState.autoplayEnabled = false;

    // Update progress bar to exact position immediately.
    updateProgressBar();

    // Add paused class to overlay.
    const overlay = currentPreviewState.overlayContainer?.querySelector(SELECTORS.PREVIEW_OVERLAY);
    if (overlay) {
        overlay.classList.add('mod_kahoodle-progress-paused');
    }
};

/**
 * Resume the autoplay countdown
 */
const resumeAutoplay = () => {
    if (currentPreviewState.autoplayEnabled) {
        return;
    }

    currentPreviewState.autoplayEnabled = true;

    // Remove paused class from overlay.
    const overlay = currentPreviewState.overlayContainer?.querySelector(SELECTORS.PREVIEW_OVERLAY);
    if (overlay) {
        overlay.classList.remove('mod_kahoodle-progress-paused');
    }

    // Restart the timer.
    startAutoplay();
};

/**
 * Handle preview control button clicks
 *
 * @param {Event} e The click event
 */
const handlePreviewControls = (e) => {
    const backButton = e.target.closest(SELECTORS.PREVIEW_CONTROL_BACK);
    if (backButton) {
        e.preventDefault();
        navigatePreview(-1);
        return;
    }

    const nextButton = e.target.closest(SELECTORS.PREVIEW_CONTROL_NEXT);
    if (nextButton) {
        e.preventDefault();
        navigatePreview(1);
        return;
    }

    const closeButton = e.target.closest(SELECTORS.PREVIEW_CONTROL_CLOSE);
    if (closeButton) {
        e.preventDefault();
        closePreview();
        return;
    }

    const pauseButton = e.target.closest(SELECTORS.PREVIEW_CONTROL_PAUSE);
    if (pauseButton) {
        e.preventDefault();
        pauseAutoplay();
        return;
    }

    const resumeButton = e.target.closest(SELECTORS.PREVIEW_CONTROL_RESUME);
    if (resumeButton) {
        e.preventDefault();
        resumeAutoplay();
    }
};

/**
 * Handle keyboard navigation in preview
 *
 * @param {KeyboardEvent} e The keyboard event
 */
const handlePreviewKeyboard = (e) => {
    if (!currentPreviewState.overlayContainer) {
        return;
    }

    switch (e.key) {
        case 'ArrowLeft':
            e.preventDefault();
            navigatePreview(-1);
            break;
        case 'ArrowRight':
            e.preventDefault();
            navigatePreview(1);
            break;
        case 'Escape':
            e.preventDefault();
            closePreview();
            break;
        case ' ':
            e.preventDefault();
            if (currentPreviewState.autoplayEnabled) {
                pauseAutoplay();
            } else {
                resumeAutoplay();
            }
            break;
    }
};

/**
 * Navigate to the previous or next question in preview
 *
 * @param {number} direction -1 for previous, 1 for next
 */
const navigatePreview = async(direction) => {
    const newIndex = currentPreviewState.currentIndex + direction;

    // Check bounds.
    if (newIndex < 0 || newIndex >= currentPreviewState.questionstages.length) {
        return;
    }

    currentPreviewState.currentIndex = newIndex;
    await showPreviewOverlay();
};

/**
 * Close the preview overlay
 */
const closePreview = () => {
    // Stop autoplay timer.
    stopAutoplayTimer();

    if (currentPreviewState.overlayContainer) {
        currentPreviewState.overlayContainer.remove();
        currentPreviewState.overlayContainer = null;
    }

    // Remove keyboard event listener.
    document.removeEventListener('keydown', handlePreviewKeyboard);

    // Reset state.
    currentPreviewState = {
        roundId: null,
        questionstages: [],
        currentIndex: 0,
        overlayContainer: null,
        autoplayEnabled: true,
        autoplayTimerId: null,
        autoplayStartTime: null,
        autoplayElapsed: 0,
    };
};
