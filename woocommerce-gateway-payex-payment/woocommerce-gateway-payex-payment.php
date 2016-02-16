<?php
/*
Plugin Name: WooCommerce PayEx Payments Gateway
Plugin URI: http://payex.com/
Description: Provides a Credit Card Payment Gateway through PayEx for WooCommerce.
Version: 2.0.0pre6
Author: AAIT Team
Author URI: http://aait.se/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 3.1
*/

require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = PucFactory::buildUpdateChecker(
	'http://payex.aait.se/application/meta/check?key=vFoib9ZAZGWmyC205pAidnc',
	__FILE__
);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Payex_Payment {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );

		// Add admin menu
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 99 );

		// Payment fee
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fee' ) );

		// Add statuses for payment complete
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'add_valid_order_statuses' ), 10, 2 );

		// Add MasterPass button to Cart Page
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'add_mp_button_to_cart' ) );

		// Add MasterPass button to Product Page
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_mp_button_to_product_page' ) );

		// Check is MasterPass Purchase
		add_action( 'template_redirect', array( $this, 'check_mp_purchase' ) );
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payex_payment' ) . '">' . __( 'PayEx Settings', 'woocommerce-gateway-payex-payment' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 */
	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		// Localization
		load_plugin_textdomain( 'woocommerce-gateway-payex-payment', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Includes
		include_once( dirname( __FILE__ ) . '/library/Px/Px.php' );
		include_once( dirname( __FILE__ ) . '/includes/wc-compatibility-functions.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-abstract.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-payment.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-bankdebit.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-invoice.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-factoring.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-wywallet.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-masterpass.php' );

		// Addons
		include_once( dirname( __FILE__ ) . '/addons/class-wc-payex-addon-ssn.php' );
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		$mp_settings = get_option( 'woocommerce_payex_masterpass_settings' );
		if ( $mp_settings['enabled'] === 'yes' ) {
			wp_enqueue_style( 'wc-gateway-payex-masterpass', plugins_url( '/assets/css/masterpass.css', __FILE__ ), array(), false, 'all' );
		}
	}

	/**
	 * Register the gateways for use
	 */
	public function register_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Payex_Payment';
		$methods[] = 'WC_Gateway_Payex_Bankdebit';
		$methods[] = 'WC_Gateway_Payex_Invoice';
		$methods[] = 'WC_Gateway_Payex_Factoring';
		$methods[] = 'WC_Gateway_Payex_Wywallet';
		$methods[] = 'WC_Gateway_Payex_MasterPass';

		return $methods;
	}

	/**
	 * Add fee when selected payment method
	 */
	public function add_cart_fee() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Get Current Payment Method
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$default            = get_option( 'woocommerce_default_gateway', current( array_keys( $available_gateways ) ) );
		$current            = WC()->session->get( 'chosen_payment_method', $default );
		$current_gateway    = $available_gateways[ $current ];

		// Fee feature in Invoice and Factoring modules
		if ( ! in_array( $current_gateway->id, array( 'payex_invoice', 'payex_factoring' ) ) ) {
			return;
		}

		// Is Fee is not specified
		if ( abs( $current_gateway->fee ) < 0.01 ) {
			return;
		}

		// Add Fee
		$fee_title = $current_gateway->id === 'payex_invoice' ? __( 'Invoice Fee', 'woocommerce-gateway-payex-payment' ) : __( 'Factoring Fee', 'woocommerce-gateway-payex-payment' );
		WC()->cart->add_fee( $fee_title, $current_gateway->fee, ( $current_gateway->fee_is_taxable === 'yes' ), $current_gateway->fee_tax_class );
	}

	/**
	 * Allow processing/completed statuses for capture
	 * @param $statuses
	 * @param $order
	 *
	 * @return array
	 */
	public function add_valid_order_statuses($statuses, $order) {
		if ( strpos($order->payment_method, 'payex') !== false ) {
			$statuses = array_merge( $statuses, array( 'processing', 'completed' ) );
		}

		return $statuses;
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {
		$order              = wc_get_order( $order_id );
		$transaction_status = get_post_meta( $order_id, '_payex_transaction_status', true );
		if ( empty( $transaction_status ) ) {
			return;
		}

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$gateway = $gateways[$order->payment_method];
		if ( $gateway && (string) $transaction_status === '3' ) {
			// Get Additional Values
			$additionalValues = '';
			if ( $gateway->id === 'payex_factoring' ) {
				$additionalValues = 'FINANCINGINVOICE_ORDERLINES=' . urlencode( $gateway->getInvoiceExtraPrintBlocksXML( $order ) );
			}

			// Init PayEx
			$gateway->getPx()->setEnvironment( $gateway->account_no, $gateway->encrypted_key, $gateway->testmode === 'yes' );

			// Call PxOrder.Capture5
			$params = array(
				'accountNumber'     => '',
				'transactionNumber' => $order->get_transaction_id(),
				'amount'            => round( 100 * $order->order_total ),
				'orderId'           => $order->id,
				'vatAmount'         => 0,
				'additionalValues'  => $additionalValues
			);
			$result = $gateway->getPx()->Capture5( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				$gateway->log( 'PxOrder.Capture5:' . $result['errorCode'] . '(' . $result['description'] . ')' );

				$message = sprintf( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $result['errorCode'] . ' (' . $result['description'] . ')' );
				$order->update_status( 'on-hold', $message );
				WC_Admin_Meta_Boxes::add_error( $message );
				return;
			}

			update_post_meta( $order->id, '_payex_transaction_status', $result['transactionStatus'] );
			$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
			$order->payment_complete( $result['transactionNumber'] );
		}
	}

	/**
	 * Capture payment when the order is changed from on-hold to cancelled
	 *
	 * @param  int $order_id
	 */
	public function cancel_payment( $order_id ) {
		$order              = wc_get_order( $order_id );
		$transaction_status = get_post_meta( $order_id, '_payex_transaction_status', true );
		if ( empty( $transaction_status ) ) {
			return;
		}

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$gateway = $gateways[$order->payment_method];
		if ( $gateway && (string) $transaction_status === '3' ) {
			$gateway = new WC_Gateway_Payex_Payment();

			// Call PxOrder.Cancel2
			$params = array(
				'accountNumber'     => '',
				'transactionNumber' => $order->get_transaction_id()
			);
			$result = $gateway->getPx()->Cancel2( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				$gateway->log( 'PxOrder.Cancel2:' . $result['errorCode'] . '(' . $result['description'] . ')' );

				$message = sprintf( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $result['errorCode'] . ' (' . $result['description'] . ')' );
				$order->update_status( 'on-hold', $message );
				WC_Admin_Meta_Boxes::add_error( $message );

				return;
			}

			update_post_meta( $order->id, '_payex_transaction_status', $result['transactionStatus'] );
			$order->add_order_note( sprintf( __( 'Transaction canceled. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
		}
	}

	/**
	 * Add PayEx Add-Ons link to Admin menu
	 */
	public function admin_menu() {
		$addons = apply_filters( 'woocommerce_payex_addons', array() );
		if ( count( $addons ) > 0 ) {
			$show_in_menu = current_user_can( 'manage_woocommerce' ) ? 'woocommerce' : false;
			$slug         = add_submenu_page( $show_in_menu, __( 'PayEx Add-Ons' ), __( 'PayEx Add-Ons' ), 'manage_woocommerce', 'wc_payex_addons', array(
				&$this,
				'admin_page_addon'
			) );
		}
	}

	/**
	 * PayEx Add-Ons Admin Page
	 */
	public function admin_page_addon() {
		$addons = apply_filters( 'woocommerce_payex_addons', array() );
		if ( count( $addons ) > 0 ) {
			$default       = array_shift( array_keys( $addons ) );
			$current_addon = ( isset( $_GET['addon'] ) ) ? $_GET['addon'] : $default;
		}
		?>
		<div class="wrap woocommerce">
			<div class="icon32 woocommerce-dynamic-pricing" id="icon-woocommerce">
				<br>
			</div>
			<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
				<?php foreach ( $addons as $addon_id => $addon ) : ?>
					<?php $class = ( $current_addon == $addon_id ) ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>
					<a href="<?php echo admin_url( 'admin.php?page=wc_payex_addons&addon=' . $addon_id ) ?>" class="<?php echo $class; ?>">
						<?php echo $addon['title']; ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<div class="tab_top"><h3 class="has-help"><?php echo $addons[ $current_addon ]['title']; ?></h3>
				<?php if ( ! empty ( $addons[ $current_addon ]['description'] ) ) : ?>
					<p class="help"><?php echo $addons[ $current_addon ]['description']; ?></p>
				<?php endif; ?>
			</div>

			<div class="payex-addon">
				<?php
				if ( ! empty ( $addons[ $current_addon ]['callback'] ) && is_callable( $addons[ $current_addon ]['callback'] ) ) {
					call_user_func( $addons[ $current_addon ]['callback'] );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add MasterPass Button to Cart page
	 */
	public function add_mp_button_to_cart() {
		$mp_settings = get_option( 'woocommerce_payex_masterpass_settings' );
		if ( $mp_settings['display_cart_button'] === 'yes' && $mp_settings['enabled'] === 'yes' ) {
			wc_get_template(
				'masterpass/cart-button.php',
				array(
					'image'       => plugins_url( '/assets/images/masterpass-button.png', __FILE__ ),
					'description' => $mp_settings['description'],
					'link'        => add_query_arg( 'mp_from_cart_page', 1, get_permalink() )
				),
				'',
				dirname( __FILE__ ) . '/templates/'
			);
		}
	}

	/**
	 * Add MasterPass Button to Single Product page
	 */
	public function add_mp_button_to_product_page() {
		$mp_settings = get_option( 'woocommerce_payex_masterpass_settings' );
		if ( $mp_settings['display_pp_button'] === 'yes' && $mp_settings['enabled'] === 'yes' ) {
			wc_get_template(
				'masterpass/product-button.php',
				array(
					'image'       => plugins_url( '/assets/images/masterpass-button.png', __FILE__ ),
					'description' => $mp_settings['description'],
					'link'        => add_query_arg( 'mp_from_product_page', 1, get_permalink() )
				),
				'',
				dirname( __FILE__ ) . '/templates/'
			);
		}
	}

	/**
	 * Check for MasterPass purchase from cart page
	 **/
	function check_mp_purchase() {
		// Check for MasterPass purchase from cart page
		if ( isset( $_GET['mp_from_cart_page'] ) && $_GET['mp_from_cart_page'] === '1' ) {
			$gateway = new WC_Gateway_Payex_MasterPass;
			$gateway->masterpass_button_action();
		}

		// Check for MasterPass purchase from product page
		if ( isset( $_POST['mp_from_product_page'] ) && $_POST['mp_from_product_page'] === '1' ) {
			$gateway = new WC_Gateway_Payex_MasterPass;
			$gateway->masterpass_button_action();
		}
	}
}

new WC_Payex_Payment();
