/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Placeholder } from '@wordpress/components';
import { pullquote } from '@wordpress/icons';

function Edit() {
	return <Placeholder label={ __( 'Marketplace', 'newspack-ads' ) } icon={ pullquote } />;
}

export default Edit;
