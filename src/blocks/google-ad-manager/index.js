/**
 * External dependencies
 */
import { Path, SVG } from '@wordpress/components';
import { getCategories } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { __ } from '@wordpress/i18n';
import edit from './edit';
/**
 * Style dependencies - will load in editor
 */
import './editor.scss';

export const name = 'google-ad-manager';
export const title = __( 'Google Ad Manager' );

/* From https://material.io/tools/icons */
export const icon = (
	<SVG xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
		<Path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
		<Path d="M0 0h24v24H0z" fill="none"/>
	</SVG>
);
export const settings = {
	title,
	icon,
	category: getCategories().some( ( { slug } ) => slug === 'newspack' ) ? 'newspack' : 'common',
	keywords: [ __( 'ad' ), __( 'advert' ), __( 'ads' ) ],
	description: __( 'A block for displaying ad inventory from Google Ad Manager.' ),
	attributes: {
		activeAd: {
			type: 'string',
		}
	},
	supports: {
		html: false,
		align: true,
	},
	edit,
	save: () => null, // to use view.php
};
