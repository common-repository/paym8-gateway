<?php
/**
 * Plugin Name: PayM8 Gateway
 * Description: Plugin for the WC payments gateway using the PayM8 Axis platform
 * Version: 1.1
 * Author: <a href="https://www.paym8.co.za/" target="_blank">PayM8</a>
 *
 * @package PayM8 Payments Gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wc paym8 init
 * Initialization of gateway
 *
 * @since 1.1.0
 */
function wc_paym8_init() {
	// do we have wc loaded?
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

		logger( "WC found! \n" );
		logger( "Loading classes \n" );

		require_once plugin_basename( 'includes/class-wc-gateway-paym8.php' );
		load_plugin_textdomain( 'wc-gateway-paym8', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

		if ( ! class_exists( 'WC_Gateway_PayM8' ) ) {
			logger( "PayM8 Gateway Class [WC_Gateway_PayM8] not found! \n" );
			return;
		}

		add_filter( 'woocommerce_payment_gateways', 'wc_paym8_add_gateway' );
		add_action( 'wp_enqueue_scripts', 'load_paym8_styles' );
	}
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_paym8_gateway_plugin_links' );
add_action( 'plugins_loaded', 'wc_paym8_init', 0 );
register_activation_hook( __FILE__, 'paym8_gateway_activation' );
register_deactivation_hook( __FILE__, 'paym8_gateway_deactivation' );

/**
 * Load paym8 styles
 * PayM8 styles for gateway
 *
 * @since 1.1.0
 */
function load_paym8_styles() {

	logger( "Loading styles \n" );
	wp_register_style( 'paym8_loader_styles', plugins_url( 'includes/css/paym8_loader.css', __FILE__ ), array(), '1.1' );
	wp_enqueue_style( 'paym8_loader_styles' );
}

/**
 * Wc paym8 gateway plugin links
 * Gateway plugin links
 *
 * Links.
 *
 * @param string $links The links param.
 *
 * @since 1.1.0
 */
function wc_paym8_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paym8_gateway' ) . '">' . __( 'Configure', 'wc_gateway_paym8' ) . '</a>',
		'<a href="https://www.paym8.co.za/contact-us/" target="_blank">' . __( 'Support', 'wc_gateway_paym8' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}

/**
 * Paym8 gateway activation
 * Activate gateway
 *
 * @since 1.1.0
 */
function paym8_gateway_activation() {
	register_uninstall_hook( __FILE__, 'paym8_uninstall_gateway' );
}

/**
 * Paym8 gateway deactivation
 * Deactivate gateway
 *
 * @since 1.1.0
 */
function paym8_gateway_deactivation() {
}

/**
 * Paym8 uninstall gateway
 * Uninstall gateway
 *
 * @since 1.0.0
 */
function paym8_uninstall_gateway() {
	logger( 'Uninstalling PaYM8 gateway, deleting options' );
	delete_option( 'woocommerce_paym8_gateway_settings' );

	wp_cache_flush();
}

/**
 * Wc_paym8_add_gateway
 * Add gateway to list
 *
 * Gateways.
 *
 * @param string $gateways The message param.
 *
 * @since 1.1.0
 */
function wc_paym8_add_gateway( $gateways ) {
	$gateways[] = 'WC_Gateway_PayM8';
	return $gateways;
}

/**
 * Logger
 * Logger data
 *
 * Message.
 *
 * @param string $message The message param.
 *
 * @since 1.1.0
 */
function logger( $message ) {
	$context = array( 'source' => 'paym8_plugin_setup' );
	$logger  = wc_get_logger();
	$logger->info( $message, $context );
}





