// This file is part of Moodle - http://moodle.org/
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
 * TODO describe module game
 *
 * @module     mod_kahoodle/game
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as PubSub from 'core/pubsub';
import * as RealTimeEvents from 'tool_realtime/events';
import * as Notification from 'core/notification';
import * as RealTimeApi from 'tool_realtime/api';
import Templates from 'core/templates';

const SELECTORS = {
    MAINDIV: '#mod_kahoodle_game[data-cmid][data-contextid]',
    TRANSITIONBUTTON: '[data-action="transition"]',
    JOINBUTTON: '[data-action="join"]',

};

var initialised = false;

export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    PubSub.subscribe(RealTimeEvents.CONNECTION_LOST, (e) => {
        window.console.log('Error', e);
        Notification.exception({
            name: 'Error',
            message: 'Something went wrong, please refresh the page'});
    });

    PubSub.subscribe(RealTimeEvents.EVENT, (data) => {
        window.console.log('received event ' + JSON.stringify(data));
        const {context, component, payload} = data;
        window.console.log('-details-: '+JSON.stringify({contextid: context.id, component, payload}));
        const node = document.querySelector(SELECTORS.MAINDIV);
        if (!payload || component != 'mod_kahoodle' || context.id != node.dataset.contextid) {
            window.console.log('Ignoring event for different context or component');
            return;
        }

        const updates = data.payload;
        window.console.log('updates = ' + JSON.stringify(updates));
        // Render updates.
        if (!updates['template']) {
            window.console.error('Unexpected result - template is missing');
        } else if (updates['data'] === undefined) {
            window.console.error('Unexpected result - data is missing');
        } else {
            // TODO validate template and data.
            Templates.render(updates['template'], updates['data'])
            .then(function(html, js) {
                // Append the link to the most suitable place on the page with fallback to legacy selectors and finally the body if
                // there is no better place.
                Templates.replaceNodeContents(node, html, js);

                return null;
            })
            .catch(function(error) {
                window.console.error('Error rendering template:', error);
            });
        }
    });

    document.addEventListener('click', (e) => {
        // const joinButton = e.target.closest(SELECTORS.MAINDIV + " " + SELECTORS.JOINBUTTON);
        // if (joinButton) {
        //     doJoin();
        // }
        const transitionButton = e.target.closest(SELECTORS.MAINDIV + " " + SELECTORS.TRANSITIONBUTTON);
        if (transitionButton) {
            doTransition();
        }
    });
};

export const doTransition = () => {
    const node = document.querySelector(SELECTORS.MAINDIV);
    RealTimeApi.sendToServer({
        contextid: node.dataset.contextid,
        component: 'mod_kahoodle',
        area: 'game',
        itemid: 0,
    }, {
        action: 'transition'
    });
};

export const doAnswer = (questionid, answer) => {
    const node = document.querySelector(SELECTORS.MAINDIV);
    RealTimeApi.sendToServer({
        contextid: node.dataset.contextid,
        component: 'mod_kahoodle',
        area: 'game',
        itemid: parseInt(node.dataset.playerid ?? 0)
    }, {
        action: 'answer',
        questionid,
        answer
    });
};


// export const doJoin = () => {
//     const node = document.querySelector(SELECTORS.MAINDIV);
//     RealTimeApi.sendToServer({
//         contextid: node.dataset.contextid,
//         component: 'mod_kahoodle',
//         area: 'game',
//         itemid: 0
//     }, {
//         action: 'join',
//     });
// };
