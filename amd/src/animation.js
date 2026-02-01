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

const SELECTORS = {
    MAINELEMENT: '.mod_kahoodle-leaderboard-main[data-stage="revision"]',
    PODIUMCONTAINER: '.mod_kahoodle-podium-container',
    PODIUMSVG: '.mod_kahoodle-podium-svg',
    PODIUMWINNERS: '.mod_kahoodle-podium-winners'
};

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
    const podiumSvg = main.querySelector(SELECTORS.PODIUMSVG);
    const winners = main.querySelector(SELECTORS.PODIUMWINNERS);

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
    container.style.opacity = '1';

    // Show podium for 10 seconds, then switch to leaders.
    setTimeout(function() {
        main.dataset.substage = 'leaders';
    }, 10000);
}