/**
 * WordPress dependencies
 */
import { getCategories } from '@wordpress/blocks';
import { pullquote } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import edit from './edit';

export const name = 'marketplace';
export const title = __( 'Marketplace', 'newspack-ads' );

export const settings = {
	title,
	icon: {
		src: pullquote,
		foreground: '#36f',
	},
	category: getCategories().some( ( { slug } ) => slug === 'newspack' ) ? 'newspack' : 'common',
	keywords: [
		__( 'ad', 'newspack-ads' ),
		__( 'advert', 'newspack-ads' ),
		__( 'ads', 'newspack-ads' ),
	],
	description: __( 'Sell your ad slots.', 'newspack-ads' ),
	attributes: {},
	supports: {
		html: false,
		align: [ 'left', 'center', 'right', 'wide', 'full' ],
		color: {
			text: false,
			background: true,
		},
	},
	edit,
	save: () => null, // to use Newspack_Ads\Marketplace::render_marketplace_purchase()
};
