/**
 * WordPress dependencies
 */
import { getCategories } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ad as icon } from '../utils/icons';
import edit from './edit';

/**
 * Style dependencies - will load in editor
 */
import './editor.scss';

export const name = 'ad-slot';
export const title = __( 'Ad Slot', 'newspack-ads' );

export const settings = {
	apiVersion: 3,
	title,
	icon: {
		src: icon,
		foreground: '#406ebc',
	},
	category: getCategories().some( ( { slug } ) => slug === 'newspack' ) ? 'newspack' : 'common',
	keywords: [ __( 'ad', 'newspack-ads' ), __( 'slot', 'newspack-ads' ), __( 'placement', 'newspack-ads' ), __( 'ads', 'newspack-ads' ) ],
	description: __( 'Render an ad in a wizard-managed global placement (above header, sticky footer, etc.).', 'newspack-ads' ),
	attributes: {
		placement: {
			type: 'string',
			default: '',
		},
	},
	supports: {
		html: false,
		visibility: false,
	},
	edit,
	save: () => null, // Dynamic block — PHP render_block() owns the front-end markup.
};
