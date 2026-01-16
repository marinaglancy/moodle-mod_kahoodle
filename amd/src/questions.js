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

const SELECTORS = {
    QUESTIONS_REGION: '[data-region="mod_kahoodle-questions"]',
    ADD_QUESTION_BUTTON: '[data-action="mod_kahoodle-add-question"]',
    EDIT_QUESTION_BUTTON: '[data-action="mod_kahoodle-edit-question"]',
    DELETE_QUESTION_BUTTON: '[data-action="mod_kahoodle-delete-question"]',
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
            }
        });
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
