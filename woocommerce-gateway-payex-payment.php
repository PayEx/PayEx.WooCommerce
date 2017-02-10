<?php
/*
Plugin Name: WooCommerce PayEx Payments Gateway
Plugin URI: http://payex.com/
Description: Provides a Credit Card Payment Gateway through PayEx for WooCommerce.
Version: 2.1.5
Author: AAIT Team
Author URI: http://aait.se/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 3.1
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

require_once dirname( __FILE__ ) . '/vendor/autoload.php';

class WC_Payex_Payment {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Activation
		register_activation_hook( __FILE__, __CLASS__ . '::install' );

		// Actions
		add_action( 'init', array( $this, 'create_credit_card_post_type' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );

		// Add admin menu
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 99 );

		// Payment fee
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fee' ) );

		// Add statuses for payment complete
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array(
			$this,
			'add_valid_order_statuses'
		), 10, 2 );

		// Add MasterPass button to Cart Page
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'add_mp_button_to_cart' ) );

		// Add MasterPass button to Product Page
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_mp_button_to_product_page' ) );

		// Check is MasterPass Purchase
		add_action( 'template_redirect', array( $this, 'check_mp_purchase' ) );

		// PayEx Credit Card: Payment Method Change Callback
		add_action( 'template_redirect', array( $this, 'check_payment_method_changed' ) );

		// Add Upgrade Notice
		if ( version_compare( get_option( 'woocommerce_payex_version', '1.0.0' ), '2.0.0', '<' ) ) {
			add_action( 'admin_notices', __CLASS__ . '::upgrade_notice' );
		}
		add_action( 'admin_notices', __CLASS__ . '::admin_notices' );
		add_action( 'admin_notices', __CLASS__ . '::check_backward_compatibility', 40 );

		// Add SSN Checkout Field
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'before_checkout_billing_form' ) );
		add_action( 'wp_ajax_payex_process_ssn', array( $this, 'ajax_payex_process_ssn' ) );
		add_action( 'wp_ajax_nopriv_payex_process_ssn', array( $this, 'ajax_payex_process_ssn' ) );

		// Add Email Classes
		add_filter( 'woocommerce_email_classes', array( $this, 'add_email_classes' ), 10, 1 );

		// Init Cron Tasks
		add_action( 'wp', __CLASS__ . '::add_cron_tasks' );

		// Cron Tasks Actions
		add_action( 'payex_check_cards', __CLASS__ . '::check_cards' );

		// Add Countries for SSN field
		add_filter( 'woocommerce_payex_countries_ssn', array( $this, 'add_countries_ssn' ), 10, 1 );
	}

	/**
	 * Install
	 */
	public static function install() {
		if ( ! get_option( 'woocommerce_payex_version' ) ) {
			add_option( 'woocommerce_payex_version', '2.0.0' );
		}
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=payex' ) ) . '">' . __( 'Settings', 'woocommerce-gateway-payex-payment' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 */
	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		// Localization
		load_plugin_textdomain( 'woocommerce-gateway-payex-payment', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Includes
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-abstract.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-payment.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-bankdebit.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-invoice.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-factoring.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-wywallet.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-masterpass.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-swish.php' );
        include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-payex-mobilepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-payex-credit-cards.php' );
	}

	/**
	 * Admin Notices
	 */
	public static function admin_notices() {
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		$list = array(
			'payex', 'payex_bankdebit', 'payex_invoice',
			'payex_factoring', 'payex_wywallet', 'payex_masterpass',
			'payex_swish'
		);

		foreach ($list as $item) {
			if ( isset( $available_gateways[$item] ) ) {
				$gateway = $available_gateways[$item];
				$settings = $gateway->settings;
				if ( empty( $settings['account_no'] ) || empty( $settings['encrypted_key'] ) ) {
					echo '<div class="error"><p>' . sprintf( __( 'PayEx Payments for WooCommerce is almost ready. To get started <a href="%s">connect your PayEx account</a>.', 'woocommerce-gateway-payex-payment' ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $gateway->id ) ) ) . '</p></div>';
					break;
				}
			}
		}
	}

	/**
	 * Upgrade Notice
	 */
	public static function upgrade_notice() {
		if ( current_user_can( 'update_plugins' ) ) {
			?>
			<div id="message" class="error">
				<p>
					<?php
					echo esc_html__( 'Warning! WooCommerce PayEx Payments plugin requires to update the database structure.', 'woocommerce-gateway-payex-payment' );
					echo ' ' . sprintf( esc_html__( 'Please click %s here %s to start upgrade.', 'woocommerce-gateway-payex-payment' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-payex-upgrade' ) ) . '">', '</a>' );
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Check for backward compatibility with woocommerce-gateway-payex
	 */
	public static function check_backward_compatibility() {
		if ( current_user_can( 'update_plugins' ) ) {
			// Check is already migrated
			if ( get_option( 'woocommerce_payex_migrated' ) !== false ) {
				return;
			}

			// Check woocommerce-gateway-payex is active
			if ( ! in_array( 'woocommerce-gateway-payex/gateway-payex.php', get_option( 'active_plugins' ) ) ) {
				return;
			}

			// Check payex settings is unconfigured yet
			$settings = get_option( 'woocommerce_payex_settings' );
			if ( $settings !== false ) {
				return;
			}

			// Check settings of woocommerce-gateway-payex plugin
			$settings = get_option( 'woocommerce_payex_pm_settings' );
			if ( $settings === false ) {
				return;
			}

			// Check account_no and encrypted_key are configured
			if ( empty( $settings['account_no'] ) || empty( $settings['encrypted_key'] ) ) {
				return;
			}

			?>
			<div id="message" class="updated woocommerce-message">
				<p class="main">
					<strong><?php echo esc_html__( 'Data migration.', 'woocommerce-gateway-payex-payment' ); ?></strong>
				</p>
				<p>
					<?php
					echo esc_html__( 'We\'ve detected that you\'ve used an older version of the WooCommerce PayEx integration.', 'woocommerce-gateway-payex-payment' );
					echo '<br />';
					echo esc_html__( 'Click the "Upgrade" button below to setup this new integration with your existing PayEx details', 'woocommerce-gateway-payex-payment' );
					echo '<br />';
					echo esc_html__( 'Please be sure to backup your website before doing this. Thank you for choosing PayEx!', 'woocommerce-gateway-payex-payment' );
					?>
				</p>
				<p class="submit">
					<a class="button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-payex-migrate' ) ); ?>">
						<?php echo esc_html__( 'Upgrade', 'woocommerce-gateway-payex-payment' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Upgrade Page
	 */
	public static function upgrade_page() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Run Database Update
		include_once( dirname( __FILE__ ) . '/includes/class-wc-payex-update.php' );
		WC_Payex_Update::update();

		echo esc_html__( 'Upgrade finished.', 'woocommerce-gateway-payex-payment' );
	}

	/**
	 * Migrate Page
	 */
	public static function migrate_page() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Check is already migrated
		if ( get_option( 'woocommerce_payex_migrated' ) !== false ) {
			return;
		}

		// Copy settings
		$settings = get_option( 'woocommerce_payex_pm_settings' );
		if ( $settings ) {
			$new_settings = array(
				'enabled' => $settings['enabled'],
				'title' => $settings['title'],
				'description' => $settings['description'],
				'account_no' => $settings['account_no'],
				'encrypted_key' => $settings['encrypted_key'],
				'testmode' => $settings['testmode'],
				'debug' => $settings['debug'],
				'purchase_operation' => $settings['purchase_operation'],
				'checkout_info' => $settings['send_order_lines'],
				'payment_view' => 'CREDITCARD',
				'language' => 'en-US',
				'responsive' => 'no',
				'save_cards' => 'no',
				'agreement_max_amount' => '1000',
				'agreement_url' => get_site_url()
			);

			update_option( 'woocommerce_payex_settings', $new_settings, true );
		}

		// Convert orders data
		$orders = get_posts( array(
			'numberposts'      => -1,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'meta_key'         => '_payment_method',
			'meta_value'       => 'payex_pm',
			'post_type'        => 'shop_order',
			'post_status'      => 'any',
			'post_parent'      => 0,
			'suppress_filters' => true,
		));

		foreach ($orders as $order) {
			$order = wc_get_order( $order->ID );

			if ( version_compare(WC_Subscriptions::$version, '2.0.0', '<') &&
			     WC_Subscriptions_Order::order_contains_subscription( $order )
			) {
				// Change payment method
				update_post_meta( $order->id, '_recurring_payment_method', 'payex' );

				// Copy agreement reference
				$agreement  = get_post_meta( $order->id, '_payex_agreement_reference', true );
				if ( ! empty( $agreement ) ) {
					continue;
				}

				$agreement  = get_post_meta( $order->id, 'payex_agreement_ref', true );
				if ( ! empty( $agreement ) ) {
					update_post_meta( $order->id, '_payex_agreement_reference', $agreement );
				}
			}

			// Change payment method
			update_post_meta( $order->id, '_payment_method', 'payex' );

			// Migration flag
			update_post_meta( $order->id, '_is_migrated_payex', true );
		}

		// Deactivate plugin
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		deactivate_plugins( 'woocommerce-gateway-payex/gateway-payex.php' );

		// Migration flag
		delete_option( 'woocommerce_payex_migrated' );
		add_option( 'woocommerce_payex_migrated', true );

		echo esc_html__( 'Migration finished.', 'woocommerce-gateway-payex-payment' );
		?>
		<script type="application/javascript">
			window.onload = function() {
				setTimeout(function () {
					window.location.href = '<?php echo esc_url( admin_url( 'index.php' ) ); ?>';
				}, 3000);
			}
		</script>

        <?php
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		$mp_settings = get_option( 'woocommerce_payex_masterpass_settings' );
		if ( $mp_settings['enabled'] === 'yes' ) {
			wp_enqueue_style( 'wc-gateway-payex-masterpass', plugins_url( '/assets/css/masterpass.css', __FILE__ ), array(), false, 'all' );
		}

		$factoring_settings = get_option( 'woocommerce_payex_factoring_settings' );
		if ( isset( $factoring_settings['checkout_field'] ) && $factoring_settings['checkout_field'] === 'yes' ) {
			wp_enqueue_style( 'wc-payex-addons-ssn', plugins_url( '/assets/css/ssn.css', __FILE__ ), array(), false, 'all' );

			wp_register_script( 'wc-payex-addons-ssn', plugins_url( '/assets/js/ssn.js', __FILE__ ), array( 'wc-checkout' ), false, true );

			// Localize the script with new data
			$translation_array = array(
				'text_require_ssn' => __( 'Please enter Social Security Number', 'woocommerce-gateway-payex-payment' ),
			);
			wp_localize_script( 'wc-payex-addons-ssn', 'WC_Payex_Addons_SSN', $translation_array );

			// Enqueued script with localized data
			wp_enqueue_script( 'wc-payex-addons-ssn' );
		}
	}

	/**
	 * Register the gateways for use
	 */
	public function register_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Payex_Payment';
		$methods[] = 'WC_Gateway_Payex_Bankdebit';
		$methods[] = 'WC_Gateway_Payex_InvoiceLedgerService';
		$methods[] = 'WC_Gateway_Payex_Factoring';
		$methods[] = 'WC_Gateway_Payex_Wywallet';
		$methods[] = 'WC_Gateway_Payex_MasterPass';
		$methods[] = 'WC_Gateway_Payex_Swish';
		$methods[] = 'WC_Gateway_Payex_Mobilepay';

		return $methods;
	}

	/**
	 * Add fee when selected payment method
	 */
	public function add_cart_fee() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Get Current Payment Method
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$default            = get_option( 'woocommerce_default_gateway' );

		if ( ! $default ) {
			$default = current( array_keys( $available_gateways ) );
		}

		$current         = WC()->session->get( 'chosen_payment_method', $default );
		$current_gateway = array_key_exists( $current, $available_gateways ) ? $available_gateways[ $current ] : null;

		// Fee feature in Invoice and Factoring modules
		if ( ! $current_gateway || ! in_array( $current_gateway->id, array( 'payex_invoice', 'payex_factoring' ) ) ) {
			return;
		}

		// Is Fee is not specified
		if ( abs( $current_gateway->fee ) < 0.01 ) {
			return;
		}

		// Add Fee
		$fee_title = $current_gateway->id === 'payex_invoice' ? __( 'Invoice Fee', 'woocommerce-gateway-payex-payment' ) : __( 'Factoring Fee', 'woocommerce-gateway-payex-payment' );
		WC()->cart->add_fee( $fee_title, $current_gateway->fee, ( $current_gateway->fee_is_taxable === 'yes' ), $current_gateway->fee_tax_class );
	}

	/**
	 * Allow processing/completed statuses for capture
	 *
	 * @param $statuses
	 * @param $order
	 *
	 * @return array
	 */
	public function add_valid_order_statuses( $statuses, $order ) {
		if ( strpos( $order->payment_method, 'payex' ) !== false ) {
			$statuses = array_merge( $statuses, array( 'processing', 'completed' ) );
		}

		return $statuses;
	}

	/**
	 * Provide Credit Card Post Type
	 */
	public function create_credit_card_post_type() {
		register_post_type( 'payex_credit_card',
			array(
				'labels'       => array(
					'name' => __( 'Credit Cards', 'woocommerce-gateway-payex-payment' )
				),
				'public'       => false,
				'show_ui'      => false,
				'map_meta_cap' => false,
				'rewrite'      => false,
				'query_var'    => false,
				'supports'     => false,
			)
		);
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {
		$order              = wc_get_order( $order_id );
		$transaction_status = get_post_meta( $order_id, '_payex_transaction_status', true );
		if ( empty( $transaction_status ) ) {
			return;
		}

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		/** @var WC_Gateway_Payex_Abstract $gateway */
		$gateway = $gateways[ $order->payment_method ];
		if ( $gateway && (string) $transaction_status === '3' ) {
			// Get Additional Values
			$additionalValues = '';
			if ( $gateway->id === 'payex_factoring' ) {
				$additionalValues = 'FINANCINGINVOICE_ORDERLINES=' . urlencode( $gateway->getInvoiceExtraPrintBlocksXML( $order ) );
			}

			// Init PayEx
			$gateway->getPx()->setEnvironment( $gateway->account_no, $gateway->encrypted_key, $gateway->testmode === 'yes' );

			// Call PxOrder.Capture5
			$params = array(
				'accountNumber'     => '',
				'transactionNumber' => $order->get_transaction_id(),
				'amount'            => round( 100 * $order->order_total ),
				'orderId'           => $order->id,
				'vatAmount'         => 0,
				'additionalValues'  => $additionalValues
			);
			$result = $gateway->getPx()->Capture5( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				$gateway->log( 'PxOrder.Capture5:' . $result['errorCode'] . '(' . $result['description'] . ')' );

				$message = sprintf( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $result['errorCode'] . ' (' . $result['description'] . ')' );
				$order->update_status( 'on-hold', $message );
				WC_Admin_Meta_Boxes::add_error( $message );

				return;
			}

			update_post_meta( $order->id, '_payex_transaction_status', $result['transactionStatus'] );
			$order->add_order_note( sprintf( __( 'Transaction captured. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
			$order->payment_complete( $result['transactionNumber'] );
		}
	}

	/**
	 * Capture payment when the order is changed from on-hold to cancelled
	 *
	 * @param  int $order_id
	 */
	public function cancel_payment( $order_id ) {
		$order              = wc_get_order( $order_id );
		$transaction_status = get_post_meta( $order_id, '_payex_transaction_status', true );
		if ( empty( $transaction_status ) ) {
			return;
		}

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		/** @var WC_Gateway_Payex_Abstract $gateway */
		$gateway = $gateways[ $order->payment_method ];
		if ( $gateway && (string) $transaction_status === '3' ) {
			// Call PxOrder.Cancel2
			$params = array(
				'accountNumber'     => '',
				'transactionNumber' => $order->get_transaction_id()
			);
			$result = $gateway->getPx()->Cancel2( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				$gateway->log( 'PxOrder.Cancel2:' . $result['errorCode'] . '(' . $result['description'] . ')' );

				$message = sprintf( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $result['errorCode'] . ' (' . $result['description'] . ')' );
				$order->update_status( 'on-hold', $message );
				WC_Admin_Meta_Boxes::add_error( $message );

				return;
			}

			update_post_meta( $order->id, '_payex_transaction_status', $result['transactionStatus'] );
			$order->add_order_note( sprintf( __( 'Transaction canceled. Transaction Id: %s', 'woocommerce-gateway-payex-payment' ), $result['transactionNumber'] ) );
		}
	}

	/**
	 * Provide Admin Menu items
	 */
	public function admin_menu() {
		// Add Upgrade Page
		global $_registered_pages;
		$hookname = get_plugin_page_hookname( 'wc-payex-upgrade', '' );
		if ( ! empty( $hookname ) ) {
			add_action( $hookname, __CLASS__ . '::upgrade_page' );
		}
		$_registered_pages[ $hookname ] = true;

		// Add Plugin migrate Page
		$hookname = get_plugin_page_hookname( 'wc-payex-migrate', '' );
		if ( ! empty( $hookname ) ) {
			add_action( $hookname, __CLASS__ . '::migrate_page' );
		}
		$_registered_pages[ $hookname ] = true;
	}

	/**
	 * Add MasterPass Button to Cart page
	 */
	public function add_mp_button_to_cart() {
		$mp_settings = get_option( 'woocommerce_payex_masterpass_settings' );
		if ( $mp_settings['display_cart_button'] === 'yes' && $mp_settings['enabled'] === 'yes' ) {
			wc_get_template(
				'masterpass/cart-button.php',
				array(
					'image'       => esc_url( plugins_url( '/assets/images/masterpass-button.png', __FILE__ ) ),
					'description' => $mp_settings['description'],
					'link'        => esc_url( add_query_arg( 'mp_from_cart_page', 1, get_permalink() ) )
				),
				'',
				dirname( __FILE__ ) . '/templates/'
			);
		}
	}

	/**
	 * Add MasterPass Button to Single Product page
	 */
	public function add_mp_button_to_product_page() {
		$mp_settings = get_option( 'woocommerce_payex_masterpass_settings' );
		if ( $mp_settings['display_pp_button'] === 'yes' && $mp_settings['enabled'] === 'yes' ) {
			wc_get_template(
				'masterpass/product-button.php',
				array(
					'image'       => esc_url( plugins_url( '/assets/images/masterpass-button.png', __FILE__ ) ),
					'description' => $mp_settings['description'],
					'link'        => esc_url( add_query_arg( 'mp_from_product_page', 1, get_permalink() ) )
				),
				'',
				dirname( __FILE__ ) . '/templates/'
			);
		}
	}

	/**
	 * Check for MasterPass purchase from cart page
	 **/
	public function check_mp_purchase() {
		// Check for MasterPass purchase from cart page
		if ( isset( $_GET['mp_from_cart_page'] ) && $_GET['mp_from_cart_page'] === '1' ) {
			$gateway = new WC_Gateway_Payex_MasterPass;
			$gateway->masterpass_button_action();
		}

		// Check for MasterPass purchase from product page
		if ( isset( $_POST['mp_from_product_page'] ) && $_POST['mp_from_product_page'] === '1' ) {
			$gateway = new WC_Gateway_Payex_MasterPass;
			$gateway->masterpass_button_action();
		}
	}

	/**
	 * Payment Method Change Callback
	 */
	public function check_payment_method_changed() {
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( isset( $gateways['payex'] ) ) {
			/** @var WC_Gateway_Payex_Payment $gateway */
			$gateway = $gateways['payex'];
			if ( $gateway->enabled === 'yes' ) {
				$gateway->check_payment_method_changed();
			}
		}
	}

	/**
	 * Hook before_checkout_billing_form
	 *
	 * @param $checkout
	 */
	public function before_checkout_billing_form( $checkout ) {
		$factoring_settings = get_option( 'woocommerce_payex_factoring_settings' );
		if ( isset( $factoring_settings['checkout_field'] ) && $factoring_settings['checkout_field'] === 'yes' ) {
			echo '<div id="payex_ssn">';
			woocommerce_form_field( 'payex_ssn', array(
				'type'        => 'text',
				'class'       => array( 'payex-ssn-class form-row-wide' ),
				'label'       => __( 'Social Security Number', 'woocommerce-gateway-payex-payment' ),
				'placeholder' => __( 'Social Security Number', 'woocommerce-gateway-payex-payment' ),
			), $checkout->get_value( 'payex_ssn' ) );

			woocommerce_form_field( 'payex_ssn_zip', array(
				'type'        => 'text',
				'class'       => array( 'payex-ssn-zip-class form-row-wide' ),
				'label'       => __( 'Postcode / ZIP', 'woocommerce' ),
			), $checkout->get_value( 'billing_postcode' ) );

			woocommerce_form_field( 'payex_ssn_country', array(
				'type'        => 'select',
				'class'       => array( 'payex-ssn-country-class form-row-wide' ),
				'label'       => __( 'Country', 'woocommerce' ),
				'options'     => wp_parse_args(
					apply_filters( 'woocommerce_payex_countries_ssn', $checkout ),
					array( '' => '' )
				),
				'input_class' => array( 'country_select' )
			), $checkout->get_value( 'billing_country' ) );

			echo '<input type="button" class="button alt" name="woocommerce_checkout_payex_ssn" id="payex_ssn_button" value="' . __( 'Get Profile', 'woocommerce-gateway-payex-payment' ) . '" />';
			echo '</div>';
		}
	}

	/**
	 * Ajax Hook
	 */
	public function ajax_payex_process_ssn() {
		// Init PayEx
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( ! $gateways['payex_factoring'] ) {
			wp_send_json_error( array( 'message' => __( 'Financing Invoice method is inactive', 'woocommerce-gateway-payex-payment' ) ) );
			exit();
		}

		/** @var WC_Gateway_Payex_Factoring $gateway */
		$gateway = $gateways['payex_factoring'];

		if ( empty( $_POST['billing_country'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select country', 'woocommerce-gateway-payex-payment' ) ) );
			exit();
		}

		if ( empty( $_POST['billing_postcode'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter postcode', 'woocommerce-gateway-payex-payment' ) ) );
			exit();
		}

		// Init PayEx
		$gateway->getPx()->setEnvironment( $gateway->account_no, $gateway->encrypted_key, $gateway->testmode === 'yes' );

		// Call PxOrder.GetAddressByPaymentMethod
		$params = array(
			'accountNumber' => '',
			'paymentMethod' => 'PXFINANCINGINVOICE' . mb_strtoupper( wc_clean( $_POST['billing_country'] ), 'UTF-8' ),
			'ssn'           => trim( wc_clean( $_POST['social_security_number'] ) ),
			'zipcode'       => trim( wc_clean( $_POST['billing_postcode'] ) ),
			'countryCode'   => trim( wc_clean( $_POST['billing_country'] ) ),
			'ipAddress'     => trim( $_SERVER['REMOTE_ADDR'] )
		);
		$result = $gateway->getPx()->GetAddressByPaymentMethod( $params );
		if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
			if ( preg_match( '/\bInvalid parameter:SocialSecurityNumber\b/i', $result['description'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid Social Security Number', 'woocommerce-gateway-payex-payment' ) ) );
				exit();
			}

			wp_send_json_error( array( 'message' => $result['errorCode'] . '(' . $result['description'] . ')' ) );
			exit();
		}

		// Parse name field
		$parser = new \FullNameParser();
		$name   = $parser->parse_name( $result['name'] );

		$output = array(
			'first_name' => $name['fname'],
			'last_name'  => $name['lname'],
			'address_1'  => $result['streetAddress'],
			'address_2'  => ! empty( $result['coAddress'] ) ? 'c/o ' . $result['coAddress'] : '',
			'postcode'   => $result['zipCode'],
			'city'       => $result['city'],
			'country'    => $result['countryCode']
		);
		wp_send_json_success( $output );
		exit();
	}

	/**
	 * Add Email Classes
	 * @param $emails
	 *
	 * @return array
	 */
	public function add_email_classes($emails) {
		if ( ! class_exists( 'WC_Email_Payex_Card_Expiring', false ) ) {
			include( dirname( __FILE__ ) . '/includes/emails/class-wc-email-payex-card-expiring.php' );
		}

		$emails['WC_Email_Payex_Card_Expiring'] = new WC_Email_Payex_Card_Expiring();
		return $emails;
	}

	/**
	 * Init Cron Tasks
	 */
	public static function add_cron_tasks() {
		if ( ! wp_next_scheduled( 'payex_check_cards' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'daily', 'payex_check_cards' );
		}
	}

	/**
	 * Cron Task: Check Cards
	 */
	public static function check_cards() {
		// Check notifications are enabled
		$card_expiring_settings = get_option( 'woocommerce_payex_card_expiring_settings', array(
			'enabled' => 'yes',
			'notifications_days' => '30'
		) );
		if ( isset( $card_expiring_settings['enabled'] ) && $card_expiring_settings['enabled'] !== 'yes' ) {
			return;
		}

		$notifications_days = (int) $card_expiring_settings['notifications_days'];

		// Date to check
		$check = time() + $notifications_days * 24 * 60 * 60;

		// Get users
		/** @var WP_User $user */
		foreach ( get_users() as $user ) {
			// Get Cards
			$args  = array(
				'post_type'   => 'payex_credit_card',
				'author'      => $user->ID,
				'numberposts' => -1,
				'orderby'     => 'post_date',
				'order'       => 'ASC',
			);
			$cards = get_posts( $args );
			foreach ($cards as $card) {
				$card_meta = get_post_meta( $card->ID, '_payex_card', true);

				// Check reminder was sent
				if ( empty( $card_meta['reminder_expiring_sent'] ) ) {
					// Check is expiring
					$expire_date = strtotime( $card_meta['expire_date'] );
					if ( $check >= $expire_date ) {
						do_action('payex_card_expiring_mail', $card->ID);
					}
				}
			}
		}
	}

	/**
	 * Add Countries for SSN Field
	 * @param $checkout
	 *
	 * @return array
	 */
	public function add_countries_ssn($checkout) {
		$countries = array(
			'SE' => __( 'Sweden', 'woocommerce' ),
			'NO' => __( 'Norway', 'woocommerce' ),
		);

		return $countries;
	}
}

new WC_Payex_Payment();
