/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component } from '@wordpress/element';
import { SelectControl, Placeholder } from '@wordpress/components';
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

	dimensionsFromAd = adData => {
		const { code } = adData || {};
		const widthRegex = /width[:=].*?([0-9].*?)(?:px|\s)/i;
		const width = ( code || '' ).match( widthRegex );
		const heightRegex = /height[:=].*?([0-9].*?)(?:px|\s)/i;
		const height = ( code || '' ).match( heightRegex );
		return {
			width: width ? parseInt( width[ 1 ] ) : 450,
			height: height ? parseInt( height[ 1 ] ) : 100,
		};
	};

	render() {
		/**
		 * Constants
		 */
		const { attributes, setAttributes } = this.props;
		const { activeAd } = attributes;
		const { adUnits } = this.state;
		const activeAdData = adUnits.find( adUnit => parseInt( adUnit.id ) === parseInt( activeAd ) );
		const { width, height } = this.dimensionsFromAd( activeAdData );
		const style = {
			width: `${ width }px`,
			height: `${ height }px`,
		};
		return (
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
		);
	}
}

export default Edit;
