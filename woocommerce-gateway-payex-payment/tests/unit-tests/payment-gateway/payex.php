<?php

require_once dirname( __FILE__ ) . '/../../includes/class-wc-payement-unit-test-case.php';

class WC_Tests_Payment_Payex extends WC_Payment_Unit_Test_Case {
	/**
	 * @var WC_Payex_Payment
	 */
	protected $object;

	/**
	 * Setup test case.
	 */
	public function setUp() {
		parent::setUp();
		// Init PayEx Payments plugin
		$this->object = new WC_Payex_Payment();
		$this->object->init();
		$this->object->create_credit_card_post_type();

		// Add PayEx to PM List
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'payment_gateways' ) );
	}

	/**
	 * Register Payment Gateway and inject settings
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function payment_gateways($gateways) {
		// Enable and Configure PayEx Payments
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		foreach ($payment_gateways as $id => $gateway) {
			if ( strpos( $id, 'payex' ) !== false ) {
				$gateways[$id] = $payment_gateways[$id];
				$gateways[$id]->enabled = 'yes';
				$gateways[$id]->testmode = 'yes';
				$gateways[$id]->account_no = '1';
				$gateways[$id]->encrypted_key = '1';
			}
		}

		return $gateways;
	}

	/**
	 * Test PayEx is available
	 */
	public function test_wc_payment() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->assertArrayHasKey( 'payex', $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_Payment', $payment_gateways['payex'] );
	}

	/**
	 * Test Order
	 */
	public function test_wc_payment_payex_order() {
		// Get payment gateways
		$payment_gateways = WC()->payment_gateways->payment_gateways();

		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();

		// Set payment gateway
		$order->set_payment_method( $payment_gateways['payex'] );

		$this->assertInstanceOf( 'WC_Order', wc_get_order( $order->id ) );
		$this->assertEquals( 'payex', $order->payment_method );
	}

	/**
	 * Test Valid Order Statuses
	 * @see WC_Payex_Payment::add_valid_order_statuses
	 */
	public function test_wc_payment_payex_complete_statuses() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();

		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $payment_gateways['payex'] );

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
		$payment_gateways = WC()->payment_gateways->payment_gateways();

		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $payment_gateways['payex'] );

		// Add Transaction data
		update_post_meta( $order->id, '_transaction_id', '123456' );
		update_post_meta( $order->id, '_payex_transaction_status', '3' );

		// Check Transaction Id
		$this->assertEquals( '123456', $order->get_transaction_id() );

		$this->object->capture_payment($order->id);

		// Check Order Status
		$this->assertEquals( 'on-hold', $order->get_status() );
	}

	/**
	 * Test Cancel
	 * @see WC_Payex_Payment::cancel_payment
	 */
	public function test_wc_payment_payex_cancel() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();

		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $payment_gateways['payex'] );

		// Add Transaction data
		update_post_meta( $order->id, '_transaction_id', '123456' );
		update_post_meta( $order->id, '_payex_transaction_status', '3' );

		// Check Transaction Id
		$this->assertEquals( '123456', $order->get_transaction_id() );

		$this->object->cancel_payment($order->id);

		// Check Order Status
		$this->assertEquals( 'on-hold', $order->get_status() );
	}

	/**
	 * Test Checkout Process
	 * @throws Exception
	 */
	public function test_wc_payment_payex_checkout() {
		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		// Create dummy product
		$product = WC_Helper_Product::create_simple_product();

		// Set product is virtual
		update_post_meta( $product->id, '_virtual', 'yes' );

		// Add product to cart
		WC()->cart->add_to_cart( $product->id, 1 );
		WC()->cart->calculate_totals();

		// Set Checkout fields
		$_POST['_wpnonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['terms'] = 0;
		$_POST['createaccount'] = 0;
		$_POST['payment_method'] = 'payex';
		$_POST['shipping_method'] = 'flat-rate';
		$_POST['ship_to_different_address'] = false;

		$address = array(
			'first_name' => 'Tester',
			'last_name' => 'Tester',
			'company' => '',
			'address_1' => 'Street',
			'address_2' => '',
			'city' => 'Albany',
			'state' => 'NY',
			'postcode' => '10001',
			'country' => 'US',
			'email' => 'tester@example.com',
			'phone' => '518-457-5181'
		);
		foreach ($address as $key => $value) {
			$_POST['billing_' . $key] = $value;
			$_POST['shipping_' . $key] = $value;
		}

		// Process Checkout
		try {
			$_SERVER['HTTP_USER_AGENT'] = '';
			WC()->checkout()->process_checkout();
		} catch (Exception $e) {
			if ($e->getCode() !== 200) {
				throw $e;
			}

			$json = json_decode($this->_last_response, true);
			$this->assertInternalType('array', $json);

			// Check error string from Payex response
			$this->assertContains( 'The hash on request is not valid, this might be due to the encryption key being incorrect', $this->_last_response );
		}

		$this->assertTrue( true );
	}
}
