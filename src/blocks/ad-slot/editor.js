/**
 * WordPress dependencies
 */
import { unregisterBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { registerBlock } from '../utils/register-block';
import { ad as icon } from '../utils/icons';
import edit from './edit';
import metadata from './block.json';

/**
 * Style dependencies - will load in editor.
 */
import './editor.scss';

const { name } = metadata;

const labels = {
	title: __( 'Ad Slot', 'newspack-ads' ),
	description: __( 'Render an ad in a wizard-managed global placement (above header, sticky footer, etc.).', 'newspack-ads' ),
};

const adSlot = {
	name,
	settings: {
		...metadata,
		...labels,
		icon: {
			src: icon,
			foreground: '#406ebc',
		},
		edit,
		save: () => null, // Dynamic block — PHP render_block() owns the front-end markup.
	},
};

// wp.domReady is required for core filters to work with this custom block.
// See https://github.com/WordPress/gutenberg/issues/9757.
wp.domReady( () => {
	registerBlock( adSlot );

	// Restrict the Ad Slot block to the Site Editor. The `editor` bundle is
	// enqueued in both the post editor and the Site Editor, so unregister it
	// when we're not in the Site Editor.
	if ( ! window.location.pathname.endsWith( '/wp-admin/site-editor.php' ) ) {
		unregisterBlockType( name );
	}
} );
