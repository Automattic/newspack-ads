( function ( api, $ ) {
	$( document ).ready( function () {
		if ( 'undefined' !== typeof api.selectiveRefresh ) {
			/**
			 * The placement preview render callback does not differ different hooks
			 * and renders all placement mocks. We should hide placement hooks that
			 * don't belong in this preview.
			 */
			api.selectiveRefresh.bind( 'partial-content-rendered', function ( placement ) {
				const containerClasses = placement.container[ 0 ].classList;
				const hookClass = [ ...containerClasses ].find( className => /^hook-/.test( className ) );
				if ( hookClass ) {
					placement.container
						.find( `.newspack-ads__ad-placement-mock:not(.${ hookClass })` )
						.hide();
				}
			} );
		}
	} );
} )( wp.customize, window.jQuery );
