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
			$amount = $order->get_total();
		}

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Call PxOrder.Credit5
		$params = array(
			'accountNumber'     => '',
			'transactionNumber' => $order->get_transaction_id(),
			'amount'            => round( 100 * $amount ),
			'orderId'           => $order->get_id(),
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
	 * Get Transaction Info
	 * @param $transaction_id
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function get_transaction_info( $transaction_id ) {
		// Call PxOrder.GetTransactionDetails2
		$params = array(
			'accountNumber'     => '',
			'transactionNumber' => $transaction_id
		);
		$details = $this->getPx()->GetTransactionDetails2( $params );
		if ( $details['code'] !== 'OK' || $details['description'] !== 'OK' || $details['errorCode'] !== 'OK' ) {
			throw new Exception( $this->getVerboseErrorMessage( $details ) );
		}

		return $details;
	}

	/**
	 * Capture
	 *
	 * @param WC_Order|int $order
	 * @param bool         $amount
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function capture_payment( $order, $amount = FALSE ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		// Call PxOrder.Capture5
		$params = array(
			'accountNumber'     => '',
			'transactionNumber' => $order->get_transaction_id(),
			'amount'            => round( 100 * $amount ),
			'orderId'           => $order->get_id(),
			'vatAmount'         => 0,
			'additionalValues'  => ''
		);
		$result = $this->getPx()->Capture5( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PxOrder.Capture5:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$message = sprintf( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $result['errorCode'] . ' (' . $result['description'] . ')' );
			throw new Exception( $message );
		}

		// Disable status change hook
		remove_action( 'woocommerce_order_status_changed', 'WC_Payex_Admin_Actions::order_status_changed', 10 );

		update_post_meta( $order->get_id(), '_payex_transaction_status', $result['transactionStatus'] );
		$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
		$order->payment_complete( $result['transactionNumber'] );
	}

	/**
	 * Cancel
	 *
	 * @param WC_Order|int $order
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function cancel_payment( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		// Call PxOrder.Cancel2
		$params = array(
			'accountNumber'     => '',
			'transactionNumber' => $order->get_transaction_id()
		);
		$result = $this->getPx()->Cancel2( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PxOrder.Cancel2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$message = sprintf( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $result['errorCode'] . ' (' . $result['description'] . ')' );
			throw new Exception( $message );
		}

		// Disable status change hook
		remove_action( 'woocommerce_order_status_changed', 'WC_Payex_Admin_Actions::order_status_changed', 10 );

		update_post_meta( $order->get_id(), '_transaction_id', $result['transactionNumber'] );
		update_post_meta( $order->get_id(), '_payex_transaction_status', $result['transactionStatus'] );
		$message = sprintf( __( 'Transaction canceled. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] );
		$order->update_status('cancelled', $message);
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
	 * @param $order_id
	 *
	 * @return void
	 */
	public function log( $message, $order_id = null ) {
		// Is Enabled
		if ( $this->debug !== 'yes' ) {
			return;
		}

		// Get Logger instance
		$log = new WC_Logger();

		// Write message to log
		if ( ! is_string( $message ) ) {
			$message = var_export( $message, true );
		}

		if ( ! empty( $order_id ) ) {
			$message .= sprintf( ' (OrderID: %s)', $order_id );
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
	 * @param array|false   $additional
	 * @param WC_Order|bool $order
	 *
	 * @return string
	 */
	protected function get_additional_values( array $additional, $order = false ) {
		$result = apply_filters( 'woocommerce_payex_additional_values', $additional, $order );

		return implode( '&', $result );
	}

	/**
	 * Check is WooCommerce >= 3.0
	 * @return bool
	 */
	public function is_wc3() {
		return version_compare( WC()->version, '3.0', '>=' );
	}

	/**
	 * Get Order Lines
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function get_order_items( $order ) {
		$item = array();

		// WooCommerce 3
		if ( $this->is_wc3() ) {
			foreach ( $order->get_items() as $order_item ) {
				/** @var WC_Order_Item_Product $order_item */
				$price        = $order->get_line_subtotal( $order_item, false, false );
				$priceWithTax = $order->get_line_subtotal( $order_item, true, false );
				$tax          = $priceWithTax - $price;
				$taxPercent   = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;

				$item[] = array(
					'type' => 'product',
					'name' => $order_item->get_name(),
					'qty' => $order_item->get_quantity(),
					'price_with_tax' => sprintf( "%.2f", $priceWithTax ),
					'price_without_tax' => sprintf( "%.2f", $price ),
					'tax_price' => sprintf( "%.2f", $tax ),
					'tax_percent' => sprintf( "%.2f", $taxPercent )
				);
			};

			// Add Shipping Line
			if ( (float) $order->get_shipping_total() > 0 ) {
				$shipping = $order->get_shipping_total();
				$tax = $order->get_shipping_tax();
				$shippingWithTax = $shipping + $tax;
				$taxPercent = ( $tax > 0 ) ? round( 100 / ( $shipping / $tax) ) : 0;

				$item[] = array(
					'type' => 'shipping',
					'name' => $order->get_shipping_method(),
					'qty' => 1,
					'price_with_tax' => sprintf( "%.2f", $shippingWithTax ),
					'price_without_tax' => sprintf( "%.2f", $shipping ),
					'tax_price' => sprintf( "%.2f", $tax ),
					'tax_percent' => sprintf( "%.2f", $taxPercent )
				);
			}

			// Add fee lines
			foreach ( $order->get_fees() as $order_fee ) {
				/** @var WC_Order_Item_Fee $order_fee */
				$fee = $order_fee->get_total();
				$tax = $order_fee->get_total_tax();
				$feeWithTax = $fee + $tax;
				$taxPercent = ( $tax > 0 ) ? round( 100 / ( $fee / $tax) ) : 0;

				$item[] = array(
					'type' => 'fee',
					'name' => $order_fee->get_name(),
					'qty' => 1,
					'price_with_tax' => sprintf( "%.2f", $feeWithTax ),
					'price_without_tax' => sprintf( "%.2f", $fee ),
					'tax_price' => sprintf( "%.2f", $tax ),
					'tax_percent' => sprintf( "%.2f", $taxPercent )
				);
			}

			// Add discount line
			if ( $order->get_total_discount( false ) > 0 ) {
				$discount = $order->get_total_discount( true );
				$discountWithTax = $order->get_total_discount( false );
				$tax          = $discountWithTax - $discount;
				$taxPercent   = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

				$item[] = array(
					'type' => 'discount',
					'name' => __( 'Discount', 'woocommerce-gateway-payex-payment' ),
					'qty' => 1,
					'price_with_tax' => sprintf( "%.2f", -1 * $discountWithTax ),
					'price_without_tax' => sprintf( "%.2f", -1 * $discount ),
					'tax_price' => sprintf( "%.2f", -1 * $tax ),
					'tax_percent' => sprintf( "%.2f", $taxPercent )
				);
			}
			return $item;
		}

		// WooCommerce 2.6
		foreach ( $order->get_items() as $order_item ) {
			$price        = $order->get_line_subtotal( $order_item, false, false );
			$priceWithTax = $order->get_line_subtotal( $order_item, true, false );
			$tax          = $priceWithTax - $price;
			$taxPercent   = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;

			$item[] = array(
				'type' => 'product',
				'name' => $order_item['name'],
				'qty' => $order_item['qty'],
				'price_with_tax' => sprintf( "%.2f", $priceWithTax ),
				'price_without_tax' => sprintf( "%.2f", $price ),
				'tax_price' => sprintf( "%.2f", $tax ),
				'tax_percent' => sprintf( "%.2f", $taxPercent )
			);
		};

		// Add Shipping Line
		if ( (float) $order->order_shipping > 0 ) {
			$taxPercent = ( $order->order_shipping_tax > 0 ) ? round( 100 / ( $order->order_shipping / $order->order_shipping_tax ) ) : 0;

			$item[] = array(
				'type' => 'shipping',
				'name' => $order->get_shipping_method(),
				'qty' => 1,
				'price_with_tax' => sprintf( "%.2f", $order->order_shipping + $order->order_shipping_tax ),
				'price_without_tax' => sprintf( "%.2f", $order->order_shipping ),
				'tax_price' => sprintf( "%.2f", $order->order_shipping_tax ),
				'tax_percent' => sprintf( "%.2f", $taxPercent )
			);
		}

		// Add fee lines
		foreach ( $order->get_fees() as $order_fee ) {
			$taxPercent = ( $order_fee['line_tax'] > 0 ) ? round( 100 / ( $order_fee['line_total'] / $order_fee['line_tax'] ) ) : 0;

			$item[] = array(
				'type' => 'fee',
				'name' => $order_fee['name'],
				'qty' => 1,
				'price_with_tax' => sprintf( "%.2f", $order_fee['line_total'] + $order_fee['line_tax'] ),
				'price_without_tax' => sprintf( "%.2f", $order_fee['line_total'] ),
				'tax_price' => sprintf( "%.2f", $order_fee['line_tax'] ),
				'tax_percent' => sprintf( "%.2f", $taxPercent )
			);
		}

		// Add discount line
		if ( $order->get_total_discount( false ) > 0 ) {
			$discount = $order->get_total_discount( true );
			$discountWithTax = $order->get_total_discount( false );
			$tax          = $discountWithTax - $discount;
			$taxPercent   = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			$item[] = array(
				'type' => 'discount',
				'name' => __( 'Discount', 'woocommerce-gateway-payex-payment' ),
				'qty' => 1,
				'price_with_tax' => sprintf( "%.2f", -1 * $discountWithTax ),
				'price_without_tax' => sprintf( "%.2f", -1 * $discount ),
				'tax_price' => sprintf( "%.2f", -1 * $tax ),
				'tax_percent' => sprintf( "%.2f", $taxPercent )
			);
		}

		return $item;
	}

	/**
	 * Calculate VAT of items
	 *
	 * @param array $items
	 *
	 * @return int|mixed
	 */
	public static function get_items_vat( array $items ) {
		$values = array_unique( array_column( $items, 'tax_percent' ), SORT_NUMERIC );

		if ( count( $values) >= 2 ) {
			return 0;
		}

		return array_shift( $values );
	}

	/**
	 * Prepare Address Info
	 * @param WC_Order $order
	 * @return array
	 */
	public function get_address_info( $order ) {
		$countries = WC()->countries->countries;
		$states    = WC()->countries->states;

		$billing_country = $order->get_billing_country();
		$billing_state = $order->get_billing_state();

		$params = array(
			'billingFirstName' => $order->get_billing_first_name(),
			'billingLastName' => $order->get_billing_last_name(),
			'billingAddress1' => $order->get_billing_address_1(),
			'billingAddress2' => $order->get_billing_address_2(),
			'billingAddress3' => '',
			'billingPostNumber' => $order->get_billing_postcode(),
			'billingCity' => $order->get_billing_city(),
			'billingState' => isset( $states[ $billing_country ][ $billing_state ] ) ? $states[ $billing_country ][ $billing_state ] : $billing_state,
			'billingCountry' => isset( $countries[ $billing_country ] ) ? $countries[ $billing_country ] : $billing_country,
			'billingCountryCode' => $billing_country,
			'billingEmail' => $order->get_billing_email(),
			'billingPhone' => $order->get_billing_phone(),
			'billingGsm' => '',
			'deliveryFirstName' => '',
			'deliveryLastName' => '',
			'deliveryAddress1' => '',
			'deliveryAddress2' => '',
			'deliveryAddress3' => '',
			'deliveryPostNumber' => '',
			'deliveryCity' => '',
			'deliveryState' => '',
			'deliveryCountry' => '',
			'deliveryCountryCode' => '',
			'deliveryEmail' => '',
			'deliveryPhone' => '',
			'deliveryGsm' => '',
		);

		// Add Delivery Info
		if ( $this->order_needs_shipping( $order ) ) {
			$shipping_country = $order->get_shipping_country();
			$shipping_state = $order->get_shipping_state();

			$params = array_merge($params, array(
				'deliveryFirstName' => $order->get_shipping_first_name(),
				'deliveryLastName' => $order->get_shipping_last_name(),
				'deliveryAddress1' => $order->get_shipping_address_1(),
				'deliveryAddress2' => $order->get_shipping_address_2(),
				'deliveryAddress3' => '',
				'deliveryPostNumber' => $order->get_shipping_postcode(),
				'deliveryCity' => $order->get_shipping_city(),
				'deliveryState' => isset( $states[ $shipping_country ][ $shipping_state ] ) ? $states[ $shipping_country ][ $shipping_state ] : $shipping_state,
				'deliveryCountry' => isset( $countries[ $shipping_country ] ) ? $countries[ $shipping_country ] : $shipping_country,
				'deliveryCountryCode' => $shipping_country,
				'deliveryEmail' => $order->get_billing_email(),
				'deliveryPhone' => $order->get_billing_phone(),
				'deliveryGsm' => '',
			));
		}

		return $params;
	}

	/**
	 * Check is Order should be shipped
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	public function order_needs_shipping( $order ) {
		$items = $order->get_items();
		foreach ($items as $item) {
			$product = $this->is_wc3() ? $item->get_product() : wc_get_product( $item['product_id'] );
			if ( $product && $product->needs_shipping() ) {
				return true;
			}
		}

		return false;
	}
}