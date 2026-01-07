<?php
/**
 * Plugin Name: Dokan Paddle Vendor Subscriptions
 * Description: Paddle-based subscription gateway for Dokan vendor subscription packs.
 * Author: Asad + ChatGPT
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DPVS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPVS_URL', plugin_dir_url( __FILE__ ) );

require_once DPVS_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', function () {
    // We still need Dokan + WooCommerce for the gateway, but settings page will exist regardless.
    if ( ! class_exists( 'WeDevs_Dokan' ) || ! class_exists( 'WooCommerce' ) ) {
        // Settings page still loads (Plugin::instance will run), but gateway init will bail early.
    }

    \WeDevs\DokanPro\PaddleSubscriptions\Plugin::instance();
} );
