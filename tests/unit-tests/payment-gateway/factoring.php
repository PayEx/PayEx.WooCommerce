<?php

require_once dirname( __FILE__ ) . '/../../includes/class-wc-payment-unit-test-case.php';

class WC_Tests_Payment_Factoring extends WC_Payment_Unit_Test_Case {
	/**
	 * @var WC_Payex_Payment
	 */
	protected $object;

	/**
	 * @var WC_Gateway_Payex_Factoring
	 */
	private $gateway;

	/**
	 * @var WooCommerce
	 */
	private $wc;

	const METHOD = 'payex_factoring';

	/**
	 * Setup test case.
	 */
	public function setUp() {
		parent::setUp();

		$this->wc = WC();

		// Init PayEx Payments plugin
		$this->object = new WC_Payex_Payment();
		$this->object->init();
		$this->object->create_credit_card_post_type();

		// Init PayEx Payments plugin
		$this->gateway              = new WC_Gateway_Payex_Factoring();
		$this->gateway->enabled     = 'yes';
		$this->gateway->testmode    = 'yes';
		$this->gateway->description = 'Test';

		// Add PayEx to PM List
		tests_add_filter( 'woocommerce_payment_gateways', array( $this, 'payment_gateways' ) );
		tests_add_filter( 'woocommerce_available_payment_gateways', array( $this, 'payment_gateways' ) );

		// Override order currency
		tests_add_filter( 'woocommerce_order_get_currency', array( $this, 'order_currency' ), 1, 2 );
	}

	/**
	 * Register Payment Gateway and inject settings
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function payment_gateways( $gateways ) {
		$payment_gateways = $this->wc->payment_gateways->payment_gateways();
		foreach ( $payment_gateways as $id => $gateway ) {
			if ( strpos( $id, self::METHOD ) !== false ) {
				$gateways[ $id ]                = $payment_gateways[ $id ];
				$gateways[ $id ]->enabled       = 'yes';
				$gateways[ $id ]->testmode      = 'yes';
				$gateways[ $id ]->account_no    = getenv( 'PAYEX_ACCOUNT_NUMBER' );
				$gateways[ $id ]->encrypted_key = getenv( 'PAYEX_ENCRYPTION_KEY' );
				$gateways[ $id ]->description   = 'Test';
			}
		}

		return $gateways;
	}

	/**
	 * @param $currency
	 * @param $order
	 *
	 * @return string
	 */
	public function order_currency( $currency, $order ) {
		return 'SEK';
	}

	/**
	 * Test PayEx is available
	 */
	public function test_wc_payment() {
		$payment_gateways = $this->wc->payment_gateways();
		$this->assertArrayHasKey( self::METHOD, $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_Factoring', $payment_gateways[ self::METHOD ] );
	}

	/**
	 * Test Order
	 */
	public function test_wc_payment_payex_order() {
		// Get payment gateways
		$payment_gateways = $this->wc->payment_gateways();

		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();

		// Set payment gateway
		$order->set_payment_method( $payment_gateways[ self::METHOD ] );
		$order->save();

		// Reload Order
		$order = wc_get_order( $order->get_id() );

		$this->assertInstanceOf( 'WC_Order', wc_get_order( $order->get_id() ) );
		$this->assertEquals( self::METHOD, $order->get_payment_method() );
	}

	/**
	 * Test Valid Order Statuses
	 * @see WC_Payex_Payment::add_valid_order_statuses
	 */
	public function test_wc_payment_payex_complete_statuses() {
		$payment_gateways = $this->wc->payment_gateways();

		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $payment_gateways[ self::METHOD ] );
		$order->save();

		// Reload Order
		$order = wc_get_order( $order->get_id() );

		$valid_order_statuses = apply_filters(
			'woocommerce_valid_order_statuses_for_payment_complete',
			array( 'on-hold', 'pending', 'failed', 'cancelled' ),
			$order
		);

		// Check 'processing', 'completed' in valid order statuses list
		$this->assertContains( 'processing', $valid_order_statuses );
		$this->assertContains( 'completed', $valid_order_statuses );
	}

	/**
	 * Test Post Types
	 * @see WC_Payex_Payment::create_credit_card_post_type
	 */
	public function test_wc_payment_payex_post_types() {
		$post_types = get_post_types();
		$this->assertArrayHasKey( 'payex_credit_card', $post_types );
	}

	/**
	 * Test Capture
	 * @see WC_Payex_Payment::capture_payment
	 */
	public function test_wc_payment_payex_capture() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $this->gateway );

		// Add Transaction data
		$order->set_transaction_id( '123456' );
		$order->update_meta_data( '_payex_transaction_status', '3' );
		$order->save();

		// Reload Order
		$order = wc_get_order( $order->get_id() );

		// Check Transaction Id
		$this->assertEquals( '123456', $order->get_transaction_id() );

		$this->gateway->capture_payment( $order->get_id() );

		// Reload Order
		$order = wc_get_order( $order->get_id() );

		// Check Order Status
		$this->assertEquals( 'on-hold', $order->get_status() );
	}

	/**
	 * Test Cancel
	 * @see WC_Payex_Payment::cancel_payment
	 */
	public function test_wc_payment_payex_cancel() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $this->gateway );

		// Add Transaction data
		$order->set_transaction_id( '123456' );
		$order->update_meta_data( '_payex_transaction_status', '3' );
		$order->save();

		// Reload Order
		$order = wc_get_order( $order->get_id() );

		// Check Transaction Id
		$this->assertEquals( '123456', $order->get_transaction_id() );

		$this->gateway->cancel_payment( $order->get_id() );

		// Reload Order
		$order = wc_get_order( $order->get_id() );

		// Check Order Status
		$this->assertEquals( 'on-hold', $order->get_status() );
	}

	/**
	 * Test Checkout Process
	 * @throws Exception
	 */
	public function test_wc_payment_payex_checkout() {
		wc_maybe_define_constant( 'DOING_AJAX', true );
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		// Get Payment Gateways
		$payment_gateways = $this->wc->payment_gateways();

		// Create dummy product
		$product = WC_Helper_Product::create_simple_product();

		// Set product is virtual
		$product->set_virtual( 'yes' );
		$product->save();

		// Add product to cart
		$this->wc->cart->add_to_cart( $product->get_id(), 1 );
		$this->wc->cart->calculate_totals();

		// Set Checkout fields
		$_POST['_wpnonce']                  = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['terms']                     = 0;
		$_POST['createaccount']             = 0;
		$_POST['payment_method']            = self::METHOD;
		$_POST['shipping_method']           = 'flat-rate';
		$_POST['ship_to_different_address'] = false;
		$_POST['social-security-number']    = '5907195662';

		$address = array(
			'first_name' => 'Eva Dagmar Christina',
			'last_name'  => 'Tannerdal',
			'company'    => '',
			'address_1'  => 'Gunbritt Boden p12',
			'address_2'  => '',
			'city'       => 'Småbyen',
			'state'      => 'Småbyen',
			'postcode'   => '29620',
			'country'    => 'SE',
			'email'      => 'tester@example.com',
			'phone'      => '518-457-5181'
		);
		foreach ( $address as $key => $value ) {
			$_POST[ 'billing_' . $key ]  = $value;
			$_POST[ 'shipping_' . $key ] = $value;
		}

		// Process Checkout
		$_SERVER['HTTP_USER_AGENT'] = '';
		//$this->wc->checkout()->process_checkout();

		// Simulate checkout process
		wc_set_time_limit( 0 );
		do_action( 'woocommerce_before_checkout_process' );
		do_action( 'woocommerce_checkout_process' );

		// Create Order
		$order_id = $this->wc->checkout()->create_order( $_POST );

		// Store Order ID in session so it can be re-used after payment failure
		$this->wc->session->set( 'order_awaiting_payment', $order_id );

		// Process Payment
		$result = $payment_gateways[ self::METHOD ]->process_payment( $order_id );

		$this->assertInternalType( 'array', $result );

		// Check response have redirect
		$this->assertArrayHasKey( 'redirect', $result );
	}
}

