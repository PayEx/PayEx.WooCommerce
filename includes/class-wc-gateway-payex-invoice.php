<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Payex_InvoiceLedgerService extends WC_Gateway_Payex_Abstract {
	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'payex_invoice';
		$this->has_fields   = true;
		$this->method_title = __( 'PayEx Invoice (Ledger Service)', 'woocommerce-gateway-payex-payment' );
		$this->icon         = apply_filters( 'woocommerce_payex_invoice_icon', plugins_url( '/assets/images/payex.gif', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
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
		$this->description        = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->language           = isset( $this->settings['language'] ) ? $this->settings['language'] : 'en-US';
		$this->distribution       = isset( $this->settings['distribution'] ) ? $this->settings['distribution'] : '1';
		$this->invoicetext        = isset( $this->settings['invoicetext'] ) ? $this->settings['invoicetext'] : 'Invoice text';
		$this->invoiceduedays     = isset( $this->settings['invoiceduedays'] ) ? $this->settings['invoiceduedays'] : '15';
		$this->credit_check       = isset( $this->settings['credit_check'] ) ? $this->settings['credit_check'] : 'yes';
		$this->allow_unapproved   = isset( $this->settings['allow_unapproved'] ) ? $this->settings['allow_unapproved'] : 'no';
		$this->testmode           = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'yes';
		$this->debug              = isset( $this->settings['debug'] ) ? $this->settings['debug'] : 'no';
		$this->fee                = isset( $this->settings['fee'] ) ? (float) $this->settings['fee'] : 0;
		$this->fee_is_taxable     = isset( $this->settings['fee_is_taxable'] ) ? $this->settings['fee_is_taxable'] : 'no';
		$this->fee_tax_class      = isset( $this->settings['fee_tax_class'] ) ? $this->settings['fee_tax_class'] : 'standard';

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
			wp_enqueue_script( 'wc-gateway-payex-invoice-fee', plugins_url( '/assets/js/fee.js', dirname( __FILE__ ) ), array( 'wc-checkout' ), false, true );
		}
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
				'default'     => __( 'PayEx Invoice', 'woocommerce-gateway-payex-payment' )
			),
			'description'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payex-payment' ),
				'default'     => '',
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
			'distribution'       => array(
				'title'       => __( 'Invoice distribution', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => array(
					'1'  => 'Paper by mail',
					'11' => 'PDF by e-mail',
				),
				'description' => __( 'Invoice distribution', 'woocommerce-gateway-payex-payment' ),
				'default'     => '11'
			),
			'invoicetext'        => array(
				'title'       => __( 'Invoice Text', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'text',
				'description' => __( 'Invoice Text', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'Invoice text'
			),
			'invoiceduedays'     => array(
				'title'             => __( 'Invoice due days', 'woocommerce-gateway-payex-payment' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any'
				),
				'description'       => __( 'Invoice due days', 'woocommerce-gateway-payex-payment' ),
				'default'           => '15'
			),
			'credit_check'   => array(
				'title'   => __( 'Credit Check', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Credit Check', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'allow_unapproved'   => array(
				'title'   => __( 'Allow unapproved by CreditCheck', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Allow unapproved by CreditCheck', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'testmode'           => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayEx Test Mode', 'woocommerce-gateway-payex-payment' ),
				'default' => 'yes'
			),
			'debug'              => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'fee'                => array(
				'title'             => __( 'Fee', 'woocommerce-gateway-payex-payment' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any'
				),
				'description'       => __( 'Factoring fee. Set 0 to disable.', 'woocommerce-gateway-payex-payment' ),
				'default'           => '0'
			),
			'fee_is_taxable'     => array(
				'title'   => __( 'Fee is Taxable', 'woocommerce-gateway-payex-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Fee is Taxable', 'woocommerce-gateway-payex-payment' ),
				'default' => 'no'
			),
			'fee_tax_class'      => array(
				'title'       => __( 'Tax class of Fee', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'select',
				'options'     => $this->getTaxClasses(),
				'description' => __( 'Tax class of fee.', 'woocommerce-gateway-payex-payment' ),
				'default'     => 'standard'
			),
		);
	}

	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {
		parent::payment_fields();
		?>
		<div class="invoice-type-select">
			<label for="pxinvoice_method:private">
				<?php echo __( 'Private', 'woocommerce-gateway-payex-payment' ); ?>
				<input type="radio" name="pxinvoice_method" id="pxinvoice_method:private" value="private" checked />
			</label>

			<div id="pxinvoice_private">
				<p class="form-row form-row-wide">
					<label for="socialSecurityNumber">
						<?php echo __( 'Social Security Number', 'woocommerce-gateway-payex-payment' ); ?>
						<abbr class="required">*</abbr>
					</label>
					<input type="text" class="input-text required-entry" name="socialSecurityNumber" id="socialSecurityNumber"
					       placeholder="" value="">
				</p>
			</div>
			<br />
			<label for="pxinvoice_method:corporate">
				<?php echo __( 'Corporate', 'woocommerce-gateway-payex-payment' ); ?>
				<input type="radio" name="pxinvoice_method" id="pxinvoice_method:corporate" value="corporate" />
			</label>

			<div id="pxinvoice_corporate" style="display: none;">
				<p class="form-row form-row-wide">
					<label for="organizationNumber">
						<?php echo __( 'Organization Number', 'woocommerce-gateway-payex-payment' ); ?>
						<abbr class="required">*</abbr>
					</label>
					<input type="text" class="input-text required-entry" name="organizationNumber" id="organizationNumber"
					       placeholder="" value="">
				</p>
			</div>
		</div>
		<script type="text/javascript">
			(function ($) {
				$(document).ready(function () {
					$("input[name='pxinvoice_method']").on('click', function () {
						var pxinvoice_method = $("input[name='pxinvoice_method']:checked").val();
						switch (pxinvoice_method) {
							case 'private':
								$('#pxinvoice_private').show();
								$('#pxinvoice_corporate').hide();
								break;
							case 'corporate':
								$('#pxinvoice_corporate').show();
								$('#pxinvoice_private').hide();
								break;
							default :
							//
						}
					});
				});
			})(jQuery);
		</script>

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
		if ( empty( $_POST['pxinvoice_method'] ) ) {
			wc_add_notice( __( 'Please select invoice method.', 'woocommerce-gateway-payex-payment' ), 'error' );

			return;
		}

		// Credit Check is disabled
		if ( $this->credit_check !== 'yes' ) {
			return;
		}

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Verify
		switch ( $_POST['pxinvoice_method'] ) {
			case 'private':
				if ( empty( $_POST['socialSecurityNumber'] ) ) {
					wc_add_notice( __( 'Please enter your Social Security Number and confirm your order.', 'woocommerce-gateway-payex-payment' ), 'error' );

					return;
				}

				// Call PxVerification.CreditCheckPrivate
				$params = array(
					'accountNumber'        => '',
					'countryCode'          => wc_clean( $_POST['billing_country'] ),
					'socialSecurityNumber' => wc_clean( $_POST['socialSecurityNumber'] ),
					'firstName'            => wc_clean( $_POST['billing_first_name'] ),
					'lastName'             => wc_clean( $_POST['billing_last_name'] ),
					'amount'               => round( WC()->cart->total * 100 ),
					'clientIPAddress'      => $_SERVER['REMOTE_ADDR']
				);
				$status = $this->getPx()->CreditCheckPrivate2( $params );
				if ( preg_match( '/\bInvalid parameter:SocialSecurityNumber\b/i', $status['description'] ) ) {
					wc_add_notice( __( 'Invalid Social Security Number', 'woocommerce-gateway-payex-payment' ), 'error' );

					return;
				}

				break;
			case 'corporate':
				if ( empty( $_POST['organizationNumber'] ) ) {
					wc_add_notice( __( 'Please enter your Organization Number and confirm your order.', 'woocommerce-gateway-payex-payment' ), 'error' );

					return;
				}

				// Call PxVerification.CreditCheckCorporate
				$params = array(
					'accountNumber'      => '',
					'countryCode'        => wc_clean( $_POST['billing_country'] ),
					'organizationNumber' => wc_clean( $_POST['organizationNumber'] ),
					'amount'             => round( WC()->cart->total * 100 )
				);
				$status = $this->getPx()->CreditCheckCorporate2( $params );
				if ( preg_match( '/\bInvalid parameter:OrganizationNumber\b/i', $status['description'] ) ) {
					wc_add_notice( __( 'Invalid Organization Number', 'woocommerce-gateway-payex-payment' ), 'error' );

					return;
				}

				break;
			default:
				$status = array();
		}

		// Check status
		if ( $status['code'] === 'OK' && $status['description'] === 'OK' && $status['errorCode'] === 'OK' ) {
			// Check if credit check went ok
			if ( $status['creditStatus'] === 'True' ) {
				WC()->session->payex_invoice_credit = $status;
			} elseif ( $this->allow_unapproved === 'yes' ) {
				// Allow unapproved
				WC()->session->payex_invoice_credit = $status;
			} else {
				// Declining payment
				wc_add_notice( __( 'Unfortunately PayEx did not grant you Invoice credit. Please try other means of payment', 'woocommerce-gateway-payex-payment' ), 'error' );
			}
		} else {
			wc_add_notice( $this->getVerboseErrorMessage( $status ), 'error' );
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

		$customer_id = (int) $order->get_user_id();
		$amount      = $order->get_total();

		// Init PayEx
		$this->getPx()->setEnvironment( $this->account_no, $this->encrypted_key, $this->testmode === 'yes' );

		// Call PxOrder.Initialize8
		$params = array(
			'accountNumber'     => '',
			'purchaseOperation' => $this->purchase_operation,
			'price'             => round( $amount * 100 ),
			'priceArgList'      => '',
			'currency'          => $order->get_currency(),
			'vat'               => 0,
			'orderID'           => $order->get_id(),
			'productNumber'     => $customer_id, // Customer Id
			'description'       => $this->description,
			'clientIPAddress'   => $_SERVER['REMOTE_ADDR'],
			'clientIdentifier'  => '',
			'additionalValues'  => $this->get_additional_values( array(), $order ),
			'externalID'        => '',
			'returnUrl'         => 'http://localhost.no/return',
			'view'              => 'INVOICE',
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

		// Credit Check is disabled
		if ( $this->credit_check === 'yes' ) {
			$credit_data = WC()->session->payex_invoice_credit;
		} else {
			$credit_data = array(
				'firstName' => $order->get_billing_first_name(),
				'lastName' => $order->get_billing_last_name(),
				'address' => trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()),
				'postCode' => $order->get_billing_postcode(),
				'city' => $order->get_billing_city(),
				'name' => $order->get_billing_company(),
				'creditCheckRef' => ''
			);
		}

		// Call Invoice Purchase
		$result      = array();
		switch ( $_POST['pxinvoice_method'] ) {
			case 'private':
				// Call PxOrder.PurchaseInvoicePrivate
				$params = array(
					'accountNumber'        => '',
					'orderRef'             => $orderRef,
					'customerRef'          => $customer_id,
					'customerName'         => trim( $credit_data['firstName'] . ' ' . $credit_data['lastName'] ),
					'streetAddress'        => $credit_data['address'],
					'coAddress'            => $credit_data['address'],
					'postalCode'           => $credit_data['postCode'],
					'city'                 => $credit_data['city'],
					'country'              => $order->get_billing_country(),
					'socialSecurityNumber' => wc_clean( $_POST['socialSecurityNumber'] ),
					'phoneNumber'          => '',
					'email'                => $order->get_billing_email(),
					'productCode'          => '0001',
					'creditcheckRef'       => $credit_data['creditCheckRef'],
					'mediaDistribution'    => $this->distribution,
					'invoiceText'          => $this->invoicetext,
					'invoiceDate'          => date( 'Y-m-d' ),
					'invoiceDueDays'       => $this->invoiceduedays,
					'invoiceNumber'        => $order_id,
					'invoiceLayout'        => ''
				);
				$result = $this->getPx()->PurchaseInvoicePrivate( $params );
				break;
			case 'corporate':
				// Call PxOrder.PurchaseInvoiceCorporate
				$params = array(
					'accountNumber'      => '',
					'orderRef'           => $orderRef,
					'companyRef'         => $customer_id,
					'companyName'        => $credit_data['name'], // Firm name
					'streetAddress'      => $credit_data['address'],
					'coAddress'          => $credit_data['address'],
					'postalCode'         => $credit_data['postCode'],
					'city'               => $credit_data['city'],
					'country'            => $order->get_billing_country(),
					'organizationNumber' => wc_clean( $_POST['organizationNumber'] ),
					'phoneNumber'        => '',
					'email'              => $order->get_billing_email(),
					'productCode'        => '0001',
					'creditcheckRef'     => $credit_data['creditCheckRef'],
					'mediaDistribution'  => $this->distribution,
					'invoiceText'        => $this->invoicetext,
					'invoiceDate'        => date( 'Y-m-d' ),
					'invoiceDueDays'     => $this->invoiceduedays,
					'invoiceNumber'      => $order_id,
					'invoiceLayout'      => ''
				);
				$result = $this->getPx()->PurchaseInvoiceCorporate( $params );
				break;
		}

		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			$this->log( 'PurchaseInvoicePrivate / PurchaseInvoiceCorporate:' . $result['errorCode'] . '(' . $result['description'] . ')' );

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
				$order->update_status( 'on-hold', sprintf( __( 'Transaction is pending. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			case 3:
				$order->update_status( 'on-hold', sprintf( __( 'Transaction authorized. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			case 4:
				// Cancel
				$order->update_status( 'cancelled' );
				wc_add_notice( $this->getVerboseErrorMessage( $result ), 'error' );

				return;
			case 5:
			default:
				// Cancel when Error
				$message = __( 'Transaction is failed.', 'woocommerce-gateway-payex-payment' );
				if ( ! empty( $result['transactionNumber'] ) ) {
					$message = sprintf( __( 'Transaction is failed. Transaction Id: %s.', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] );
				}

				$message .= ' ' . sprintf( __( 'Details: %s.', 'woocommerce-gateway-payex-payment' ), $this->getVerboseErrorMessage( $result ) );

				$order->update_status( 'failed', $message );

				return;
		}
	}
}

