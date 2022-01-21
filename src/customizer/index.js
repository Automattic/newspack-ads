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

( function( $ ) {
	wp.customize.bind( 'ready', function() {
		let controls = [];
		const sections = wp.customize.panel( 'newspack-ads' ).sections();
		sections.forEach( function( section ) {
			controls = controls.concat( section.controls() );
		} );
		controls.forEach( function( control ) {
			if ( control.params.type !== 'newspack_ads_placement' ) {
				return;
			}
			let value;
			try {
				value = JSON.parse( control.setting.get() || '{}' );
			} catch ( e ) {
				value = { enabled: false };
			}
			const updateValue = ( path, val ) => {
				value = set( value, path, val );
				control.setting.set( JSON.stringify( value ) );
			};
			control.container.on( 'change', 'input[type=checkbox]', function() {
				updateValue( 'enabled', $( this ).is( ':checked' ) );
			} );
			control.container.on( 'change', 'select', function() {
				const $select = $( this );
				const hook = $select.data( 'hook' );
				let path = 'ad_unit';
				if ( hook ) {
					path = [ 'hooks', hook, 'ad_unit' ];
				}
				updateValue( path, $select.val() );
			} );
		} );
	} );
} )( window.jQuery );
