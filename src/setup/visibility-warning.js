/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';

const AD_BLOCK = 'newspack-ads/ad-unit';
const NOTICE_ID = 'newspack-ads/ad-visibility-warning';

const AdVisibilityWarning = () => {
	const hasHiddenAdContainer = useSelect( select => {
		const { getClientIdsWithDescendants, getBlockName, getBlockParents, getBlockAttributes } =
			select( 'core/block-editor' );
		const isHidden = clientId => {
			const viewport = getBlockAttributes( clientId )?.metadata?.blockVisibility?.viewport;
			return viewport && Object.values( viewport ).some( visible => visible === false );
		};
		return getClientIdsWithDescendants().some( id => {
			if ( getBlockName( id ) !== AD_BLOCK ) {
				return false;
			}
			return getBlockParents( id ).some( isHidden );
		} );
	}, [] );

	const { createWarningNotice, removeNotice } = useDispatch( 'core/notices' );

	useEffect( () => {
		if ( hasHiddenAdContainer ) {
			createWarningNotice(
				'<p>' +
					__(
						'One or more hidden blocks contain an ad unit. Ad blocks and their containers will remain visible on all screen sizes — hiding ads with CSS may result in penalties.',
						'newspack-ads'
					) +
					'</p><p>' +
					__(
						'To control which ads appear at different breakpoints, use your ad provider settings. To find the hidden block, go to Document Overview > List view.',
						'newspack-ads'
					) +
					'</p>',
				{ id: NOTICE_ID, isDismissible: true, __unstableHTML: true }
			);
		} else {
			removeNotice( NOTICE_ID );
		}
	}, [ hasHiddenAdContainer, createWarningNotice, removeNotice ] );

	return null;
};

registerPlugin( 'newspack-ads-visibility-warning', { render: AdVisibilityWarning } );
