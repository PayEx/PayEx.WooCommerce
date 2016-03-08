<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Payex_Update {

	/** @var array DB updates that need to be run */
	private static $db_updates = array(
		'2.0.0' => 'updates/update-2.0.0.php',
	);

	/**
	 * Handle updates
	 */
	public static function update() {
		$current_version = get_option( 'woocommerce_payex_version' );
		foreach ( self::$db_updates as $version => $updater ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				include dirname( __FILE__ ) . '/../' . $updater;
				self::update_db_version( $version );
			}
		}

		self::update_db_version();
	}

	/**
	 * Update DB version to current
	 */
	private static function update_db_version( $version = null ) {
		if ( is_null( $version ) ) {
			// Get Last Version
			$plugin = get_plugin_data( dirname( __FILE__ ) . '/../woocommerce-gateway-payex-payment.php', false, false );
			if ( ! empty( $plugin['Version'] ) ) {
				$version = $plugin['Version'];
			}
		}

		delete_option( 'woocommerce_payex_version' );
		add_option( 'woocommerce_payex_version', $version );
	}
}