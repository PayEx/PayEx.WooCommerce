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
			'subscription_payment_method_change',
			'subscription_date_changes',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled            = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title              = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->account_no         = isset( $this->settings['account_no'] ) ? $this->settings['account_no'] : '';
		$this->encrypted_key      = isset( $this->settings['encrypted_key'] ) ? $this->settings['encrypted_key'] : '';
		$this->purchase_operation = isset( $this->settings['purchase_operation'] ) ? $this->settings['purchase_operation'] : 'SALE';
		$this->payment_view       = isset( $this->settings['payment_view'] ) ? $this->settings['payment_view'] : '';
		$this->description        = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->language           = isset( $this->settings['language'] ) ? $this->settings['language'] : 'en-US';
		$this->testmode           = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'yes';
		$this->checkout_info      = isset( $this->settings['checkout_info'] ) ? $this->settings['checkout_info'] : 'yes';
		$this->responsive         = isset( $this->settings['responsive'] ) ? $this->settings['responsive'] : 'no';
		$this->max_amount         = isset( $this->settings['max_amount'] ) ? $this->settings['max_amount'] : 0;
		$this->agreement_url      = isset( $this->settings['agreement_url'] ) ? $this->settings['agreement_url'] : '';
		$this->debug              = isset( $this->settings['debug'] ) ? $this->settings['debug'] : 'no';

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'transaction_callback' ) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );

		// Subscriptions
		add_action( 'reactivated_subscription_' . $this->id, array( &$this, 'reactivated_subscription' ), 10, 2 );
		add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 3 );
		add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', array( &$this, 'remove_renewal_order_meta' ), 10, 4 );

		// After an order that was placed to switch a subscription is processed/completed, make sure the subscription switch is complete
		add_action( 'woocommerce_payment_complete', array( &$this, 'maybe_complete_switch' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( &$this, 'maybe_complete_switch' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( &$this, 'maybe_complete_switch' ), 10, 1 );

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		}
	}

	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_payex_supported_currencies',
			array( 'DKK', 'EUR', 'GBP', 'NOK', 'SEK', 'USD' )
		) );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'title'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( 'PayEx Payments', 'woocommerce-gateway-payex-payment' )
			),
			'description'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( 'PayEx Payments', 'woocommerce-gateway-payex-payment' ),
			),
			'account_no'         => array(
				'title'       => __( 'Account Number', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'Account Number of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'encrypted_key'      => array(
				'title'       => __( 'Encryption Key', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'PayEx Encryption Key of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'purchase_operation' => array(
				'title'       => __( 'Purchase Operation', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => array( 'AUTHORIZATION' => 'Authorization', 'SALE' => 'Sale' ),
				'description' => __( 'If used AUTHORIZATION then amount will be authorized (2-phased transaction). If used SALE then amount will be captured (1-phased transaction).', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'SALE'
			),
			'payment_view'       => array(
				'title'       => __( 'Payment View', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => array(
					'PX'          => 'Payment Menu',
					'CREDITCARD'  => 'Credit Card',
					'INVOICE'     => 'Invoice',
					'DIRECTDEBIT' => 'Direct Debit',
					'PAYPAL'      => 'PayPal',
				),
				'description' => __( 'Default payment method.', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'PX'
			),
			'language'           => array(
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
			'testmode'           => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayEx Test Mode', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'checkout_info'      => array(
				'title'   => __( 'Enable checkout information', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send order lines and billing/delivery addresses to PayEx', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'responsive'         => array(
				'title'   => __( 'Enable Responsive Skinning', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Use Responsive web design on PayEx pages', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'max_amount'         => array(
				'title'       => __( 'Max amount per transaction', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This option use with WC Subscriptions plugin. One single transaction can never be greater than this amount.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( '1000', 'woocommerce-gateway-payex-payment' )
			),
			'agreement_url'      => array(
				'title'       => __( 'Agreement URL', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This option use with WC Subscriptions plugin. A reference that links this agreement to something the merchant takes money for.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( get_site_url(), 'woocommerce-gateway-payex-payment' )
			),
			'debug'              => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
		);
	}

	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {
		echo sprintf( __( 'You will be redirected to <a target="_blank" href="%s">PayEx</a> website when you place an order.', 'woocommerce-gateway-payex-payment' ), 'http://www.payex.com' );
		echo '<div class="clear"></div>';
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

		// Get Transaction Details
		$transactionId = $_POST['transactionNumber'];

		// Call PxOrder.GetTransactionDetails2
		$params  = array(
			'accountNumber'     => '',
			'transactionNumber' => $transactionId
		);
		$details = $this->getPx()->GetTransactionDetails2( $params );
		if ( $details['code'] !== 'OK' || $details['description'] !== 'OK' || $details['errorCode'] !== 'OK' ) {
			exit( 'Error:' . $details['errorCode'] . ' (' . $details['description'] . ')' );
		}

		$order_id          = $details['orderId'];
		$transactionStatus = (int) $details['transactionStatus'];

		$this->log( 'TC: Incoming transaction: ' . $transactionId );
		$this->log( 'TC: Transaction Status: ' . $transactionStatus );
		$this->log( 'TC: OrderId: ' . $order_id );

		// Load order
		$order = new WC_Order( $order_id );

		// Check orderID in Store
		if ( ! $order ) {
			$this->log( 'TC: OrderID ' . $order_id . ' not found on store.' );
			header( sprintf( '%s %s %s', 'HTTP/1.1', '500', 'FAILURE' ), true, '500' );
			header( sprintf( 'Status: %s %s', '500', 'FAILURE' ), true, '500' );
			exit( 'FAILURE' );
		}

		// Save Transaction
		update_post_meta( $order->id, '_transaction_id', $transactionId );
		update_post_meta( $order->id, '_payex_transaction_status', $transactionStatus );

		/* 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
		switch ( $transactionStatus ) {
			case 0;
			case 3:
				// Complete order
				$params = array(
					'accountNumber' => '',
					'orderRef'      => $_POST['orderRef']
				);
				$result = $this->getPx()->Complete( $params );
				if ( $result['errorCodeSimple'] !== 'OK' ) {
					exit( 'Error:' . $details['errorCode'] . ' (' . $details['description'] . ')' );
				}

				switch ( (int) $result['transactionStatus'] ) {
					case 0:
					case 6:
						$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
						$order->payment_complete();

						// Activate subscriptions
						if ( self::isRecurringAvailable( $order ) ) {
							WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
						}
						break;
					case 1:
						$order->update_status( 'on-hold', sprintf( __( 'Transaction pending. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
						break;
					case 3:
						$order->update_status( 'on-hold', sprintf( __( 'Transaction authorized. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
						break;
					case 4:
						// Cancel
						$order->cancel_order();
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
				$order->cancel_order();
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

				// Activate subscriptions
				if ( self::isRecurringAvailable( $order ) ) {
					WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
				}

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
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;
		$order = wc_get_order( $order_id );

		// When Order amount is empty
		if ( $order->get_total() == 0 ) {
			$order->payment_complete();
			$woocommerce->cart->empty_cart();

			// Activate subscriptions
			if ( self::isRecurringAvailable( $order ) ) {
				WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		$customer_id = (int) $order->customer_user;
		$amount      = $order->order_total;
		$currency    = get_option( 'woocommerce_currency' );
		$agreement   = '';

		$additional = ( $this->payment_view === 'PX' ? 'PAYMENTMENU=TRUE' : '' );
		if ( $this->responsive === 'yes' ) {
			$separator = ( ! empty( $additional ) && mb_substr( $additional, - 1 ) !== '&' ) ? '&' : '';
			$additional .= $separator . 'USECSS=RESPONSIVEDESIGN';
		}

		// Create Recurring Agreement
		if ( self::isRecurringAvailable( $order ) ) {
			if ( $this->payment_view !== 'CREDITCARD' ) {
				$this->add_message( sprintf( __( 'PayEx Recurring Payments don\'t work with WC Subscriptions in "%s" mode. Please set "Credit Card" mode.', 'woocommerce-gateway-payex-payment' ), $this->payment_view ), 'error' );

				return;
			}

			// Call PxAgreement.CreateAgreement3
			$params = array(
				'accountNumber'     => '',
				'merchantRef'       => $this->agreement_url,
				'description'       => $this->description,
				'purchaseOperation' => $this->purchase_operation,
				'maxAmount'         => round( $this->max_amount * 100 ),
				'notifyUrl'         => '',
				'startDate'         => '',
				'stopDate'          => ''
			);
			$result = $this->getPx()->CreateAgreement3( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
				$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

				return;
			}

			$agreement = $result['agreementRef'];

			// Save Agreement Reference
			update_post_meta( $order_id, '_payex_agreement_reference', $result );
		}

		// Call PxOrder.Initialize8
		$params = array(
			'accountNumber'     => '',
			'purchaseOperation' => $this->purchase_operation,
			'price'             => round( $amount * 100 ),
			'priceArgList'      => '',
			'currency'          => $currency,
			'vat'               => 0,
			'orderID'           => $order->id,
			'productNumber'     => $customer_id, // Customer Id
			'description'       => $this->description,
			'clientIPAddress'   => $_SERVER['REMOTE_ADDR'],
			'clientIdentifier'  => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
			'additionalValues'  => $additional,
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
			$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

			return;
		}

		$orderRef    = $result['orderRef'];
		$redirectUrl = $result['redirectUrl'];

		if ( $this->checkout_info === 'yes' ) {
			// add Order Lines
			$i = 1;
			foreach ( $order->get_items() as $product ) {
				$taxPercent = ( $product['line_tax'] > 0 ) ? round( 100 / ( $product['line_total'] / $product['line_tax'] ) ) : 0;

				// Call PxOrder.AddSingleOrderLine2
				$params = array(
					'accountNumber'    => '',
					'orderRef'         => $orderRef,
					'itemNumber'       => $i,
					'itemDescription1' => $product['name'],
					'itemDescription2' => '',
					'itemDescription3' => '',
					'itemDescription4' => '',
					'itemDescription5' => '',
					'quantity'         => $product['qty'],
					'amount'           => (int) ( 100 * ( $product['line_total'] + $product['line_tax'] ) ),
					//must include tax
					'vatPrice'         => (int) ( 100 * $product['line_tax'] ),
					'vatPercent'       => (int) ( 100 * $taxPercent )
				);
				$result = $this->getPx()->AddSingleOrderLine2( $params );
				if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
					$this->log( 'PxOrder.AddSingleOrderLine2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
					$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
					$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

					return;
				}

				$i ++;
			};

			// Add Shipping Line
			if ( (float) $order->order_shipping > 0 ) {
				$taxPercent = ( $order->order_shipping_tax > 0 ) ? round( 100 / ( $order->order_shipping / $order->order_shipping_tax ) ) : 0;

				$params = array(
					'accountNumber'    => '',
					'orderRef'         => $orderRef,
					'itemNumber'       => $i,
					'itemDescription1' => ! empty( $order->shipping_method_title ) ? $order->shipping_method_title : __( 'Shipping', 'woocommerce-gateway-payex-payment' ),
					'itemDescription2' => '',
					'itemDescription3' => '',
					'itemDescription4' => '',
					'itemDescription5' => '',
					'quantity'         => 1,
					'amount'           => (int) ( 100 * ( $order->order_shipping + $order->order_shipping_tax ) ),
					//must include tax
					'vatPrice'         => (int) ( 100 * $order->order_shipping_tax ),
					'vatPercent'       => (int) ( 100 * $taxPercent )
				);
				$result = $this->getPx()->AddSingleOrderLine2( $params );
				if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
					$this->log( 'PxOrder.AddSingleOrderLine2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
					$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
					$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

					return;
				}

				$i ++;
			}

			// Add fee lines
			foreach ( $order->get_fees() as $fee ) {
				$taxPercent = ( $fee['line_tax'] > 0 ) ? round( 100 / ( $fee['line_total'] / $fee['line_tax'] ) ) : 0;

				$params = array(
					'accountNumber'    => '',
					'orderRef'         => $orderRef,
					'itemNumber'       => $i,
					'itemDescription1' => $fee['name'],
					'itemDescription2' => '',
					'itemDescription3' => '',
					'itemDescription4' => '',
					'itemDescription5' => '',
					'quantity'         => 1,
					'amount'           => (int) ( 100 * ( $fee['line_total'] + $fee['line_tax'] ) ), //must include tax
					'vatPrice'         => (int) ( 100 * $fee['line_tax'] ),
					'vatPercent'       => (int) ( 100 * $taxPercent )
				);
				$result = $this->getPx()->AddSingleOrderLine2( $params );
				if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
					$this->log( 'PxOrder.AddSingleOrderLine2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
					$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
					$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

					return;
				}

				$i ++;
			}

			// Add discount line
			if ( $order->get_total_discount() > 0 ) {
				$params = array(
					'accountNumber'    => '',
					'orderRef'         => $orderRef,
					'itemNumber'       => $i,
					'itemDescription1' => __( 'Discount', 'woocommerce-gateway-payex-payment' ),
					'itemDescription2' => '',
					'itemDescription3' => '',
					'itemDescription4' => '',
					'itemDescription5' => '',
					'quantity'         => 1,
					'amount'           => - 1 * (int) ( $order->get_total_discount() * 100 ),
					'vatPrice'         => 0,
					'vatPercent'       => 0
				);
				$result = $this->getPx()->AddSingleOrderLine2( $params );
				if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
					$this->log( 'PxOrder.AddSingleOrderLine2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
					$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
					$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

					return;
				}
			}

			// Add Order Address
			$countries = $woocommerce->countries->countries;
			$states    = $woocommerce->countries->states;

			// Call PxOrder.AddOrderAddress2
			$params = array(
				'accountNumber'      => '',
				'orderRef'           => $orderRef,
				'billingFirstName'   => $order->billing_first_name,
				'billingLastName'    => $order->billing_last_name,
				'billingAddress1'    => $order->billing_address_1,
				'billingAddress2'    => $order->billing_address_2,
				'billingAddress3'    => '',
				'billingPostNumber'  => $order->billing_postcode,
				'billingCity'        => $order->billing_city,
				'billingState'       => isset( $states[ $order->billing_country ][ $order->billing_state ] ) ? $states[ $order->billing_country ][ $order->billing_state ] : $order->billing_state,
				'billingCountry'     => isset( $countries[ $order->billing_country ] ) ? $countries[ $order->billing_country ] : $order->billing_country,
				'billingCountryCode' => $order->billing_country,
				'billingEmail'       => $order->billing_email,
				'billingPhone'       => $order->billing_phone,
				'billingGsm'         => '',
			);

			$shipping_params = array(
				'deliveryFirstName'   => '',
				'deliveryLastName'    => '',
				'deliveryAddress1'    => '',
				'deliveryAddress2'    => '',
				'deliveryAddress3'    => '',
				'deliveryPostNumber'  => '',
				'deliveryCity'        => '',
				'deliveryState'       => '',
				'deliveryCountry'     => '',
				'deliveryCountryCode' => '',
				'deliveryEmail'       => '',
				'deliveryPhone'       => '',
				'deliveryGsm'         => '',
			);

			if ( $woocommerce->cart->needs_shipping() ) {
				$shipping_params = array(
					'deliveryFirstName'   => $order->shipping_first_name,
					'deliveryLastName'    => $order->shipping_last_name,
					'deliveryAddress1'    => $order->shipping_address_1,
					'deliveryAddress2'    => $order->shipping_address_2,
					'deliveryAddress3'    => '',
					'deliveryPostNumber'  => $order->shipping_postcode,
					'deliveryCity'        => $order->shipping_city,
					'deliveryState'       => isset( $states[ $order->shipping_country ][ $order->shipping_state ] ) ? $states[ $order->shipping_country ][ $order->shipping_state ] : $order->shipping_state,
					'deliveryCountry'     => isset( $countries[ $order->shipping_country ] ) ? $countries[ $order->shipping_country ] : $order->shipping_country,
					'deliveryCountryCode' => $order->shipping_country,
					'deliveryEmail'       => $order->billing_email,
					'deliveryPhone'       => $order->billing_phone,
					'deliveryGsm'         => '',
				);
			}

			$params += $shipping_params;

			$result = $this->getPx()->AddOrderAddress2( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				$this->log( 'PxOrder.AddOrderAddress2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
				$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
				$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

				return;
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
		global $woocommerce;

		if ( empty( $_GET['key'] ) ) {
			return;
		}

		// Validate Payment Method
		$order = $this->get_order_by_order_key( $_GET['key'] );
		if ($order && $order->payment_method !== $this->id) {
			return;
		}

		// Check OrderRef is exists
		if ( empty( $_GET['orderRef'] ) ) {
			return;
		}

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Call PxOrder.Complete
		$params = array(
			'accountNumber' => '',
			'orderRef'      => $_GET['orderRef']
		);
		$result = $this->getPx()->Complete( $params );
		if ( $result['errorCodeSimple'] !== 'OK' ) {
			$this->log( 'PxOrder.Complete:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

			return;
		}

		if ( ! isset( $result['transactionNumber'] ) ) {
			$result['transactionNumber'] = '';
		}

		// If there is no transactionStatus in the response then the order failed
		if ( ! isset( $result['transactionStatus'] ) ) {
			$result['transactionStatus'] = '5';
		}

		// Get Order
		$order_id  = (int) $result['orderId'];
		$order = wc_get_order( $order_id );

		// Check order is exists
		if ( ! $order ) {
			return;
		}

		// Check transaction is already success
		$transaction_status = get_post_meta( $order->id, '_payex_transaction_status', true );
		if ( in_array( $transaction_status, array( '0', '3', '6' ) ) ) {
			return;
		}

		$order->add_order_note( sprintf( __( 'Customer returned from PayEx. Order reference: %s', 'woocommerce-gateway-payex-payment' ), $_GET['orderRef'] ) );

		// Save Transaction
		update_post_meta( $order->id, '_transaction_id', $result['transactionNumber'] );
		update_post_meta( $order->id, '_payex_transaction_status', $result['transactionStatus'] );

		/* Transaction statuses:
		0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
		switch ( (int) $result['transactionStatus'] ) {
			case 0:
			case 6:
				$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				$order->payment_complete();
				$woocommerce->cart->empty_cart();

				// Activate subscriptions
				if ( self::isRecurringAvailable( $order ) ) {
					WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
				}
				break;
			case 1:
				$order->update_status( 'on-hold', sprintf( __( 'Transaction is pending. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				$woocommerce->cart->empty_cart();
				break;
			case 3:
				$order->update_status( 'on-hold', sprintf( __( 'Transaction authorized. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				$woocommerce->cart->empty_cart();
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
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Check transaction status
		$transaction_status = get_post_meta( $order_id, '_payex_transaction_status', true );
		if ( ! in_array( (string) $transaction_status, array( '0', '6' ) ) ) {
			return false;
		}

		// Full Refund
		if ( is_null( $amount ) ) {
			$amount = $order->order_total;
		}

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Call PxOrder.Credit5
		$params = array(
			'accountNumber'     => '',
			'transactionNumber' => $order->get_transaction_id(),
			'amount'            => round( 100 * $amount ),
			'orderId'           => $order->id,
			'vatAmount'         => 0,
			'additionalValues'  => ''
		);
		$result = $this->getPx()->Credit5( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PxOrder.Credit5:' . $result['errorCode'] . '(' . $result['description'] . ')' );

			return new WP_Error( 'woocommerce-gateway-payex-payment', $this->getVerboseErrorMessage( $result ) );
		}

		$order->add_order_note( sprintf( __( 'Refunded: %s. Transaction ID: %s. Reason: %s', 'woocommerce-gateway-payex-payment' ), wc_price( $amount ), $result['transactionNumber'], $reason ) );

		return true;
	}

	/**
	 * When a subscription is activated
	 *
	 * @param $order
	 * @param $product_id
	 */
	function reactivated_subscription( $order, $product_id ) {
		$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $product_id );

		$agreement = get_post_meta( $order->id, '_payex_agreement_reference', true );
		if ( empty( $agreement['agreementRef'] ) ) {
			WC_Subscriptions_Manager::put_subscription_on_hold_for_order( $order );
			$order->add_order_note( sprintf( __( 'Subscription "%s" suspended with PayEx. Details: %s', 'woocommerce-gateway-payex-payment' ), $item['name'], __( 'Invalid agreement reference' ) ) );

			return;
		}

		// Check Agreement Status
		// Call PxAgreement.AgreementCheck
		$params = array(
			'accountNumber' => '',
			'agreementRef'  => $agreement['agreementRef'],
		);
		$result = $this->getPx()->AgreementCheck( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			WC_Subscriptions_Manager::put_subscription_on_hold_for_order( $order );
			$order->add_order_note( sprintf( __( 'Subscription "%s" suspended with PayEx. Details: %s', 'woocommerce-gateway-payex-payment' ), $item['name'], $this->getVerboseErrorMessage( $result ) ) );

			return;
		}

		if ( ! isset( $result['agreementStatus'] ) || (int) $result['agreementStatus'] !== 1 ) {
			WC_Subscriptions_Manager::put_subscription_on_hold_for_order( $order );
			$order->add_order_note( sprintf( __( 'Subscription "%s" suspended with PayEx. Details: %s', 'woocommerce-gateway-payex-payment' ), $item['name'], __( 'Invalid agreement status' ) ) );

			return;
		}

		$order->add_order_note( sprintf( __( 'Subscription "%s" reactivated with PayEx', 'woocommerce-gateway-payex-payment' ), $item['name'] ) );
	}

	/**
	 * When a subscription payment is due.
	 *
	 * @param $amount_to_charge
	 * @param $order
	 * @param $product_id
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {
		if ( $amount_to_charge === 0 ) {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order, $product_id );
		} else {
			$item      = WC_Subscriptions_Order::get_item_by_product_id( $order, $product_id );
			$agreement = get_post_meta( $order->id, '_payex_agreement_reference', true );
			if ( ! is_array( $agreement ) || empty( $agreement['agreementRef'] ) ) {
				WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
				$order->add_order_note( sprintf( __( 'Failed to charge "%s" from Credit Card with PayEx. Subscription "%s". Details: %s.', 'woocommerce-gateway-payex-payment' ), wc_price( $amount_to_charge ), $item['name'], __( 'Invalid agreement reference', 'woocommerce-gateway-payex-payment' ) ) );

				return;
			}

			// Check Agreement Status
			// Call PxAgreement.AgreementCheck
			$params = array(
				'accountNumber' => '',
				'agreementRef'  => $agreement['agreementRef'],
			);
			$result = $this->getPx()->AgreementCheck( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
				$order->add_order_note( sprintf( __( 'Failed to charge "%s" from Credit Card with PayEx. Subscription "%s". Details: %s.', 'woocommerce-gateway-payex-payment' ), wc_price( $amount_to_charge ), $item['name'], $this->getVerboseErrorMessage( $result ) ) );

				return;
			}

			if ( ! isset( $result['agreementStatus'] ) || (int) $result['agreementStatus'] !== 1 ) {
				WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
				$order->add_order_note( sprintf( __( 'Failed to charge "%s" from Credit Card with PayEx. Subscription "%s". Details: %s.', 'woocommerce-gateway-payex-payment' ), wc_price( $amount_to_charge ), $item['name'], __( 'Invalid agreement status', 'woocommerce-gateway-payex-payment' ) ) );

				return;
			}

			// Call PxAgreement.AutoPay3
			$params = array(
				'accountNumber'     => '',
				'agreementRef'      => $agreement['agreementRef'],
				'price'             => round( $amount_to_charge * 100 ),
				'productNumber'     => (int) $order->customer_user,
				'description'       => $this->description,
				'orderId'           => $order->id,
				'purchaseOperation' => $this->purchase_operation,
				'currency'          => get_option( 'woocommerce_currency' )
			);
			$result = $this->getPx()->AutoPay3( $params );
			if ( $result['errorCodeSimple'] !== 'OK' ) {
				WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
				$order->add_order_note( sprintf( __( 'Failed to charge "%s" from Credit Card with PayEx. Subscription "%s". Details: %s.', 'woocommerce-gateway-payex-payment' ), wc_price( $amount_to_charge ), $item['name'], $this->getVerboseErrorMessage( $result ) ) );

				return;
			}

			// Save Transaction
			update_post_meta( $order->id, '_transaction_id', $result['transactionNumber'] );
			update_post_meta( $order->id, '_payex_transaction_status', $result['transactionStatus'] );

			// Add Order Note
			$order->add_order_note( sprintf( __( 'Charged "%s" from Credit Card with PayEx. Subscription "%s". Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), wc_price( $amount_to_charge ), $item['name'], $result['transactionNumber'] ) );

			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order, $product_id );
		}
	}

	/**
	 * Don't transfer Agreement meta when creating a parent renewal order.
	 *
	 * @access public
	 *
	 * @param array  $order_meta_query  MySQL query for pulling the metadata
	 * @param int    $original_order_id Post ID of the order being used to purchased the subscription being renewed
	 * @param int    $renewal_order_id  Post ID of the order created for renewing the subscription
	 * @param string $new_order_role    The role the renewal order is taking, one of 'parent' or 'child'
	 *
	 * @return string
	 */
	function remove_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {

		if ( 'parent' == $new_order_role ) {
			$order_meta_query .= " AND `meta_key` NOT IN ("
			                     . "'_payex_agreement_reference', "
			                     . "'_payex_transaction_status' )";
		}

		return $order_meta_query;
	}

	/**
	 * After payment is completed on an order for switching a subscription, complete the switch.
	 * Clone agreement reference when subscription been switched
	 *
	 * @param $order_id int The current order.
	 */
	public function maybe_complete_switch( $order_id ) {
		$original_subscription_key = get_post_meta( $order_id, '_switched_subscription_key', true );
		if ( ! empty( $original_subscription_key ) ) {
			$agreement = get_post_meta( $order_id, '_payex_agreement_reference', true );
			if ( empty( $agreement ) ) {
				$original_subscription = WC_Subscriptions_Manager::get_subscription( $original_subscription_key );
				$original_agreement    = get_post_meta( $original_subscription['order_id'], '_payex_agreement_reference', true );
				if ( ! empty( $original_agreement ) ) {
					update_post_meta( $order_id, '_payex_agreement_reference', $original_agreement );
				}
			}
		}
	}
}
