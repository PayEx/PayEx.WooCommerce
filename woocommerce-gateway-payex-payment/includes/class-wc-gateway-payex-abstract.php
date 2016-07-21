<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Payex_Abstract extends WC_Payment_Gateway {
	protected $_px;

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
			return new WP_Error( 'woocommerce-gateway-payex-payment', __( 'Unable to perform refund. The transaction must be captured.', 'woocommerce-gateway-payex-payment' ) );
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
	 * Get PayEx Handler
	 * @return \PayEx\Px
	 */
	public function getPx() {
		if ( ! $this->_px ) {
			global $wp_version;

			$plugin_version = get_file_data(
				dirname(__FILE__) . '/../woocommerce-gateway-payex-payment.php',
				array('Version'),
				'woocommerce-gateway-payex-payment'
			);

			$this->_px = new \PayEx\Px();
			$this->_px->setUserAgent(sprintf("PayEx.Ecommerce.Php/%s PHP/%s WordPress/%s WooCommerce/%s PayEx.WooCommerce/%s",
				\PayEx\Px::VERSION,
				phpversion(),
				$wp_version,
				WC()->version,
				$plugin_version[0]
			));
		}

		return $this->_px;
	}

	/**
	 * Get Locale for PayEx
	 *
	 * @param array $lang
	 *
	 * @return string
	 */
	public function getLocale( $lang ) {
		$allowed_langs = array(
			'en' => 'en-US',
			'sv' => 'sv-SE',
			'nb' => 'nb-NO',
			'da' => 'da-DK',
			'es' => 'es-ES',
			'de' => 'de-DE',
			'fi' => 'fi-FI',
			'fr' => 'fr-FR',
			'pl' => 'pl-PL',
			'cs' => 'cs-CZ',
			'hu' => 'hu-HU'
		);

		$locale = strtolower( substr( $lang, 0, 2 ) );

		if ( isset( $allowed_langs[ $locale ] ) ) {
			return $allowed_langs[ $locale ];
		}

		return 'en-US';
	}

	/**
	 * Debug Log
	 *
	 * @param $message
	 *
	 * @return int
	 */
	public function log( $message ) {
		global $woocommerce;

		// Is Enabled
		if ( $this->debug !== 'yes' ) {
			return;
		}

		// Get Logger instance
		if ( version_compare( $woocommerce->version, '2.1.0', '>=' ) ) {
			$log = new WC_Logger();
		} else {
			$log = $woocommerce->logger();
		}

		// Write message to log
		if ( ! is_string( $message ) ) {
			$message = var_export( $message, true );
		}

		$log->add( $this->id, $message );
	}

	/**
	 * Get Tax Classes
	 * @return array
	 */
	public function getTaxClasses() {
		// Get tax classes
		$tax_classes           = array_filter( array_map( 'trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) );
		$tax_class_options     = array();
		$tax_class_options[''] = __( 'Standard', 'woocommerce' );
		foreach ( $tax_classes as $class ) {
			$tax_class_options[ sanitize_title( $class ) ] = $class;
		}

		return $tax_class_options;
	}

	/**
	 * Get verbose error message by Error Code
	 *
	 * @param $errorCode
	 *
	 * @return string | false
	 */
	public function getErrorMessageByCode( $errorCode ) {
		$errorMessages = array(
			'REJECTED_BY_ACQUIRER'                    => __( 'Your customers bank declined the transaction, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
			//'Error_Generic'                           => __( 'An unhandled exception occurred', 'woocommerce-gateway-payex-payment' ),
			'3DSecureDirectoryServerError'            => __( 'A problem with Visa or MasterCards directory server, that communicates transactions for 3D-Secure verification', 'woocommerce-gateway-payex-payment' ),
			'AcquirerComunicationError'               => __( 'Communication error with the acquiring bank', 'woocommerce-gateway-payex-payment' ),
			'AmountNotEqualOrderLinesTotal'           => __( 'The sum of your order lines is not equal to the price set in initialize', 'woocommerce-gateway-payex-payment' ),
			'CardNotEligible'                         => __( 'Your customers card is not eligible for this kind of purchase, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
			'CreditCard_Error'                        => __( 'Some problem occurred with the credit card, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
			'PaymentRefusedByFinancialInstitution'    => __( 'Your customers bank declined the transaction, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
			'Merchant_InvalidAccountNumber'           => __( 'The merchant account number sent in on request is invalid', 'woocommerce-gateway-payex-payment' ),
			'Merchant_InvalidIpAddress'               => __( 'The IP address the request comes from is not registered in PayEx, you can set it up in PayEx Admin under Merchant profile', 'woocommerce-gateway-payex-payment' ),
			'Access_MissingAccessProperties'          => __( 'The merchant does not have access to requested functionality', 'woocommerce-gateway-payex-payment' ),
			'Access_DuplicateRequest'                 => __( 'Your customers bank declined the transaction, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
			'Admin_AccountTerminated'                 => __( 'The merchant account is not active', 'woocommerce-gateway-payex-payment' ),
			'Admin_AccountDisabled'                   => __( 'The merchant account is not active', 'woocommerce-gateway-payex-payment' ),
			'ValidationError_AccountLockedOut'        => __( 'The merchant account is locked out', 'woocommerce-gateway-payex-payment' ),
			'ValidationError_Generic'                 => __( 'Generic validation error', 'woocommerce-gateway-payex-payment' ),
			'ValidationError_HashNotValid'            => __( 'The hash on request is not valid, this might be due to the encryption key being incorrect', 'woocommerce-gateway-payex-payment' ),
			//'ValidationError_InvalidParameter'        => __( 'One of the input parameters has invalid data. See paramName and description for more information', 'woocommerce-gateway-payex-payment' ),
			'OperationCancelledbyCustomer'            => __( 'The operation was cancelled by the client', 'woocommerce-gateway-payex-payment' ),
			'PaymentDeclinedDoToUnspecifiedErr'       => __( 'Unexpecter error at 3rd party', 'woocommerce-gateway-payex-payment' ),
			'InvalidAmount'                           => __( 'The amount is not valid for this operation', 'woocommerce-gateway-payex-payment' ),
			'NoRecordFound'                           => __( 'No data found', 'woocommerce-gateway-payex-payment' ),
			'OperationNotAllowed'                     => __( 'The operation is not allowed, transaction is in invalid state', 'woocommerce-gateway-payex-payment' ),
			'ACQUIRER_HOST_OFFLINE'                   => __( 'Could not get in touch with the card issuer', 'woocommerce-gateway-payex-payment' ),
			'ARCOT_MERCHANT_PLUGIN_ERROR'             => __( 'The card could not be verified', 'woocommerce-gateway-payex-payment' ),
			'REJECTED_BY_ACQUIRER_CARD_BLACKLISTED'   => __( 'There is a problem with this card', 'woocommerce-gateway-payex-payment' ),
			'REJECTED_BY_ACQUIRER_CARD_EXPIRED'       => __( 'The card expired', 'woocommerce-gateway-payex-payment' ),
			'REJECTED_BY_ACQUIRER_INSUFFICIENT_FUNDS' => __( 'Insufficient funds', 'woocommerce-gateway-payex-payment' ),
			'REJECTED_BY_ACQUIRER_INVALID_AMOUNT'     => __( 'Incorrect amount', 'woocommerce-gateway-payex-payment' ),
			'USER_CANCELED'                           => __( 'Payment cancelled', 'woocommerce-gateway-payex-payment' ),
			'CardNotAcceptedForThisPurchase'          => __( 'Your Credit Card not accepted for this purchase', 'woocommerce-gateway-payex-payment' ),
			'CreditCheckNotApproved'                  => __( 'CreditCheck failed', 'woocommerce-gateway-payex-payment' )
		);
		$errorMessages = array_change_key_case( $errorMessages, CASE_UPPER );

		$errorCode = mb_strtoupper( $errorCode );

		return isset( $errorMessages[ $errorCode ] ) ? $errorMessages[ $errorCode ] : false;
	}

	/**
	 * Get Verbose Error Message
	 *
	 * @param array $details
	 *
	 * @return string
	 */
	public function getVerboseErrorMessage( array $details ) {
		$errorCode    = isset( $details['transactionErrorCode'] ) ? $details['transactionErrorCode'] : $details['errorCode'];
		$errorMessage = $this->getErrorMessageByCode( $errorCode );
		if ( $errorMessage ) {
			return $errorMessage;
		}

		$errorCode        = isset( $details['transactionErrorCode'] ) ? $details['transactionErrorCode'] : '';
		$errorDescription = isset( $details['transactionThirdPartyError'] ) ? $details['transactionThirdPartyError'] : '';
		if ( empty( $errorCode ) && empty( $errorDescription ) ) {
			$errorCode        = $details['code'];
			$errorDescription = $details['description'];
		}

		return sprintf( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $errorCode . ' (' . $errorDescription . ')' );
	}

	/**
	 * Add message
	 *
	 * @param string $message
	 *
	 * @param string $notice_type
	 */
	public function add_message( $message = '', $notice_type = 'error' ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $notice_type );
		} else { // WC < 2.1
			global $woocommerce;
			if ( 'error' === $notice_type ) {
				$woocommerce->add_error( $message );
			} else {
				$woocommerce->add_message( $message );
			}

			$woocommerce->set_messages();
		}
	}

	/**
	 * Check Order is Recurring Payment available
	 *
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	public static function order_contains_subscription( $order ) {
		if ( ! class_exists( 'WC_Subscriptions', false ) ) {
			return false;
		}

		return wcs_order_contains_subscription( $order );
	}

	/**
	 * Finds an Order based on an order key.
	 *
	 * @param $order_key
	 *
	 * @return bool|WC_Order
	 */
	public function get_order_by_order_key( $order_key ) {
		$order_id = wc_get_order_id_by_order_key( $order_key );
		if ( $order_id ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Prepare Additional Values string
	 *
	 * @param array|false                   $additional
	 * @param WC_Order|WC_Subscription|bool $order
	 *
	 * @return string
	 */
	protected function get_additional_values( array $additional, $order = false ) {
		$result = apply_filters( 'woocommerce_payex_additional_values', $additional, $order );

		return implode( '&', $result );
	}
}