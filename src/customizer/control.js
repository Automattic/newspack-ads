const set = ( obj = {}, paths = [], value ) => {
	const inputObj = obj === null ? {} : { ...obj };

	if ( ! Array.isArray( paths ) ) {
		paths = [ paths ];
	}

	if ( paths.length === 0 ) {
		return inputObj;
	}

	if ( paths.length === 1 ) {
		const path = paths[ 0 ];
		inputObj[ path ] = value;
		return { ...inputObj, [ path ]: value };
	}

	const [ path, ...rest ] = paths;
	const currentNode = inputObj[ path ];

	const childNode = set( currentNode, rest, value );

	return { ...inputObj, [ path ]: childNode };
};

( function ( $ ) {
	wp.customize.bind( 'ready', function () {
		let controls = [];
		const sections = wp.customize.panel( 'newspack-ads' ).sections();
		sections.forEach( function ( section ) {
			controls = controls.concat( section.controls() );
		} );
		controls.forEach( function ( control ) {
			if ( control.params.type !== 'newspack_ads_placement' ) {
				return;
			}
			let value;
			try {
				value = JSON.parse( control.setting.get() || '{}' );
			} catch ( e ) {
				value = { enabled: false, provider: 'gam' };
			}
			control.container.find( `[data-provider]` ).hide();
			control.container.find( `[data-provider="${ value.provider || 'gam' }"]` ).show();
			const updateValue = ( hook, path, val ) => {
				if ( hook ) {
					path = [ 'hooks', hook, path ];
				}
				value = set( value, path, val );
				control.setting.set( JSON.stringify( value ) );
			};
			control.container.on( 'change', 'input[type=checkbox]', function () {
				updateValue( '', 'enabled', $( this ).is( ':checked' ) );
			} );
			control.container.find( '.placement-hook-control' ).each( function () {
				const $container = $( this );
				const $provider = $container.find( '.provider-select select' );
				const $adUnit = $container.find( '.ad-unit-select select' );
				const hook = $( this ).data( 'hook' ) || '';
				$container.find( '[data-provider]' ).hide();
				$container.find( `[data-provider="${ $provider.val() }"]` ).show();
				$provider.on( 'change', function () {
					updateValue( hook, 'provider', $provider.val() );
					$container.find( '[data-provider]' ).hide();
					$container.find( `[data-provider="${ $provider.val() }"]` ).show();
				} );
				$adUnit.on( 'change', function () {
					updateValue( hook, 'ad_unit', $adUnit.val() );
				} );
			} );
		} );
	} );
} )( window.jQuery );
