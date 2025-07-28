import { domReady, debounce, elementCollides } from './utils';

/**
 * Selector for the main element to place the side rail placements on.
 *
 * @type {string}
 */
const mainElement = '#main';

/**
 * Selectors for elements to detect collisions with.
 *
 * @type {string[]}
 */
const collisionElements = [
	'#masthead',
	'#main',
	'.above-footer-widgets',
	'#colophon',
	'.newspack_global_ad.sticky',
];

/**
 * Initialize a side rail placement.
 *
 * @param {string}   selector The selector for the side rail placement.
 * @param {string}   side     The side of the side rail placement.
 * @param {NodeList} elements The elements to detect collisions with.
 *
 * @return {void}
 */
function initPlacement(selector, side, elements) {
	const main = document.querySelector(mainElement);
	if (!main) {
		return;
	}

	const element = document.querySelector(selector);
	if (!element) {
		return;
	}
	element.style.right = 'auto';

	const ad = element.querySelector('div');
	if (!ad) {
		return;
	}

	// Prepend a reference div to the element.
	const refDiv = document.createElement('div');
	refDiv.style.position = 'absolute';
	refDiv.style.top = '0';
	refDiv.style.left = '0';
	refDiv.style.width = '100%';
	refDiv.style.height = '100%';
	refDiv.style.zIndex = '9999';
	refDiv.style.pointerEvents = 'none';
	element.prepend(refDiv);

	const handleCollision = () => {
		if (elementCollides(refDiv, elements)) {
			ad.style.display = 'none';
		} else {
			ad.style.display = 'block';
		}
	};

	const fixPosition = () => {
		const mainRect = main.getBoundingClientRect();
		if (side === 'left') {
			element.style.left = `${mainRect.left - element.offsetWidth}px`;
		} else {
			element.style.left = `${mainRect.right}px`;
		}
	};

	const handlePlacement = () => {
		fixPosition();
		handleCollision();
	};

	window.addEventListener('scroll', debounce(handlePlacement, 75));
	window.addEventListener('resize', debounce(handlePlacement, 75));

	window.googletag = window.googletag || { cmd: [] };
	window.googletag.cmd.push(function () {
		window.googletag
			.pubads()
			.addEventListener('slotRenderEnded', function (event) {
				const container = document.getElementById(
					event.slot.getSlotElementId()
				);
				if (container.parentNode !== element) {
					return;
				}
				container.parentNode.style.width = event.size[0] + 'px';
				handlePlacement();
			});
	});
}

domReady(() => {
	const elements = document.querySelectorAll(collisionElements.join(','));
	initPlacement('.newspack_global_ad.left_side_rail', 'left', elements);
	initPlacement('.newspack_global_ad.right_side_rail', 'right', elements);
});
