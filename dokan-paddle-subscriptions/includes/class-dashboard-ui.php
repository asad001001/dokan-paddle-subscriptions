<?php

namespace WeDevs\DokanPro\PaddleSubscriptions;

use DokanPro\Modules\Subscription\Helper as SubscriptionHelper;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Dashboard_UI {

    /** @var Vendor_Subscription_Gateway */
    protected $gateway;

    public function __construct( Vendor_Subscription_Gateway $gateway ) {
        $this->gateway = $gateway;

        add_filter( 'dokan_get_dashboard_nav', [ $this, 'add_menu' ] );
        add_filter( 'dokan_query_var_filter', [ $this, 'add_query_var' ] );
        add_action( 'dokan_load_custom_template', [ $this, 'load_template' ] );
        add_action( 'template_redirect', [ $this, 'maybe_handle_cancel_request' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /** Registers /dashboard/buy-subscription/. */
    public function add_query_var( $vars ) {
        $vars[] = 'buy-subscription';

        return $vars;
    }

    public function add_menu( $urls ) {
        $urls['buy-subscription'] = [
            'title' => __( 'Subscription', 'dokan' ),
            'icon'  => '<i class="fas fa-gem"></i>',
            'url'   => dokan_get_navigation_url( 'buy-subscription' ),
            'pos'   => 90,
        ];

        return $urls;
    }

    public function enqueue_scripts() {
        // Only load on the vendor dashboard for logged-in users (avoids leaking data on public pages).
        if ( ! is_user_logged_in() || ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) {
            return;
        }

        wp_enqueue_script(
            'dpvs-vendor-subscriptions',
            DPVS_URL . 'assets/js/vendor-subscriptions.js',
            [ 'jquery' ],
            DPVS_VERSION,
            true
        );

        $vendor_id = get_current_user_id();

        wp_localize_script( 'dpvs-vendor-subscriptions', 'DPVS', [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'currentUserId' => $vendor_id,
            'currentEmail'  => wp_get_current_user()->user_email,
            'nonce'         => wp_create_nonce( 'dpvs_nonce' ),
        ] );
    }

    public function load_template( $query_vars ) {
        if ( ! isset( $query_vars['buy-subscription'] ) ) {
            return;
        }

        $settings     = Admin_Settings::get_settings();
        $vendor_id    = get_current_user_id();
        $subscription = $this->gateway->get_vendor_subscription_info( $vendor_id );

        // All published Dokan subscription packs.
        $packs = new WP_Query( [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'product_pack',
                ],
            ],
        ] );

        $available_packs = array_values( array_filter(
            $packs->posts,
            [ SubscriptionHelper::class, 'is_subscription_pack_available' ]
        ) );

        wc_get_template(
            'vendor-subscription-status.php',
            [
                'subscription'    => $subscription,
                'available_packs' => $available_packs,
                'settings'        => $settings,
            ],
            '',
            DPVS_DIR . 'templates/'
        );
    }

    public function maybe_handle_cancel_request() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        if ( empty( $_POST['dpvs_action'] ) || 'cancel_subscription' !== $_POST['dpvs_action'] ) {
            return;
        }

        if ( empty( $_POST['dpvs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dpvs_nonce'] ) ), 'dpvs_cancel_subscription' ) ) {
            return;
        }

        $vendor_id = get_current_user_id();
        $result    = $this->gateway->cancel_from_dashboard( $vendor_id );

        $url = dokan_get_navigation_url( 'buy-subscription' );

        if ( $result['success'] ) {
            wp_safe_redirect( add_query_arg( 'dpvs_cancel', '1', $url ) );
        } else {
            wp_safe_redirect( add_query_arg( 'dpvs_cancel_failed', '1', $url ) );
        }

        exit;
    }
}
