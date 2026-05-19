/**
 * WordPress dependencies
 */
import { registerBlockType, unregisterBlockType } from '@wordpress/blocks';
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import { name, settings } from '.';

const blockName = `newspack-ads/${ name }`;

registerBlockType( blockName, settings );

/**
 * Restrict the Ad Placement block to the Site Editor.
 *
 * The webpack `editor` entry is enqueued in both the post editor and the Site
 * Editor (via Ad_Unit_Block::enqueue_block_assets). Once the DOM is ready we
 * check the URL: if we're not in the Site Editor, unregister the block so it
 * doesn't appear in the post-editor inserter.
 */
domReady( () => {
	if ( ! window.location.pathname.endsWith( '/wp-admin/site-editor.php' ) ) {
		unregisterBlockType( blockName );
	}
} );
