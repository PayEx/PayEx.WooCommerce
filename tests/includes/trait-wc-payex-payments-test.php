<?php

trait WC_Payex_Payments_Test {
	/**
	 * @var WC_Gateway_Payex_Abstract
	 */
	private $gateway;

	/**
	 * @var string
	 */
	private $class_name;

	/**
	 * @var WooCommerce
	 */
	private $wc;

	/**
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Setup test case.
	 */
	public function setUp() {
		parent::setUp();

		// Set settings
		$this->wc = WC();
		$gateways = $this->wc->payment_gateways()->payment_gateways();

		// Init PayEx Payments plugin
		$this->gateway    = $gateways[ self::METHOD ];
		$this->class_name = get_class( $this->gateway );

		// Override order currency
		tests_add_filter( 'woocommerce_order_get_currency', array( $this, 'order_currency' ), 1, 2 );

		// Create Order
		$this->order = WC_Helper_Order::create_order();

		// Set payment gateway
		$this->order->set_payment_method( $gateways[ self::METHOD ] );
		$this->order->set_currency( 'SEK' );
		$this->order->save();

		// Reload Order
		$this->order = wc_get_order( $this->order->get_id() );
	}

	public function tearDown() {
		$this->gateway    = null;
		$this->class_name = null;
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
	 * Test Post Types
	 * @see WC_Payex_Payment::create_credit_card_post_type
	 */
	public function test_wc_payment_payex_post_types() {
		$post_types = get_post_types();
		$this->assertArrayHasKey( 'payex_credit_card', $post_types );
	}

	/**
	 * Test PayEx is available
	 */
	public function test_wc_payment() {
		$gateways = $this->wc->payment_gateways()->payment_gateways();
		$this->assertArrayHasKey( self::METHOD, $gateways );
		$this->assertInstanceOf( $this->class_name, $gateways[ self::METHOD ] );
	}

	/**
	 * Test Order
	 */
	public function test_wc_payment_payex_order() {
		$this->assertInstanceOf( 'WC_Order', wc_get_order( $this->order->get_id() ) );
		$this->assertEquals( self::METHOD, $this->order->get_payment_method() );
	}

	/**
	 * Test Valid Order Statuses
	 * @see WC_Payex_Payment::add_valid_order_statuses
	 */
	public function test_wc_payment_payex_complete_statuses() {
		$valid_order_statuses = apply_filters(
			'woocommerce_valid_order_statuses_for_payment_complete',
			[ 'on-hold', 'pending', 'failed', 'cancelled' ],
			$this->order
		);

		// Check 'processing', 'completed' in valid order statuses list
		$this->assertContains( 'processing', $valid_order_statuses );
		$this->assertContains( 'completed', $valid_order_statuses );
	}


	/**
	 * Test Capture
	 * @see WC_Gateway_Payex_Abstract::capture_payment
	 * @see WC_Gateway_Payex_Abstract::cancel_payment
	 */
	public function test_wc_payment_payex_actions() {
		// Test Capture
		// Add Transaction data
		$this->order->set_transaction_id( '123456' );
		$this->order->update_meta_data( '_payex_transaction_status', '3' );
		$this->order->save();

		// Reload Order
		$order = wc_get_order( $this->order->get_id() );

		// Check Order Status
		$this->assertEquals( 'pending', $order->get_status() );

		// Check Transaction Id
		$this->assertEquals( '123456', $order->get_transaction_id() );

		// Capture
		try {
			$this->gateway->capture_payment( $order->get_id() );

			throw new Exception( 'Test is failed.' );
		} catch ( Exception $e ) {
			$this->assertEquals( 'PayEx error: NoRecordFound (123456)', $e->getMessage() );
		}

		// Test Cancellation
		// Add Transaction data
		$order->set_transaction_id( '1234567' );
		$order->update_meta_data( '_payex_transaction_status', '3' );
		$order->save();

		// Reload Order
		$order = wc_get_order( $order->get_id() );

		// Check Transaction Id
		$this->assertEquals( '1234567', $order->get_transaction_id() );

		// Cancel
		try {
			$this->gateway->cancel_payment( $order->get_id() );

			throw new Exception( 'Test is failed.' );
		} catch ( Exception $e ) {
			$this->assertEquals( 'PayEx error: OperationNotAllowed (Initial transaction must have status Authorize)', $e->getMessage() );
		}

		// Check Order Status
		$this->assertEquals( 'pending', $order->get_status() );
	}

	/**
	 * Test Checkout Process
	 * @throws Exception
	 */
	public function test_wc_payment_payex_checkout() {
		wc_maybe_define_constant( 'DOING_AJAX', true );
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

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

		$address = [
			'first_name' => 'Tester',
			'last_name'  => 'Tester',
			'company'    => '',
			'address_1'  => 'Street',
			'address_2'  => '',
			'city'       => 'Albany',
			'state'      => 'NY',
			'postcode'   => '10001',
			'country'    => 'US',
			'email'      => 'tester@example.com',
			'phone'      => '518-457-5181'
		];
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

		// Set currency
		$order = wc_get_order( $order_id );
		$order->set_currency( 'SEK' );

		// Store Order ID in session so it can be re-used after payment failure
		$this->wc->session->set( 'order_awaiting_payment', $order_id );

		// Process Payment
		$result = $this->gateway->process_payment( $order_id );

		$this->assertInternalType( 'array', $result );

		// Check response have redirect
		$this->assertArrayHasKey( 'redirect', $result );
	}
}

