<?php
/*
 * Plugin Name: MerchantX Gateway for WooCommerce
 * Plugin URI: https://merchantx.com/
 * Description: Adds the MerchantX Gateway for WooCommerce.
 * Version: 1.0
 * Author: MerchantX
 * Author URI: https://merchantx.com/
 * Developer: Sean Conklin
 * Developer URI: https://codedcommerce.com/
 * Text Domain: woo-merchantx
 * Domain Path: /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.5.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }


// Gateway Endpoint
define( 'MERCHANTX_QUERY_URL', 'https://merchantx.transactiongateway.com/api/query.php' );
define( 'MERCHANTX_3STEP_URL', 'https://merchantx.transactiongateway.com/api/v2/three-step' );
$settings = get_option( 'woocommerce_merchantx_settings' );
define( 'MERCHANTX_API_KEY', isset( $settings['apikey'] ) ? $settings['apikey'] : ''  );


// Main WP Hooks
add_action( 'plugins_loaded', [ 'merchantx_plugin', 'plugins_loaded' ] );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ 'merchantx_plugin', 'plugin_action_links' ] );


// Plugin Class
class merchantx_plugin {


	// Run At Plugins Loaded
	static function plugins_loaded() {

		// Check For Woo Gateway Class
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action( 'admin_notices', [ 'merchantx_plugin', 'admin_notices' ] );
			return;
		}

		// Adds Gateway
		add_filter( 'woocommerce_payment_gateways', [ 'merchantx_plugin', 'woocommerce_payment_gateways' ] );

		// Include the WooCommerce Custom Payment Gateways classes
		require_once( 'class.gateway.php' );
		require_once( 'class.gateway-helpers.php' );

		// Gateway WP Hooks
		add_action( 'wp_ajax_nopriv_merchantx_stepOne_addBilling', 'merchantx_stepOne_addBilling' );
		add_action( 'wp_ajax_merchantx_stepOne_addBilling', 'merchantx_stepOne_addBilling' );
		add_action( 'wp_ajax_nopriv_merchantx_stepOne', 'merchantx_stepOne' );
		add_action( 'wp_ajax_merchantx_stepOne', 'merchantx_stepOne' );
		add_action( 'wp_ajax_nopriv_merchantx_deletePaymentMethod', 'merchantx_deletePaymentMethod' );
		add_action( 'wp_ajax_merchantx_deletePaymentMethod', 'merchantx_deletePaymentMethod' );
		add_action( 'wp_enqueue_scripts', 'merchantx_pw_load_scripts' );
	}


	// WooCommerce Not Found Notice
	static function admin_notices() {
		echo sprintf(
			'<div class="error"><p>%s</p></div>',
			sprintf(
				__( 'WooCommerce Custom Payment Gateways depends on the last version of %s to work!', 'woo-merchantx' ),
				'<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
			)
		);
	}


	// Adds Gateway
	static function woocommerce_payment_gateways( $methods ) {
		$methods[] = 'MerchantX_Payment_Gateway';
		return $methods;
	}


	// Plugin Action Links
	static function plugin_action_links( $links ) {
		$settings = [
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=merchantx' ),
				__( 'Settings', 'woo-merchantx' )
			),
		];
		return array_merge( $settings, $links );
	}

// End Plugin Class
}
