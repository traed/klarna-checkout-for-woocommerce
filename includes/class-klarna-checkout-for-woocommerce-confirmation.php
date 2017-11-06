<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_Confirmation class.
 *
 * Handles Klarna Checkout confirmation page.
 */
class Klarna_Checkout_For_WooCommerce_Confirmation {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Klarna_Checkout_For_WooCommerce_Confirmation constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'maybe_hide_checkout_form' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_populate_wc_checkout' ) );
		add_action( 'wp_footer', array( $this, 'maybe_submit_wc_checkout' ), 999 );
		add_filter( 'the_title', array( $this, 'confirm_page_title' ) );
	}

	/**
	 * Filter Checkout page title in confirmation page.
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function confirm_page_title( $title ) {
		if ( ! is_admin() && is_main_query() && in_the_loop() && is_page() && is_checkout() && isset( $_GET['confirm'] ) && 'yes' === $_GET['confirm'] ) {
			$title = 'Please wait while we process your order.';
			remove_filter( 'the_title', array( $this, 'confirm_page_title' ) );
		}

		return $title;
	}

	/**
	 * Hides WooCommerce checkout form in KCO confirmation page.
	 */
	public function maybe_hide_checkout_form() {
		if ( ! $this->is_kco_confirmation() ) {
			return;
		}

		echo '<style>form.woocommerce-checkout,div.woocommerce-info{display:none!important}</style>';
	}

	/**
	 * Populates WooCommerce checkout form in KCO confirmation page.
	 */
	public function maybe_populate_wc_checkout( $checkout ) {
		if ( ! $this->is_kco_confirmation() ) {
			return;
		}

		echo '<div id="kco-confirm-loading"></div>';

		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
		$response        = KCO_WC()->api->request_post_get_order( $klarna_order_id );
		$klarna_order    = json_decode( $response['body'] );

		$this->save_customer_data( $klarna_order );
	}

	/**
	 * Submits WooCommerce checkout form in KCO confirmation page.
	 */
	public function maybe_submit_wc_checkout() {
		if ( ! $this->is_kco_confirmation() ) {
			return;
		}
		?>

		<script>
			jQuery(function ($) {
				$('input#terms').prop('checked', true);
				$('input#payment_method_klarna_checkout_for_woocommerce').prop('checked', true);

				<?php
				$extra_field_values = WC()->session->get( 'kco_wc_extra_fields_values', array() );

				foreach ( $extra_field_values as $field_name => $field_value ) { ?>

					var elementName = "<?php echo $field_name; ?>";
					var elementValue = "<?php echo $field_value; ?>";
					var element = $('*[name="' + elementName + '"]');

					console.log(elementName);
					console.log(elementValue);
					console.log(element);
					console.log(element.type);

					if (element.length) {
						if (element.is('select')) { // Select.
							var selectedOption = element.find('option[value="' + elementValue + '"]');
							selectedOption.prop('selected', true);
						} else if ('radio' === element.get(0).type) { // Radio.
							var checkedRadio = $('*[name="' + elementName + '"][value="' + elementValue + '"]');
							checkedRadio.prop('checked', true);
						} else if ('checkbox' === element.get(0).type) { // Checkbox.
							if (elementValue) {
								element.prop('checked', true);
							}
						} else { // Text and textarea.
							element.val(elementValue);
						}
					}

				<?php } ?>

				$('form.woocommerce-checkout').submit();
			});
		</script>
		<?php
	}

	/**
	 * Checks if in KCO confirmation page.
	 *
	 * @return bool
	 */
	private function is_kco_confirmation() {
		if ( isset( $_GET['confirm'] ) && 'yes' === $_GET['confirm'] && isset( $_GET['kco_wc_order_id'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Saves customer data from Klarna order into WC()->customer.
	 *
	 * @param $klarna_order
	 */
	private function save_customer_data( $klarna_order ) {
		// First name.
		WC()->customer->set_billing_first_name( $klarna_order->billing_address->given_name );
		WC()->customer->set_shipping_first_name( $klarna_order->shipping_address->given_name );

		// Last name.
		WC()->customer->set_billing_last_name( $klarna_order->billing_address->family_name );
		WC()->customer->set_shipping_last_name( $klarna_order->shipping_address->family_name );

		// Company.

		// Country.
		WC()->customer->set_billing_country( $klarna_order->billing_address->country );
		WC()->customer->set_shipping_country( $klarna_order->shipping_address->country );

		// Street address 1.
		WC()->customer->set_billing_address_1( $klarna_order->billing_address->street_address );
		WC()->customer->set_shipping_address_1( $klarna_order->shipping_address->street_address );

		// Street address 2.
		WC()->customer->set_billing_address_2( $klarna_order->billing_address->street_address2 );
		WC()->customer->set_shipping_address_2( $klarna_order->shipping_address->street_address2 );

		// City.
		WC()->customer->set_billing_city( $klarna_order->billing_address->city );
		WC()->customer->set_shipping_city( $klarna_order->shipping_address->city );

		// County/State.
		WC()->customer->set_billing_state( $klarna_order->billing_address->region );
		WC()->customer->set_shipping_state( $klarna_order->shipping_address->region );

		// Postcode.
		WC()->customer->set_billing_postcode( $klarna_order->billing_address->postal_code );
		WC()->customer->set_shipping_postcode( $klarna_order->shipping_address->postal_code );

		// Phone.
		WC()->customer->set_billing_phone( $klarna_order->billing_address->phone );

		// Email.
		WC()->customer->set_billing_email( $klarna_order->billing_address->email );

		WC()->customer->save();
	}

}

Klarna_Checkout_For_WooCommerce_Confirmation::get_instance();
