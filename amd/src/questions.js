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

const SELECTORS = {
    QUESTIONS_REGION: '[data-region="mod_kahoodle-questions"]',
    ADD_QUESTION_BUTTON: '[data-action="mod_kahoodle-add-question"]',
    EDIT_QUESTION_BUTTON: '[data-action="mod_kahoodle-edit-question"]',
};

/**
 * Initialize the add question button
 *
 * @param {number} roundId The round ID
 */
export const initAddQuestion = (roundId) => {
    const addButton = document.querySelector(SELECTORS.ADD_QUESTION_BUTTON);
    if (addButton) {
        addButton.addEventListener('click', async(e) => {
            e.preventDefault();
            await openQuestionForm(roundId);
        });
    }

    // Use event delegation for edit buttons (they are dynamically rendered in the report).
    const questionsRegion = document.querySelector(SELECTORS.QUESTIONS_REGION);
    if (questionsRegion) {
        questionsRegion.addEventListener('click', async(e) => {
            const editButton = e.target.closest(SELECTORS.EDIT_QUESTION_BUTTON);
            if (editButton) {
                e.preventDefault();
                const roundQuestionId = editButton.dataset.roundquestionid;
                await openQuestionForm(roundId, parseInt(roundQuestionId, 10));
            }
        });
    }
};

/**
 * Open the question form modal
 *
 * @param {number} roundId The round ID
 * @param {number} roundQuestionId Optional round question ID for editing
 */
const openQuestionForm = async(roundId, roundQuestionId = 0) => {
    const modalForm = new ModalForm({
        formClass: 'mod_kahoodle\\form\\question',
        args: {
            roundid: roundId,
            roundquestionid: roundQuestionId,
        },
        modalConfig: {
            title: await getString(roundQuestionId ? 'editquestion' : 'addquestion', 'mod_kahoodle'),
        },
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        const questionsRegion = document.querySelector(SELECTORS.QUESTIONS_REGION);
        const reportElement = questionsRegion?.querySelector(reportSelectors.regions.report);
        if (reportElement) {
            dispatchEvent(reportEvents.tableReload, {preservePagination: true}, reportElement);
        }
    });

    modalForm.show();
};
