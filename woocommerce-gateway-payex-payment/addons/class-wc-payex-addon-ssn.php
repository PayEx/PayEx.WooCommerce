<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// PHP-Name-Parser by Josh Fraser
// See https://github.com/joshfraser/PHP-Name-Parser
require_once dirname( __FILE__ ) . '/includes/parser.php';

class WC_Payex_Addon_SSN {
	public $options;
	protected $_px;

	public function __construct() {
		// Init options
		$this->options = wp_parse_args( get_option( 'woocommerce_payex_addons_ssn_check', array() ), array(
			'ssn_enabled' => false,
			'testmode' => false,
			'account_no' => null,
			'encrypted_key' => null,
		) );

		// Register PayEx Addons
		add_filter('woocommerce_payex_addons', array( $this, 'register_addons' ), 10, 1 );

		// Admin init
		add_action( 'admin_init', array($this, 'register_settings') );

		// Actions
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_action( 'woocommerce_before_checkout_billing_form', array( &$this, 'before_checkout_billing_form' ) );
		add_action( 'wp_ajax_payex_process_ssn', array( &$this, 'ajax_payex_process_ssn' ) );
		add_action( 'wp_ajax_nopriv_payex_process_ssn', array( &$this, 'ajax_payex_process_ssn' ) );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'woocommerce_payex_addons', 'woocommerce_payex_addons_ssn_check' );
	}


	/**
	 * Register PayEx Addons
	 * @param $addons
	 *
	 * @return mixed
	 */
	public function register_addons($addons) {
		$addons['ssn_check'] = array(
			'title' => __( 'Social Security Number Field', 'woocommerce-gateway-payex-payment' ),
			'description' => __( 'Add Social Security Number Field to Checkout', 'woocommerce-gateway-payex-payment' ),
			'callback' => __CLASS__ . '::addon_tab_ssn_check'
		);

		return $addons;
	}

	/**
	 * Callback for PayEx Addon: SSN Field
	 */
	public static function addon_tab_ssn_check() {
		require_once dirname( __FILE__ ) . '/includes/admin/view/html-admin-page-ssn.php';
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		if ( $this->options['ssn_enabled'] ) {
			wp_enqueue_script( 'wc-payex-addons-ssn', plugins_url( '/assets/js/ssn.js', __FILE__ ), array( 'wc-checkout' ), false, true );
		}
	}

	/**
	 * Hook before_checkout_billing_form
	 * @param $checkout
	 */
	public function before_checkout_billing_form( $checkout ) {
		if ( $this->options['ssn_enabled'] ) {
			echo '<div id="payex_ssn">';
			woocommerce_form_field( 'payex_ssn', array(
				'type'        => 'text',
				'class'       => array( 'payex-ssn-class form-row-wide' ),
				'label'       => __( 'Social Security Number', 'woocommerce-gateway-payex-payment' ),
				'placeholder' => __( 'Social Security Number', 'woocommerce-gateway-payex-payment' ),
			), $checkout->get_value( 'payex_ssn' ) );

			echo '<input type="button" class="button alt" name="woocommerce_checkout_payex_ssn" id="payex_ssn_button" value="' . __( 'Get Profile', 'woocommerce-gateway-payex-payment' ) . '" />';
			echo '</div>';
		}
	}

	/**
	 * Ajax Hook
	 */
	public function ajax_payex_process_ssn() {
		// Init PayEx
		$this->getPx()->setEnvironment( $this->options['account_no'], $this->options['encrypted_key'], (bool) $this->options['testmode'] );

		if ( empty( $_POST['billing_country'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select country', 'woocommerce-gateway-payex-payment' ) ) );
			exit();
		}

		if ( empty( $_POST['billing_postcode'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter postcode', 'woocommerce-gateway-payex-payment' ) ) );
			exit();
		}

		// Call PxOrder.GetAddressByPaymentMethod
		$params = array(
			'accountNumber' => '',
			'paymentMethod' => $_POST['billing_country'] === 'SE' ? 'PXFINANCINGINVOICESE' : 'PXFINANCINGINVOICENO',
			'ssn' => trim($_POST['social_security_number']),
			'zipcode' => trim($_POST['billing_postcode']),
			'countryCode' => trim($_POST['billing_country']),
			'ipAddress' => trim($_SERVER['REMOTE_ADDR'])
		);
		$result = $this->getPx()->GetAddressByPaymentMethod($params);
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			if ( preg_match( '/\bInvalid parameter:SocialSecurityNumber\b/i', $result['description'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid Social Security Number', 'woocommerce-gateway-payex-payment' ) ) );
				exit();
			}

			wp_send_json_error( array( 'message' => $result['errorCode'] . '(' . $result['description'] . ')' ) );
			exit();
		}

		// Parse name field
		$parser = new FullNameParser();
		$name = $parser->parse_name($result['name']);

		$output = array(
			'first_name' => $name['fname'],
			'last_name'  => $name['lname'],
			'address_1'  => $result['streetAddress'],
			'address_2'  => ! empty($result['coAddress']) ? 'c/o ' . $result['coAddress'] : '',
			'postcode'   => $result['zipCode'],
			'city'       => $result['city'],
			'country'    => $result['countryCode']
		);
		wp_send_json_success( $output );
		exit();
	}

	/**
	 * Get PayEx Handler
	 * @return Px
	 */
	public function getPx() {
		if ( ! $this->_px ) {
			$this->_px = new Px();
		}

		return $this->_px;
	}
}

new WC_Payex_Addon_SSN();
