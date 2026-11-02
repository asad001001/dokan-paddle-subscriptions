<?php
/**
 * Paddle webhook endpoint: POST /wp-json/dpvs/v1/webhook
 *
 * @package DokanPaddleSubscriptions
 */

namespace WeDevs\DokanPro\PaddleSubscriptions;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Webhook_Handler {

    /** Max allowed age (seconds) of a signed webhook, to block replay attacks. */
    const SIGNATURE_TOLERANCE = 300;

    /** @var Vendor_Subscription_Gateway */
    protected $gateway;

    public function __construct( Vendor_Subscription_Gateway $gateway ) {
        $this->gateway = $gateway;

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'dpvs/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle' ],
            // Public endpoint: authenticity is checked via the Paddle-Signature header.
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Verify the Paddle Billing signature.
     *
     * Header format: "ts=1671552777;h1=<hmac-sha256 of "ts:raw_body">".
     *
     * @param WP_REST_Request $request Incoming request.
     * @return true|WP_Error
     */
    protected function verify_signature( WP_REST_Request $request ) {
        $secret = Admin_Settings::get_settings()['webhook_secret'];

        if ( '' === $secret ) {
            return new WP_Error( 'dpvs_no_secret', 'Webhook secret is not configured.', [ 'status' => 500 ] );
        }

        $header = (string) $request->get_header( 'paddle-signature' );
        $parts  = [];

        foreach ( explode( ';', $header ) as $pair ) {
            $kv = explode( '=', $pair, 2 );
            if ( 2 === count( $kv ) ) {
                $parts[ trim( $kv[0] ) ] = trim( $kv[1] );
            }
        }

        if ( empty( $parts['ts'] ) || empty( $parts['h1'] ) ) {
            return new WP_Error( 'dpvs_bad_signature', 'Missing signature.', [ 'status' => 401 ] );
        }

        if ( abs( time() - (int) $parts['ts'] ) > self::SIGNATURE_TOLERANCE ) {
            return new WP_Error( 'dpvs_expired_signature', 'Signature expired.', [ 'status' => 401 ] );
        }

        $expected = hash_hmac( 'sha256', $parts['ts'] . ':' . $request->get_body(), $secret );

        if ( ! hash_equals( $expected, $parts['h1'] ) ) {
            return new WP_Error( 'dpvs_bad_signature', 'Invalid signature.', [ 'status' => 401 ] );
        }

        return true;
    }

    public function handle( WP_REST_Request $request ) {
        $verified = $this->verify_signature( $request );
        if ( is_wp_error( $verified ) ) {
            return $verified;
        }

        $payload    = (array) $request->get_json_params();
        $event_type = strtolower( $payload['event_type'] ?? ( $payload['event'] ?? '' ) );

        switch ( $event_type ) {
            case 'subscription.trialing':
            case 'subscription.activated':
                $this->gateway->handle_webhook_activated( $payload );
                break;

            case 'subscription.canceled': // Paddle Billing spelling.
            case 'subscription.cancelled':
                $this->gateway->handle_webhook_cancelled( $payload );
                break;

            case 'subscription.updated':
            case 'subscription.renewed':
                $this->gateway->handle_webhook_renewed( $payload );
                break;
        }

        // Always acknowledge so Paddle does not keep retrying unhandled event types.
        return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }
}
