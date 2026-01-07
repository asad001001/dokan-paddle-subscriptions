<?php

namespace WeDevs\DokanPro\PaddleSubscriptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    protected static $instance;

    public $admin_settings;
    public $paddle;
    public $gateway;
    public $dashboard_ui;
    public $webhooks;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init();
    }

    private function includes() {
        require_once DPVS_DIR . 'includes/class-admin-settings.php';
        require_once DPVS_DIR . 'includes/class-paddle-client.php';
        require_once DPVS_DIR . 'includes/class-vendor-subscription-gateway.php';
        require_once DPVS_DIR . 'includes/class-dashboard-ui.php';
        require_once DPVS_DIR . 'includes/class-webhook-handler.php';
    }

    private function init() {
        // Settings page always available
        $this->admin_settings = new Admin_Settings();

        // Gateway logic only if Dokan Subscription is there
        if ( ! class_exists( '\DokanPro\Modules\Subscription\SubscriptionPack' )
            || ! class_exists( '\DokanPro\Modules\Subscription\Helper' ) ) {
            return;
        }

        // Only run gateway if enabled in our own settings
        $settings = Admin_Settings::get_settings();
        if ( $settings['enabled'] !== '1' ) {
            return;
        }

        $this->paddle      = new Paddle_Client();
        $this->gateway     = new Vendor_Subscription_Gateway( $this->paddle );
        $this->dashboard_ui = new Dashboard_UI( $this->gateway );
        $this->webhooks    = new Webhook_Handler( $this->gateway );
    }
}
