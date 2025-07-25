import './style.scss';

const mainElement = '#main';

const margin = 32;

const collidableElements = [
	'#masthead',
	'#main',
	'.above-footer-widgets',
	'#colophon',
	'.newspack_global_ad.sticky',
];

function debounce(fn, delay) {
	let timeoutId;
	return function (...args) {
		clearTimeout(timeoutId);
		timeoutId = setTimeout(() => fn.apply(this, args), delay);
	};
}

function checkCollision(rect, targetElement) {
	const targetRect = targetElement.getBoundingClientRect();
	return !(
		rect.right < targetRect.left ||
		rect.left > targetRect.right ||
		rect.bottom < targetRect.top ||
		rect.top > targetRect.bottom
	);
}

const checkCollisions = (element, elements, cb) => {
	const rect = element.getBoundingClientRect();
	// Out of screen bounds.
	if (
		rect.left < 0 ||
		window.innerWidth < rect.right ||
		rect.top < 0 ||
		window.innerHeight < rect.bottom
	) {
		cb(true);
		return;
	}
	// Check for any collision with collidable elements.
	cb([...elements].some(el => checkCollision(rect, el)));
};

function initPlacement(selector, side) {
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

	const handleCollisions = () => {
		checkCollisions(refDiv, collisionElements, collided => {
			if (collided) {
				ad.style.display = 'none';
			} else {
				ad.style.display = 'block';
			}
		});
	};

	const fixPosition = () => {
		const mainRect = main.getBoundingClientRect();
		if (side === 'left') {
			element.style.left = `${mainRect.left - element.offsetWidth - margin}px`;
		} else {
			element.style.left = `${mainRect.right + margin}px`;
		}
	};

	const handlePlacement = () => {
		fixPosition();
		handleCollisions();
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

const collisionElements = document.querySelectorAll(
	collidableElements.join(',')
);
initPlacement('.newspack_global_ad.left_side_rail', 'left');
initPlacement('.newspack_global_ad.right_side_rail', 'right');
