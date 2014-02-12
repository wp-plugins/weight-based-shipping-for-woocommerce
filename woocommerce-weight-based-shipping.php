<?php
/**
 * Plugin Name: Weight based shipping for Woocommerce
 * Description: Simple weight based shipping method for Woocommerce.
 * Version: 1.4
 * Author: dangoodman
 */

add_action( 'plugins_loaded', 'init_woowbs', 0 );

function init_woowbs() {

	if ( ! class_exists( 'WC_Shipping_Method' ) ) return;

	class WC_Weight_Based_Shipping extends WC_Shipping_Method {

		function __construct() {
			$this->id           = 'WC_Weight_Based_Shipping';
			$this->method_title = __( 'Weight based', 'woocommerce' );

			$this->admin_page_heading     = __( 'Weight based shipping', 'woocommerce' );
			$this->admin_page_description = __( 'Define shipping by weight', 'woocommerce' );

			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_admin_options' ) );

			$this->init();
		}

		function init() {
			$this->init_form_fields();
			$this->init_settings();

			$this->enabled      = $this->settings['enabled'];
			$this->title        = $this->settings['title'];
            $this->availability = 'all';
			$this->type         = 'order';
			$this->tax_status   = $this->settings['tax_status'];
			$this->fee          = $this->settings['fee'];
		}

		function init_form_fields() {
			$this->form_fields = array(
				'enabled'    => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this shipping method', 'woocommerce' ),
					'default' => 'no',
				),
				'title'      => array(
					'title'       => __( 'Method Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Weight Based Shipping', 'woocommerce' ),
				),
				'tax_status' => array(
					'title'       => __( 'Tax Status', 'woocommerce' ),
					'type'        => 'select',
					'description' => '',
					'default'     => 'taxable',
					'options'     => array(
						'taxable' => __( 'Taxable', 'woocommerce' ),
						'none'    => __( 'None', 'woocommerce' ),
					),
				),
				'fee'        => array(
					'title'       => __( 'Handling Fee', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Fee excluding tax, e.g. 3.50. Leave blank to disable.', 'woocommerce' ),
					'default'     => '',
				),
				'rate'       => array(
					'title'       => __( 'Shipping Rate', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Set your shipping price for 1 ' . get_option( 'woocommerce_weight_unit' ) . '. Example: <code>1.95</code>.', 'woocommerce' ),
					'default'     => '',
				),
			);
		}

		function calculate_shipping( $package = array() ) {

			global $woocommerce;

			$weight = $woocommerce->cart->cart_contents_weight;
			$rate   = $this->settings['rate'];
			$price  = $weight * $rate;

			if ( $price <= 0 ) {
				return false;
			}

			if ( $this->fee > 0 && $package['destination']['country'] ) {
				$price = $price + $this->fee;
			}

			$this->add_rate( array
			(
				'id'       => $this->id,
				'label'    => $this->title,
				'cost'     => $price,
				'taxes'    => '',
				'calc_tax' => 'per_order'
			) );
		}

		public function admin_options() {
			?>
				<h3><?php _e( 'Weight based shipping', 'woocommerce' ); ?></h3>
				<p><?php _e( 'Lets you calculate shipping based on total weight of the cart.', 'woocommerce' ); ?></p>
				<table class="form-table">
					<?php $this->generate_settings_html(); ?>
				</table>
			<?php
		}
	}
}

function add_woowbs( $methods ) {
	$methods[] = 'WC_Weight_Based_Shipping';
	return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_woowbs' );

?>