<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Payex_Admin_Actions {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_boxes' );

		// Add scripts and styles for admin
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_enqueue_scripts' );

		// Add Action
		add_action( 'wc_ajax_payex_action', array( $this, 'ajax_payex_action' ) );

		// Status Change Actions
		add_action( 'woocommerce_order_status_changed', __CLASS__ . '::order_status_changed', 10, 4 );
	}

	/**
	 * Add meta boxes in admin
	 * @return void
	 */
	public static function add_meta_boxes() {
		global $post_id;
		$order = wc_get_order( $post_id );
		if ( $order && in_array( $order->get_payment_method(), WC_Payex_Payment::$_methods ) ) {
			add_meta_box(
				'payex_payment_actions',
				__( 'PayEx Payments', 'woocommerce-gateway-payex-payment' ),
				__CLASS__ . '::order_meta_box_payment_actions',
				'shop_order',
				'side',
				'default'
			);
		}
	}


	/**
	 * MetaBox for Payment Actions
	 * @return void
	 */
	public static function order_meta_box_payment_actions() {
		global $post_id;
		$order              = wc_get_order( $post_id );
		$payment_method     = $order->get_payment_method();
		$transaction_id     = $order->get_transaction_id();
		$transaction_status = get_post_meta( $order->get_id(), '_payex_transaction_status', true );

		// Fetch transaction info
		if ( ( $details = get_transient( 'trans_' . $payment_method . $transaction_id ) ) === false ) {
			$gateways = WC()->payment_gateways()->payment_gateways();
			/** @var WC_Gateway_Payex_Abstract $gateway */
			$gateway = $gateways[$payment_method];

			// Init PayEx
			$gateway->getPx()->setEnvironment( $gateway->account_no, $gateway->encrypted_key, $gateway->testmode === 'yes' );

			try {
				$details = $gateway->get_transaction_info( $transaction_id );
				set_transient( 'trans_' . $payment_method . $transaction_id, $details, 60 * MINUTE_IN_SECONDS );
			} catch (Exception $e) {
				$details = array();
			}
		}

		// Prepare info
		$fields = array(
			'Payment Method' => array( 'paymentMethod', 'cardProduct' ),
			'Masked Number' => array( 'maskedNumber', 'maskedCard' ),
			'Bank Hash' => array( 'BankHash', 'csId', 'panId' ),
			'Bank Reference' => array( 'bankReference' ),
			'Authenticated Status' => array( 'AuthenticatedStatus', 'authenticatedStatus' ),
			'Transaction Reference' => array( 'transactionRef' ),
			'Transaction Number' => array( 'transactionNumber' ),
			'Transaction Status' => array( 'transactionStatus' ),
			'Transaction Error Code' => array( 'transactionErrorCode' ),
			'Transaction Error Description' => array( 'transactionErrorDescription' ),
			'Transaction ThirdParty Error' => array( 'transactionThirdPartyError' )
		);

		$info = array();
		foreach ( $fields as $description => $list ) {
			foreach ( $list as $key => $value ) {
				if ( ! empty( $details[$value] ) ) {
					$info[$description] = $details[$value];
					break;
				}
			}
		}

		wc_get_template(
			'admin/payment-actions.php',
			array(
				'order'              => $order,
				'transaction_id'     => $transaction_id,
				'fields'             => $fields,
				'details'            => $details,
				'info'               => $info,
				'transaction_status' => $transaction_status,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( $hook === 'post.php' ) {
			// Scripts
			wp_register_script( 'payex-payments-admin-js', plugin_dir_url( __FILE__ ) . '../assets/js/admin.js' );

			// Localize the script
			$translation_array = array(
				'ajax_url'  => WC_AJAX::get_endpoint('payex_action'),
				'text_wait' => __( 'Please wait...', 'woocommerce-gateway-payex-payment' ),
			);
			wp_localize_script( 'payex-payments-admin-js', 'Payex_Payments_Admin', $translation_array );

			// Enqueued script with localized data
			wp_enqueue_script( 'payex-payments-admin-js' );
		}
	}

	/**
	 * Action
	 * @return void
	 */
	public function ajax_payex_action() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'payex' ) ) {
			exit( 'No naughty business' );
		}

		$transaction_id = (int) $_REQUEST['transaction_id'];
		$order_id       = (int) $_REQUEST['order_id'];
		$payex_action   = $_REQUEST['payex_action'];

		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new Exception('Failed to get order');
			}

			// Get payment method instance
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->payment_gateways();
			/** @var WC_Gateway_Payex_Abstract $gateway */
			$gateway = $gateways[$payment_method];

			// Init PayEx
			$gateway->getPx()->setEnvironment( $gateway->account_no, $gateway->encrypted_key, $gateway->testmode === 'yes' );

			switch ($payex_action) {
				case 'capture':
					$gateway->capture_payment( $order, $order->get_total() );
					wp_send_json_success();
					break;
				case 'cancel':
					$gateway->cancel_payment( $order );
					wp_send_json_success();
					break;
			}
		} catch (Exception $e) {
			wp_send_json_error( sprintf( __( 'Error: %s', 'woocommerce-gateway-payex-payment' ), $e->getMessage() ) );
		}
	}

	/**
	 * Order Status Change: Capture/Cancel
	 *
	 * @param $order_id
	 * @param $from
	 * @param $to
	 * @param $order
	 */
	public static function order_status_changed( $order_id, $from, $to, $order ) {
		// Check if Status actions are allowed
		$settings = get_option( 'woocommerce_payex_settings' );
		if ( ! isset( $settings['use_statuses'] ) || $settings['use_statuses'] !== 'yes' ) {
			return;
		}

		// We are need "on-hold" only
		if ( $from !== 'on-hold' ) {
			return;
		}

		// Backward compatibility
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order = wc_get_order( $order_id );
		}

		$transaction_status = get_post_meta( $order_id, '_payex_transaction_status', true );
		if ( empty( $transaction_status ) ) {
			return;
		}

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->payment_gateways();

		/** @var WC_Gateway_Payex_Abstract $gateway */
		$gateway = $gateways[ $order->get_payment_method() ];

		// Init PayEx
		$gateway->getPx()->setEnvironment( $gateway->account_no, $gateway->encrypted_key, $gateway->testmode === 'yes' );

		switch ( $to ) {
			case 'cancelled':
				// Cancel payment
				if ( (string) $transaction_status === '3' ) {
					try {
						$gateway->cancel_payment( $order );
					} catch ( Exception $e ) {
						$message = $e->getMessage();
						WC_Admin_Meta_Boxes::add_error( $message );

						// Rollback
						$order->update_status( $from, sprintf( __( 'Order status rollback. %s', 'woocommerce-gateway-payex-payment' ), $message ) );
					}
				}
				break;
			case 'processing':
			case 'completed':
				// Capture payment
				if ( (string) $transaction_status === '3' ) {
					try {
						$gateway->capture_payment( $order, $order->get_total() );
					} catch ( Exception $e ) {
						$message = $e->getMessage();
						WC_Admin_Meta_Boxes::add_error( $message );

						// Rollback
						$order->update_status( $from, sprintf( __( 'Order status rollback. %s', 'woocommerce-gateway-payex-payment' ), $message ) );
					}
				}
				break;
			default:
				// no break
		}
	}
}

new WC_Payex_Admin_Actions();
