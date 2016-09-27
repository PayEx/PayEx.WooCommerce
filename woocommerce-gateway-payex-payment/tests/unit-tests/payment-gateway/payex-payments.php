<?php

require_once dirname(__FILE__) . '/../../../../../woocommerce/includes/class-wc-payment-gateways.php';
require_once dirname(__FILE__) . '/../../../woocommerce-gateway-payex-payment.php';

class WC_Tests_Payment_Payex extends WC_Unit_Test_Case {

	/** @var  WC_Payex_Payment */
	protected $plugin;

	/**
	 * Last AJAX response
	 * @var string
	 */
	protected $_last_response = '';

	/**
	 * Setup test case.
	 */
	public function setUp() {

		parent::setUp();

		// Add PayEx to PM List
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'payment_gateways' ) );

		// Init PayEx Payments plugin
		$this->plugin = new WC_Payex_Payment();
		$this->plugin->init();
		$this->plugin->create_credit_card_post_type();
	}

	/**
	 * Tear down the test fixture.
	 * Reset $_POST, remove the wp_die() override
	 */
	public function tearDown() {
		parent::tearDown();

		$_POST = array();
		$_GET = array();

		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1 );
	}

	/**
	 * Return our callback handler
	 * @return callback
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * @param $message
	 *
	 * @throws Exception
	 */
	public function dieHandler( $message ) {
		$this->_last_response .= ob_get_clean();
		if ( '' === $this->_last_response ) {
			if ( is_scalar( $message ) ) {
				throw new \Exception( (string) $message );
			} else {
				throw new \Exception( '0' );
			}
		} else {
			throw new \Exception( $message, 200 );
		}
	}

	/**
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
	public function test_wc_payment_payex() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->assertArrayHasKey( 'payex', $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_Payment', $payment_gateways['payex'] );
	}

	/**
	 * Test PayEx Bankdebit is available
	 */
	public function test_wc_payment_payex_bankdebit() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->assertArrayHasKey( 'payex_bankdebit', $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_Bankdebit', $payment_gateways['payex_bankdebit'] );
	}

	/**
	 * Test PayEx Factoring is available
	 */
	public function test_wc_payment_payex_factoring() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->assertArrayHasKey( 'payex_factoring', $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_Factoring', $payment_gateways['payex_factoring'] );
	}

	/**
	 * Test PayEx Invoice is available
	 */
	public function test_wc_payment_payex_invoice() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->assertArrayHasKey( 'payex_invoice', $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_Invoice', $payment_gateways['payex_invoice'] );
	}

	/**
	 * Test PayEx MasterPass is available
	 */
	public function test_wc_payment_payex_masterpass() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->assertArrayHasKey( 'payex_masterpass', $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_MasterPass', $payment_gateways['payex_masterpass'] );
	}

	/**
	 * Test PayEx Swish is available
	 */
	public function test_wc_payment_payex_swish() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->assertArrayHasKey( 'payex_swish', $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_Swish', $payment_gateways['payex_swish'] );
	}

	/**
	 * Test PayEx WyWallet is available
	 */
	public function test_wc_payment_payex_wywallet() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->assertArrayHasKey( 'payex_wywallet', $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_Wywallet', $payment_gateways['payex_wywallet'] );
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

		$this->plugin->capture_payment($order->id);

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

		$this->plugin->cancel_payment($order->id);

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

		// @todo Fix "Test code or tested code did not (only) close its own output buffers"
		// Ajax Die Handler
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );

		// Create dummy product
		$product = WC_Helper_Product::create_simple_product();

		// Set product is virtual
		update_post_meta( $product->get_id(), '_virtual', 'yes' );

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
