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
};

// Cache for preview questions data, keyed by roundId.
let previewCache = {};

// Current preview state.
let currentPreviewState = {
    roundId: null,
    questions: [],
    currentIndex: 0,
    overlayContainer: null,
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
 * @returns {Object} Processed question data ready for template
 */
const processQuestionData = (question) => {
    // Decode the JSON-encoded type-specific data.
    const typeData = JSON.parse(question.typedata || '{}');

    // Store decoded type data under typedata to avoid naming conflicts.
    const processed = {...question, typedata: typeData};

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

    // Process each question to decode typedata and add isType boolean.
    const processedQuestions = response.questions.map(processQuestionData);

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
            questions: questions,
            currentIndex: startIndex,
            overlayContainer: null,
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
    const question = currentPreviewState.questions[currentPreviewState.currentIndex];
    if (!question) {
        return;
    }

    // Render the template.
    const html = await Templates.render('mod_kahoodle/question_progress', question);

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
    if (newIndex < 0 || newIndex >= currentPreviewState.questions.length) {
        return;
    }

    currentPreviewState.currentIndex = newIndex;
    await showPreviewOverlay();
};

/**
 * Close the preview overlay
 */
const closePreview = () => {
    if (currentPreviewState.overlayContainer) {
        currentPreviewState.overlayContainer.remove();
        currentPreviewState.overlayContainer = null;
    }

    // Remove keyboard event listener.
    document.removeEventListener('keydown', handlePreviewKeyboard);

    // Reset state.
    currentPreviewState = {
        roundId: null,
        questions: [],
        currentIndex: 0,
        overlayContainer: null,
    };
};
