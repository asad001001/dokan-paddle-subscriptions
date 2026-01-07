<?php

namespace WeDevs\DokanPro\PaddleSubscriptions;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Webhook_Handler {

    /** @var Vendor_Subscription_Gateway */
    protected $gateway;

    public function __construct( Vendor_Subscription_Gateway $gateway ) {
        $this->gateway = $gateway;

        add_action( 'rest_api_init', function () {
            register_rest_route( 'dpvs/v1', '/webhook', [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle' ],
                'permission_callback' => '__return_true',
            ] );
        } );
    }

    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();

        // TODO: verify Paddle signature here using paddle_webhook_secret

        $event_type = strtolower( $payload['event_type'] ?? ( $payload['event'] ?? '' ) );

        switch ( $event_type ) {
            case 'subscription.trialing':
                $this->gateway->handle_webhook_activated( $payload );
                return [ 'status' => 'ok','data'=>$payload ];
                break;
                
            case 'subscription.activated':
                $this->gateway->handle_webhook_activated( $payload );
                return [ 'status' => 'ok','data'=>$payload ];
                break;

            case 'subscription.cancelled':
                $this->gateway->handle_webhook_cancelled( $payload );
                return [ 'status' => 'ok','data'=>$payload ];
                break;

            case 'subscription.updated':
            case 'subscription.renewed':
                $this->gateway->handle_webhook_renewed( $payload );
                return [ 'status' => 'ok','data'=>$payload ];
                break;
        }

        
    }
}
