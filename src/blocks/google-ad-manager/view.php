<?php
/**
 * Server-side rendering of the `newspack-gam/author-bio` block.
 *
 * @package WordPress
 */

/**
 * Renders the `newspack-blocks/author-bio` block on server.
 *
 * @param array $attributes The block attributes.
 *
 * @return string Returns the post content with latest posts added.
 */
function newspack_gam_render_block_google_ad_manager( $attributes ) {
	$active_ad = isset( $attributes['activeAd'] ) ? (int) $attributes['activeAd'] : 0;
	if ( 1 > $active_ad ) {
		return '';
	}

	$classes = Newspack_GAM_Blocks::block_classes( 'newspack-gam-google-ad-manager', $attributes );

	$ad_unit = Newspack_GAM_Model::get_ad_unit( $active_ad );
	$content = sprintf(
		'<div class="%s">%s</div>',
		esc_attr( $classes ),
		$ad_unit['code'] /* TODO: escape with wp_kses() */
	);

	Newspack_GAM_Blocks::enqueue_view_assets( 'google-ad-manager' );

	return $content;
}

/**
 * Registers the `newspack-blocks/google-ad-manager` block on server.
 */
function newspack_gam_register_google_ad_manager() {
	register_block_type(
		'newspack-gam/google-ad-manager',
		array(
			'attributes'      => array(
				'activeAd' => array(
					'type' => 'integer',
				),
			),
			'render_callback' => 'newspack_gam_render_block_google_ad_manager',
		)
	);
}
add_action( 'init', 'newspack_gam_register_google_ad_manager' );
