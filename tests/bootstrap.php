<?php
/**
 * PHPUnit bootstrap file
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // WPCS: XSS ok.
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/woocommerce-gateway-payex-payment.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin', 20 );

/**
 * Install PayEx Test Data
 */
function _payex_install() {
	foreach (['payex_bankdebit',
		'payex_factoring',
		'payex_invoice',
		'payex_masterpass',
		'payex_mobilepay',
		'payex',
		'payex_swish',
		'payex_wywallet'] as $method)
	{
		$settings = [
			'enabled'       => 'yes',
			'testmode'      => 'yes',
			'debug'         => 'yes',
			'account_no'    => getenv( 'PAYEX_ACCOUNT_NUMBER' ),
			'encrypted_key' => getenv( 'PAYEX_ENCRYPTION_KEY' ),
			'description'   => 'Test',
		];

		switch ($method) {
			case 'payex':
				$settings['payment_view'] = 'CREDITCARD';
				break;
			case 'payex_factoring':
				$settings['mode'] = 'FINANCING';
				break;
			case 'payex_invoice':
				$settings['credit_check'] = 'no';
				break;
		}
		update_option( sprintf( 'woocommerce_%s_settings', $method ), $settings, 'yes' );
	}

	echo esc_html( 'Installing PayEx Payments...' . PHP_EOL );
}

tests_add_filter( 'setup_theme', '_payex_install', 20 );

// Start up the WP testing environment.
//require $_tests_dir . '/includes/bootstrap.php';
require $_tests_dir . '/../woocommerce/tests/bootstrap.php';
