<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Payex_Swish extends WC_Gateway_Payex_Abstract {

	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'payex_swish';
		$this->has_fields   = true;
		$this->method_title = __( 'Swish (via PayEx)', 'woocommerce-gateway-payex-payment' );
		$this->icon         = apply_filters( 'woocommerce_payex_swish_icon', plugins_url( '/assets/images/swish.png', dirname( __FILE__ ) ) );
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
				'default'     => __( 'Swish (via PayEx)', 'woocommerce-gateway-payex-payment' )
			),
			'description'   => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => '',
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
		parent::payment_fields();

		echo sprintf( __( 'You will be redirected to <a target="_blank" href="%s">PayEx</a> website when you place an order.', 'woocommerce-gateway-payex-payment' ), 'http://www.payex.com' );
		?>
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
		//
	}

	/**
	 * Get phone number from WC Order
	 *
	 * @param $order
	 *
	 * @return string|null
	 */
	protected function get_phone_number( $order ) {
		$billing_country = $order->get_billing_country();
		$billing_phone = preg_replace('/[^0-9\+]/', '', $order->get_billing_phone());

		if ( ! preg_match('/^((00|\+)([1-9][1-9])|0([1-9]))(\d*)/', $billing_phone, $matches) ) {
			return null;
		}
		switch ($billing_country) {
			case 'SE':
				$country_code = '46';
				break;
			default:
				$country_code = '46';
		}


		if ( isset( $matches[3] ) && isset( $matches[5]) ) { // country code present
			$billing_phone = $matches[3] . $matches[5];
		}
		if ( isset( $matches[4]) && isset( $matches[5]) ) { // no country code present. removing leading 0
			$billing_phone = $country_code . $matches[4] . $matches[5];
		}

		return strlen($billing_phone) > 7 && strlen($billing_phone) < 16 ? $billing_phone : null;
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

		// Additional Values
		$additional = array();
		if ( $this->responsive === 'yes' ) {
			$additional[] = 'USECSS=RESPONSIVEDESIGN';
		}
		if ( $phone = $this->get_phone_number( $order ) ) {
			$additional[] = 'MSISDN=' . $phone;
		}

		$returnUrl = html_entity_decode( $this->get_return_url( $order ) );
		$cancelUrl = html_entity_decode( $order->get_cancel_order_url() );

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Call PxOrder.Initialize8
		$params = array(
			'accountNumber'     => '',
			'purchaseOperation' => 'SALE',
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
			'returnUrl'         => $returnUrl,
			'view'              => 'SWISH',
			'agreementRef'      => '',
			'cancelUrl'         => $cancelUrl,
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
		$redirectUrl = $result['redirectUrl'];

		if ( $this->checkout_info === 'yes' ) {
			// add Order Lines
			$items = $this->get_order_items( $order );
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
		if ( empty( $_GET['key'] ) ) {
			return;
		}

		// Validate Payment Method
		$order = $this->get_order_by_order_key( $_GET['key'] );
		$payment_method = $this->is_wc3() ? $order->get_payment_method() : $order->payment_method;
		if ( $order && $payment_method !== $this->id ) {
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

		$order->add_order_note( sprintf( __( 'Customer returned from PayEx. Order reference: %s', 'woocommerce-gateway-payex-payment' ), $_GET['orderRef'] ) );

		// Save Transaction
		update_post_meta( $order->get_id(), '_transaction_id', $result['transactionNumber'] );
		update_post_meta( $order->get_id(), '_payex_transaction_status', $result['transactionStatus'] );

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
				$order->update_status( 'on-hold', sprintf( __( 'Transaction is pending. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				WC()->cart->empty_cart();
				break;
			case 3:
				$order->update_status( 'on-hold', sprintf( __( 'Transaction authorized. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				WC()->cart->empty_cart();
				break;
			case 4:
				// Cancel
				$order->update_status( 'cancelled' );
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
