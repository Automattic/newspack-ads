/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useState, useEffect } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { SVG, PanelBody, Placeholder, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import PlacementControl from '../../placements/placement-control';

function Edit( { attributes, setAttributes } ) {
	const [ inFlight, setInFlight ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ biddersError, setBiddersError ] = useState( null );
	const [ providers, setProviders ] = useState( [] );
	const [ bidders, setBidders ] = useState( [] );

	useEffect( async () => {
		setInFlight( true );
		// Fetch providers.
		try {
			setProviders( await apiFetch( { path: '/newspack-ads/v1/providers' } ) );
		} catch ( err ) {
			setError( err );
		}
		// Fetch bidders.
		try {
			setBidders( await apiFetch( { path: '/newspack-ads/v1/bidders' } ) );
		} catch ( err ) {
			setBiddersError( err );
		}
		setInFlight( false );
	}, [] );

	// Legacy attribute.
	if ( attributes.activeAd ) {
		setAttributes( { ad_unit: attributes.activeAd } );
	}

	const provider = providers.find( p => p.id === attributes.provider );
	const unit = provider?.units?.find( u => u.value === attributes.ad_unit );
	const sizes = unit?.sizes || [];
	const containerHeight = Math.max( ...sizes.map( s => s[ 1 ] ), 100 );

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={ __( 'Ad settings', 'newspack-newsletters' ) }>
					{ inFlight ? (
						<Spinner />
					) : (
						<PlacementControl
							providers={ providers }
							bidders={ bidders }
							value={ attributes }
							onChange={ value => setAttributes( value ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<Placeholder label={ __( 'Ad', 'newspack-ads' ) }>
				{ error && <Notice isError noticeText={ error } isDismissible={ false } /> }
				{ provider === 'gam' && biddersError && (
					<Notice isWarning noticeText={ biddersError } isDismissible={ false } />
				) }
				<div className="newspack-ads-ad-block-placeholder" style={ { height: containerHeight } }>
					{ sizes.map( ( size, index ) => (
						<div
							key={ attributes.ad_unit + index }
							style={ { width: size[ 0 ], height: size[ 1 ] } }
						>
							<SVG>
								<line x1="0" y1="100%" x2="100%" y2="0" />
								<line x1="0" y1="0" x2="100%" y2="100%" />
							</SVG>
						</div>
					) ) }
					{ inFlight && <Spinner /> }
				</div>
			</Placeholder>
		</Fragment>
	);
}

export default Edit;
