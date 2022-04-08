import { set, debounce } from 'lodash';

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
			const _update = debounce( function () {
				control.setting.set( JSON.stringify( value ) );
			}, 300 );
			const updateValue = ( hook, path, val ) => {
				if ( ! Array.isArray( path ) ) {
					path = [ path ];
				}
				if ( hook ) {
					path = [ 'hooks', hook ].concat( path );
				}
				value = set( value, path, val );
				_update();
			};
			control.container.on( 'change', 'input[type=checkbox]', function () {
				updateValue( '', 'enabled', $( this ).is( ':checked' ) );
			} );
			control.container.find( '.placement-hook-control' ).each( function () {
				const $container = $( this );
				const $provider = $container.find( '.provider-select select' );
				const $adUnit = $container.find( '.ad-unit-select select' );
				const $bidders_ids = $container.find( '.bidder-id-input input' );
				const hook = $container.data( 'hook' ) || '';
				$provider.on( 'change', function () {
					const val = $( this ).val();
					updateValue( hook, 'provider', val );
					// Clear ad unit values
					$adUnit.val( '' ).change();
					// Show provider specific inputs
					$container.find( '[data-provider]' ).hide();
					$container.find( `[data-provider="${ val }"]` ).show();
				} );
				$adUnit.on( 'change', function () {
					updateValue( hook, 'ad_unit', $( this ).val() );
				} );
				$bidders_ids.on( 'change', function () {
					const bidderId = $( this ).data( 'bidder-id' );
					updateValue( hook, [ 'bidders_ids', bidderId ], $( this ).val() );
				} );
			} );
		} );
	} );
} )( window.jQuery );
