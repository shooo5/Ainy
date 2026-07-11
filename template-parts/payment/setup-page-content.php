<?php
/**
 * 契約・お支払いページ本体セクション
 *
 * @var array<string, mixed> $vm
 * @var array<string, mixed>|null $payment_flash
 * @var array<string, mixed>|null $leader_transfer_checkout
 * @var bool $club_plan_required_banner
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = is_array($args ?? null) ? $args : [];
$vm = is_array($args['vm'] ?? null) ? $args['vm'] : [];
$payment_flash = $args['payment_flash'] ?? null;
$leader_transfer_checkout = $args['leader_transfer_checkout'] ?? null;
$club_plan_required_banner = !empty($args['club_plan_required_banner']);
$primary_action = is_array($vm['primary_action'] ?? null) ? $vm['primary_action'] : [];
$primary_cta_type = (string) ($primary_action['cta_type'] ?? 'none');
$status_board_main_class = 'payment-setup-status-board__main';
if (empty($vm['show_free_period'])) {
    $status_board_main_class .= ' payment-setup-status-board__main--no-trial';
}
if (empty($vm['show_free_period']) && empty($vm['show_registration_complete']) && empty($vm['show_next_steps_column'])) {
    $status_board_main_class .= ' payment-setup-status-board__main--plan-only';
}
?>

<?php if (is_array($payment_flash) && !empty($payment_flash['message'])) : ?>
<div class="payment-setup-section payment-setup-flash payment-setup-flash--<?php echo esc_attr((string) ($payment_flash['type'] ?? 'info')); ?>" role="status">
    <p><?php echo esc_html((string) $payment_flash['message']); ?></p>
</div>
<?php endif; ?>

<?php if ($leader_transfer_checkout !== null) : ?>
<div class="payment-setup-section payment-setup-alert" role="alert">
    <h2 class="payment-setup-section__title">代表者への譲渡：お支払い設定が必要です</h2>
    <p>チームの代表者に任命されました。<strong><?php echo esc_html((string) ($leader_transfer_checkout['checkout_deadline'] ?? '')); ?></strong> までに Checkout を完了してください。</p>
</div>
<?php endif; ?>

<?php if (!empty($club_plan_required_banner)) : ?>
<div class="payment-setup-section payment-setup-alert">
    <h2 class="payment-setup-section__title">Clubプランが必要です</h2>
    <p>出欠管理・メンバー管理・保護者連絡などは Clubプランでご利用いただけます。</p>
    <p><button type="button" class="btn btn-primary js-payment-plan-select" data-plan-id="plan_club" data-product-plan="club">Clubプランに変更</button></p>
</div>
<?php endif; ?>

<?php if (!empty($vm['exit_pending'])) : ?>
<div class="payment-setup-section payment-setup-alert payment-setup-alert--warning">
    <h2 class="payment-setup-section__title">解約手続き中</h2>
    <p>このチームは解約手続き中です。<?php if (!empty($vm['exit_available_until_label'])) : ?><?php echo esc_html((string) $vm['exit_available_until_label']); ?>までご利用いただけます。<?php endif; ?></p>
    <p class="payment-setup-note">手続き中は翌月以降の試合募集・申請はできません。</p>
</div>
<?php endif; ?>

<!-- 現在の契約状況 -->
<section class="payment-setup-section payment-setup-contract-status" aria-labelledby="payment-setup-current-plan-title">
    <h2 id="payment-setup-current-plan-title" class="payment-setup-section__title payment-setup-section__title--icon">
        <span class="payment-setup-section__title-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        現在の契約状況
    </h2>

    <div class="payment-setup-status-board">
        <div class="<?php echo esc_attr($status_board_main_class); ?>">
            <div class="payment-setup-status-col payment-setup-status-col--plan">
                <div class="payment-setup-status-plan">
                    <div class="payment-setup-status-plan__icon" aria-hidden="true">
                        <?php echo aidunite_render_theme_icon(($vm['product_plan'] ?? 'match') === 'club' ? 'group' : 'trophy', ['width' => '28', 'height' => '28'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <div class="payment-setup-status-plan__body">
                        <span class="payment-setup-status-badge payment-setup-status-badge--on-light payment-setup-status-badge--<?php echo esc_attr((string) ($vm['contract_status_modifier'] ?? 'inactive')); ?>">
                            <?php echo esc_html((string) ($vm['contract_status_label'] ?? '')); ?>
                        </span>
                        <h3 class="payment-setup-status-plan__name"><?php echo esc_html((string) ($vm['plan_name'] ?? '')); ?></h3>
                        <?php if (!empty($vm['plan_tagline'])) : ?>
                        <p class="payment-setup-status-plan__tagline"><?php echo esc_html((string) $vm['plan_tagline']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($vm['team_name'])) : ?>
                        <p class="payment-setup-status-plan__team">
                            <span class="payment-setup-status-plan__team-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('group', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            対象チーム：<?php echo esc_html((string) $vm['team_name']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($vm['available_features'])) : ?>
                <div class="payment-setup-status-features payment-setup-status-features--desktop">
                    <p class="payment-setup-status-features__title">利用できる機能</p>
                    <ul class="payment-setup-status-features__list" role="list">
                        <?php foreach ((array) $vm['available_features'] as $feature_label) : ?>
                        <li class="payment-setup-status-features__item">
                            <span class="payment-setup-status-features__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '18', 'height' => '18'], 'aidunite-icon--success'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <span><?php echo esc_html((string) $feature_label); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($vm['show_free_period'])) : ?>
            <div class="payment-setup-status-col payment-setup-status-col--trial" role="status">
                <p class="payment-setup-status-trial__heading">
                    <span aria-hidden="true"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    無料期間終了まで
                </p>
                <?php if ($vm['trial_days_remaining'] !== null) : ?>
                <p class="payment-setup-status-trial__days">残り<?php echo (int) $vm['trial_days_remaining']; ?>日</p>
                <?php endif; ?>
                <?php if (!empty($vm['free_until_short'])) : ?>
                <p class="payment-setup-status-trial__until"><?php echo esc_html((string) $vm['free_until_short']); ?></p>
                <?php endif; ?>
                <?php if (!empty($vm['trial_period_note'])) : ?>
                <p class="payment-setup-status-trial__pill"><?php echo esc_html((string) $vm['trial_period_note']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($vm['show_registration_complete'])) : ?>
            <div class="payment-setup-status-col payment-setup-status-col--action payment-setup-status-col--complete">
                <div class="payment-setup-status-complete" role="status">
                    <h3 class="payment-setup-status-complete__title">
                        <span class="payment-setup-status-complete__title-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '18', 'height' => '18'], 'aidunite-icon--success'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        登録完了
                    </h3>
                    <p class="payment-setup-status-complete__lead">カードの登録が完了しました</p>
                    <?php if (!empty($vm['registration_complete_note'])) : ?>
                    <p class="payment-setup-status-complete__note"><?php echo esc_html((string) $vm['registration_complete_note']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif (!empty($vm['show_next_steps_column'])) : ?>
            <div class="payment-setup-status-col payment-setup-status-col--action">
                <div class="payment-setup-status-action">
                    <h3 class="payment-setup-status-action__title">
                        <span class="payment-setup-status-action__title-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('payments', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        次にやること
                    </h3>
                    <?php if (!empty($primary_action['headline'])) : ?>
                    <p class="payment-setup-status-action__lead"><?php echo esc_html((string) $primary_action['headline']); ?></p>
                    <?php elseif (empty($vm['has_registered_card']) && !empty($vm['card_registration_label'])) : ?>
                    <p class="payment-setup-status-action__lead"><?php echo esc_html((string) $vm['card_registration_label']); ?></p>
                    <?php endif; ?>
                    <?php if ($primary_cta_type === 'checkout' && !empty($primary_action['cta_label'])) : ?>
                    <button type="button" class="btn btn-primary payment-setup-status-action__cta js-start-stripe-checkout">
                        <span class="payment-setup-status-action__cta-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('payments', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <?php echo esc_html((string) $primary_action['cta_label']); ?>
                    </button>
                    <?php elseif ($primary_cta_type === 'portal' && !empty($primary_action['cta_label'])) : ?>
                    <button type="button" class="btn btn-primary payment-setup-status-action__cta js-stripe-billing-portal">
                        <?php echo esc_html((string) $primary_action['cta_label']); ?>
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($primary_action['note'])) : ?>
                    <p class="payment-setup-status-action__note">
                        <span aria-hidden="true"><?php echo aidunite_render_theme_icon('lock', ['width' => '14', 'height' => '14'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <?php echo esc_html((string) $primary_action['note']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($vm['available_feature_items'])) : ?>
        <div class="payment-setup-status-features payment-setup-status-features--mobile">
            <div class="payment-setup-status-features__head">
                <p class="payment-setup-status-features__title">利用できる機能</p>
                <a class="payment-setup-status-features__link" href="<?php echo esc_url((string) ($vm['plan_info_url'] ?? home_url('/plan-info'))); ?>">すべて見る</a>
            </div>
            <ul class="payment-setup-feature-chips" role="list">
                <?php foreach ((array) $vm['available_feature_items'] as $feature_item) : ?>
                <li class="payment-setup-feature-chips__item">
                    <span class="payment-setup-feature-chips__icon" aria-hidden="true">
                        <?php echo aidunite_render_theme_icon((string) ($feature_item['icon'] ?? 'check_circle'), ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="payment-setup-feature-chips__label"><?php echo esc_html((string) ($feature_item['title'] ?? '')); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="payment-setup-status-metrics" role="list">
            <div class="payment-setup-status-metric" role="listitem">
                <span class="payment-setup-status-metric__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('payments', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <div>
                    <p class="payment-setup-status-metric__label">月額料金</p>
                    <p class="payment-setup-status-metric__value">
                        <?php echo esc_html((string) ($vm['monthly_fee_display'] ?? '¥0')); ?>
                        <span class="payment-setup-status-metric__unit"><?php echo esc_html((string) ($vm['monthly_fee_unit'] ?? '/ 月（税込）')); ?></span>
                    </p>
                </div>
            </div>
            <?php if (!empty($vm['show_free_period']) && !empty($vm['free_period_end_label'])) : ?>
            <div class="payment-setup-status-metric" role="listitem">
                <span class="payment-setup-status-metric__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <div>
                    <p class="payment-setup-status-metric__label">無料期間終了日</p>
                    <p class="payment-setup-status-metric__value"><?php echo esc_html((string) $vm['free_period_end_label']); ?></p>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($vm['next_billing_date'])) : ?>
            <div class="payment-setup-status-metric" role="listitem">
                <span class="payment-setup-status-metric__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('payments', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <div>
                    <p class="payment-setup-status-metric__label">次回請求予定日</p>
                    <p class="payment-setup-status-metric__value"><?php echo esc_html((string) $vm['next_billing_date']); ?></p>
                    <?php if (!empty($vm['next_billing_conditional_note'])) : ?>
                    <p class="payment-setup-status-metric__note"><?php echo esc_html((string) $vm['next_billing_conditional_note']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- プランを変更する -->
<section class="payment-setup-section" aria-labelledby="payment-setup-change-plan-title">
    <div class="payment-setup-section__head payment-setup-section__head--split">
        <h2 id="payment-setup-change-plan-title" class="payment-setup-section__title">プランを変更する</h2>
        <a class="payment-setup-section__head-link" href="<?php echo esc_url((string) ($vm['plan_info_url'] ?? home_url('/plan-info'))); ?>">プランの詳細を比較する</a>
    </div>

    <div class="payment-setup-plan-grid">
        <?php foreach ((array) ($vm['plan_cards'] ?? []) as $card) : ?>
        <?php
        $is_selected = !empty($card['is_selected']);
        $is_upgrade_locked = !empty($card['is_upgrade_locked']);
        $card_classes = 'payment-setup-plan-card';
        if ($is_selected) {
            $card_classes .= ' is-selected';
        }
        if (!empty($card['plan_badge_modifier']) && $card['plan_badge_modifier'] === 'club') {
            $card_classes .= ' is-club';
        }
        if ($is_upgrade_locked) {
            $card_classes .= ' is-locked';
        }
        ?>
        <article class="<?php echo esc_attr($card_classes); ?>" data-product-plan="<?php echo esc_attr((string) ($card['product_plan'] ?? '')); ?>">
            <?php if (!empty($card['plan_badge_label'])) : ?>
            <span class="payment-setup-plan-card__badge payment-setup-plan-card__badge--<?php echo esc_attr((string) ($card['plan_badge_modifier'] ?? 'club')); ?>">
                <?php echo esc_html((string) $card['plan_badge_label']); ?>
            </span>
            <?php endif; ?>
            <?php if ($is_selected) : ?>
            <span class="payment-setup-plan-card__radio is-checked" aria-hidden="true"></span>
            <?php else : ?>
            <span class="payment-setup-plan-card__radio" aria-hidden="true"></span>
            <?php endif; ?>
            <div class="payment-setup-plan-card__icon" aria-hidden="true">
                <?php echo aidunite_render_theme_icon((string) ($card['plan_icon'] ?? 'trophy'), ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <h3 class="payment-setup-plan-card__name"><?php echo esc_html((string) ($card['name'] ?? '')); ?></h3>
            <p class="payment-setup-plan-card__price"><?php echo esc_html((string) ($card['price_label'] ?? '')); ?></p>
            <p class="payment-setup-plan-card__summary"><?php echo esc_html((string) ($card['summary'] ?? '')); ?></p>
            <?php if (!empty($card['minimum_note'])) : ?>
            <p class="payment-setup-plan-card__note"><?php echo esc_html((string) $card['minimum_note']); ?></p>
            <?php endif; ?>
            <?php if (!empty($card['feature_labels'])) : ?>
            <ul class="payment-setup-plan-card__features" role="list">
                <?php foreach ((array) $card['feature_labels'] as $feature_label) : ?>
                <li>
                    <span class="payment-setup-plan-card__feature-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '16', 'height' => '16'], 'aidunite-icon--success'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php echo esc_html((string) $feature_label); ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ($is_selected) : ?>
            <p class="payment-setup-plan-card__current" aria-current="true">現在のプラン</p>
            <?php elseif ($is_upgrade_locked) : ?>
            <p class="payment-setup-plan-card__locked"><?php echo esc_html((string) ($card['upgrade_locked_note'] ?? '試合成立後にアップグレードできます')); ?></p>
            <?php elseif (!empty($vm['can_change_plan'])) : ?>
            <button
                type="button"
                class="btn btn-outline payment-setup-plan-card__action js-payment-plan-select"
                data-plan-id="<?php echo esc_attr((string) ($card['plan_id'] ?? '')); ?>"
                data-product-plan="<?php echo esc_attr((string) ($card['product_plan'] ?? '')); ?>"
            >このプランにする</button>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- お支払い方法 -->
<section class="payment-setup-section" aria-labelledby="payment-setup-payment-method-title">
    <div class="payment-setup-section__head">
        <h2 id="payment-setup-payment-method-title" class="payment-setup-section__title">お支払い方法</h2>
        <p class="payment-setup-section__lead">登録済みのクレジットカードの確認・変更（Stripe）</p>
    </div>

    <div class="payment-setup-payment-card">
        <?php
        $default_card = is_array($vm['default_card'] ?? null) ? $vm['default_card'] : null;
        $use_portal = !empty($vm['show_portal_cta']);
        ?>
        <?php if ($default_card !== null) : ?>
        <div class="payment-setup-payment-card__row">
            <div class="payment-setup-payment-card__info">
                <span class="payment-setup-payment-card__brand" aria-hidden="true"><?php echo aidunite_render_theme_icon('payments', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <div>
                    <p class="payment-setup-payment-card__label"><?php echo esc_html((string) ($default_card['masked'] ?? 'クレジットカード')); ?></p>
                    <p class="payment-setup-payment-card__meta">
                        有効期限 <?php echo esc_html((string) ($default_card['exp_label'] ?? '')); ?>
                        <?php if (!empty($default_card['is_default'])) : ?>
                        <span class="payment-setup-payment-card__default">デフォルト</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if ($use_portal) : ?>
            <button type="button" class="btn btn-outline js-stripe-billing-portal">変更する</button>
            <?php elseif (!empty($vm['show_checkout_cta'])) : ?>
            <button type="button" class="btn btn-outline js-start-stripe-checkout">変更する</button>
            <?php endif; ?>
        </div>
        <?php elseif (!empty($vm['has_registered_card'])) : ?>
        <div class="payment-setup-payment-card__row">
            <div class="payment-setup-payment-card__info">
                <span class="payment-setup-payment-card__brand" aria-hidden="true"><?php echo aidunite_render_theme_icon('payments', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <div>
                    <p class="payment-setup-payment-card__label">クレジットカード（登録済み）</p>
                    <p class="payment-setup-payment-card__meta">毎月1日に自動で課金されます</p>
                </div>
            </div>
            <?php if ($use_portal) : ?>
            <button type="button" class="btn btn-outline js-stripe-billing-portal">変更する</button>
            <?php endif; ?>
        </div>
        <?php else : ?>
        <div class="payment-setup-payment-card__row payment-setup-payment-card__row--empty">
            <div>
                <p class="payment-setup-payment-card__label">カード未登録</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="payment-setup-trust" aria-label="お支払いのセキュリティ">
        <p class="payment-setup-trust__lead">
            <?php echo aidunite_render_theme_icon('lock', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            Stripeで安全に管理されます
        </p>
        <ul class="payment-setup-card-brands" aria-label="対応カードブランド">
            <li class="payment-setup-card-brands__item payment-setup-card-brands__item--visa">VISA</li>
            <li class="payment-setup-card-brands__item payment-setup-card-brands__item--mastercard">Mastercard</li>
            <li class="payment-setup-card-brands__item payment-setup-card-brands__item--jcb">JCB</li>
            <li class="payment-setup-card-brands__item payment-setup-card-brands__item--amex">AMEX</li>
        </ul>
    </div>

    <?php if ($use_portal && $default_card === null && empty($vm['has_registered_card'])) : ?>
    <button type="button" class="btn btn-outline payment-setup-payment-change js-stripe-billing-portal">お支払い方法を変更する</button>
    <?php elseif (!empty($vm['show_checkout_cta']) && $default_card === null && empty($vm['has_registered_card'])) : ?>
    <button type="button" class="btn btn-outline payment-setup-payment-change js-start-stripe-checkout">お支払い方法を変更する</button>
    <?php endif; ?>
</section>

<!-- 請求履歴 -->
<section class="payment-setup-section<?php echo !empty($vm['billing_history_collapsed']) ? ' payment-setup-section--billing-collapsed' : ''; ?>" aria-labelledby="payment-setup-billing-title">
    <div class="payment-setup-section__head payment-setup-section__head--split">
        <div>
            <h2 id="payment-setup-billing-title" class="payment-setup-section__title">請求履歴</h2>
            <p class="payment-setup-section__lead">過去のご請求内容を確認できます</p>
        </div>
        <?php if (empty($vm['billing_history_collapsed']) && !empty($vm['show_portal_cta']) && (int) ($vm['billing_history_total'] ?? 0) > 0) : ?>
        <button type="button" class="payment-setup-section__link-btn js-stripe-billing-portal">すべて見る</button>
        <?php endif; ?>
    </div>

    <?php if (!empty($vm['billing_history_collapsed'])) : ?>
    <details class="payment-setup-billing-collapse">
        <summary class="payment-setup-billing-collapse__summary">
            <span class="payment-setup-billing-collapse__summary-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('attach_file', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span class="payment-setup-billing-collapse__summary-text"><?php echo esc_html((string) ($vm['billing_history_collapsed_summary'] ?? '無料期間中は請求はありません')); ?></span>
            <span class="payment-setup-billing-collapse__summary-hint">詳細を表示</span>
        </summary>
        <div class="payment-setup-billing-collapse__body">
            <?php if (!empty($vm['billing_history'])) : ?>
            <ul class="payment-setup-billing-list" role="list">
                <?php foreach ((array) $vm['billing_history'] as $item) : ?>
                <li class="payment-setup-billing-list__item<?php echo !empty($item['is_card_verification']) ? ' payment-setup-billing-list__item--verification' : ''; ?>">
                    <div class="payment-setup-billing-list__main">
                        <span class="payment-setup-billing-list__period"><?php echo esc_html((string) ($item['label'] ?? '')); ?></span>
                        <span class="payment-setup-billing-list__amount"><?php echo esc_html((string) ($item['amount_display'] ?? ('¥' . number_format((int) ($item['amount'] ?? 0))))); ?></span>
                    </div>
                    <div class="payment-setup-billing-list__actions">
                        <span class="payment-setup-billing-list__status payment-setup-billing-list__status--<?php echo esc_attr((string) ($item['status'] ?? 'paid')); ?>">
                            <?php echo esc_html((string) ($item['status_label'] ?? '支払い済み')); ?>
                        </span>
                        <?php if (!empty($item['invoice_pdf'])) : ?>
                        <a
                            class="payment-setup-billing-list__download"
                            href="<?php echo esc_url((string) $item['invoice_pdf']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="<?php echo esc_attr((string) ($item['label'] ?? '')); ?>の領収書をダウンロード"
                        >
                            <?php echo aidunite_render_theme_icon('attach_file', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else : ?>
            <div class="payment-setup-empty-inline payment-setup-empty-inline--billing" role="status">
                <p><?php echo esc_html((string) ($vm['billing_history_empty_message'] ?? 'まだ請求履歴はありません。')); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($vm['show_portal_cta'])) : ?>
            <button type="button" class="btn btn-outline payment-setup-billing-all js-stripe-billing-portal">すべての履歴を見る</button>
            <?php endif; ?>
        </div>
    </details>
    <?php elseif (!empty($vm['billing_history'])) : ?>
    <ul class="payment-setup-billing-list" role="list">
        <?php foreach ((array) $vm['billing_history'] as $item) : ?>
        <li class="payment-setup-billing-list__item<?php echo !empty($item['is_card_verification']) ? ' payment-setup-billing-list__item--verification' : ''; ?>">
            <div class="payment-setup-billing-list__main">
                <span class="payment-setup-billing-list__period"><?php echo esc_html((string) ($item['label'] ?? '')); ?></span>
                <span class="payment-setup-billing-list__amount"><?php echo esc_html((string) ($item['amount_display'] ?? ('¥' . number_format((int) ($item['amount'] ?? 0))))); ?></span>
            </div>
            <div class="payment-setup-billing-list__actions">
                <span class="payment-setup-billing-list__status payment-setup-billing-list__status--<?php echo esc_attr((string) ($item['status'] ?? 'paid')); ?>">
                    <?php echo esc_html((string) ($item['status_label'] ?? '支払い済み')); ?>
                </span>
                <?php if (!empty($item['invoice_pdf'])) : ?>
                <a
                    class="payment-setup-billing-list__download"
                    href="<?php echo esc_url((string) $item['invoice_pdf']); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php echo esc_attr((string) ($item['label'] ?? '')); ?>の領収書をダウンロード"
                >
                    <?php echo aidunite_render_theme_icon('attach_file', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </a>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else : ?>
    <div class="payment-setup-empty-inline payment-setup-empty-inline--billing" role="status">
        <span class="payment-setup-empty-inline__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('attach_file', ['width' => '28', 'height' => '28'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <p><?php echo esc_html((string) ($vm['billing_history_empty_message'] ?? 'まだ請求履歴はありません。')); ?></p>
    </div>
    <?php if (!empty($vm['show_portal_cta'])) : ?>
    <button type="button" class="btn btn-outline payment-setup-billing-all js-stripe-billing-portal">すべての履歴を見る</button>
    <?php else : ?>
    <button type="button" class="btn btn-outline payment-setup-billing-all" disabled>すべての履歴を見る</button>
    <?php endif; ?>
    <?php endif; ?>
</section>

<!-- セキュリティ情報 -->
<section class="payment-setup-section payment-setup-security" aria-labelledby="payment-setup-security-title">
    <h2 id="payment-setup-security-title" class="payment-setup-security__title">
        <?php echo aidunite_render_theme_icon('lock', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        セキュリティ
    </h2>
    <div class="payment-setup-security__visual">
        <span class="payment-setup-security__shield" aria-hidden="true"><?php echo aidunite_render_theme_icon('visibility_lock', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <p>クレジットカード情報は Stripe により安全に処理されます。当社がカード情報を保存することはありません。</p>
    </div>
</section>
