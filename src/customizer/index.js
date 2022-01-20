import { set } from 'lodash';

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
			const $json = control.container.find( 'input[type=hidden]' );
			let value;
			try {
				value = JSON.parse( control.setting.get() || '{}' );
			} catch ( e ) {
				value = { enabled: false };
			}
			control.container.on( 'change', 'input[type=checkbox]', function() {
				set( value, 'enabled', $( this ).is( ':checked' ) );
				$json.val( JSON.stringify( value ) );
				control.setting.set( JSON.stringify( value ) );
			} );
			control.container.on( 'change', 'select', function() {
				const $select = $( this );
				const ad_unit = $select.val();
				const hook = $select.data( 'hook' );
				let path = 'ad_unit';
				if ( hook ) {
					path = [ 'hooks', hook, 'ad_unit' ];
				}
				set( value, path, ad_unit );
				$json.val( JSON.stringify( value ) );
				control.setting.set( JSON.stringify( value ) );
			} );
		} );
	} );
} )( window.jQuery );
