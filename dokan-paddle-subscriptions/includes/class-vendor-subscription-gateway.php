<?php

namespace WeDevs\DokanPro\PaddleSubscriptions;

use DokanPro\Modules\Subscription\SubscriptionPack;
use DokanPro\Modules\Subscription\Helper as SubscriptionHelper;
use DokanPro\Modules\Subscription\SubscriptionOrderMetaBuilder;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Vendor_Subscription_Gateway {

    const USER_META_SUB_ID = '_dokan_paddle_vendor_subscription_id';

    /** @var Paddle_Client */
    protected $paddle;

    public function __construct( Paddle_Client $paddle ) {
        $this->paddle = $paddle;

        // When admin saves a subscription pack, sync with Paddle
        add_action(
            'woocommerce_process_product_meta_product_pack',
            [ $this, 'handle_pack_saved' ],
            99,
            1
        );
    }

    /**
     * On Dokan subscription pack save, create (or update) Paddle product + price.
     */
    public function handle_pack_saved( $product_id ) {
        if ( ! class_exists( SubscriptionHelper::class ) ) {
            return;
        }

        $pack = new SubscriptionPack( $product_id, 0 );

        $amount         = (float) $pack->get_price();
        $interval_count = (int) $pack->get_recurring_interval();
        $interval_unit  = $pack->get_period_type(); // day|week|month|year
        $pack_title     = $pack->get_package_title();
        $is_recurring   = $pack->is_recurring();
        $has_trial      = $pack->is_trial();
        $trial_interval = $pack->get_trial_period_types();
        $trial_freq     = $pack->get_trial_range();

        if ( $amount <= 0 || ! $interval_unit ) {
            return;
        }

        $existing_price_id   = get_post_meta( $product_id, '_dokan_paddle_price_id', true );
        $existing_product_id = get_post_meta( $product_id, '_dokan_paddle_product_id', true );

        // For simplicity: always create a new price/product if not present
        if ( ! $existing_price_id || ! $existing_product_id ) {
            $result = $this->paddle->create_product_and_price_for_pack(
                $product_id,
                $pack_title,
                $amount,
                $interval_count,
                $interval_unit,
                $has_trial,
                $trial_interval,
                $trial_freq,
                $is_recurring
            );
        } else {
            // You can implement update logic if Paddle supports it; for now just skip
            return;
        }

        if ( ! $result['success'] ) {
            update_post_meta( $product_id, '_dokan_paddle_last_error', $result['error'] );
            return;
        }

        $data        = $result['data'];
        $price_id    = $data['price_id'] ?? '';
        $product_rem = $data['product_id'] ?? '';

        if ( $price_id ) {
            update_post_meta( $product_id, '_dokan_paddle_price_id', $price_id );
        }

        if ( $product_rem ) {
            update_post_meta( $product_id, '_dokan_paddle_product_id', $product_rem );
        }
    }

    /**
     * Paddle Billing sends custom_data inside "data"; older payloads had it top-level.
     */
    protected function get_custom_data( array $payload ) {
        $custom = $payload['data']['custom_data'] ?? ( $payload['custom_data'] ?? [] );

        return is_array( $custom ) ? $custom : [];
    }

    /**
     * Handle Paddle webhook: subscription activated (or trial started).
     */
    public function handle_webhook_activated( array $payload ) {
        $custom = $this->get_custom_data( $payload );

        $vendor_id      = isset( $custom['user_id'] ) ? absint( $custom['user_id'] ) : 0;
        $pack_id        = isset( $custom['pack_id'] ) ? absint( $custom['pack_id'] ) : 0;
        $paddle_sub_id  = $payload['data']['id'] ?? ( $payload['subscription_id'] ?? '' );
        $next_billed_at = $payload['data']['next_billed_at'] ?? '';
        $status         = strtolower( $payload['data']['status'] ?? 'active' );

        if ( ! $vendor_id || ! $pack_id || ! $paddle_sub_id ) {
            return;
        }

        if ( ! in_array( $status, [ 'active', 'trialing' ], true ) ) {
            return;
        }

        $order = $this->create_subscription_order( $vendor_id, $pack_id, $paddle_sub_id );

        $subscription_pack = new SubscriptionPack( $pack_id, $vendor_id );
        $subscription_pack->activate_subscription( $order );

        update_user_meta( $vendor_id, self::USER_META_SUB_ID, $paddle_sub_id );

        if ( $next_billed_at ) {
            update_user_meta( $vendor_id, 'product_pack_enddate', $next_billed_at );
        }

        $builder = new SubscriptionOrderMetaBuilder( $order, $vendor_id, $pack_id );
        $builder->build();

        $order->add_order_note(
            sprintf( __( 'Paddle subscription activated. Subscription ID: %s', 'dokan' ), $paddle_sub_id )
        );
    }

    /**
     * Handle Paddle webhook: subscription renewed.
     */
    public function handle_webhook_renewed( array $payload ) {
        $custom       = $this->get_custom_data( $payload );
        $vendor_id    = isset( $custom['user_id'] ) ? absint( $custom['user_id'] ) : 0;
        $pack_id      = isset( $custom['pack_id'] ) ? absint( $custom['pack_id'] ) : 0;
        $next_billed  = $payload['data']['next_billed_at'] ?? '';

        if ( ! $vendor_id || ! $pack_id ) {
            return;
        }

        $subscription_pack = new SubscriptionPack( $pack_id, $vendor_id );
        $subscription_pack->reactivate_subscription();

        if ( $next_billed ) {
            update_user_meta( $vendor_id, 'product_pack_enddate', $next_billed );
        }
    }

    /**
     * Handle Paddle webhook: subscription cancelled.
     */
    public function handle_webhook_cancelled( array $payload ) {
        $custom    = $this->get_custom_data( $payload );
        $vendor_id = isset( $custom['user_id'] ) ? absint( $custom['user_id'] ) : 0;

        if ( ! $vendor_id ) {
            return;
        }

        $subscription_pack = new SubscriptionPack( 0, $vendor_id );
        $subscription_pack->cancel_subscription();

        delete_user_meta( $vendor_id, self::USER_META_SUB_ID );
    }

    /**
     * Cancel from vendor dashboard (server-side).
     */
    public function cancel_from_dashboard( $vendor_id ) {
        $paddle_sub_id = get_user_meta( $vendor_id, self::USER_META_SUB_ID, true );

        if ( ! $paddle_sub_id ) {
            return [ 'success' => false, 'error' => 'No Paddle subscription found.' ];
        }

        $result = $this->paddle->cancel_subscription( $paddle_sub_id, 'next_billing_period' );

        if ( $result['success'] ) {
            update_user_meta( $vendor_id, 'dokan_has_active_cancelled_subscrption', true );
        }

        return $result;
    }

    protected function create_subscription_order( $vendor_id, $pack_id, $paddle_sub_id ) {
        if ( ! function_exists( 'wc_create_order' ) ) {
            return new WC_Order();
        }

        $product = wc_get_product( $pack_id );
        $order   = wc_create_order( [ 'customer_id' => $vendor_id ] );

        if ( $product ) {
            $order->add_product( $product, 1 );
        }

        $order->set_payment_method( 'dokan_paddle_vendor_subscriptions' );
        $order->set_status( 'completed' );
        $order->calculate_totals();

        $order->update_meta_data( '_dokan_is_vendor_subscription_order', 'yes' );
        $order->update_meta_data( '_dokan_paddle_subscription_id', $paddle_sub_id );
        $order->save();

        return $order;
    }

    /**
     * Helper for dashboard: current subscription info.
     */
    public function get_vendor_subscription_info( $vendor_id ) {
        $subscription_order = SubscriptionHelper::get_subscription_order( $vendor_id );
        $pack_id            = SubscriptionHelper::get_subscription_pack_id( $vendor_id );
        $end_date           = SubscriptionHelper::get_pack_end_date( $vendor_id );
        $is_active          = SubscriptionHelper::vendor_has_subscription( $vendor_id );
        $paddle_sub_id      = get_user_meta( $vendor_id, self::USER_META_SUB_ID, true );

        return [
            'subscription_order' => $subscription_order,
            'pack_id'            => $pack_id,
            'end_date'           => $end_date,
            'is_active'          => $is_active,
            'subscription_id'    => $paddle_sub_id,
        ];
    }
}
