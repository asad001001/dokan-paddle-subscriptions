<?php

namespace WeDevs\DokanPro\PaddleSubscriptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Paddle_Client {

    const SANDBOX_URL = 'https://sandbox-api.paddle.com';
    const LIVE_URL    = 'https://api.paddle.com';

    protected $api_key;
    protected $mode;
    protected $base_url;

    public function __construct() {
        $settings       = Admin_Settings::get_settings();
        $this->api_key  = $settings['api_key'];
        $this->mode     = $settings['mode'];
        $this->base_url = ( 'sandbox' === $this->mode ) ? self::SANDBOX_URL : self::LIVE_URL;
    }

    protected function post( $path, array $body ) {
        $url = trailingslashit( $this->base_url ) . ltrim( $path, '/' );

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            return [ 'success' => true, 'data' => $data ];
        }

        return [
            'success' => false,
            'error'   => $data['error']['detail'] ?? 'Paddle API error (HTTP ' . $code . ')',
            'data'    => $data,
        ];
    }

    /**
     * Create a Paddle product + price for a Dokan subscription pack.
     *
     * @return array{success:bool, data?:array, error?:string}
     */
    public function create_product_and_price_for_pack( $wp_product_id, $name, $amount, $interval_count, $interval_unit, $has_trial, $trial_interval, $trial_freq, $is_recurring ) {
        // 1. Create product.
        $product_payload = [
            'name'         => $name,
            'tax_category' => 'standard',
            'description'  => 'Dokan vendor subscription pack #' . $wp_product_id,
        ];

        $product_res = $this->post( '/products', $product_payload );
        if ( ! $product_res['success'] ) {
            return $product_res;
        }

        $paddle_product_id = $product_res['data']['data']['id'] ?? '';
        if ( ! $paddle_product_id ) {
            return [
                'success' => false,
                'error'   => 'Paddle product_id missing',
                'data'    => $product_res['data'],
            ];
        }

        // 2. Create price. Paddle expects the amount in the lowest currency unit (cents).
        $price_payload = [
            'description'   => $name,
            'product_id'    => $paddle_product_id,
            'name'          => $name,
            'unit_price'    => [
                'amount'        => (string) (int) round( (float) $amount * 100 ),
                'currency_code' => 'USD',
            ],
            'tax_mode'      => 'account_setting',
            'billing_cycle' => $is_recurring ? [
                'interval'  => (string) $interval_unit,
                'frequency' => (int) $interval_count,
            ] : null,
        ];

        if ( $has_trial ) {
            $price_payload['trial_period'] = [
                'interval'  => $trial_interval,
                'frequency' => (int) $trial_freq,
            ];
        }

        $price_res = $this->post( '/prices', $price_payload );
        if ( ! $price_res['success'] ) {
            return $price_res;
        }

        $paddle_price_id = $price_res['data']['data']['id'] ?? '';
        if ( ! $paddle_price_id ) {
            return [
                'success' => false,
                'error'   => 'Paddle price_id missing',
                'data'    => $price_res['data'],
            ];
        }

        return [
            'success' => true,
            'data'    => [
                'product_id' => $paddle_product_id,
                'price_id'   => $paddle_price_id,
            ],
        ];
    }

    public function cancel_subscription( $subscription_id, $effective_from = 'next_billing_period' ) {
        $subscription_id = urlencode( $subscription_id );
        return $this->post( "/subscriptions/{$subscription_id}/cancel", [
            'effective_from' => $effective_from,
        ] );
    }
}
