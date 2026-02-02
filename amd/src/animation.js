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
 * Animation elements for kahoodle.
 *
 * @module     mod_kahoodle/animation
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as PubSub from 'core/pubsub';

const SELECTORS = {
    MAINELEMENT: '.mod_kahoodle-leaderboard-main[data-stage="revision"]',
    PODIUMCONTAINER: '.mod_kahoodle-podium-container',
    PODIUMSVG: '.mod_kahoodle-podium-svg',
    PODIUMWINNERS: '.mod_kahoodle-podium-winners'
};

const ANIMATIONDELAY = 2500; // Milliseconds.

/**
 * On loading revision stage - display podium first and leaders later.
 */
export function initRevisionStage() {
    const main = document.querySelector(SELECTORS.MAINELEMENT);
    if (main) {
        if (main.dataset.skippodium === '1' || window.innerWidth < 930) {
            // Skip podium, go directly to leaders.
            main.dataset.substage = 'leaders';
        } else {
            // Show podium first and then switch to leaders.
            main.dataset.substage = 'podium';
            initPodium(main);
        }
    }
}

/**
 * Initialize podium positioning and resizing.
 *
 * @param {HTMLElement} main
 */
function initPodium(main) {
    const container = main.querySelector(SELECTORS.PODIUMCONTAINER);
    const podiumSvg = container.querySelector(SELECTORS.PODIUMSVG);
    const winners = container.querySelector(SELECTORS.PODIUMWINNERS);

    if (!container || !podiumSvg || !winners) {
        return;
    }

    const positionElements = function() {
        const containerHeight = container.offsetHeight;
        const containerWidth = container.offsetWidth;
        const bottomMargin = 20;

        // Podium is 45% of container height.
        let podiumHeight = Math.round(containerHeight * 0.45);

        // SVG aspect ratio is approximately 3940:1130 (width:height).
        const svgAspectRatio = 3940 / 1130;
        let podiumWidth = podiumHeight * svgAspectRatio;

        // Ensure podium doesn't exceed container width.
        if (podiumWidth > containerWidth) {
            podiumWidth = containerWidth;
            podiumHeight = podiumWidth / svgAspectRatio;
        }

        // Position podium at bottom with margin.
        podiumSvg.style.bottom = bottomMargin + 'px';
        podiumSvg.style.height = podiumHeight + 'px';
        podiumSvg.style.width = 'auto';

        // Wait for SVG to render, then match winners width.
        requestAnimationFrame(function() {
            const actualPodiumWidth = podiumSvg.offsetWidth;
            winners.style.width = (actualPodiumWidth * 183 / 197) + 'px';
            // Position winners just above the podium.
            winners.style.bottom = (bottomMargin + podiumHeight) + 'px';
        });
    };

    // Initial positioning.
    positionElements();

    // Reposition on resize.
    window.addEventListener('resize', positionElements);

    // Start the animation, then switch to leaders.
    podiumAnimation(container)
    .then(() => null)
    .catch(() => null)
    .finally(() => {
        main.dataset.substage = 'leaders';
        PubSub.publish('mod_kahoodle:reveal', 'all');
    });
}

/**
 * Play podium animation.
 *
 * @param {HTMLElement} container
 * @returns {Promise<void>}
 */
async function podiumAnimation(container) {
    const podiumSvg = container.querySelector(SELECTORS.PODIUMSVG);
    const winners = container.querySelector(SELECTORS.PODIUMWINNERS);

    // Get podium group elements.
    const group1 = podiumSvg.querySelector('#group1');
    const group2 = podiumSvg.querySelector('#group2');
    const group3 = podiumSvg.querySelector('#group3');

    // Get points text elements (may not exist if no score).
    const rank1Points = podiumSvg.querySelector('#rank1-points');
    const rank2Points = podiumSvg.querySelector('#rank2-points');
    const rank3Points = podiumSvg.querySelector('#rank3-points');

    // Get winner containers.
    const winnerRank1 = winners.querySelector('.mod_kahoodle-podium-place.mod_kahoodle-podium-rank1');
    const winnerRank2 = winners.querySelector('.mod_kahoodle-podium-place.mod_kahoodle-podium-rank2');
    const winnerRank3 = winners.querySelector('.mod_kahoodle-podium-place.mod_kahoodle-podium-rank3');

    // Set initial opacity to 0 for all animated elements.
    [group1, group2, group3, rank1Points, rank2Points, rank3Points, winnerRank1, winnerRank2, winnerRank3]
    .filter(el => el !== null)
    .forEach(el => {
        el.style.opacity = '0';
    });

    container.style.opacity = '1';

    await sleep(ANIMATIONDELAY);

    // Animate rank 3 (bronze).
    await fadeIn(group3);
    if (rank3Points) {
        await sleep(ANIMATIONDELAY);
        await fadeIn(rank3Points);
        await sleep(ANIMATIONDELAY);
        await fadeIn(winnerRank3);
        PubSub.publish('mod_kahoodle:reveal_rank', 'rank3');
        await sleep(ANIMATIONDELAY * 2);
    } else {
        fadeIn(winnerRank3);
        await sleep(ANIMATIONDELAY);
    }

    // Animate rank 2 (silver).
    await fadeIn(group2);
    if (rank2Points) {
        await sleep(ANIMATIONDELAY);
        await fadeIn(rank2Points);
        await sleep(ANIMATIONDELAY);
        await fadeIn(winnerRank2);
        PubSub.publish('mod_kahoodle:reveal_rank', 'rank2');
        await sleep(ANIMATIONDELAY * 2);
    } else {
        fadeIn(winnerRank2);
        await sleep(ANIMATIONDELAY);
    }

    // Animate rank 1 (gold).
    await fadeIn(group1);
    await sleep(ANIMATIONDELAY);
    await fadeIn(rank1Points);
    await sleep(ANIMATIONDELAY);
    await fadeIn(winnerRank1);
    PubSub.publish('mod_kahoodle:reveal_rank', 'rank1');

    await sleep(ANIMATIONDELAY * 5);
}

/**
 * Pause execution for a specified duration.
 *
 * @param {Number} ms time in milliseconds
 * @returns {Promise<void>}
 */
async function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Gradually fade in an element by transitioning its opacity to 1.
 *
 * @param {HTMLElement|null} element The element to fade in
 * @param {Number} duration Duration of the transition in milliseconds
 * @returns {Promise<void>}
 */
async function fadeIn(element, duration = 500) {
    if (!element) {
        return;
    }
    element.style.transition = `opacity ${duration}ms ease-in-out`;
    element.style.opacity = '1';
    await sleep(duration);
}