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
		'SAMPO'   => 'Danske Bank',
		'AKTIA'   => 'Aktia',
		'OP'      => 'OP',
		'OMASP'   => 'Oma Säästöpankki',
		'NB:FI'   => 'Nordea Finland',
		'SHB:FI'  => 'Handelsbanken Finland',
		'SPANKKI' => 'S-Pankki',
		'TAPIOLA' => 'TAPIOLA',
		'AALAND'  => 'Ålandsbanken',
		'POP'     => 'POP Pankki',
		'SP'      => 'Säästöpankki',
	);

	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'payex_bankdebit';
		$this->has_fields   = true;
		$this->method_title = __( 'PayEx Bank Debit', 'woocommerce-gateway-payex-payment' );
		$this->icon         = apply_filters( 'woocommerce_payex_bankdebit_icon', plugins_url( '/assets/images/payex.gif', dirname( __FILE__ ) ) );
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
		parent::payment_fields();
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
		if ( empty( $_POST['bank_id'] ) || ! isset( self::$available_banks[ $_POST['bank_id'] ] ) ) {
			wc_add_notice( __( 'Wrong bank.', 'woocommerce-gateway-payex-payment' ), 'error' );
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
        if ($this->checkout_info === 'yes') {
            $amount = array_sum( array_column( $items, 'price_with_tax' ) );
        } else {
            $amount = $order->get_total();
        }

        $bank_id     = ! empty( $_POST['bank_id'] ) ? wc_clean( $_POST['bank_id'] ) : 'NB';

		// Additional Values
		$additional  = array();
		if ($this->responsive === 'yes') {
			$additional[] = 'USECSS=RESPONSIVEDESIGN';
		}

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
			'view'              => 'DIRECTDEBIT',
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
			wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

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
