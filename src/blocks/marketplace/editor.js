/**
 * WordPress dependencies
 */
import { getCategories, registerBlockType } from '@wordpress/blocks';
import { pullquote } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { Placeholder } from '@wordpress/components';

const settings = {
	title: __( 'Marketplace', 'newspack-ads' ),
	icon: {
		src: pullquote,
		foreground: '#36f',
	},
	category: getCategories().some( ( { slug } ) => slug === 'newspack' ) ? 'newspack' : 'common',
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
	edit: () => <Placeholder label={ __( 'Marketplace', 'newspack-ads' ) } icon={ pullquote } />,
	save: () => null, // to use Newspack_Ads\Marketplace\Purchase_Block::render_marketplace_purchase()
};

registerBlockType( 'newspack-ads/marketplace', settings );
