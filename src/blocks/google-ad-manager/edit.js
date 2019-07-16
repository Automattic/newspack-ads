/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component, Fragment } from '@wordpress/element';
import { SelectControl, Placeholder, withNotices } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { icon } from './';

class Edit extends Component {
	/**
	 * Constructor.
	 */
	constructor() {
		super( ...arguments );
		this.state = {
			adUnits: [],
		};
	}

	componentDidMount() {
		apiFetch( { path: '/newspack/v1/wizard/adunits' } ).then( adUnits =>
			this.setState( { adUnits } )
		);
	}

	componentDidUpdate( prevProps ) {
		const { attributes, noticeOperations } = this.props;
		const { activeAd } = attributes;
		if ( activeAd !== prevProps.attributes.activeAd ) {
			const { code, width, height } = this.activeAdDataForActiveAd( activeAd );
			if ( code && ( ! width && ! height ) ) {
				noticeOperations.createErrorNotice( __( 'Invalid ad unit code. No dimensions available' ) );
			} else {
				noticeOperations.removeAllNotices();
			}
		}
	}

	adUnitsForSelect = adUnits => {
		return [
			{
				label: __( 'Select an ad unit' ),
				value: null,
			},
			...Object.values( adUnits ).map( adUnit => {
				return {
					label: adUnit.name,
					value: adUnit.id,
				};
			} ),
		];
	};

	activeAdDataForActiveAd = activeAd => {
		const { adUnits } = this.state;
		const data = adUnits.find( adUnit => parseInt( adUnit.id ) === parseInt( activeAd ) );
		return this.dimensionsFromAd( data );
	};

	dimensionsFromAd = adData => {
		const { noticeOperations } = this.props;
		const { code } = adData || {};
		const widthRegex = /width[:=].*?([0-9].*?)(?:px|\s)/i;
		const widthMatch = ( code || '' ).match( widthRegex );
		const heightRegex = /height[:=].*?([0-9].*?)(?:px|\s)/i;
		const heightMatch = ( code || '' ).match( heightRegex );
		const width = widthMatch && parseInt( widthMatch[ 1 ] );
		const height = heightMatch && parseInt( heightMatch[ 1 ] );
		return {
			code,
			width,
			height,
		};
	};

	render() {
		/**
		 * Constants
		 */
		const { attributes, setAttributes, noticeUI } = this.props;
		const { activeAd } = attributes;
		const { adUnits } = this.state;
		const { width, height } = this.activeAdDataForActiveAd( activeAd );
		const style = {
			width: `${ width || 400 }px`,
			height: `${ height || 100 }px`,
		};
		return (
			<Fragment>
				{ noticeUI }
				<div className="wp-block-newspack-blocks-google-ad-manager">
					<div className="newspack-gam-ad" style={ style }>
						<Placeholder>
							<SelectControl
								label={ __( 'Ad Unit' ) }
								value={ activeAd }
								options={ this.adUnitsForSelect( adUnits ) }
								onChange={ activeAd => setAttributes( { activeAd } ) }
							/>
						</Placeholder>
					</div>
				</div>
			</Fragment>
		);
	}
}

export default withNotices( Edit );
