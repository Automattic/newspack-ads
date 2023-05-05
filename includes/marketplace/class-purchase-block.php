<?php
/**
 * Newspack Ads Marketplace Purchase Block.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Marketplace;

use Newspack_Ads\Marketplace;
use Newspack_Ads\Placements;

/**
 * Newspack Ads Marketplace Purchase Block Class.
 */
final class Purchase_Block {

	const PURCHASE_ACTION = 'newspack_ads_purchase';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'init', [ __CLASS__, 'register_block' ] );
		\add_action( 'template_redirect', [ __CLASS__, 'handle_purchase' ] );
	}

	/**
	 * Register block.
	 */
	public static function register_block() {
		\register_block_type(
			'newspack-ads/marketplace',
			[
				'render_callback' => [ __CLASS__, 'render_marketplace_purchase' ],
			]
		);
	}

	/**
	 * Handle purchase.
	 */
	public static function handle_purchase() {
		if ( ! isset( $_POST[ self::PURCHASE_ACTION ] ) ) {
			return;
		}
		$nonce = \sanitize_text_field( \wp_unslash( $_POST[ self::PURCHASE_ACTION ] ) );
		if ( ! \wp_verify_nonce( $nonce, self::PURCHASE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'newspack-ads' ) );
		}

		$products = isset( $_POST['products'] ) ? array_map( 'sanitize_text_field', \wp_unslash( $_POST['products'] ) ) : [];
		if ( empty( $products ) ) {
			\wp_die( \esc_html__( 'No products selected.', 'newspack-ads' ) );
		}

		$cart = \WC()->cart;
		$cart->empty_cart();
		foreach ( $products as $product_id ) {
			$cart->add_to_cart( $product_id );
		}
		$cart->calculate_totals();
		$cart->maybe_set_cart_cookies();

		$checkout_url = \wc_get_checkout_url();
		\wp_safe_redirect( $checkout_url );
		exit;
	}

	/**
	 * Render the UI for puchasing placements.
	 */
	public static function render_marketplace_purchase() {
		$placements = Placements::get_placements();
		$products   = array_map( [ 'Newspack_Ads\Marketplace', 'get_product_data' ], Marketplace::get_products() );
		ob_start();
		?>
		<div class="newspack-ads__marketplace">
			<form method="POST" target="_top">
				<?php wp_nonce_field( self::PURCHASE_ACTION, self::PURCHASE_ACTION ); ?>
				<div class="newspack-ads__marketplace__products">
					<?php foreach ( $products as $product ) : ?>
						<p>
							<label>
								<input type="checkbox" name="products[]" value="<?php echo esc_attr( $product['id'] ); ?>" />
								<?php echo esc_html( Marketplace::get_product_title( $product ) ); ?>
							</label>
						</p>
					<?php endforeach; ?>
				</div>
				<div class="newspack-ads_marketplace__period">
					<p>
						<input type="date" name="from" />
						<input type="date" name="to" />
					</p>
				</div>
				<button class="newspack-ads_marketplace__purchase"><?php esc_html_e( 'Proceed to payment', 'newspack-ads' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
Purchase_Block::init();
