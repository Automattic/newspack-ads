'use strict';

/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import { Component } from '@wordpress/element';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect, select } from '@wordpress/data';

/**
 * Add a section to the Document settings with a toggle for suppressing ads on the current single.
 */
class NewspackSuppressAdsPanel extends Component {
	render() {
		const { meta, updateSuppressAds } = this.props;
		console.log( meta );
		return (
			<PluginDocumentSettingPanel
				name="newspack-ad-free"
				title={ __( 'Suppress Ads', 'newspack' ) }
				className="newspack-subtitle"
			>
				<ToggleControl 
					label={ __( 'Don\'t show ads on this post or page', 'newspack' ) }
        			checked={ meta.newspack_suppress_ads }
					onChange={ value => {
						updateSuppressAds( value, meta );
					} }
				/>
			</PluginDocumentSettingPanel>
		);
	}
};

const ComposedPanel = compose( [
	withSelect( _select => {
		const { getCurrentPostAttribute, getEditedPostAttribute } = _select( 'core/editor' );
		return {
			meta: { ...getCurrentPostAttribute( 'meta' ), ...getEditedPostAttribute( 'meta' ) },
		};
	} ),
	withDispatch( dispatch => ( {
		updateSuppressAds( value, meta ) {
			meta = {
				...meta,
				newspack_suppress_ads: value,
			};
			dispatch( 'core/editor' ).editPost( { meta } );
		},
	} ) ),
] )( NewspackSuppressAdsPanel );


registerPlugin( 'plugin-document-setting-panel-newspack-suppress-ads', {
	render: ComposedPanel,
	icon: null,
} );
