<?php
/**
 * Vendor subscription status + pack selection.
 *
 * @var array $subscription
 * @var array $available_packs
 */

use DokanPro\Modules\Subscription\Helper as SubscriptionHelper;

$sub_order     = $subscription['subscription_order'] ?? null;
$pack_id       = $subscription['pack_id'] ?? 0;
$end_date      = $subscription['end_date'] ?? '';
$is_active     = ! empty( $subscription['is_active'] );
$subscription_id = $subscription['subscription_id'] ?? '';
?>
<?php do_action( 'dokan_dashboard_wrap_start' ); ?>
<div class="dokan-dashboard-wrap">
    <?php do_action( 'dokan_dashboard_content_before' ); ?>
    <div class="dokan-dashboard-content">
        <?php do_action( 'dokan_dashboard_content_inside_before' ); ?>
        <article class="dokan-subscription-box dokan-subscription-box--shadow">
            <?php if ( $is_active && $pack_id ) : ?>
                <div class="dpvs-current-card">
                    <div class="dpvs-current-card__header">
                        <div class="dpvs-pill dpvs-pill--success">
                            <?php esc_html_e( 'Active', 'dokan' ); ?>
                        </div>
                        <p class="dpvs-current-card__title"><?php esc_html_e( 'Current Plan', 'dokan' ); ?></p>
                        <h3 class="dpvs-current-card__name"><?php echo esc_html( get_the_title( $pack_id ) ); ?></h3>
                    </div>
                    <div class="dpvs-current-card__meta">
                        <?php if ( $end_date ) : ?>
                            <p><strong><?php esc_html_e( 'Valid Until:', 'dokan' ); ?></strong> <?php echo esc_html( $end_date ); ?></p>
                        <?php endif; ?>
                        <?php if ( $subscription_id ) : ?>
                            <p><strong><?php esc_html_e( 'Paddle Subscription ID:', 'dokan' ); ?></strong> <?php echo esc_html( $subscription_id ); ?></p>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="dpvs-current-card__actions">
                        <?php wp_nonce_field( 'dpvs_cancel_subscription', 'dpvs_nonce' ); ?>
                        <input type="hidden" name="dpvs_action" value="cancel_subscription">
                        <button type="submit" class="dokan-btn dokan-btn-danger dpvs-btn">
                            <?php esc_html_e( 'Cancel Subscription', 'dokan' ); ?>
                        </button>
                    </form>
                </div>
            <?php else : ?>
                <div class="dpvs-current-card">
                    <h2 class="dpvs-current-card__title"><?php esc_html_e( 'Current Plan', 'dokan' ); ?></h2>
                    <p class="dpvs-current-card__name"><?php esc_html_e( 'No active subscription', 'dokan' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['dpvs_cancel'] ) ) : ?>
                <p class="dpvs-alert dpvs-alert--success"><?php esc_html_e( 'Cancellation requested. Your subscription will end at the next billing date.', 'dokan' ); ?></p>
            <?php elseif ( isset( $_GET['dpvs_cancel_failed'] ) ) : ?>
                <p class="dpvs-alert dpvs-alert--error"><?php esc_html_e( 'Failed to cancel subscription. Please contact support.', 'dokan' ); ?></p>
            <?php endif; ?>
        </article>

        <article class="dokan-subscription-box dokan-subscription-box--shadow" style="margin-top:20px;">
            <header class="dpvs-section-header">
                <h2><?php esc_html_e( 'Available Plans', 'dokan' ); ?></h2>
                <p class="dpvs-section-sub"><?php esc_html_e( 'Pick a plan that suits your store.', 'dokan' ); ?></p>
            </header>

            <?php if ( empty( $available_packs ) ) : ?>
                <p><?php esc_html_e( 'No subscription packs are available at the moment.', 'dokan' ); ?></p>
            <?php else : ?>
                <div class="dpvs-plans-grid">
                    <?php foreach ( $available_packs as $pid ) : ?>
                        <?php
                        $product          = wc_get_product( $pid );
                        $price_display    = $product ? $product->get_price_html() : '';
                        $paddle_price_id  = get_post_meta( $pid, '_dokan_paddle_price_id', true );
                        $is_current       = ( (int) $pid === (int) $pack_id );
                        ?>
                        <div class="dpvs-plan-card<?php echo $is_current ? ' dpvs-plan-card--active' : ''; ?>">
                            <div class="dpvs-plan-card__inner">
                                <p class="dpvs-plan-card__name"><?php echo esc_html( get_the_title( $pid ) ); ?></p>
                                <p class="dpvs-plan-card__price"><?php echo wp_kses_post( $price_display ); ?></p>
                                <ul class="dpvs-plan-meta">
                                    <li><span class="dpvs-plan-meta__label"><?php esc_html_e( 'Billing:', 'dokan' ); ?></span> <?php esc_html_e( 'Every 1 period', 'dokan' ); ?></li>
                                    <li><span class="dpvs-plan-meta__label"><?php esc_html_e( 'Product Limit:', 'dokan' ); ?></span> 2000</li>
                                    <li><span class="dpvs-plan-meta__label"><?php esc_html_e( 'Validity:', 'dokan' ); ?></span> 30 <?php esc_html_e( 'days', 'dokan' ); ?></li>
                                </ul>
                                <div class="dpvs-plan-card__body">
                                    <?php if ( $is_current ) : ?>
                                        <span class="dpvs-pill dpvs-pill--primary"><?php esc_html_e( 'Current Plan', 'dokan' ); ?></span>
                                    <?php elseif ( ! $paddle_price_id ) : ?>
                                        <span class="dpvs-pill dpvs-pill--muted"><?php esc_html_e( 'Not configured for Paddle', 'dokan' ); ?></span>
                                    <?php else : ?>
                                        <?php
                                        $action = 'buy';
                                        if ( $product && $is_active && $pack_id ) {
                                            $current_product = wc_get_product( $pack_id );
                                            if ( $current_product ) {
                                                $action = ( (float) $product->get_price() > (float) $current_product->get_price() ) ? 'upgrade' : 'downgrade';
                                            }
                                        }
                                        ?>
                                        <button
                                            class="dokan-btn dokan-btn-theme dpvs-pack-button dpvs-btn dpvs-btn--full"
                                            data-pack-id="<?php echo esc_attr( $pid ); ?>"
                                            data-price-id="<?php echo esc_attr( $paddle_price_id ); ?>"
                                            data-action="<?php echo esc_attr( $action ); ?>"
                                        >
                                            <?php
                                            if ( 'upgrade' === $action ) {
                                                esc_html_e( 'Upgrade with Paddle', 'dokan' );
                                            } elseif ( 'downgrade' === $action ) {
                                                esc_html_e( 'Downgrade with Paddle', 'dokan' );
                                            } else {
                                                esc_html_e( 'Subscribe with Paddle', 'dokan' );
                                            }
                                            ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
        <?php do_action( 'dokan_dashboard_content_inside_after' ); ?>
    </div>
    <?php do_action( 'dokan_dashboard_content_after' ); ?>
</div>
<?php do_action( 'dokan_dashboard_wrap_end' ); 
// print_r($settings)



?>
 <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    <script>
    <?php if($settings['enabled']){ ?>
    
        Paddle.Environment.set('<?= $settings['mode']; ?>');

        Paddle.Initialize({
            token: '<?= $settings['client_token']; ?>'
        });
        
        <?php } ?>
    </script>
        
<style>
.dokan-subscription-box {
    background: #fff;
    padding: 20px;
}

.dokan-subscription-box--shadow {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.dpvs-section-header {
    margin-bottom: 12px;
}

.dpvs-section-sub {
    color: #7a8a9b;
    margin: 0;
}

.dpvs-current-card__header {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.dpvs-current-card__title {
    margin: 8px 0 0;
    font-weight: 500;
    color: #1b2633;
}

.dpvs-current-card__name {
    margin: 4px 0 0;
    font-size: 22px;
    font-weight: 500;
    color: #FA4F26;
}

.dpvs-current-card__meta p {
    margin: 6px 0;
    color: #4b5563;
}

.dpvs-current-card__actions {
    margin-top: 14px;
}

.dpvs-plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-top: 12px;
}

.dpvs-plan-card {
    border: 1px solid #FA4F26;
    border-radius: 12px;
    background: #fff;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dpvs-plan-card--active {
    border-color: #FA4F26;
}

.dpvs-plan-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
}

.dpvs-plan-card__inner {
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    height: 100%;
}

.dpvs-plan-card__name {
    font-size: 18px;
    font-weight: 700;
    color: #1b2633;
    margin: 0;
}

.dpvs-plan-card__price {
    font-size: 22px;
    font-weight: 700;
    color: #FA4F26;
    margin: 0;
}

.dpvs-plan-card__body {
    margin-top: auto;
}

.dpvs-plan-meta {
    list-style: none;
    padding: 0;
    margin: 0 0 10px;
    color: #4b5563;
    font-size: 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.dpvs-plan-meta__label {
    font-weight: 700;
    color: #1b2633;
    margin-right: 6px;
}

.dpvs-pill {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.dpvs-pill--success {
    background: #e8f6ef;
    color: #0f9b4c;
}

.dpvs-pill--primary {
    background: #fff3eb;
    color: #FA4F26;
}

.dpvs-pill--muted {
    background: #edf2f7;
    color: #4b5563;
}

.dpvs-alert {
    padding: 10px 12px;
    border-radius: 6px;
    margin-top: 10px;
}

.dpvs-alert--success {
    background: #e8f6ef;
    color: #0f9b4c;
}

.dpvs-alert--error {
    background: #fdecef;
    color: #FA4F26;
}

.dpvs-btn {
    border-radius: 999px;
    font-weight: 700;
}

.dpvs-btn--full {
    width: 100%;
}

.dpvs-plan-card .dpvs-btn {
    background: #FA4F26;
    border-color: #FA4F26;
}

.dpvs-plan-card .dpvs-btn:hover {
    background: #d84d22;
    border-color: #d84d22;
}

.dpvs-plan-card--active .dpvs-btn {
    background: #1b2633;
    border-color: #1b2633;
}

@media (max-width: 767px) {
    .dpvs-plan-card__inner {
        padding: 16px;
    }

    .dpvs-plans-grid {
        grid-template-columns: 1fr;
    }
}
</style>