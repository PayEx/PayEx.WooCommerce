<?php
/**
 * Admin View: Page - Addons
 *
 * @var string $view
 * @var object $addons
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<?php settings_errors(); ?>
<h3><span><?php _e( 'General Options', 'woocommerce-gateway-payex-payment' ); ?></span></h3>
<?php

$options = wp_parse_args( get_option( 'woocommerce_payex_addons_ssn_check', array() ), array(
	'ssn_enabled' => false,
	'testmode' => false,
	'account_no' => null,
	'encrypted_key' => null,
) );

?>
<form method="post" action="options.php">
	<?php settings_fields( 'woocommerce_payex_addons' ); ?>
<table class="form-table">
	<tbody>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="woocommerce_payex_addons_ssn_check[ssn_enabled]">
				<?php _e( 'Enable/Disable', 'woocommerce-gateway-payex-payment' ); ?>
			</label>
		</th>
		<td class="forminp">
			<fieldset>
				<legend class="screen-reader-text">
					<span><?php _e( 'Enable/Disable', 'woocommerce-gateway-payex-payment' ); ?></span>
				</legend>
				<label for="woocommerce_payex_addons_ssn_check[ssn_enabled]">
					<input type="checkbox" name="woocommerce_payex_addons_ssn_check[ssn_enabled]" id="woocommerce_payex_addons_ssn_check[ssn_enabled]" value="1" <?php checked( $options['ssn_enabled'], 1 ); ?> />
					<?php _e( 'Add SSN field to checkout', 'woocommerce' ); ?>
				</label>
				<br />
			</fieldset>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="woocommerce_payex_addons_ssn_check[account_no]">
				<?php _e( 'Account Number', 'woocommerce-gateway-payex-payment' ); ?>
			</label>
		</th>
		<td class="forminp">
			<fieldset>
				<legend class="screen-reader-text">
					<span><?php _e( 'Account Number', 'woocommerce-gateway-payex-payment' ); ?></span>
				</legend>
				<input class="input-text regular-input" type="text" name="woocommerce_payex_addons_ssn_check[account_no]" id="woocommerce_payex_addons_ssn_check[account_no]" value="<?php esc_attr_e($options['account_no']); ?>" />
				<p class="description"><?php _e( 'Account Number of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ); ?></p>
			</fieldset>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="woocommerce_payex_addons_ssn_check[encrypted_key]">
				<?php _e( 'Encryption Key', 'woocommerce-gateway-payex-payment' ); ?>
			</label>
		</th>
		<td class="forminp">
			<fieldset>
				<legend class="screen-reader-text">
					<span><?php _e( 'Encryption Key', 'woocommerce-gateway-payex-payment' ); ?></span>
				</legend>
				<input class="input-text regular-input" type="text" name="woocommerce_payex_addons_ssn_check[encrypted_key]" id="woocommerce_payex_addons_ssn_check[encrypted_key]" value="<?php esc_attr_e($options['encrypted_key']); ?>" />
				<p class="description"><?php _e( 'Encryption Key of PayEx Merchant.', 'woocommerce-gateway-payex-payment' ); ?></p>
			</fieldset>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="woocommerce_payex_addons_ssn_check[testmode]">
				<?php _e( 'Test Mode', 'woocommerce-gateway-payex-payment' ); ?>
			</label>
		</th>
		<td class="forminp">
			<fieldset>
				<legend class="screen-reader-text">
					<span><?php _e( 'Test Mode', 'woocommerce-gateway-payex-payment' ); ?></span>
				</legend>
				<label for="woocommerce_payex_addons_ssn_check[testmode]">
					<input type="checkbox" name="woocommerce_payex_addons_ssn_check[testmode]" id="woocommerce_payex_addons_ssn_check[testmode]" value="1" <?php checked( $options['testmode'], 1 ); ?> />
					<?php _e( 'Enable PayEx Test Mode', 'woocommerce-gateway-payex-payment' ); ?>
				</label>
			</fieldset>
		</td>
	</tr>
	</tbody>
</table>
<p class="submit">
	<input name="save" class="button-primary" type="submit" value="<?php _e( 'Save Changes', 'woocommerce' ) ?>">
</p>
</form>
