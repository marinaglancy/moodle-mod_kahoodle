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
import SortableList from 'core/sortable_list';
import $ from 'jquery';
import * as DynamicTable from 'core_table/dynamic';
import * as Player from 'mod_kahoodle/player';

const SELECTORS = {
    QUESTIONS_REGION: '[data-region="mod_kahoodle-questions"]',
    ADD_QUESTION_BUTTON: '[data-action="mod_kahoodle-add-question"]',
    EDIT_QUESTION_BUTTON: '[data-action="mod_kahoodle-edit-question"]',
    DELETE_QUESTION_BUTTON: '[data-action="mod_kahoodle-delete-question"]',
    DUPLICATE_QUESTION_BUTTON: '[data-action="mod_kahoodle-duplicate-question"]',
    PREVIEW_QUESTION_BUTTON: '[data-action="mod_kahoodle-preview-question"]',
    SORTABLE_QUESTIONS_LIST: '[data-region="mod_kahoodle-questions"] tbody',
};

// Cache for preview questions data, keyed by roundId.
let previewCache = {};

// Preview state.
let previewPlayerState = null;
let previewStages = [];
let previewIndex = 0;

/**
 * Initialize the questions page
 *
 * @param {number} roundId The round ID
 * @param {Array} questionTypes Array of {type, name} objects for available question types
 * @param {boolean} isFullyEditable Whether the round is fully editable (can add/delete/reorder)
 */
export const init = (roundId, questionTypes, isFullyEditable) => {
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

            const duplicateButton = e.target.closest(SELECTORS.DUPLICATE_QUESTION_BUTTON);
            if (duplicateButton) {
                e.preventDefault();
                const roundQuestionId = parseInt(duplicateButton.dataset.roundquestionid, 10);
                const targetRoundId = duplicateButton.dataset.targetroundid
                    ? parseInt(duplicateButton.dataset.targetroundid, 10) : 0;
                await duplicateQuestion(roundQuestionId, targetRoundId);
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
            reportElement.addEventListener(DynamicTable.Events.tableContentRefreshed, () => {
                // Clear the preview cache when report reloads.
                previewCache = {};
                // Re-initialize sorting after reload (only if fully editable).
                if (isFullyEditable) {
                    initSorting();
                }
            });
        }

        if (isFullyEditable) {
            initSorting();
        }
    }
};

/**
 * Initialize sortable question list
 */
const initSorting = () => {
    const listElement = $(SELECTORS.SORTABLE_QUESTIONS_LIST);
    listElement.find('> tr.emptyrow').remove();
    const getRowOrder = (element, prop = 'sortorder') =>
        parseInt(element?.find('td:first-child span')?.attr('data-' + prop), 10);

    const sortableList = new SortableList(listElement);
    sortableList.getItemOrder = getRowOrder;
    sortableList.getElementName = function(element) {
        return getString('sortorderx', 'mod_kahoodle', getRowOrder(element));
    };
    const sortableColumns = $(SELECTORS.SORTABLE_QUESTIONS_LIST + ' > tr');
    sortableColumns.on(SortableList.EVENTS.DROP, async(e, info) => {
        if (!info.positionChanged) {
            return null;
        }
        const roundquestionid = getRowOrder(info.element, 'roundquestionid');
        const oldsortorder = getRowOrder(info.element);
        let newsortorder = getRowOrder(info.targetNextElement);
        if (isNaN(newsortorder)) {
            // Moved to the end.
            newsortorder = -1;
        } else if (newsortorder > oldsortorder) {
            newsortorder -= 1;
        }
        // Call web service to update sort order.
        try {
            await fetchMany([{
                methodname: 'mod_kahoodle_change_question_sortorder',
                args: {
                    roundquestionid: roundquestionid,
                    newsortorder: newsortorder,
                },
            }]);
            // Reload the questions table to reflect new order.
            reloadQuestionsTable();
        } catch (error) {
            Notification.exception(error);
        }
        return null;
    });
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
 * Duplicate a question
 *
 * @param {number} roundQuestionId The round question ID to duplicate
 * @param {number} targetRoundId Target round ID (0 = same round)
 */
const duplicateQuestion = async(roundQuestionId, targetRoundId = 0) => {
    if (targetRoundId) {
        // Cross-round duplication: show confirmation and redirect after.
        const confirmMessage = await getString('duplicatequestiontoround', 'mod_kahoodle');
        const confirmTitle = await getString('duplicatequestion', 'mod_kahoodle');
        Notification.confirm(
            confirmTitle,
            confirmMessage,
            await getString('duplicatequestion', 'mod_kahoodle'),
            await getString('cancel', 'core'),
            async() => {
                try {
                    await fetchMany([{
                        methodname: 'mod_kahoodle_duplicate_question',
                        args: {roundquestionid: roundQuestionId, targetroundid: targetRoundId},
                    }])[0];
                    window.location.href = M.cfg.wwwroot + '/mod/kahoodle/questions.php?roundid=' + targetRoundId;
                } catch (error) {
                    Notification.exception(error);
                }
            }
        );
    } else {
        // Same-round duplication: no confirmation needed.
        try {
            await fetchMany([{
                methodname: 'mod_kahoodle_duplicate_question',
                args: {roundquestionid: roundQuestionId},
            }])[0];
            reloadQuestionsTable();
        } catch (error) {
            Notification.exception(error);
        }
    }
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

        previewStages = questions;
        previewIndex = startIndex;

        previewPlayerState = Player.create({
            containerClass: 'mod_kahoodle-preview-container',
            onNext: () => navigatePreview(1),
            onBack: () => navigatePreview(-1),
            onClose: () => closePreview(),
        });

        await showCurrentPreviewStage();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Show the current preview stage
 */
const showCurrentPreviewStage = async() => {
    const question = previewStages[previewIndex];
    if (!question || !previewPlayerState) {
        return;
    }

    await Player.showStage(previewPlayerState, question.template, question, question.duration);
};

/**
 * Navigate to the previous or next question in preview
 *
 * @param {number} direction -1 for previous, 1 for next
 */
const navigatePreview = async(direction) => {
    const newIndex = previewIndex + direction;

    // Check bounds.
    if (newIndex < 0 || newIndex >= previewStages.length) {
        return;
    }

    previewIndex = newIndex;
    await showCurrentPreviewStage();
};

/**
 * Close the preview overlay
 */
const closePreview = () => {
    if (previewPlayerState) {
        Player.close(previewPlayerState);
        previewPlayerState = null;
    }

    previewStages = [];
    previewIndex = 0;
};
