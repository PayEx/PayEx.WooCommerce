<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Payex_Payment extends WC_Gateway_Payex_Abstract {
	/** @var array PayEx TC Spider IPs */
	static protected $_allowed_ips = array(
		'82.115.146.170', // Production
		'82.115.146.10' // Test
	);

	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'payex';
		$this->has_fields   = false;
		$this->method_title = __( 'PayEx Payments', 'woocommerce-gateway-payex-payment' );
		$this->icon         = apply_filters( 'woocommerce_payex_payment_icon', plugins_url( '/assets/images/payex.gif', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			//'multiple_subscriptions',
		);

		// @todo Add Deprecated support of WC Subscriptions 1.5
		if ( class_exists( 'WC_Subscriptions', false ) && version_compare( WC_Subscriptions::$version, '2.0.0', '<' ) ) {
			unset(
				$this->supports['subscriptions'],
				$this->supports['subscription_cancellation'],
				$this->supports['subscription_suspension'],
				$this->supports['subscription_reactivation'],
				$this->supports['subscription_amount_changes'],
				$this->supports['subscription_date_changes'],
				$this->supports['subscription_payment_method_change'],
				$this->supports['subscription_payment_method_change_customer'],
				$this->supports['subscription_payment_method_change_admin']
			);
		}

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled              = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title                = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->account_no           = isset( $this->settings['account_no'] ) ? $this->settings['account_no'] : '';
		$this->encrypted_key        = isset( $this->settings['encrypted_key'] ) ? $this->settings['encrypted_key'] : '';
		$this->purchase_operation   = isset( $this->settings['purchase_operation'] ) ? $this->settings['purchase_operation'] : 'SALE';
		$this->payment_view         = isset( $this->settings['payment_view'] ) ? $this->settings['payment_view'] : '';
		$this->description          = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->language             = isset( $this->settings['language'] ) ? $this->settings['language'] : 'en-US';
		$this->testmode             = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'yes';
		$this->checkout_info        = isset( $this->settings['checkout_info'] ) ? $this->settings['checkout_info'] : 'yes';
		$this->responsive           = isset( $this->settings['responsive'] ) ? $this->settings['responsive'] : 'no';
		$this->save_cards           = isset( $this->settings['save_cards'] ) ? $this->settings['save_cards'] : 'no';
		$this->agreement_max_amount = isset( $this->settings['agreement_max_amount'] ) ? $this->settings['agreement_max_amount'] : '100000';
		$this->agreement_url        = isset( $this->settings['agreement_url'] ) ? $this->settings['agreement_url'] : '';
		$this->debug                = isset( $this->settings['debug'] ) ? $this->settings['debug'] : 'no';
		$this->use_statuses         = isset( $this->settings['use_statuses'] ) ? $this->settings['use_statuses'] : 'yes';

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'transaction_callback' ) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );

		// Subscriptions
		if ( class_exists( 'WC_Subscriptions_Order' ) && version_compare( WC_Subscriptions::$version, '2.0.0', '>=' ) ) {
			// WC Subscriptions 2.0+
			add_action( 'woocommerce_payment_complete', array( &$this, 'add_subscription_card_id' ), 10, 1 );

			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
				$this,
				'scheduled_subscription_payment'
			), 10, 2 );

			add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array(
				$this,
				'update_failing_payment_method'
			), 10, 2 );

			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );

			// Allow store managers to manually set card id as the payment method on a subscription
			add_filter( 'woocommerce_subscription_payment_meta', array(
				$this,
				'add_subscription_payment_meta'
			), 10, 2 );

			add_filter( 'woocommerce_subscription_validate_payment_meta', array(
				$this,
				'validate_subscription_payment_meta'
			), 10, 2 );

			// Display the credit card used for a subscription in the "My Subscriptions" table
			add_filter( 'woocommerce_my_subscriptions_payment_method', array(
				$this,
				'maybe_render_subscription_payment_method'
			), 10, 2 );

			// Callback for "Use New Credit Card" Payment Change
			//add_action( 'template_redirect', array( $this, 'check_payment_method_changed' ) );
		}
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'              => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'title'                => array(
				'title'       => __( 'Title', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( 'PayEx Payments', 'woocommerce-gateway-payex-payment' )
			),
			'description'          => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => '',
			),
			'account_no'           => array(
				'title'       => __( 'Account Number', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'Account Number of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'encrypted_key'        => array(
				'title'       => __( 'Encryption Key', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'PayEx Encryption Key of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'purchase_operation'   => array(
				'title'       => __( 'Purchase Operation', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => array( 'AUTHORIZATION' => 'Authorization', 'SALE' => 'Sale' ),
				'description' => __( 'If used AUTHORIZATION then amount will be authorized (2-phased transaction). If used SALE then amount will be captured (1-phased transaction).', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'SALE'
			),
			'payment_view'         => array(
				'title'       => __( 'Payment View', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => array(
					'CREDITCARD'  => 'Credit Card',
					'INVOICE'     => 'Invoice',
					'DIRECTDEBIT' => 'Direct Debit',
					'PAYPAL'      => 'PayPal',
				),
				'description' => __( 'Default payment method.', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'CREDITCARD'
			),
			'language'             => array(
				'title'       => __( 'Language', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => array(
					'en-US' => 'English',
					'sv-SE' => 'Swedish',
					'nb-NO' => 'Norway',
					'da-DK' => 'Danish',
					'es-ES' => 'Spanish',
					'de-DE' => 'German',
					'fi-FI' => 'Finnish',
					'fr-FR' => 'French',
					'pl-PL' => 'Polish',
					'cs-CZ' => 'Czech',
					'hu-HU' => 'Hungarian'
				),
				'description' => __( 'Language of pages displayed by PayEx during payment.', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'en-US'
			),
			'testmode'             => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayEx Test Mode', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'checkout_info'        => array(
				'title'   => __( 'Enable checkout information', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send order lines and billing/delivery addresses to PayEx', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'responsive'           => array(
				'title'   => __( 'Enable Responsive Skinning', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Use Responsive web design on PayEx pages', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'save_cards'           => array(
				'title'       => __( 'Allow Stored Cards', 'woocommerce-gateway-payex-payment' ),
				'label'       => __( 'Allow logged in customers to save credit card profiles to use for future purchases', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'agreement_max_amount' => array(
				'title'       => __( 'Max amount per transaction', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This option use with WC Subscriptions plugin. One single transaction can never be greater than this amount.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( '100000', 'woocommerce-gateway-payex-payment' )
			),
			'agreement_url'        => array(
				'title'       => __( 'Agreement URL', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This option use with WC Subscriptions plugin. A reference that links this agreement to something the merchant takes money for.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( get_site_url(), 'woocommerce-gateway-payex-payment' )
			),
			'debug'                => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'use_statuses'         => array(
				'title'   => __( 'Use order statuses for payment actions', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Use order statuses for payment actions', 'woocommerce-gateway-payex-payment' ),
				'description' => __( '"Processing/Completed" - for capture payment. "Cancelled" - for cancel payment.', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
		);
	}

	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {
		if ( is_user_logged_in() && $this->save_cards === 'yes' ) {
			$cards = $this->get_saved_cards();
		} else {
			$cards = array();
		}

		$card_id = 0;
		if ( isset( $_GET['change_payment_method'] ) && abs( $_GET['change_payment_method'] ) > 0 ) {
			$subscription_id = abs( $_GET['change_payment_method'] );
			$card_id         = get_post_meta( $subscription_id, '_payex_card_id', true );
		}

		parent::payment_fields();

		wc_get_template(
			'checkout/payment-fields.php',
			array(
				'gateway'          => $this,
				'cards'            => $cards,
				'selected_card_id' => $card_id
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 */
	public function thankyou_page( $order_id ) {
		//
	}

	/**
	 * Transaction Callback
	 * Use as "?wc-api=WC_Gateway_Payex"
	 */
	public function transaction_callback() {
		@ob_clean();

		// Check is PayEx Request
		if ( ! in_array( $_SERVER['REMOTE_ADDR'], self::$_allowed_ips ) ) {
			$this->log( 'TC: Access denied for this request. It\'s not PayEx Spider.' );
			header( sprintf( '%s %s %s', 'HTTP/1.1', '403', 'Access denied. Accept PayEx Transaction Callback only.' ), true, '403' );
			header( sprintf( 'Status: %s %s', '403', 'Access denied. Accept PayEx Transaction Callback only.' ), true, '403' );
			exit( 'Error: Access denied. Accept PayEx Transaction Callback only. ' );
		}

		// Check Post Fields
		$this->log( 'TC: Requested Params: ' . var_export( $_POST, true ) );
		if ( count( $_POST ) == 0 || empty( $_POST['transactionNumber'] ) ) {
			$this->log( 'TC: Error: Empty request received.' );
			header( sprintf( '%s %s %s', 'HTTP/1.1', '500', 'FAILURE' ), true, '500' );
			header( sprintf( 'Status: %s %s', '500', 'FAILURE' ), true, '500' );
			exit( 'FAILURE' );
		}

		// Check orderID in Store
		$order_id = wc_clean( $_POST['orderId'] );
		if ( ! $order = wc_get_order( $order_id ) ) {
			$this->log( 'TC: OrderID ' . $order_id . ' not found on store.' );
			header( sprintf( '%s %s %s', 'HTTP/1.1', '500', 'FAILURE' ), true, '500' );
			header( sprintf( 'Status: %s %s', '500', 'FAILURE' ), true, '500' );
			exit( 'FAILURE' );
		}

		// Get Payment Method
		$payment_method = $order->get_payment_method();
		$gateways = WC()->payment_gateways()->payment_gateways();

		/** @var WC_Gateway_Payex_Abstract $gateway */
		$gateway = $gateways[ $payment_method ];

		// Init PayEx
		$gateway->getPx()->setEnvironment( $gateway->account_no, $gateway->encrypted_key, $gateway->testmode === 'yes' );

		// Get Transaction Details
		$transactionId = wc_clean( $_POST['transactionNumber'] );

		// Call PxOrder.GetTransactionDetails2
		$params  = array(
			'accountNumber'     => '',
			'transactionNumber' => $transactionId
		);
		$details = $gateway->getPx()->GetTransactionDetails2( $params );
		if ( $details['code'] !== 'OK' || $details['description'] !== 'OK' || $details['errorCode'] !== 'OK' ) {
			exit( 'Error:' . $details['errorCode'] . ' (' . $details['description'] . ')' );
		}

		$transactionStatus = (int) $details['transactionStatus'];

		$this->log( 'TC: Incoming transaction: ' . $transactionId );
		$this->log( 'TC: Transaction Status: ' . $transactionStatus );
		$this->log( 'TC: OrderId: ' . $order_id );

		// Save Transaction
		update_post_meta( $order->get_id(), '_transaction_id', $transactionId );
		update_post_meta( $order->get_id(), '_payex_transaction_status', $transactionStatus );

		// Disable status change hook
		remove_action( 'woocommerce_order_status_changed', 'WC_Payex_Admin_Actions::order_status_changed', 10 );

		/* 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
		switch ( $transactionStatus ) {
			case 0;
			case 3:
				// Complete order
				$params = array(
					'accountNumber' => '',
					'orderRef'      => $_POST['orderRef']
				);
				$result = $gateway->getPx()->Complete( $params );
				if ( $result['errorCodeSimple'] !== 'OK' ) {
					exit( 'Error:' . $details['errorCode'] . ' (' . $details['description'] . ')' );
				}

				switch ( (int) $result['transactionStatus'] ) {
					case 0:
					case 6:
						$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
						$order->payment_complete();
						break;
					case 1:
						$order_stock_reduced = $this->is_wc3() ? $order->get_meta( '_order_stock_reduced', true ) : get_post_meta( $order_id, '_order_stock_reduced', true );
						if ( ! $order_stock_reduced ) {
							$this->is_wc3() ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();
						}

						$order->update_status( 'on-hold', sprintf( __( 'Transaction pending. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
						break;
					case 3:
						$order_stock_reduced = $this->is_wc3() ? $order->get_meta( '_order_stock_reduced', true ) : get_post_meta( $order_id, '_order_stock_reduced', true );
						if ( ! $order_stock_reduced ) {
							$this->is_wc3() ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();
						}

						$order->update_status( 'on-hold', sprintf( __( 'Transaction authorized. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
						break;
					case 4:
						// Cancel
						$order->update_status( 'cancelled' );
						break;
					case 5:
					default:
						// Cancel when Errors
						$order->update_status( 'failed', __( 'Transaction failed.', 'woocommerce-gateway-payex-payment' ) );
						break;
				}

				$this->log( 'TC: OrderId ' . $order_id . ' Complete with TransactionStatus ' . $result['transactionStatus'], $order_id );
				break;
			case 2:
				// Refund
				// @todo Perform WooCommerce Refund
				// Set Order Status
				$order->update_status( 'refunded', __( 'Order refunded.', 'woocommerce-gateway-payex-payment' ) );
				$this->log( 'TC: OrderId ' . $order_id . ' refunded', $order_id );
				break;
			case 4;
				// Cancel
				// Set Order Status
				$order->update_status( 'cancelled' );
				$this->log( 'TC: OrderId ' . $order_id . ' canceled', $order_id );
				break;
			case 5:
				// Cancel when Errors
				// Set Order Status
				$order->update_status( 'failed', __( 'Transaction failed.', 'woocommerce-gateway-payex-payment' ) );
				$this->log( 'TC: OrderId ' . $order_id . ' canceled', $order_id );
				break;
			case 6:
				// Set Order Status to captured
				$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $transactionId ) );
				$order->payment_complete();

				$this->log( 'TC: OrderId ' . $order_id . ' captured', $order_id );
				break;
			default:
				$this->log( 'TC: Unknown Transaction Status', $order_id );
				header( sprintf( '%s %s %s', 'HTTP/1.1', '500', 'FAILURE' ), true, '500' );
				header( sprintf( 'Status: %s %s', '500', 'FAILURE' ), true, '500' );
				exit( 'FAILURE' );
		}

		// Show "OK"
		$this->log( 'TC: Done.' );
		header( sprintf( '%s %s %s', 'HTTP/1.1', '200', 'OK' ), true, '200' );
		header( sprintf( 'Status: %s %s', '200', 'OK' ), true, '200' );
		exit( 'OK' );
	}

	/**
	 * Validate Frontend Fields
	 * @return bool|void
	 */
	public function validate_fields() {
		//
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		$order = wc_get_order( $order_id );

		// Check Recurring options
		if ( self::order_contains_subscription( $order ) ) {
			// Payment View
			if ( $this->payment_view !== 'CREDITCARD' ) {
				wc_add_notice( sprintf( __( 'PayEx Recurring Payments don\'t work with WC Subscriptions in "%s" mode. Please set "Credit Card" mode.', 'woocommerce-gateway-payex-payment' ), $this->payment_view ), 'error' );

				return false;
			}

			// Store Cards
			if ( $this->save_cards !== 'yes' ) {
				wc_add_notice( __( 'PayEx Recurring Payments don\'t work  WC Subscriptions without Store Cards option.', 'woocommerce-gateway-payex-payment' ), 'error' );

				return false;
			}
		}

        $items = $this->get_order_items( $order );
		if ($this->checkout_info === 'yes') {
            $amount = array_sum( array_column( $items, 'price_with_tax' ) );
        } else {
            $amount = $order->get_total();
        }

		$currency    = $order->get_currency();
		$agreement   = '';

		// Prepare additional values
		$additional = array();

		// Set Responsive Mode
		if ( $this->responsive === 'yes' ) {
			// PayEx Payment Page 2.0  works only for View 'Credit Card' and 'Direct Debit' at the moment
			if ( in_array( $this->payment_view, array( 'CREDITCARD', 'DIRECTDEBIT' ) ) ) {
				$additional[] = 'RESPONSIVE=1';
			} else {
				$additional[] = 'USECSS=RESPONSIVEDESIGN';
			}
		}

		// Change Payment Method
		if ( class_exists( 'WC_Subscriptions_Change_Payment_Gateway', false ) && WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment ) {
			// Use New Credit Card
			if ( $_POST['payex-credit-card'] === 'new' ) {
				// Create Recurring Agreement
				$agreement = $this->create_agreement();
				if ( ! $agreement ) {
					wc_add_notice( __( 'Failed to create agreement reference', 'woocommerce-gateway-payex-payment' ), 'error' );

					return false;
				}

				$additional[] = 'VERIFICATION=true';

				// Call PxOrder.Initialize8
				$params = array(
					'accountNumber'     => '',
					'purchaseOperation' => $this->purchase_operation,
					'price'             => round( $order->get_total() * 100 ),
					'priceArgList'      => '',
					'currency'          => $order->get_currency(),
					'vat'               => 0,
					'orderID'           => $order->get_id(),
					'productNumber'     => $order->get_id(),
					'description'       => $this->description,
					'clientIPAddress'   => $_SERVER['REMOTE_ADDR'],
					'clientIdentifier'  => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
					'additionalValues'  => $this->get_additional_values( $additional, $order ),
					'externalID'        => '',
					'returnUrl'         => add_query_arg( 'payex_new_credit_card', '1', $this->get_return_url( $order ) ),
					'view'              => 'CREDITCARD',
					'agreementRef'      => $agreement,
					'cancelUrl'         => html_entity_decode( $order->get_cancel_order_url() ),
					'clientLanguage'    => $this->language
				);
				$result = $this->getPx()->Initialize8( $params );
				if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
					$this->log( 'PxOrder.Initialize8:' . $result['errorCode'] . '(' . $result['description'] . ')' );
					wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

					return false;
				}

				return array(
					'result'   => 'success',
					'redirect' => $result['redirectUrl']
				);
			}

			// Use Saved Credit Card
			if ( isset( $_POST['payex-credit-card'] ) && abs( $_POST['payex-credit-card'] ) > 0 ) {
				$card_id = $_POST['payex-credit-card'];
				$card    = get_post( $card_id );
				if ( $card->post_author != $order->get_user_id() ) {
					wc_add_notice( __( 'You are not the owner of this card.', 'woocommerce-gateway-payex-payment' ), 'error' );

					return false;
				}

				update_post_meta( $order->get_id(), '_payex_card_id', $card_id );

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}

			// Default action
			wc_add_notice( __( 'Unable to change the payment method', 'woocommerce-gateway-payex-payment' ) );

			return false;
		} elseif ( $this->order_contains_subscription($order) && $order->get_total() == 0 ) {
			// Trial period for Subscription
			// Use New Credit Card
			if ( $_POST['payex-credit-card'] === 'new' ) {
				// Create Recurring Agreement
				$agreement = $this->create_agreement();
				if ( ! $agreement ) {
					wc_add_notice( __( 'Failed to create agreement reference', 'woocommerce-gateway-payex-payment' ), 'error' );

					return false;
				}

				$additional[] = 'VERIFICATION=true';

				// Call PxOrder.Initialize8
				$params = array(
					'accountNumber'     => '',
					'purchaseOperation' => $this->purchase_operation,
					'price'             => round( $order->get_total() * 100 ),
					'priceArgList'      => '',
					'currency'          => $order->get_currency(),
					'vat'               => 0,
					'orderID'           => $order->get_id(),
					'productNumber'     => $order->get_id(),
					'description'       => $this->description,
					'clientIPAddress'   => $_SERVER['REMOTE_ADDR'],
					'clientIdentifier'  => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
					'additionalValues'  => $this->get_additional_values( $additional, $order ),
					'externalID'        => '',
					'returnUrl'         => html_entity_decode( $this->get_return_url( $order ) ),
					'view'              => 'CREDITCARD',
					'agreementRef'      => $agreement,
					'cancelUrl'         => html_entity_decode( $order->get_cancel_order_url() ),
					'clientLanguage'    => $this->language
				);
				$result = $this->getPx()->Initialize8( $params );
				if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
					$this->log( 'PxOrder.Initialize8:' . $result['errorCode'] . '(' . $result['description'] . ')' );
					wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

					return false;
				}

				return array(
					'result'   => 'success',
					'redirect' => $result['redirectUrl']
				);
			}

			// Use Saved Credit Card
			if ( isset( $_POST['payex-credit-card'] ) && abs( $_POST['payex-credit-card'] ) > 0 ) {
				$card_id   = $_POST['payex-credit-card'];
				$card      = get_post( $card_id );
				$card_meta = get_post_meta( $card->ID, '_payex_card', true );
				if ( $card->post_author != $order->get_user_id() ) {
					wc_add_notice( __( 'You are not the owner of this card.', 'woocommerce-gateway-payex-payment' ), 'error' );

					return false;
				}

				// Set Card ID
				update_post_meta( $order->get_id(), '_payex_card_id', $card_id );

				// Payment success
				$order->payment_complete();
				$order->add_order_note(
					sprintf(
						__( 'Payment success. Credit Card: %s', 'woocommerce-gateway-payex-payment' ),
						$card_meta['masked_number']
					)
				);

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}

			// Default action
			wc_add_notice( __( 'Unable to process payment', 'woocommerce-gateway-payex-payment' ) );

			return false;
		} elseif ( $order->get_total() == 0 ) {
			// Allow empty order amount for "Payment Change" and "Subscription trial period" only
			wc_add_notice( __( 'Sorry, order total must be greater than zero.', 'woocommerce-gateway-payex-payment' ), 'error' );

			return false;
		}


		// Get Saved Credit Card
		if ( is_user_logged_in() && $this->save_cards === 'yes' && $this->payment_view === 'CREDITCARD' ) {
			if ( isset( $_POST['payex-credit-card'] ) && abs( $_POST['payex-credit-card'] ) > 0 ) {
				// Get Agreement Reference of Selected Card
				$card = get_post( wc_clean( $_POST['payex-credit-card'] ) );
				if ( $card->post_author != $order->get_user_id() ) {
					wc_add_notice( __( 'You are not the owner of this card.', 'woocommerce-gateway-payex-payment' ), 'error' );

					return false;
				}

				$card_meta = get_post_meta( $card->ID, '_payex_card', true );
				$agreement = $card_meta['agreement_reference'];

				// Pay using Saved Credit Card
				// Call PxAgreement.AutoPay3
				$params = array(
					'accountNumber'     => '',
					'agreementRef'      => $agreement,
					'price'             => round( $amount * 100 ),
					'productNumber'     => $order->get_id(),
					'description'       => $this->description,
					'orderId'           => $order->get_id(),
					'purchaseOperation' => $this->purchase_operation,
					'currency'          => $currency
				);
				$result = $this->getPx()->AutoPay3( $params );
				if ( $result['errorCodeSimple'] !== 'OK' ) {
					$this->log( 'PxAgreement.AutoPay3:' . $result['errorCode'] . '(' . $result['description'] . ')' );
					$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
					wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

					return false;
				}

				// Payment success
				if ( in_array( $result['transactionStatus'], array( '0', '3', '6' ) ) ) {
					$order->add_order_note(
						sprintf(
							__( 'Payment success. Transaction Status: %s. Transaction Id: %s. Credit Card: %s', 'woocommerce-gateway-payex-payment' ),
							$result['transactionStatus'],
							$result['transactionNumber'],
							$card_meta['masked_number']
						)
					);
				}

				// Save meta
				add_post_meta( $order->get_id(), '_payex_card_id', $card->ID );

				return array(
					'result'   => 'success',
					'redirect' => add_query_arg( 'transaction_id', $result['transactionNumber'], $this->get_return_url( $order ) )
				);
			} else {
				// Create Recurring Agreement
				$agreement = $this->create_agreement();
				if ( ! $agreement ) {
					wc_add_notice( __( 'Failed to create agreement reference', 'woocommerce-gateway-payex-payment' ), 'error' );

					return false;
				}
			}
		}

		// Call PxOrder.Initialize8
		$params = array(
			'accountNumber'     => '',
			'purchaseOperation' => $this->purchase_operation,
			'price'             => round( $amount * 100 ),
			'priceArgList'      => '',
			'currency'          => $currency,
			'vat'               => 0,
			'orderID'           => $order->get_id(),
			'productNumber'     => $order->get_id(),
			'description'       => $this->description,
			'clientIPAddress'   => $_SERVER['REMOTE_ADDR'],
			'clientIdentifier'  => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
			'additionalValues'  => $this->get_additional_values( $additional, $order ),
			'externalID'        => '',
			'returnUrl'         => html_entity_decode( $this->get_return_url( $order ) ),
			'view'              => $this->payment_view,
			'agreementRef'      => $agreement,
			'cancelUrl'         => html_entity_decode( $order->get_cancel_order_url() ),
			'clientLanguage'    => $this->language
		);
		$result = $this->getPx()->Initialize8( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PxOrder.Initialize8:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
			wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

			return false;
		}

		$orderRef    = $result['orderRef'];
		$redirectUrl = $result['redirectUrl'];

		if ( $this->checkout_info === 'yes' ) {
			// add Order Lines
			foreach ($items as $id => $item) {
				// Call PxOrder.AddSingleOrderLine2
				$params = array(
					'accountNumber'    => '',
					'orderRef'         => $orderRef,
					'itemNumber'       => $id,
					'itemDescription1' => $item['name'],
					'itemDescription2' => '',
					'itemDescription3' => '',
					'itemDescription4' => '',
					'itemDescription5' => '',
					'quantity'         => $item['qty'],
					'amount'           => (int) ( 100 * $item['price_with_tax'] ),
					//must include tax
					'vatPrice'         => (int) ( 100 * $item['tax_price'] ),
					'vatPercent'       => (int) ( 100 * $item['tax_percent'] )
				);
				$result = $this->getPx()->AddSingleOrderLine2( $params );
				if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
					$this->log( 'PxOrder.AddSingleOrderLine2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
					$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
					wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

					return false;
				}
			}

			// Add Order Address Info
			$params = array_merge( array(
				'accountNumber' => '',
				'orderRef' => $orderRef
			), $this->get_address_info( $order ) );
			$result = $this->getPx()->AddOrderAddress2( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				$this->log( 'PxOrder.AddOrderAddress2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
				$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
				wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

				return false;
			}
		}

		$order->add_order_note( __( 'Customer has been redirected to PayEx.', 'woocommerce-gateway-payex-payment' ) );

		return array(
			'result'   => 'success',
			'redirect' => $redirectUrl
		);
	}

	/**
	 * Payment confirm action
	 */
	public function payment_confirm() {
		if ( ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		if ( empty( $_GET['key'] ) ) {
			return;
		}

		// Validate Payment Method
		$order = $this->get_order_by_order_key( $_GET['key'] );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$payment_method = $this->is_wc3() ? $order->get_payment_method() : $order->payment_method;
		if ( $payment_method !== $this->id ) {
			return;
		}

		// Check OrderRef is exists
		if ( empty( $_GET['orderRef'] ) && empty( $_GET['transaction_id'] ) ) {
			return;
		}

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Retrieve Transaction Details
		if ( ! empty( $_GET['orderRef'] ) ) {
			// Call PxOrder.Complete
			$params = array(
				'accountNumber' => '',
				'orderRef'      => $_GET['orderRef']
			);
			$result = $this->getPx()->Complete( $params );
			if ( $result['errorCodeSimple'] !== 'OK' ) {
				$this->log( 'PxOrder.Complete:' . $result['errorCode'] . '(' . $result['description'] . ')' );
				wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

				return;
			}

			if ( ! isset( $result['transactionNumber'] ) ) {
				$result['transactionNumber'] = '';
			}

			// If there is no transactionStatus in the response then the order failed
			if ( ! isset( $result['transactionStatus'] ) ) {
				$result['transactionStatus'] = '5';
			}
		} else {
			// Call PxOrder.GetTransactionDetails2
			$params = array(
				'accountNumber'     => '',
				'transactionNumber' => $_GET['transaction_id']
			);
			$result = $this->getPx()->GetTransactionDetails2( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				$this->log( 'PxOrder.GetTransactionDetails2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
				wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

				return;
			}
		}

		// Validate Order
		if ( $order->get_id() !== (int) $result['orderId'] ) {
			wc_add_notice( __( 'The transaction belongs to another order.', 'woocommerce-gateway-payex-payment' ), 'error' );

			return;
		}

		// Get Order
		$order_id = (int) $result['orderId'];
		$order    = wc_get_order( $order_id );

		// Check order is exists
		if ( ! $order ) {
			return;
		}

		// Check transaction is already success
		$transaction_status = get_post_meta( $order->get_id(), '_payex_transaction_status', true );
		if ( in_array( $transaction_status, array( '0', '3', '6' ) ) ) {
			return;
		}

		if ( ! empty( $_GET['orderRef'] ) ) {
			$order->add_order_note( sprintf( __( 'Customer returned from PayEx. Order reference: %s', 'woocommerce-gateway-payex-payment' ), $_GET['orderRef'] ) );
		}

		// Save Transaction
		update_post_meta( $order->get_id(), '_transaction_id', $result['transactionNumber'] );
		update_post_meta( $order->get_id(), '_payex_transaction_status', $result['transactionStatus'] );

		// Save Agreement Reference
		if ( ! empty( $result['agreementRef'] ) ) {
			$agreement_status = $this->agreement_check( $result['agreementRef'] );
			if ( $agreement_status === 1 ) {
				// Save Credit Card
				$this->agreement_save( $order->get_id(), $result['agreementRef'], $result );
			}
		}

		// Disable status change hook
		remove_action( 'woocommerce_order_status_changed', 'WC_Payex_Admin_Actions::order_status_changed', 10 );

		/* Transaction statuses:
		0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
		switch ( (int) $result['transactionStatus'] ) {
			case 0:
			case 6:
				$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				$order->payment_complete( $result['transactionNumber'] );
				WC()->cart->empty_cart();
				break;
			case 1:
				$order_stock_reduced = $this->is_wc3() ? $order->get_meta( '_order_stock_reduced', true ) : get_post_meta( $order_id, '_order_stock_reduced', true );
				if ( ! $order_stock_reduced ) {
					$this->is_wc3() ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();
				}

				$order->update_status( 'on-hold', sprintf( __( 'Transaction is pending. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				WC()->cart->empty_cart();
				break;
			case 3:
				$order_stock_reduced = $this->is_wc3() ? $order->get_meta( '_order_stock_reduced', true ) : get_post_meta( $order_id, '_order_stock_reduced', true );
				if ( ! $order_stock_reduced ) {
					$this->is_wc3() ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();
				}

				$order->update_status( 'on-hold', sprintf( __( 'Transaction authorized. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				WC()->cart->empty_cart();
				break;
			case 4:
				// Cancel
				$order->cancel_order();
				break;
			case 5:
			default:
				// Cancel when Error
				$message = __( 'Transaction is failed.', 'woocommerce-gateway-payex-payment' );
				if ( ! empty( $result['transactionNumber'] ) ) {
					$message = sprintf( __( 'Transaction is failed. Transaction Id: %s.', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] );
				}

				$message .= ' ' . sprintf( __( 'Details: %s.', 'woocommerce-gateway-payex-payment' ), $this->getVerboseErrorMessage( $result ) );

				$order->update_status( 'failed', $message );
				break;
		}
	}

	/**
	 * When a subscription payment is due.
	 *
	 * @param float $amount_to_charge
	 * @param WC_Order $renewal_order
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		try {
			$card_id = get_post_meta( $renewal_order->get_id(), '_payex_card_id', true );
			if ( empty( $card_id ) ) {
				throw new Exception( 'Invalid Credit Card Id' );
			}

			// Load Saved Credit Card
			$post = get_post( $card_id );
			$card = get_post_meta( $post->ID, '_payex_card', true );
			if ( empty( $card ) ) {
				throw new Exception( 'Invalid Credit Card' );
			}

			// Validate Card owner
			if ( $card['customer_id'] !== $renewal_order->get_user_id() ) {
				throw new Exception( 'Credit Card access error: wrong owner' );
			}

			$agreement = $card['agreement_reference'];

			// Init PayEx
			$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

			// Check Agreement Status
			$agreement_status = $this->agreement_check( $agreement );
			if ( $agreement_status !== 1 ) {
				throw new Exception( 'Invalid agreement status' );
			}

			// Pay using Saved Credit Card
			// Call PxAgreement.AutoPay3
			$params = array(
				'accountNumber'     => '',
				'agreementRef'      => $agreement,
				'price'             => round( $amount_to_charge * 100 ),
				'productNumber'     => $renewal_order->get_user_id(),
				'description'       => $this->description,
				'orderId'           => $renewal_order->get_id(),
				'purchaseOperation' => $this->purchase_operation,
				'currency'          => $renewal_order->get_currency()
			);
			$result = $this->getPx()->AutoPay3( $params );
			if ( $result['errorCodeSimple'] !== 'OK' ) {
				$this->log( 'PxAgreement.AutoPay3:' . $result['errorCode'] . '(' . $result['description'] . ')' );
				throw new Exception( $this->getVerboseErrorMessage( $result ) );
			}

			// Save Transaction
			update_post_meta( $renewal_order->get_id(), '_transaction_id', $result['transactionNumber'] );
			update_post_meta( $renewal_order->get_id(), '_payex_transaction_status', $result['transactionStatus'] );

			// Save meta
			add_post_meta( $renewal_order->get_id(), '_payex_card_id', $card_id );

			// Payment success
			if ( in_array( $result['transactionStatus'], array( '0', '3', '6' ) ) ) {
				$renewal_order->payment_complete( $result['transactionNumber'] );
				$renewal_order->add_order_note(
					sprintf(
						__( 'Payment success. Transaction Status: %s. Transaction Id: %s. Credit Card: %s', 'woocommerce-gateway-payex-payment' ),
						$result['transactionStatus'],
						$result['transactionNumber'],
						$card['masked_number']
					)
				);
			} else {
				throw new Exception( sprintf( __( 'Transaction Status: %s. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionStatus'], $result['transactionNumber'] ) );
			}
		} catch ( Exception $e ) {
			if ( is_array( $card ) && ! empty( $card['masked_number'] ) ) {
				$renewal_order->update_status( 'failed', sprintf( __( 'Failed to charge "%s" from Credit Card "%s". %s.', 'woocommerce' ), wc_price( $amount_to_charge ), $card['masked_number'], $e->getMessage() ) );
			} else {
				$renewal_order->update_status( 'failed', sprintf( __( 'Failed to charge "%s". %s.', 'woocommerce' ), wc_price( $amount_to_charge ), $e->getMessage() ) );
			}
		}
	}

	/**
	 * Get Saved Credit Cards
	 * @return array
	 */
	public function get_saved_cards() {
		if ( ! is_user_logged_in() ) {
			return array();
		}

		$args  = array(
			'post_type'   => 'payex_credit_card',
			'author'      => get_current_user_id(),
			'numberposts' => - 1,
			'orderby'     => 'post_date',
			'order'       => 'ASC',
		);
		$cards = get_posts( $args );

		return $cards;
	}

	/**
	 * Save Credit Card
	 *
	 * @param $user_id
	 * @param $agreement_reference
	 * @param $payment_method
	 * @param $masked_number
	 * @param $expire_date
	 *
	 * @return int|WP_Error
	 */
	public function save_card( $user_id, $agreement_reference, $payment_method, $masked_number, $expire_date ) {
		$cards = $this->get_saved_cards();

		$card = array(
			'post_type'     => 'payex_credit_card',
			'post_title'    => sprintf( __( 'Credit Card %s &ndash; %s', 'woocommerce-gateway-payex-payment' ), $masked_number, strftime( _x( '%b %d, %Y @ %I:%M %p', 'Token date parsed by strftime', 'woocommerce-gateway-payex-payment' ) ) ),
			'post_content'  => '',
			'post_status'   => 'publish',
			'ping_status'   => 'closed',
			'post_author'   => $user_id,
			'post_password' => uniqid( 'card_' ),
			'post_category' => '',
		);

		$post_id   = wp_insert_post( $card );
		$card_meta = array(
			'customer_id'         => $user_id,
			'agreement_reference' => $agreement_reference,
			'payment_method'      => $payment_method,
			'masked_number'       => $masked_number,
			'expire_date'         => $expire_date,
			'is_default'          => count( $cards ) > 0 ? 'no' : 'yes',
		);
		add_post_meta( $post_id, '_payex_card', $card_meta );

		return $post_id;
	}


	/**
	 * Update the card meta for a subscription after using Authorize.Net to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 *
	 * @param WC_Subscription $subscription  The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( $subscription->get_id(), '_payex_card_id', get_post_meta( $renewal_order->get_id(), '_payex_card_id', true ) );
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
	 *
	 * @since 2.4
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_payex_card_id' => array(
					'value' => get_post_meta( $subscription->get_id(), '_payex_card_id', true ),
					'label' => 'Saved Credit Card ID',
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
	 *
	 * @since 2.4
	 *
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array  $payment_meta      associative array of meta data required for automatic payments
	 *
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {
			if ( ! isset( $payment_meta['post_meta']['_payex_card_id']['value'] ) || empty( $payment_meta['post_meta']['_payex_card_id']['value'] ) ) {
				throw new Exception( 'Saved Credit Card ID is required.' );
			}
		}
	}

	/**
	 * Don't transfer customer meta to resubscribe orders.
	 *
	 * @access public
	 *
	 * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return void
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( $resubscribe_order->get_id(), '_payex_card_id' );
	}

	/**
	 * Clone Card ID when Subscription created
	 *
	 * @param $order_id
	 */
	public function add_subscription_card_id( $order_id ) {
		$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'parent' ) );
		foreach ( $subscriptions as $subscription ) {
			/** @var WC_Subscription $subscription */
			$card_id = get_post_meta( $subscription->get_id(), '_payex_card_id', true );

			if ( empty( $card_id ) ) {
				$parent_id = $payment_method = $this->is_wc3() ? $subscription->get_parent_id() : $subscription->order->id;
				$order_card_id = get_post_meta( $parent_id, '_payex_card_id', true );
				add_post_meta( $subscription->get_id(), '_payex_card_id', $order_card_id );
			}
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string          $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription              the subscription details
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		// bail for other payment methods
		$payment_method = $this->is_wc3() ? $subscription->get_payment_method() : $subscription->payment_method;
		if ( $this->id !== $payment_method || ! $subscription->get_user_id() ) {
			return $payment_method_to_display;
		}

		$card_id = get_post_meta( $subscription->get_id(), '_payex_card_id', true );
		if ( empty( $card_id ) ) {
			return $payment_method_to_display;
		}

		// Load Saved Credit Card
		$post = get_post( $card_id );
		if ( ! $post ) {
			return $payment_method_to_display;
		}

		$card = get_post_meta( $post->ID, '_payex_card', true );
		if ( empty( $card ) ) {
			return $payment_method_to_display;
		}

		if ( empty( $card['expire_date'] ) ) {
			$payment_method_to_display = sprintf( __( 'Via %s card', 'woocommerce-gateway-payex-payment' ), $card['masked_number'] );
		} else {
			$payment_method_to_display = sprintf( __( 'Via %s card ending in %s', 'woocommerce-gateway-payex-payment' ), $card['masked_number'], date( 'Y/m', strtotime( $card['expire_date'] ) ) );
		}

		return $payment_method_to_display;
	}


	/**
	 * Callback for "Use New Credit Card" Payment Change
	 */
	public function check_payment_method_changed() {
		if ( ! empty( $_GET['orderRef'] ) && ! empty( $_GET['payex_new_credit_card'] ) ) {
			$orderRef = $_GET['orderRef'];

			// Use transient to prevent multiple requests
			if ( get_transient( $orderRef ) !== false ) {
				return;
			}

			set_transient( $orderRef, true, MINUTE_IN_SECONDS );

			// Init PayEx
			$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

			// Call PxOrder.Complete
			$params = array(
				'accountNumber' => '',
				'orderRef'      => $orderRef
			);

			$result = $this->getPx()->Complete( $params );
			if ( $result['errorCodeSimple'] !== 'OK' ) {
				wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

				return;
			}

			// Results should have agreement reference
			if ( empty( $result['agreementRef'] ) ) {
				return;
			}

			// Check transaction status
			if ( ! in_array( $result['transactionStatus'], array( '0', '3', '6' ) ) ) {
				return;
			}

			// Check order
			$order = wc_get_order( $result['orderId'] );
			if ( ! $order || $order->get_user_id() !== get_current_user_id() ) {
				return;
			}

			// Verify Agreement Reference
			$agreement_status = $this->agreement_check( $result['agreementRef'] );
			if ( $agreement_status === 1 ) {
				// Save Credit Card
				$card_id = $this->agreement_save( $order->get_id(), $result['agreementRef'], $result );
				if ( abs( $card_id ) > 0 ) {
					update_post_meta( $order->get_id(), '_payex_card_id', $card_id );
				}
			}

			wp_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit();
		}
	}


	/**
	 * Create Credit Card Agreement
	 * @return bool|string
	 */
	protected function create_agreement() {
		// Call PxAgreement.CreateAgreement3
		$params = array(
			'accountNumber'     => '',
			'merchantRef'       => $this->agreement_url,
			'description'       => $this->description,
			'purchaseOperation' => $this->purchase_operation,
			'maxAmount'         => round( $this->agreement_max_amount * 100 ),
			'notifyUrl'         => '',
			'startDate'         => '',
			'stopDate'          => ''
		);
		$result = $this->getPx()->CreateAgreement3( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			return false;
		}

		return isset( $result['agreementRef'] ) ? $result['agreementRef'] : '';
	}

	/**
	 * Get Credit Card Agreement Status
	 *
	 * @param $agreement
	 *
	 * @return bool|int
	 */
	protected function agreement_check( $agreement ) {
		// Call PxAgreement.AgreementCheck
		$params = array(
			'accountNumber' => '',
			'agreementRef'  => $agreement,
		);
		$result = $this->getPx()->AgreementCheck( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			return false;
		}

		return isset( $result['agreementStatus'] ) ? (int) $result['agreementStatus'] : false;
	}

	/**
	 * Save Agreement Reference as "Saved Card"
	 *
	 * @param       $order_id
	 * @param       $agreement
	 * @param array $transaction
	 *
	 * @return int|WP_Error
	 * @throws Exception
	 */
	public function agreement_save( $order_id, $agreement, array $transaction ) {
		$order = wc_get_order( $order_id );
		if ( $order->get_user_id() === 0 ) {
			throw new Exception( 'Unable to save agreement reference for non-registered users' );
		}

		// Extract Credit Card Details
		// Get Masked Credit Card Number
		$masked_number = sprintf( __( 'Credit Card (order #%s)', 'woocommerce-gateway-payex-payment' ), $order_id );
		if ( ! empty( $transaction['maskedNumber'] ) ) {
			$masked_number = $transaction['maskedNumber'];
		} elseif ( ! empty( $transaction['maskedCard'] ) ) {
			$masked_number = $transaction['maskedCard'];
		}

		// Get Card Type
		$card_type = '';
		if ( ! empty( $transaction['cardProduct'] ) ) {
			$card_type = $transaction['cardProduct'];
		} elseif ( ! empty( $transaction['paymentMethod'] ) ) {
			$card_type = $transaction['paymentMethod'];
		}

		/**
		 * Card types: VISA, MC (Mastercard), EUROCARD, MAESTRO, DINERS (Diners Club), AMEX (American Express), LIC,
		 * FDM, FORBRUGSFORENINGEN, JCB, FINAX, DANKORT
		 */
		$card_type = strtolower( preg_replace( '/[^A-Z]+/', '', $card_type ) );
		$card_type = str_replace( 'mc', 'mastercard', $card_type );
		if ( empty( $card_type ) ) {
			$card_type = 'visa';
		}

		// Get Expired
		$expire_date = '';
		if ( ! empty( $transaction['paymentMethodExpireDate'] ) ) {
			$expire_date = $transaction['paymentMethodExpireDate'];
		}

		// Save Credit Card Reference
		$card_id = $this->save_card(
			$order->get_user_id(),
			$agreement,
			$card_type,
			$masked_number,
			$expire_date
		);

		update_post_meta( $order_id, '_payex_card_id', $card_id );

		return $card_id;
	}
}
