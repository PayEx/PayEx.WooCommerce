<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Payex_Bankdebit extends WC_Gateway_Payex_Abstract {
	protected static $available_banks = array(
		'NB'      => 'Nordea Bank',
		'FSPA'    => 'Swedbank',
		'SEB'     => 'Svenska Enskilda Bank',
		'SHB'     => 'Handelsbanken',
		'NB:DK'   => 'Nordea Bank DK',
		'DDB'     => 'Den Danske Bank',
		'BAX'     => 'BankAxess',
		'SAMPO'   => 'Sampo',
		'AKTIA'   => 'Aktia, Säästöpankki',
		'OP'      => 'Osuuspanki, Pohjola, Oko',
		'NB:FI'   => 'Nordea Bank Finland',
		'SHB:FI'  => 'SHB:FI',
		'SPANKKI' => 'SPANKKI',
		'TAPIOLA' => 'TAPIOLA',
		'AALAND'  => 'Ålandsbanken',
	);

	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'payex_bankdebit';
		$this->has_fields   = true;
		$this->method_title = __( 'PayEx Bank Debit', 'woocommerce-gateway-payex-payment' );
		$this->icon         = apply_filters( 'woocommerce_payex_payment_icon', plugins_url( '/assets/images/payex.gif', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled       = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title         = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->account_no    = isset( $this->settings['account_no'] ) ? $this->settings['account_no'] : '';
		$this->encrypted_key = isset( $this->settings['encrypted_key'] ) ? $this->settings['encrypted_key'] : '';
		$this->description   = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->banks         = isset( $this->settings['banks'] ) ? $this->settings['banks'] : array();
		$this->language      = isset( $this->settings['language'] ) ? $this->settings['language'] : 'en-US';
		$this->testmode      = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'yes';
		$this->checkout_info = isset( $this->settings['checkout_info'] ) ? $this->settings['checkout_info'] : 'yes';
		$this->responsive    = isset( $this->settings['responsive'] ) ? $this->settings['responsive'] : 'no';
		$this->debug         = isset( $this->settings['debug'] ) ? $this->settings['debug'] : 'no';

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		}
	}

	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_payex_bankdebit_supported_currencies',
			array( 'DKK', 'EUR', 'GBP', 'NOK', 'SEK', 'USD' )
		) );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'       => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'title'         => array(
				'title'       => __( 'Title', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( 'PayEx Bank Debit', 'woocommerce-gateway-payex-payment' )
			),
			'description'   => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( 'PayEx Bank Debit', 'woocommerce-gateway-payex-payment' ),
			),
			'account_no'    => array(
				'title'       => __( 'Account Number', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'Account Number of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'encrypted_key' => array(
				'title'       => __( 'Encryption Key', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'PayEx Encryption Key of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'banks'         => array(
				'title'       => __( 'Banks', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'multiselect',
				'class'       => 'chosen_select',
				'css'         => 'width: 450px;',
				'default'     => '',
				'description' => __( 'Available banks for payment.', 'woocommerce-gateway-payex-payment' ),
				'options'     => self::$available_banks,
				'desc_tip'    => true,
			),
			'language'      => array(
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
			'testmode'      => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayEx Test Mode', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'checkout_info' => array(
				'title'   => __( 'Enable checkout information', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send order lines and billing/delivery addresses to PayEx', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'responsive'    => array(
				'title'   => __( 'Enable Responsive Skinning', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Use Responsive web design on PayEx pages', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'debug'         => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			)
		);
	}

	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {
		$banks           = $this->banks;
		$available_banks = self::$available_banks;
		?>
		<label for="bank_id"><?php echo __( 'Select bank:', 'woocommerce-gateway-payex-payment' ); ?></label>
		<select name="bank_id" id="bank_id">
			<?php foreach ( $banks as $_key => $bank_id ): ?>
				<option value="<?php echo $bank_id; ?>"><?php echo $available_banks[ $bank_id ]; ?></option>
			<?php endforeach; ?>
		</select>

		<div class="clear"></div>
		<?php
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
	 * Validate Frontend Fields
	 * @return bool|void
	 */
	public function validate_fields() {
		global $woocommerce;
		if ( empty( $_POST['bank_id'] ) || ! isset( self::$available_banks[ $_POST['bank_id'] ] ) ) {
			$this->add_message( __( 'Wrong bank.', 'woocommerce-gateway-payex-payment' ), 'error' );
		}
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

		$customer_id = (int) $order->customer_user;
		$amount      = $order->order_total;
		$currency    = get_option( 'woocommerce_currency' );
		$bank_id     = ! empty( $_POST['bank_id'] ) ? $_POST['bank_id'] : 'NB';

		$returnUrl = html_entity_decode( $this->get_return_url( $order ) );
		$cancelUrl = html_entity_decode( $order->get_cancel_order_url() );

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Call PxOrder.Initialize8
		$params = array(
			'accountNumber'     => '',
			'purchaseOperation' => 'SALE',
			'price'             => 0,
			'priceArgList'      => $bank_id . '=' . round( $amount * 100 ),
			'currency'          => $currency,
			'vat'               => 0,
			'orderID'           => $order->id,
			'productNumber'     => $customer_id, // Customer Id
			'description'       => $this->description,
			'clientIPAddress'   => $_SERVER['REMOTE_ADDR'],
			'clientIdentifier'  => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
			'additionalValues'  => ( $this->responsive === 'yes' ? 'USECSS=RESPONSIVEDESIGN' : '' ),
			'externalID'        => '',
			'returnUrl'         => $returnUrl,
			'view'              => 'DIRECTDEBIT',
			'agreementRef'      => '',
			'cancelUrl'         => $cancelUrl,
			'clientLanguage'    => $this->language
		);
		$result = $this->getPx()->Initialize8( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PxOrder.Initialize8:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
			$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

			return;
		}

		$orderRef = $result['orderRef'];

		if ( $this->checkout_info === 'yes' ) {
			// add Order Lines
			$i = 1;
			foreach ( $order->get_items() as $order_item ) {
				$price = $order->get_line_subtotal( $order_item, false, false );
				$priceWithTax = $order->get_line_subtotal( $order_item, true, false );
				$tax = $priceWithTax - $price;
				$taxPercent = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;

				// Call PxOrder.AddSingleOrderLine2
				$params = array(
					'accountNumber'    => '',
					'orderRef'         => $orderRef,
					'itemNumber'       => $i,
					'itemDescription1' => $order_item['name'],
					'itemDescription2' => '',
					'itemDescription3' => '',
					'itemDescription4' => '',
					'itemDescription5' => '',
					'quantity'         => $order_item['qty'],
					'amount'           => (int) ( 100 * $priceWithTax ),
					//must include tax
					'vatPrice'         => (int) ( 100 * $tax ),
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
			if ( $order->get_total_discount( false ) > 0 ) {
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
					'amount'           => - 1 * (int) ( $order->get_total_discount( false ) * 100 ),
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

		// Call PxOrder.PrepareSaleDD2
		$params = array(
			'accountNumber' => '',
			'orderRef'      => $orderRef,
			'userType'      => 0, // Anonymous purchase
			'userRef'       => '',
			'bankName'      => $bank_id
		);
		$result = $this->getPx()->PrepareSaleDD2( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PxOrder.PrepareSaleDD2:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
			$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

			return;
		}

		$order->add_order_note( __( 'Customer has been redirected to Bank.', 'woocommerce-gateway-payex-payment' ) );

		return array(
			'result'   => 'success',
			'redirect' => $result['redirectUrl']
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
		$order_id = (int) $result['orderId'];
		$order    = wc_get_order( $order_id );

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


}
