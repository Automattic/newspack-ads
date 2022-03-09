/**
 * External dependencies
 */
import { v4 as uuid } from 'uuid';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
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

	const provider = providers.find( p => p.id.toString() === attributes.provider );
	const unit = provider?.units?.find( u => u.value.toString() === attributes.ad_unit );
	const sizes = unit?.sizes || [];
	const containerWidth = Math.max( ...sizes.map( s => s[ 0 ] ) ) || 300;
	const containerHeight = Math.max( ...sizes.map( s => s[ 1 ] ) ) || 100;

	let label = __( 'Ad', 'newspack-ads' );
	if ( provider && unit ) {
		label = sprintf(
			// translators: %1$s is the provider name. %2$s is the ad unit name.
			__( 'Ad: %1$s - %2$s', 'newspack-ads' ),
			provider.name,
			unit.name
		);
	}

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
							onChange={ value =>
								setAttributes( {
									...value,
									id: uuid(),
								} )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<Placeholder label={ label }>
				{ error && <Notice isError noticeText={ error } isDismissible={ false } /> }
				{ provider === 'gam' && biddersError && (
					<Notice isWarning noticeText={ biddersError } isDismissible={ false } />
				) }
				<div className="newspack-ads-ad-block-placeholder" style={ { height: containerHeight } }>
					{ sizes?.length > 0 && (
						<Fragment>
							<SVG
								className="newspack-ads-ad-block-mock"
								width={ containerWidth }
								viewBox={ '0 0 ' + containerWidth + ' ' + containerHeight }
							>
								<line x1="0" y1="100%" x2="100%" y2="0" />
								<line x1="0" y1="0" x2="100%" y2="100%" />
								{ sizes.map( ( size, index ) => (
									<rect
										key={ attributes.ad_unit + index }
										width={ size[ 0 ] }
										height={ size[ 1 ] }
										x="50%"
										y="50%"
										transform={ 'translate(' + -( size[ 0 ] / 2 ) + ',' + -( size[ 1 ] / 2 ) + ')' }
									/>
								) ) }
							</SVG>
							<span className="newspack-ads-ad-block-ad-label">
								{ sprintf(
									// translators: %s is a comma-separated list of ad sizes.
									__( 'Ad: %s', 'newspack-ads' ),
									sizes.map( size => `${ size[ 0 ] }x${ size[ 1 ] }` ).join( ', ' )
								) }
							</span>
						</Fragment>
					) }
					{ inFlight && <Spinner /> }
				</div>
			</Placeholder>
		</Fragment>
	);
}

export default Edit;
