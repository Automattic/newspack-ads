import './style.scss';

const mainElement = '#main';

const margin = 32;

const collidableElements = [
	'#masthead',
	'#main',
	'.post-thumbnail',
	'.entry-header',
	'.main-content',
	'.above-footer-widgets',
	'#colophon',
	'.newspack_global_ad.sticky',
];

function checkCollision(rect, targetElement) {
	const targetRect = targetElement.getBoundingClientRect();
	return !(
		rect.right < targetRect.left ||
		rect.left > targetRect.right ||
		rect.bottom < targetRect.top ||
		rect.top > targetRect.bottom
	);
}

const handleCollisions = (element, elements, cb) => () => {
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

	const collisionCb = collided => {
		if (collided) {
			ad.style.display = 'none';
		} else {
			ad.style.display = 'block';
		}
	};

	const position = () => {
		const mainRect = main.getBoundingClientRect();
		if (side === 'left') {
			element.style.left = `${mainRect.left - element.offsetWidth - margin}px`;
		} else {
			element.style.left = `${mainRect.right + margin}px`;
		}
	};
	position();

	const collisionElements = document.querySelectorAll(
		collidableElements.join(',')
	);

	window.addEventListener(
		'scroll',
		handleCollisions(refDiv, collisionElements, collisionCb)
	);
	window.addEventListener(
		'resize',
		handleCollisions(refDiv, collisionElements, collisionCb)
	);
	window.addEventListener('resize', position);
}

initPlacement('.newspack_global_ad.left_side_rail', 'left');
initPlacement('.newspack_global_ad.right_side_rail', 'right');
