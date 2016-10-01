<?php

require_once dirname( WC_PLUGIN_FILE ) . '/includes/class-wc-payment-gateways.php' ;
require_once dirname(__FILE__) . '/../../woocommerce-gateway-payex-payment.php';

abstract class WC_Payment_Unit_Test_Case extends WC_Unit_Test_Case {
	/**
	 * @var
	 */
	protected $object;

	/**
	 * Last AJAX response
	 * @var string
	 */
	protected $_last_response = '';

	/**
	 * Setup test case
	 */
	public function setUp() {

		parent::setUp();

		// @todo Fix "Test code or tested code did not (only) close its own output buffers"
		// Ajax Die Handler
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );
	}

	/**
	 * Tear down the test case
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
}
