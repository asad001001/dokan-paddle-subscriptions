<?php
/**
 * Plugin Name:       Dokan Paddle Vendor Subscriptions
 * Description:       Sell Dokan vendor subscription packs through Paddle Billing (checkout, webhooks, cancellation).
 * Version:           1.1.0
 * Author:            Asad Ali
 * Author URI:        https://www.linkedin.com/in/asad-ali-kamboh/
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 *
 * @package DokanPaddleSubscriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DPVS_VERSION', '1.1.0' );
define( 'DPVS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPVS_URL', plugin_dir_url( __FILE__ ) );

require_once DPVS_DIR . 'includes/class-plugin.php';

/*
 * The settings page always loads. The gateway itself only boots when
 * Dokan Pro's Subscription module is active (checked in Plugin::init()).
 */
add_action( 'plugins_loaded', [ \WeDevs\DokanPro\PaddleSubscriptions\Plugin::class, 'instance' ] );
