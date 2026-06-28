<?php
/**
 * Embedded Checkout 左カラム：プラン訴求カード
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$vm = is_array($args['vm'] ?? null) ? $args['vm'] : [];
$plan_name = (string) ($vm['plan_name'] ?? 'Matchプラン');
$price_label = (string) ($vm['price_label'] ?? '');
$monthly_fee_display = (string) ($vm['monthly_fee_display'] ?? '');
$minimum_note = (string) ($vm['minimum_note'] ?? '');
$is_recommended = !empty($vm['is_recommended']);
$trial_banner = (string) ($vm['trial_banner'] ?? '');
$trial_days_remaining = (int) ($vm['trial_days_remaining'] ?? 0);
$billing_after_trial_label = (string) ($vm['billing_after_trial_label'] ?? '');
$feature_items = is_array($vm['feature_items'] ?? null) ? $vm['feature_items'] : [];
$product_plan = (string) ($vm['product_plan'] ?? 'match');
?>

<aside class="payment-checkout-plan-card" aria-label="契約プランの内容">
    <?php if ($is_recommended) : ?>
    <span class="payment-checkout-plan-card__badge">おすすめプラン</span>
    <?php endif; ?>

    <h2 class="payment-checkout-plan-card__name"><?php echo esc_html($plan_name); ?></h2>

    <p class="payment-checkout-plan-card__price">
        <?php if ($monthly_fee_display !== '') : ?>
            <span class="payment-checkout-plan-card__price-amount"><?php echo esc_html($monthly_fee_display); ?></span>
            <span class="payment-checkout-plan-card__price-unit">/ 月（税込）</span>
        <?php else : ?>
            <?php echo esc_html($price_label); ?>
        <?php endif; ?>
    </p>

    <?php if ($minimum_note !== '') : ?>
    <p class="payment-checkout-plan-card__minimum"><?php echo esc_html($minimum_note); ?></p>
    <?php endif; ?>

    <?php if ($trial_banner !== '') : ?>
    <div class="payment-checkout-plan-card__trial-banner" role="status">
        <p class="payment-checkout-plan-card__trial-title"><?php echo esc_html($trial_banner); ?></p>
        <?php if ($trial_days_remaining > 0) : ?>
        <p class="payment-checkout-plan-card__trial-days">残り<?php echo (int) $trial_days_remaining; ?>日</p>
        <?php endif; ?>
        <?php if ($billing_after_trial_label !== '') : ?>
        <p class="payment-checkout-plan-card__trial-after"><?php echo esc_html($billing_after_trial_label); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($feature_items !== []) : ?>
    <p class="payment-checkout-plan-card__features-title">チーム運営に必要な機能がすべて使えます</p>
    <ul class="payment-checkout-plan-card__features" role="list">
        <?php foreach ($feature_items as $feature_item) : ?>
        <li class="payment-checkout-plan-card__feature">
            <span class="payment-checkout-plan-card__feature-icon" aria-hidden="true">
                <?php
                if (function_exists('aidunite_render_theme_icon')) {
                    echo aidunite_render_theme_icon(
                        (string) ($feature_item['icon'] ?? 'check_circle'),
                        ['width' => '20', 'height' => '20'],
                        'aidunite-icon--inline'
                    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
            </span>
            <span class="payment-checkout-plan-card__feature-body">
                <span class="payment-checkout-plan-card__feature-title"><?php echo esc_html((string) ($feature_item['title'] ?? '')); ?></span>
                <?php if (!empty($feature_item['text'])) : ?>
                <span class="payment-checkout-plan-card__feature-text"><?php echo esc_html((string) $feature_item['text']); ?></span>
                <?php endif; ?>
            </span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($product_plan === 'match') : ?>
    <div class="payment-checkout-plan-card__highlight">
        <span class="payment-checkout-plan-card__highlight-icon" aria-hidden="true">
            <?php
            if (function_exists('aidunite_render_theme_icon')) {
                echo aidunite_render_theme_icon('emoji_events', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
        </span>
        <span class="payment-checkout-plan-card__highlight-body">
            <span class="payment-checkout-plan-card__highlight-title">初回試合成立をサポート</span>
            <span class="payment-checkout-plan-card__highlight-text">対戦相手探しから日程調整まで、試合マッチングを全力サポートします。</span>
        </span>
    </div>
    <?php endif; ?>
</aside>
