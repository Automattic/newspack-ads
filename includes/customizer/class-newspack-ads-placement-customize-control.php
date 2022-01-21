<?php
/**
 * Newspack Ads Placement Customize Control.
 * 
 * @package Newspack
 */

// Require WP_Customize_Control.
require_once ABSPATH . 'wp-includes/class-wp-customize-control.php';

/**
 * Newspack Ads Placement Customize Control Class.
 */
class Newspack_Ads_Placement_Customize_Control extends \WP_Customize_Control {

	/**
	 * Customize control type.
	 *
	 * @var string
	 */
	public $type = 'newspack_ads_placement';

	/**
	 * Available ad units.
	 *
	 * @var array[]
	 */
	private $ad_units = null;

	/**
	 * Placement configuration.
	 * 
	 * @var array
	 */
	private $placement = null;

	/**
	 * Constructor.
	 *
	 * @param WP_Customize_Manager $manager Customizer manager.
	 * @param string               $id      Control ID.
	 * @param array                $args    Control arguments.
	 */
	public function __construct( $manager, $id, $args = [] ) {
		// Available ad units.
		$this->ad_units = Newspack_Ads_Model::get_ad_units();

		// Placement configuration.
		if ( isset( $args['placement'] ) ) {
			$placements      = Newspack_Ads_Placements::get_placements();
			$this->placement = $placements[ $args['placement'] ];
		} else {
			return new WP_Error( 'newspack_ads_placement_customize_control_no_placement', __( 'No placement specified.', 'newspack-ads' ) );
		}

		parent::__construct( $manager, $id, $args );
	}

	/**
	 * To Json.
	 */
	public function to_json() {
		parent::to_json();

		$value = $this->value();
		if ( $value ) {
			if ( $this->placement['hook_name'] ) {
				$this->json['ad_unit'] = $this->get_ad_unit_value();
			}
			if ( isset( $this->placement['hooks'] ) && count( $this->placement['hooks'] ) ) {
				foreach ( $this->placement['hooks'] as $hook_key => $hook ) {
					$this->json['hooks'][ $hook_key ]['ad_unit'] = $this->get_ad_unit_value( $hook_key );
				}
			}
		}
	}

	/**
	 * Get the value of an ad unit given its hook key.
	 *
	 * @param string $hook_key Optional hook key, will look root placement otherwise.
	 *
	 * @return string Ad unit ID or empty string if not found.
	 */
	private function get_ad_unit_value( $hook_key = '' ) {
		$value = json_decode( $this->value(), true );
		if ( ! $hook_key ) {
			return $value['ad_unit'] ?? '';
		}
		return isset( $value['hooks'][ $hook_key ]['ad_unit'] ) ? $value['hooks'][ $hook_key ]['ad_unit'] : '';
	}

	/**
	 * Get the element ID for form inputs.
	 *
	 * @param string   $name Name of the input.
	 * @param string[] $args Extra arguments to append to the ID.
	 *
	 * @return string The element ID.
	 */
	private function get_element_id( $name, $args = [] ) {
		return sprintf( '_customize-%s-%s-%s', $name, $this->id, implode( '-', $args ) );
	}

	/**
	 * Render the control's ad unit select given its value and hook key.
	 *
	 * @param string $value    The current value of the control.
	 * @param string $hook_key The hook key.
	 */
	private function render_ad_unit_select( $value = '', $hook_key = '' ) {
		$id_args = [ 'ad-unit' ];
		if ( $hook_key ) {
			$id_args[] = $hook_key;
		}
		$input_id       = $this->get_element_id( 'input', $id_args );
		$description_id = $this->get_element_id( 'description', $id_args );
		$label          = $hook_key ? $this->placement['hooks'][ $hook_key ]['name'] : __( 'Ad Unit', 'newspack-ads' );
		$description    = __( 'Select an ad unit to display in this placement.', 'newspack-ads' );
		?>
		<span class="customize-control">
			<label for="<?php echo esc_attr( $input_id ); ?>" class="customize-control-title"><?php echo esc_html( $label ); ?></label>
			<span id="<?php echo esc_attr( $description_id ); ?>" class="description customize-control-description"><?php echo esc_html( $description ); ?></span>
			<select id="<?php echo esc_attr( $input_id ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>" data-hook="<?php echo esc_attr( $hook_key ); ?>">
				<option <?php selected( $value, '' ); ?>><?php esc_html_e( '&mdash; Select an ad unit &mdash;', 'newspack-ads' ); ?></option>
				<?php
				foreach ( $this->ad_units as $ad_unit ) {
						echo '<option value="' . esc_attr( $ad_unit['id'] ) . '"' . selected( $value, $ad_unit['id'], false ) . '>' . esc_html( $ad_unit['name'] ) . '</option>';
				}
				?>
			</select>
		</span>
		<?php
	}

	/**
	 * Render the control's content.
	 */
	public function render_content() {
		$value           = json_decode( $this->value(), true );
		$container_id    = $this->get_element_id( 'container' );
		$enabled         = $value && isset( $value['enabled'] ) && $value['enabled'];
		$enabled_id      = $this->get_element_id( 'input', [ 'enabled' ] );
		$enabled_desc_id = $this->get_element_id( 'description', [ 'enabled' ] );
		?>
		<div id="<?php echo esc_attr( $container_id ); ?>">
			<span class="customize-control">
				<input
					id="<?php echo esc_attr( $enabled_id ); ?>"
					aria-describedby="<?php echo esc_attr( $enabled_desc_id ); ?>"
					type="checkbox"
					value="1"
					<?php checked( $enabled ); ?>
				/>
				<label for="<?php echo esc_attr( $enabled_id ); ?>"><?php esc_html_e( 'Enabled', 'newspack-ads' ); ?></label>
				<span id="<?php echo esc_attr( $enabled_desc_id ); ?>" class="description customize-control-description"><?php esc_html_e( 'Enable this placement', 'newspack-ads' ); ?></span>
			</span>
			<?php
			if ( isset( $this->placement['hook_name'] ) && $this->placement['hook_name'] ) {
				$this->render_ad_unit_select( $this->get_ad_unit_value() );
			}
			if ( isset( $this->placement['hooks'] ) && count( $this->placement['hooks'] ) ) {
				foreach ( $this->placement['hooks'] as $hook_key => $hook ) {
					$this->render_ad_unit_select( $this->get_ad_unit_value( $hook_key ), $hook_key );
				}
			}
			?>
		</div>
		<?php
	}
}
