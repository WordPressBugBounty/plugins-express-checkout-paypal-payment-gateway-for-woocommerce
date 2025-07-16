<?php
/*
 * Plugin Name: Payment Gateway Plugin for PayPal WooCommerce ( Free )
 * Plugin URI: https://wordpress.org/plugins/express-checkout-paypal-payment-gateway-for-woocommerce/
 * Description: Accepts payments via PayPal, Credit/Debit cards, Paypal Credit, or Local Payment Methods based on country/device using PayPal Express/Smart button checkout.
 * Author: WebToffee
 * Author URI: https://www.webtoffee.com/product/paypal-express-checkout-gateway-for-woocommerce/
 * Version: 1.9.2
 * * WC requires at least: 3.0
 * WC tested up to: 10.0.2
 * Text Domain: express-checkout-paypal-payment-gateway-for-woocommerce
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 5.6
 * Requires PHP: 5.6
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'EH_PAYPAL_MAIN_PATH' ) ) {
	define( 'EH_PAYPAL_MAIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'EH_PAYPAL_MAIN_URL' ) ) {
	define( 'EH_PAYPAL_MAIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'EH_PAYPAL_VERSION' ) ) {
	define( 'EH_PAYPAL_VERSION', '1.9.2' );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';


if ( is_plugin_active( 'eh-paypal-express-checkout/eh-paypal-express-checkout.php' ) ) {
	deactivate_plugins( plugin_basename( __FILE__ ) );
	wp_die( esc_html__( 'Oops! PREMIUM Version of this Plugin Installed. Please uninstall the PREMIUM Version before activating BASIC', 'express-checkout-paypal-payment-gateway-for-woocommerce' ), '', array( 'back_link' => 1 ) );

	return;
} else {

	add_action( 'plugins_loaded', 'eh_paypal_check', 99 );

	function eh_paypal_check() {

		if ( class_exists( 'WooCommerce' ) ) {

			register_activation_hook( __FILE__, 'eh_paypal_express_init_log' );
			include EH_PAYPAL_MAIN_PATH . 'includes/log.php';

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'eh_paypal_express_plugin_action_links' );
			function eh_paypal_express_plugin_action_links( $links ) {
				$setting_link = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=eh_paypal_express' );
				$plugin_links = array(
					'<a href="' . $setting_link . '">' . __( 'Settings', 'express-checkout-paypal-payment-gateway-for-woocommerce' ) . '</a>',
					'<a href="https://www.webtoffee.com/product/paypal-express-checkout-gateway-for-woocommerce/?utm_source=free_plugin_sidebar&utm_medium=Paypal_basic&utm_campaign=Paypal&utm_content=' . EH_PAYPAL_VERSION . '" target="_blank" style="color:#3db634;">' . __( 'Premium Upgrade', 'express-checkout-paypal-payment-gateway-for-woocommerce' ) . '</a>',
					'<a href="https://wordpress.org/support/plugin/express-checkout-paypal-payment-gateway-for-woocommerce/" target="_blank">' . __( 'Support', 'express-checkout-paypal-payment-gateway-for-woocommerce' ) . '</a>',
					'<a href="https://wordpress.org/support/plugin/express-checkout-paypal-payment-gateway-for-woocommerce/reviews/" target="_blank">' . __( 'Review', 'express-checkout-paypal-payment-gateway-for-woocommerce' ) . '</a>',
				);

				if ( array_key_exists( 'deactivate', $links ) ) {
					$links['deactivate'] = str_replace( '<a', '<a class="ehpypl-deactivate-link"', $links['deactivate'] );
				}

				return array_merge( $plugin_links, $links );
			}
		} else {
			add_action( 'admin_notices', 'eh_paypal_express_wc_admin_notices', 99 );
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	}
	function eh_paypal_express_wc_admin_notices() {
		is_admin() && add_filter(
			'gettext',
			function( $translated_text, $untranslated_text, $domain ) {
				$old = array(
					'Plugin <strong>deactivated</strong>.',
					'Selected plugins <strong>deactivated</strong>.',
					'Plugin deactivated.',
					'Selected plugins deactivated.',
					'Plugin <strong>activated</strong>.',
					'Selected plugins <strong>activated</strong>.',
					'Plugin activated.',
					'Selected plugins activated.',
				);
				$new = "<span style='color:red'>PayPal Express Payment for WooCommerce (BASIC) (WebToffee)-</span> Plugin Needs WooCommerce to Work.";
				if ( in_array( $untranslated_text, $old, true ) ) {
					$translated_text = $new;
				}
				return $translated_text;
			},
			99,
			3
		);
	}
	function eh_paypal_express_init_log() {
		if ( version_compare( WC()->version, '2.7.0', '>=' ) ) {
			$log      = wc_get_logger();
			$init_msg = Eh_PayPal_Log::init_log();
			$context  = array( 'source' => 'eh_paypal_express_log' );
			$log->log( 'debug', $init_msg, $context );
		} else {
			$log      = new WC_Logger();
			$init_msg = Eh_PayPal_Log::init_log();
			$log->add( 'eh_paypal_express_log', $init_msg );
		}
	}

	function eh_paypal_express_run() {
		static $eh_paypal_plugin;
		if ( ! isset( $eh_paypal_plugin ) ) {
			require_once EH_PAYPAL_MAIN_PATH . 'includes/class-eh-paypal-init-handler.php';
			$eh_paypal_plugin = new Eh_Paypal_Express_Handlers();
		}
		return $eh_paypal_plugin;
	}
	eh_paypal_express_run()->express_run();

	/*
	*   When Skip Review option disabled, Prevent WC order creation and divert to our order creation process for prevent creating twise order
	*
	*/

	add_action( 'woocommerce_checkout_process', 'get_order_processed' );
	function get_order_processed() {

		if ( isset( WC()->session->eh_pe_checkout['order_id'] ) && isset( WC()->session->eh_pe_set['skip_review_disabled'] ) && ( 'true' === WC()->session->eh_pe_set['skip_review_disabled'] ) ) {
			$order_id = WC()->session->eh_pe_checkout['order_id'];
			$order    = wc_get_order( $order_id );

			$eh_paypal_express = new Eh_PayPal_Express_Payment();
			$eh_paypal_express->process_payment( $order_id );

			unset( WC()->session->eh_pe_set );

		}
	}
}


add_action( 'init', 'load_ehpypl_plugin_textdomain' );

/**
 * Handle localization
 */
function load_ehpypl_plugin_textdomain() {
	load_plugin_textdomain( 'express-checkout-paypal-payment-gateway-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	add_thickbox();
}


/*
 *  Displays update information for a plugin.
 */
function eh_express_checkout_paypal_payment_gateway_for_woocommerce_update_message( $data, $response ) {
	if ( isset( $data['upgrade_notice'] ) ) {
		add_action( 'admin_print_footer_scripts', 'eh_express_checkout_paypal_payment_gateway_for_woocommerce_plugin_screen_update_js' ); // fix for more than one alert text showing while updating the plugin
			$msg = str_replace( array( '<p>', '</p>' ), array( '<div>', '</div>' ), $data['upgrade_notice'] );
			$msg = str_replace( array( '<p>', '</p>' ), array( '<div>', '</div>' ), $data['upgrade_notice'] );
			echo '<style type="text/css">
            #express-checkout-paypal-payment-gateway-for-woocommerce-update .update-message p:last-child{ display:none;}     
            #express-checkout-paypal-payment-gateway-for-woocommerce-update ul{ list-style:disc; margin-left:30px;}
            .wt-update-message{ padding-left:30px;}
            </style>
            <div class="update-message wt-update-message">' . wp_kses_post( wpautop( $msg ) ) . '</div>';
	}
}
add_action( 'in_plugin_update_message-express-checkout-paypal-payment-gateway-for-woocommerce/express-checkout-paypal-payment-gateway-for-woocommerce.php', 'eh_express_checkout_paypal_payment_gateway_for_woocommerce_update_message', 10, 2 );

if ( ! function_exists( 'eh_express_checkout_paypal_payment_gateway_for_woocommerce_plugin_screen_update_js' ) ) {
	function eh_express_checkout_paypal_payment_gateway_for_woocommerce_plugin_screen_update_js() {
		?>
			<script>
				( function( $ ){
					var update_dv=$( '#express-checkout-paypal-payment-gateway-for-woocommerce-update');
					update_dv.find('.wt-update-message').next('p').remove();
					update_dv.find('a.update-link:eq(0)').click(function(){
						$('.wt-update-message').remove();
					});
				})( jQuery );
			</script>
		<?php
	}
}

//Decale compatibility with HPOS table
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
