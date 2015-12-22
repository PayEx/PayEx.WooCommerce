<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Payex_MasterPass extends WC_Gateway_Payex_Abstract {
	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'payex_masterpass';
		$this->has_fields   = false;
		$this->method_title = __( 'PayEx MasterPass', 'woocommerce-gateway-payex-payment' );
		$this->icon         = apply_filters( 'woocommerce_payex_masterpass_icon', plugins_url( '/assets/images/masterpass.png', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled                    = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title                      = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->account_no                 = isset( $this->settings['account_no'] ) ? $this->settings['account_no'] : '';
		$this->encrypted_key              = isset( $this->settings['encrypted_key'] ) ? $this->settings['encrypted_key'] : '';
		$this->purchase_operation         = isset( $this->settings['purchase_operation'] ) ? $this->settings['purchase_operation'] : 'SALE';
		$this->description                = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->language                   = isset( $this->settings['language'] ) ? $this->settings['language'] : 'en-US';
		$this->testmode                   = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'yes';
		$this->display_pp_button          = isset( $this->settings['display_pp_button'] ) ? $this->settings['display_pp_button'] : 'no';
		$this->display_cart_widget_button = isset( $this->settings['display_cart_widget_button'] ) ? $this->settings['display_cart_widget_button'] : 'no';
		$this->debug                      = isset( $this->settings['debug'] ) ? $this->settings['debug'] : 'no';

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

		// MasterPass
		add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ) );
		add_filter( 'default_checkout_country', array( $this, 'maybe_change_default_checkout_country' ) );
		add_filter( 'default_checkout_postcode', array( $this, 'maybe_change_default_checkout_postcode' ) );
		add_filter( 'woocommerce_form_field_args', array( $this, 'override_checkout_fields' ), 10, 3 );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 1 );
		add_filter( 'woocommerce_order_button_text', array( $this, 'set_order_button_text' ), 1 );

		// Clear sessions
		add_action( 'woocommerce_thankyou', array( $this, 'clear_session_values' ) );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'             => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'title'               => array(
				'title'       => __( 'Title', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( 'MasterPass', 'woocommerce-gateway-payex-payment' )
			),
			'description'         => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => __( 'Buy with MasterPass', 'woocommerce-gateway-payex-payment' ),
			),
			'account_no'          => array(
				'title'       => __( 'Account Number', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'Account Number of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'encrypted_key'       => array(
				'title'       => __( 'Encryption Key', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'PayEx Encryption Key of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ),
				'default'     => ''
			),
			'purchase_operation'  => array(
				'title'       => __( 'Purchase Operation', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => array( 'AUTHORIZATION' => 'Authorization', 'SALE' => 'Sale' ),
				'description' => __( 'If used AUTHORIZATION then amount will be authorized (2-phased transaction). If used SALE then amount will be captured (1-phased transaction).', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'SALE'
			),
			'language'            => array(
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
			'testmode'            => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayEx Test Mode', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'display_pp_button'   => array(
				'title'   => __( 'Display on product page', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display MasterPass buy button on product pages.', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'display_cart_button' => array(
				'title'   => __( 'Display in cart widget', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display MasterPass buy button in cart widget.', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'debug'               => array(
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
		echo sprintf( __( 'You will be redirected to <a target="_blank" href="%s" rel="external">MasterPass</a> website when you place an order. ', 'woocommerce-gateway-payex-payment' ), '' );
		?>
		<a target="_blank" href="<?php echo self::get_read_more_url(); ?>" rel="external"><?php _e( 'Read more', 'woocommerce-gateway-payex-payment' ); ?></a>
		<div class="clear"></div>
		<?php
		if ( WC()->session->get( 'mp_payment_selected' ) ) {
			echo '<p><a href="' . add_query_arg( 'view-pm', 'all', get_the_permalink() ) . '">' . __( 'Select other payment method', 'woocommerce-gateway-payex-payment' ) . '</a></p>';
		}
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
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// When Order amount is empty
		if ( $order->get_total() == 0 ) {
			$order->payment_complete();
			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		$orderRef = WC()->session->get( 'mp_order_reference' );
		if ( ! $orderRef ) {
			// Checkout using standard way
			// Call PxOrder.Initialize8
			$params = array(
				'accountNumber'     => '',
				'purchaseOperation' => $this->purchase_operation,
				'price'             => round( $order->order_total * 100 ),
				'priceArgList'      => '',
				'currency'          => $order->order_currency,
				'vat'               => 0,
				'orderID'           => $order->id,
				'productNumber'     => (int) $order->customer_user, // Customer Id
				'description'       => $this->description,
				'clientIPAddress'   => $_SERVER['REMOTE_ADDR'],
				'clientIdentifier'  => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
				'additionalValues'  => 'USEMASTERPASS=1&RESPONSIVE=1&SHOPPINGCARTXML=' . urlencode( $this->getShoppingCartXML( $order ) ),
				'externalID'        => '',
				'returnUrl'         => html_entity_decode( $this->get_return_url( $order ) ),
				'view'              => 'CREDITCARD',
				'agreementRef'      => '',
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

			return array(
				'result'   => 'success',
				'redirect' => $result['redirectUrl']
			);
		}

		// Call PxOrder.FinalizeTransaction
		$params = array(
			'accountNumber'   => '',
			'orderRef'        => $orderRef,
			'amount'          => round( $order->order_total * 100 ),
			'vatAmount'       => 0,
			'clientIPAddress' => $_SERVER['REMOTE_ADDR']
		);
		$result = $this->getPx()->FinalizeTransaction( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			// Check order has already been purchased
			if ( $result['code'] === 'Order_AlreadyPerformed' ) {
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}

			$this->log( 'PxOrder.FinalizeTransaction:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
			$this->add_message( $this->getVerboseErrorMessage( $result ), 'error' );

			return false;
		}

		// Save Transaction
		update_post_meta( $order->id, '_transaction_id', $result['transactionNumber'] );
		update_post_meta( $order->id, '_payex_transaction_status', $result['transactionStatus'] );

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

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order )
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
		if ( $order && $order->payment_method !== $this->id ) {
			return;
		}

		// Check OrderRef is exists
		if ( empty( $_GET['orderRef'] ) ) {
			return;
		}

		// Check transaction is already success
		$transaction_status = get_post_meta( $order->id, '_payex_transaction_status', true );
		if ( in_array( $transaction_status, array( '0', '3', '6' ) ) ) {
			return;
		}

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Call PxOrder.GetApprovedDeliveryAddress
		$params = array(
			'accountNumber' => '',
			'orderRef'      => $_GET['orderRef']
		);
		$result = $this->getPx()->GetApprovedDeliveryAddress( $params );
		if ( $result['code'] === 'OK' && $result['description'] === 'OK' && $result['errorCode'] === 'OK' ) {
			// Save Delivery address in order notes
			$address_fields = array(
				'First name'  => 'firstName',
				'Last name'   => 'lastName',
				'Country'     => 'country',
				'City'        => 'city',
				'Postal Code' => 'postalCode',
				'Address 1'   => 'address1',
				'Address 2'   => 'address2',
				'Address 3'   => 'address3',
				'Phone'       => 'phone',
				'E-Mail'      => 'eMail'
			);

			$address = '';
			foreach ( $address_fields as $key => $value ) {
				if ( ! empty( $result[ $value ] ) ) {
					$address .= $key . ': ' . $result[ $value ] . "\n";
				}
			}
			$order->add_order_note( sprintf( "MasterPass return delivery info: \n%s", $address ) );
		}

		// Call PxOrder.FinalizeTransaction
		$params = array(
			'accountNumber'   => '',
			'orderRef'        => $_GET['orderRef'],
			'amount'          => round( $order->order_total * 100 ),
			'vatAmount'       => 0,
			'clientIPAddress' => $_SERVER['REMOTE_ADDR']
		);
		$result = $this->getPx()->FinalizeTransaction( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			// Check order has already been purchased
			if ( $result['code'] === 'Order_AlreadyPerformed' ) {
				return false;
			}

			$this->log( 'PxOrder.FinalizeTransaction:' . $result['errorCode'] . '(' . $result['description'] . ')' );
			$order->update_status( 'failed', $this->getVerboseErrorMessage( $result ) );
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
	 * Init Checkout
	 */
	public function checkout_init() {
		// Get Delivery Address info from MasterPass
		$orderRef = WC()->session->get( 'mp_order_reference' );
		if ( $orderRef ) {
			// Init PayEx
			$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

			// Call PxOrder.GetApprovedDeliveryAddress
			$params = array(
				'accountNumber' => '',
				'orderRef'      => $orderRef
			);
			$result = $this->getPx()->GetApprovedDeliveryAddress( $params );
			if ( $result['code'] === 'OK' && $result['description'] === 'OK' && $result['errorCode'] === 'OK' ) {
				WC()->session->set( 'mp_delivery_address', $result );
			}
		}

		// Check to make visible all payment gateways
		if ( ! empty( $_GET['view-pm'] ) && $_GET['view-pm'] === 'all' ) {
			WC()->session->__unset( 'mp_payment_selected' );
		}
	}

	/**
	 * Set Country as Default value
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	public function maybe_change_default_checkout_country( $value ) {
		$delivery_address = WC()->session->get( 'mp_delivery_address' );
		if ( $delivery_address ) {
			$value = $delivery_address['country'];
		}

		return $value;
	}

	/**
	 * Set PostCode as Default value
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	public function maybe_change_default_checkout_postcode( $value ) {
		$delivery_address = WC()->session->get( 'mp_delivery_address' );
		if ( $delivery_address ) {
			$value = $delivery_address['postalCode'];
		}

		return $value;
	}

	/**
	 * Override checkout fields with address data received from MasterPass
	 *
	 * @param $args
	 * @param $key
	 * @param $value
	 *
	 * @return mixed
	 */
	public function override_checkout_fields( $args, $key, $value ) {
		$delivery_address = WC()->session->get( 'mp_delivery_address' );
		if ( ! $delivery_address ) {
			return $args;
		}

		switch ( $key ) {
			case 'billing_first_name':
			case 'shipping_first_name':
				$args['default'] = $delivery_address['firstName'];
				break;
			case 'billing_last_name':
			case 'shipping_last_name':
				$args['default'] = $delivery_address['lastName'];
				break;
			case 'billing_postcode':
			case 'shipping_postcode':
				$args['default'] = $delivery_address['postalCode'];
				break;
			case 'billing_address_1':
			case 'shipping_address_1':
				$args['default'] = $delivery_address['address1'];
				break;
			case 'billing_address_2':
			case 'shipping_address_2':
				$args['default'] = trim( $delivery_address['address2'] . ' ' . $delivery_address['address3'] );
				break;
			case 'billing_city':
			case 'shipping_city':
				$args['default'] = $delivery_address['city'];
				break;
			case 'billing_country':
			case 'shipping_country':
				$args['default'] = $delivery_address['country'];
				break;
			case 'billing_email':
				$args['default'] = $delivery_address['eMail'];
				break;
			case 'billing_phone':
				$args['default'] = $delivery_address['phone'];
				break;
		}

		return $args;
	}

	/**
	 * Unset all payment methods except PayEx MasterPass
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function filter_gateways( $gateways ) {
		if ( is_admin() ) {
			return $gateways;
		}

		if ( ! WC()->session->get( 'mp_payment_selected' ) ) {
			return $gateways;
		}

		$delivery_address = WC()->session->get( 'mp_delivery_address' );
		if ( ! $delivery_address ) {
			return $gateways;
		}

		foreach ( $gateways as $gateway ) {
			if ( $gateway->id !== $this->id ) {
				unset( $gateways[ $gateway->id ] );
			}
		}

		return $gateways;
	}

	/**
	 * Change "Place order" text
	 *
	 * @param $order_button_text
	 *
	 * @return string|void
	 */
	public function set_order_button_text( $order_button_text ) {
		$delivery_address = WC()->session->get( 'mp_delivery_address' );
		if ( ! $delivery_address ) {
			return $order_button_text;
		}

		return __( 'Proceed to MasterPass', 'woocommerce-gateway-payex-payment' );
	}


	/**
	 * Clear Session
	 */
	public function clear_session_values() {
		WC()->session->__unset( 'mp_order_reference' );
		WC()->session->__unset( 'mp_delivery_address' );
		WC()->session->__unset( 'mp_payment_selected' );
	}

	/**
	 * Get "Read More" link for MasterPass
	 * @return mixed|void
	 */
	public static function get_read_more_url() {
		$iso_code     = explode( '_', get_locale() );
		$country_code = strtoupper( $iso_code[0] );

		// See https://developer.mastercard.com/portal/download/attachments/48234577/Masterpass+Digital+Assets+-+Buttons+Learn+More+Links+v6+09+19+2014.pdf
		$links = array(
			'US' => 'https://www.mastercard.com/mc_us/wallet/learnmore/en/',
			'SE' => 'https://www.mastercard.com/mc_us/wallet/learnmore/se/',
			'NO' => 'https://www.mastercard.com/mc_us/wallet/learnmore/en/NO/',
			'DK' => 'https://www.mastercard.com/mc_us/wallet/learnmore/en/DK/',
			'ES' => 'https://www.mastercard.com/mc_us/wallet/learnmore/en/ES/',
			'DE' => 'https://www.mastercard.com/mc_us/wallet/learnmore/de/DE/',
			'FR' => 'https://www.mastercard.com/mc_us/wallet/learnmore/fr/FR/',
			'PL' => 'https://www.mastercard.com/mc_us/wallet/learnmore/pl/PL/',
			'CZ' => 'https://www.mastercard.com/mc_us/wallet/learnmore/cs/CZ/'
		);

		$read_more_url = isset( $links[ $country_code ] ) ? $links[ $country_code ] : $links['US'];

		return apply_filters( 'woocommerce_payex_masterpass_read_more_url', $read_more_url, $country_code );
	}

	/**
	 * Display MasterPass payment button on cart page
	 * @throws Exception
	 */
	public function masterpass_button_action() {
		if ( $this->enabled === 'no' ) {
			return;
		}

		define( 'WOOCOMMERCE_CHECKOUT', true );

		// Calculate totals
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$order_id = WC()->checkout()->create_order();
		if ( is_wp_error( $order_id ) ) {
			throw new Exception( $order_id->get_error_message() );
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$payment_method     = $available_gateways[ $this->id ];

		$order = wc_get_order( $order_id );
		$order->set_payment_method( $payment_method );

		do_action( 'woocommerce_checkout_order_processed', $order_id, array() );

		// Process payment
		if ( WC()->cart->needs_payment() ) {

			// Store Order ID in session so it can be re-used after payment failure
			WC()->session->order_awaiting_payment = $order_id;

			// Init PayEx
			$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

			// Call PxOrder.Initialize8
			$params = array(
				'accountNumber'     => '',
				'purchaseOperation' => $this->purchase_operation,
				'price'             => round( $order->order_total * 100 ),
				'priceArgList'      => '',
				'currency'          => $order->order_currency,
				'vat'               => 0,
				'orderID'           => $order->id,
				'productNumber'     => (int) $order->customer_user, // Customer Id
				'description'       => $this->description,
				'clientIPAddress'   => $_SERVER['REMOTE_ADDR'],
				'clientIdentifier'  => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
				'additionalValues'  => 'USEMASTERPASS=1&RESPONSIVE=1&SHOPPINGCARTXML=' . urlencode( $this->getShoppingCartXML( $order ) ),
				'externalID'        => '',
				'returnUrl'         => WC()->cart->get_checkout_url(),
				'view'              => 'CREDITCARD',
				'agreementRef'      => '',
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

			// Save MasterPass Order Reference
			WC()->session->set( 'mp_order_reference', $orderRef );
			WC()->session->set( 'mp_payment_selected', true );

			wp_redirect( $redirectUrl );
			exit();
		}
	}

	/**
	 * Generate Shopping Cart XML
	 *
	 * @param $order
	 *
	 * @return mixed
	 */
	public function getShoppingCartXML( &$order ) {
		$dom          = new DOMDocument( '1.0', 'utf-8' );
		$ShoppingCart = $dom->createElement( 'ShoppingCart' );
		$dom->appendChild( $ShoppingCart );

		$ShoppingCart->appendChild( $dom->createElement( 'CurrencyCode', $order->order_currency ) );
		$ShoppingCart->appendChild( $dom->createElement( 'Subtotal', (int) ( 100 * $order->order_total ) ) );

		// Add Order Lines
		foreach ( $order->get_items() as $order_item ) {
			$price = $order->get_line_subtotal( $order_item, false, false );

			$ShoppingCartItem = $dom->createElement( 'ShoppingCartItem' );
			$ShoppingCartItem->appendChild( $dom->createElement( 'Description', $order_item['name'] ) );
			$ShoppingCartItem->appendChild( $dom->createElement( 'Quantity', $order_item['qty'] ) );
			$ShoppingCartItem->appendChild( $dom->createElement( 'Value', (int) 100 * ( $price ) ) );
			$ShoppingCartItem->appendChild( $dom->createElement( 'ImageURL', '' ) );
			$ShoppingCart->appendChild( $ShoppingCartItem );
		}

		// Add discount line
		if ( $order->get_total_discount( false ) > 0 ) {
			$ShoppingCartItem = $dom->createElement( 'ShoppingCartItem' );
			$ShoppingCartItem->appendChild( $dom->createElement( 'Description', __( 'Discount', 'woocommerce-gateway-payex-payment' ) ) );
			$ShoppingCartItem->appendChild( $dom->createElement( 'Quantity', 1 ) );
			$ShoppingCartItem->appendChild( $dom->createElement( 'Value', (int) ( - 100 * $order->get_total_discount( false ) ) ) );
			$ShoppingCartItem->appendChild( $dom->createElement( 'ImageURL', '' ) );
			$ShoppingCart->appendChild( $ShoppingCartItem );
		}

		// Add fee lines
		foreach ( $order->get_fees() as $fee ) {
			$ShoppingCartItem = $dom->createElement( 'ShoppingCartItem' );
			$ShoppingCartItem->appendChild( $dom->createElement( 'Description', $fee['name'] ) );
			$ShoppingCartItem->appendChild( $dom->createElement( 'Quantity', 1 ) );
			$ShoppingCartItem->appendChild( $dom->createElement( 'Value', (int) ( 100 * $fee['line_total'] ) ) );
			$ShoppingCartItem->appendChild( $dom->createElement( 'ImageURL', '' ) );
			$ShoppingCart->appendChild( $ShoppingCartItem );
		}

		return str_replace( "\n", '', $dom->saveXML() );
	}

}
