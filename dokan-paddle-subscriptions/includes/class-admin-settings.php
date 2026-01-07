<?php

namespace WeDevs\DokanPro\PaddleSubscriptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Settings {

    const OPTION_KEY = 'dpvs_paddle_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'maybe_save' ] );
    }

    public static function get_settings() {
        $defaults = [
            'enabled'        => '1',
            'mode'           => 'sandbox',
            'api_key'        => '',
            'webhook_secret' => '',
        ];

        $saved = get_option( self::OPTION_KEY, [] );

        return wp_parse_args( $saved, $defaults );
    }

    public function add_menu() {
        // Top-level menu, fully standalone (not under Dokan)
        add_menu_page(
            __( 'Paddle Subscriptions', 'dokan' ),          // page title
            __( 'Paddle Subscriptions', 'dokan' ),          // menu title
            'manage_options',                               // capability
            'dpvs-paddle-subscriptions',                    // slug
            [ $this, 'render_page' ],                       // callback
            'dashicons-admin-generic',                      // icon
            56                                              // position
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Paddle Vendor Subscriptions', 'dokan' ); ?></h1>

            <?php if ( isset( $_GET['dpvs_saved'] ) ) : ?>
                <div class="updated notice is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'dokan' ); ?></p></div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'dpvs_save_settings', 'dpvs_nonce' ); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dpvs_enabled"><?php esc_html_e( 'Enable Paddle Subscriptions', 'dokan' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="dpvs_settings[enabled]" id="dpvs_enabled" value="1" <?php checked( $settings['enabled'], '1' ); ?> />
                                    <?php esc_html_e( 'Enable handling Dokan vendor subscriptions via Paddle.', 'dokan' ); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dpvs_mode"><?php esc_html_e( 'Mode', 'dokan' ); ?></label>
                            </th>
                            <td>
                                <select name="dpvs_settings[mode]" id="dpvs_mode">
                                    <option value="sandbox" <?php selected( $settings['mode'], 'sandbox' ); ?>><?php esc_html_e( 'Sandbox', 'dokan' ); ?></option>
                                    <option value="live" <?php selected( $settings['mode'], 'live' ); ?>><?php esc_html_e( 'Live', 'dokan' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Choose Sandbox while testing; switch to Live for production.', 'dokan' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dpvs_api_key"><?php esc_html_e( 'Paddle API Key', 'dokan' ); ?></label>
                            </th>
                            <td>
                                <input type="password" class="regular-text" name="dpvs_settings[api_key]" id="dpvs_api_key" value="<?php echo esc_attr( $settings['api_key'] ); ?>" />
                                <p class="description"><?php esc_html_e( 'Your Paddle secret API key.', 'dokan' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dpvs_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'dokan' ); ?></label>
                            </th>
                            <td>
                                <input type="password" class="regular-text" name="dpvs_settings[webhook_secret]" id="dpvs_webhook_secret" value="<?php echo esc_attr( $settings['webhook_secret'] ); ?>" />
                                <p class="description"><?php esc_html_e( 'Used to verify Paddle webhook signatures.', 'dokan' ); ?></p>
                            </td>
                        </tr>
                        <!-- client side token -->
                        <tr>
                            <th scope="row">
                                <label for="dpvs_client_token"><?php esc_html_e( 'Client Side Token', 'dokan' ); ?></label>
                            </th>
                            <td>
                                <input type="password" class="regular-text" name="dpvs_settings[client_token]" id="dpvs_client_token" value="<?php echo esc_attr( $settings['client_token'] ); ?>" />
                                <p class="description"><?php esc_html_e( 'Used to verify Paddle payment on front end.', 'dokan' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function maybe_save() {
        if ( ! isset( $_POST['dpvs_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['dpvs_nonce'], 'dpvs_save_settings' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $raw = isset( $_POST['dpvs_settings'] ) ? (array) $_POST['dpvs_settings'] : [];

        $settings = [
            'enabled'        => ! empty( $raw['enabled'] ) ? '1' : '0',
            'mode'           => ( isset( $raw['mode'] ) && in_array( $raw['mode'], [ 'sandbox', 'live' ], true ) ) ? $raw['mode'] : 'sandbox',
            'api_key'        => isset( $raw['api_key'] ) ? trim( $raw['api_key'] ) : '',
            'webhook_secret' => isset( $raw['webhook_secret'] ) ? trim( $raw['webhook_secret'] ) : '',
            'client_token'   => isset( $raw['client_token'] ) ? trim( $raw['client_token'] ) : ''
        ];

        update_option( self::OPTION_KEY, $settings );

        wp_safe_redirect( add_query_arg(
            'dpvs_saved',
            '1',
            menu_page_url( 'dpvs-paddle-subscriptions', false )
        ) );
        exit;
    }
}
