<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Payex_Factoring extends WC_Gateway_Payex_Abstract {
	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'payex_factoring';
		$this->has_fields   = true;
		$this->method_title = __( 'PayEx Financing', 'woocommerce-gateway-payex-payment' );
		$this->icon         = apply_filters( 'woocommerce_payex_factoring_icon', plugins_url( '/assets/images/null.png', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled        = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title          = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->account_no     = isset( $this->settings['account_no'] ) ? $this->settings['account_no'] : '';
		$this->encrypted_key  = isset( $this->settings['encrypted_key'] ) ? $this->settings['encrypted_key'] : '';
		$this->mode           = isset( $this->settings['mode'] ) ? $this->settings['mode'] : 'FINANCING';
		$this->description    = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->language       = isset( $this->settings['language'] ) ? $this->settings['language'] : 'en-US';
		$this->testmode       = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'yes';
		$this->debug          = isset( $this->settings['debug'] ) ? $this->settings['debug'] : 'no';
		$this->fee            = isset( $this->settings['fee'] ) ? (float) $this->settings['fee'] : 0;
		$this->fee_is_taxable = isset( $this->settings['fee_is_taxable'] ) ? $this->settings['fee_is_taxable'] : 'no';
		$this->fee_tax_class  = isset( $this->settings['fee_tax_class'] ) ? $this->settings['fee_tax_class'] : 'standard';
		$this->checkout_field = isset( $this->settings['checkout_field'] ) ? $this->settings['checkout_field'] : 'no';

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Actions
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		if ( abs( $this->fee ) !== 0 ) {
			wp_enqueue_script( 'wc-gateway-payex-factoring-fee', plugins_url( '/assets/js/fee.js', dirname( __FILE__ ) ), array( 'wc-checkout' ), false, true );
		}
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'title'          => array(
				'title'       => __( 'Title', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( 'PayEx Financing', 'woocommerce-gateway-payex-payment' )
			),
			'description'    => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => '',
			),
			'account_no'     => array(
				'title'       => __( 'Account Number', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'Account Number of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'encrypted_key'  => array(
				'title'       => __( 'Encryption Key', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'PayEx Encryption Key of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'mode'           => array(
				'title'       => __( 'Payment Type', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => array(
					'SELECT'        => __( 'User select', 'woocommerce-gateway-payex-payment' ),
					'FINANCING'     => __( 'Financing Invoice', 'woocommerce-gateway-payex-payment' ),
					'CREDITACCOUNT' => __( 'Part Payment', 'woocommerce-gateway-payex-payment' ),
				),
				'description' => __( 'Default payment type.', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'FINANCING'
			),
			'language'       => array(
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
			'testmode'       => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayEx Test Mode', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'debug'          => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'fee'            => array(
				'title'             => __( 'Fee', 'woocommerce-gateway-payex-payment' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any'
				),
				'description'       => __( 'Financing fee. Set 0 to disable.', 'woocommerce-gateway-payex-payment' ),
				'default'           => '0'
			),
			'fee_is_taxable' => array(
				'title'   => __( 'Fee is Taxable', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Fee is Taxable', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'fee_tax_class'  => array(
				'title'       => __( 'Tax class of Fee', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => $this->getTaxClasses(),
				'description' => __( 'Tax class of fee.', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'standard'
			),
			'checkout_field' => array(
				'title'   => __( 'SSN Field on Checkout page', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'SSN Field on Checkout page', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
		);
	}

	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {
		parent::payment_fields();
		?>
		<?php if ( $this->mode === 'SELECT' ): ?>
			<label for="factoring-menu"></label>
			<label for="social-security-number"><?php echo __( 'Please select payment method:', 'woocommerce-gateway-payex-payment' ); ?></label>
			<select name="factoring-menu" id="factoring-menu" class="required-entry">
				<option selected value="FINANCING"><?php echo __( 'Financing Invoice', 'woocommerce-gateway-payex-payment' ); ?></option>
				<option value="CREDITACCOUNT"><?php echo __( 'Part Payment', 'woocommerce-gateway-payex-payment' ); ?></option>
			</select>
			<div class="clear"></div>
		<?php endif; ?>

		<?php if ( $this->checkout_field !== 'yes' ): ?>
			<label for="social-security-number"><?php echo __( 'Social Security Number:', 'woocommerce-gateway-payex-payment' ); ?></label>
			<input type="text" name="social-security-number" id="social-security-number" value="" autocomplete="off">
		<?php endif; ?>

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
		if ( empty( $_POST['billing_country'] ) ) {
			wc_add_notice( __( 'Please specify country.', 'woocommerce-gateway-payex-payment' ), 'error' );
		}

		if ( ! in_array( mb_strtoupper( $_POST['billing_country'], 'UTF-8' ) , array('SE', 'NO', 'FI') ) ) {
			wc_add_notice( __( 'This country is not supported by the payment system.', 'woocommerce-gateway-payex-payment' ), 'error' );
		}

		// Validate phone number
		if ( empty( $_POST['billing_phone'] ) ) {
			wc_add_notice( __( 'Phone number required.', 'woocommerce-gateway-payex-payment' ), 'error' );
		}

		if ( ( $this->checkout_field !== 'yes' && empty( $_POST['social-security-number'] ) ) ||
		     ( $this->checkout_field === 'yes' && empty( $_POST['payex_ssn'] ) )
		) {
			wc_add_notice( __( 'Please enter your Social Security Number and confirm your order.', 'woocommerce-gateway-payex-payment' ), 'error' );
		}
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
        $items = $this->get_order_items( $order );
        $amount = array_sum( array_column( $items, 'price_with_tax' ) );

		$customer_id = (int) $order->get_user_id();
		$currency    = $order->get_currency();
		$ssn         = $this->checkout_field !== 'yes' ? wc_clean( $_POST['social-security-number'] ) : $_POST['payex_ssn'];

		// Selected Payment Mode
		if ( $this->mode === 'SELECT' ) {
			$this->mode = wc_clean( $_POST['factoring-menu'] );
		}

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Call PxOrder.Initialize8
		$params = array(
			'accountNumber'     => '',
			'purchaseOperation' => 'AUTHORIZATION',
			'price'             => round( $amount * 100 ),
			'priceArgList'      => '',
			'currency'          => $currency,
			'vat'               => round( self::get_items_vat( $items ) * 100 ),
			'orderID'           => $order->get_id(),
			'productNumber'     => $customer_id, // Customer Id
			'description'       => $this->description,
			'clientIPAddress'   => $_SERVER['REMOTE_ADDR'],
			'clientIdentifier'  => '',
			'additionalValues'  => $this->get_additional_values( array(), $order ),
			'externalID'        => '',
			'returnUrl'         => 'http://localhost.no/return',
			'view'              => 'FINANCING',
			'agreementRef'      => '',
			'cancelUrl'         => 'http://localhost.no/cancel',
			'clientLanguage'    => $this->language
		);
		$result = $this->getPx()->Initialize8( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PxOrder.Initialize8:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
			wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

			return;
		}

		$orderRef = $result['orderRef'];

		// Perform Payment
		switch ($this->mode) {
			case 'FINANCING':
				// Call PxOrder.PurchaseFinancingInvoice
				$params = array(
					'accountNumber' => '',
					'orderRef' => $orderRef,
					'socialSecurityNumber' => $ssn,
					'legalName' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'streetAddress' => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
					'coAddress' => '',
					'zipCode' => $order->get_billing_postcode(),
					'city' => $order->get_billing_city(),
					'countryCode' => $order->get_billing_country(),
					'paymentMethod' => 'PXFINANCINGINVOICE' . mb_strtoupper( $order->get_billing_country(), 'UTF-8' ),
					'email' => $order->get_billing_email(),
					'msisdn' => ( substr( $order->get_billing_phone(), 0, 1 ) === '+' ) ? $order->get_billing_phone() : '+' . $order->get_billing_phone(),
					'ipAddress' => $_SERVER['REMOTE_ADDR']
				);
				$result = $this->getPx()->PurchaseFinancingInvoice($params);
				break;
			case 'CREDITACCOUNT':
				// Call PxOrder.PurchaseCreditAccount
				$params = array(
					'accountNumber' => '',
					'orderRef' => $orderRef,
					'socialSecurityNumber' => $ssn,
					'legalName' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'streetAddress' => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
					'coAddress' => '',
					'zipCode' => $order->get_billing_postcode(),
					'city' => $order->get_billing_city(),
					'countryCode' => $order->get_billing_country(),
					'paymentMethod' => 'PXCREDITACCOUNT' . mb_strtoupper( $order->get_billing_country(), 'UTF-8' ),
					'email' => $order->get_billing_email(),
					'msisdn' => ( substr( $order->get_billing_phone(), 0, 1 ) === '+' ) ? $order->get_billing_phone() : '+' . $order->get_billing_phone(),
					'ipAddress' => $_SERVER['REMOTE_ADDR']
				);
				$result = $this->getPx()->PurchaseCreditAccount($params);
				break;
			default:
				$order->update_status( 'failed', __( 'Invalid payment mode', 'woocommerce-gateway-payex-payment' ) );
				wc_add_notice( __( 'Invalid payment mode', 'woocommerce-gateway-payex-payment' ), 'error' );
				return;
		}

		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PurchaseFinancingInvoice / PxOrder.PurchaseInvoiceSale / PurchasePartPaymentSale:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			if ( preg_match( '/\bInvalid parameter:msisdn\b/i', $result['description'] ) ) {
				wc_add_notice( __( 'Phone number not recognized, please use +countrycodenumber  ex. +467xxxxxxxxxx', 'woocommerce-gateway-payex-payment' ), 'error' );

				return;
			}

			$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
			wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

			return;
		}

		// Save Transaction
		update_post_meta( $order->get_id(), '_transaction_id', $result['transactionNumber'] );
		update_post_meta( $order->get_id(), '_payex_transaction_status', $result['transactionStatus'] );

		/* Transaction statuses:
		0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
		$transaction_status = (int) $result['transactionStatus'];
		switch ( $transaction_status ) {
			case 0:
			case 6:
				$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				$order->payment_complete( $result['transactionNumber'] );
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			case 1:
				$order_stock_reduced = $this->is_wc3() ? $order->get_meta( '_order_stock_reduced', true ) : get_post_meta( $order_id, '_order_stock_reduced', true );
				if ( ! $order_stock_reduced ) {
					$this->is_wc3() ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();
				}

				$order->update_status( 'on-hold', sprintf( __( 'Transaction is pending. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			case 3:
				$order_stock_reduced = $this->is_wc3() ? $order->get_meta( '_order_stock_reduced', true ) : get_post_meta( $order_id, '_order_stock_reduced', true );
				if ( ! $order_stock_reduced ) {
					$this->is_wc3() ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();
				}

				$order->update_status( 'on-hold', sprintf( __( 'Transaction authorized. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			case 4:
				// Cancel
				$order->update_status( 'cancelled' );
				wc_add_notice( __( 'Order canceled.', 'woocommerce-gateway-payex-payment' ), 'error' );
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
	 * Generate Invoice Print XML
	 *
	 * @param $order
	 *
	 * @return mixed
	 */
	public function getInvoiceExtraPrintBlocksXML( &$order ) {
		$dom           = new DOMDocument( '1.0', 'utf-8' );
		$OnlineInvoice = $dom->createElement( 'OnlineInvoice' );
		$dom->appendChild( $OnlineInvoice );
		$OnlineInvoice->setAttributeNS( 'http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance' );
		$OnlineInvoice->setAttributeNS( 'http://www.w3.org/2001/XMLSchema-instance', 'xsd', 'http://www.w3.org/2001/XMLSchema' );

		$OrderLines = $dom->createElement( 'OrderLines' );
		$OnlineInvoice->appendChild( $OrderLines );

		$items = $this->get_order_items( $order );
		foreach ( $items as $order_item ) {
			$OrderLine = $dom->createElement( 'OrderLine' );
			$OrderLine->appendChild( $dom->createElement( 'Product', str_replace( '&amp;', '-', htmlentities( $order_item['name'] ) ) ) );
			$OrderLine->appendChild( $dom->createElement( 'Qty', $order_item['qty'] ) );
			$OrderLine->appendChild( $dom->createElement( 'UnitPrice', sprintf( "%.2f",$order_item['price_without_tax'] / $order_item['qty'] ) ) );
			$OrderLine->appendChild( $dom->createElement( 'VatRate', round( $order_item['tax_percent'] ) ) );
			$OrderLine->appendChild( $dom->createElement( 'VatAmount', $order_item['tax_price'] ) );
			$OrderLine->appendChild( $dom->createElement( 'Amount', $order_item['price_with_tax'] ) );
			$OrderLines->appendChild( $OrderLine );
		}

		return str_replace( "\n", '', html_entity_decode( str_replace( 'xsi:xsd', 'xmlns:xsd', $dom->saveXML() ), ENT_COMPAT|ENT_XHTML, 'UTF-8' ) );
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

		// Get Additional Values
		$additionalValues = 'FINANCINGINVOICE_ORDERLINES=' . urlencode( $this->getInvoiceExtraPrintBlocksXML( $order ) );

		// Call PxOrder.Capture5
		$params = array(
			'accountNumber'     => '',
			'transactionNumber' => $order->get_transaction_id(),
			'amount'            => round( 100 * $amount ),
			'orderId'           => $order->get_id(),
			'vatAmount'         => 0,
			'additionalValues'  => $additionalValues
		);
		$result = $this->getPx()->Capture5( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PxOrder.Capture5:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$message = sprintf( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $result['errorCode'] . ' (' . $result['description'] . ')' );
			throw new Exception( $message );
		}

		update_post_meta( $order->get_id(), '_payex_transaction_status', $result['transactionStatus'] );
		$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
		$order->payment_complete( $result['transactionNumber'] );
	}
}
