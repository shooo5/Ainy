<?php
/**
 * 決済管理パネル（金額・プラン・Stripe）
 */
if (!defined('ABSPATH')) {
    exit;
}

$paid_count = (int) get_query_var('aidunite_payment_management_paid_count', 0);
$revenue_estimate = (int) get_query_var('aidunite_payment_management_revenue_estimate', 0);
$retention_ltv = get_query_var('aidunite_payment_management_retention_ltv', []);
if (!is_array($retention_ltv)) {
    $retention_ltv = [];
}
$payment_config = get_query_var('aidunite_payment_management_config', []);
if (!is_array($payment_config)) {
    $payment_config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
}
$match_amount = (int) ($payment_config['match']['monthly_amount'] ?? 2000);
$club_per_player = (int) ($payment_config['club']['per_player_amount'] ?? $payment_config['club']['base_amount'] ?? 500);
$club_minimum = (int) ($payment_config['club']['minimum_addon'] ?? 6000);
$founding = $payment_config['founding_team'] ?? ['max_slots' => 50, 'first_year_discount_percent' => 50];
$multi_team = $payment_config['multi_team_discount'] ?? ['match_second_team_percent' => 20];
$founding_slots_remaining = function_exists('aidunite_payment_read_founding_slots_remaining')
    ? (int) aidunite_payment_read_founding_slots_remaining()
    : (int) ($founding['max_slots'] ?? 50);
?>

<section class="ainy-dashboard-section page-admin-payment-management-panel" aria-label="決済設定">
    <div class="ainy-dashboard-payment-summary" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap:var(--spacing-base); margin-bottom:var(--spacing-xl);">
        <div class="ainy-dashboard-kpi-card">
            <span class="ainy-dashboard-kpi-name">有料会員数</span>
            <span class="ainy-dashboard-kpi-value"><?php echo number_format($paid_count); ?></span>
            <span class="ainy-dashboard-kpi-meta">payment_status=paid</span>
        </div>
        <div class="ainy-dashboard-kpi-card">
            <span class="ainy-dashboard-kpi-name">売上目安（月額）</span>
            <span class="ainy-dashboard-kpi-value"><?php echo number_format($revenue_estimate); ?></span>
            <span class="ainy-dashboard-kpi-meta">円（Match単価×有料会員数）</span>
        </div>
        <div class="ainy-dashboard-kpi-card">
            <span class="ainy-dashboard-kpi-name">全体の継続月数</span>
            <span class="ainy-dashboard-kpi-value"><?php echo esc_html(number_format((float) ($retention_ltv['retention_months_avg'] ?? 0), 1)); ?></span>
            <span class="ainy-dashboard-kpi-meta">ヶ月（有料会員平均）</span>
        </div>
        <div class="ainy-dashboard-kpi-card">
            <span class="ainy-dashboard-kpi-name">LTV</span>
            <span class="ainy-dashboard-kpi-value"><?php echo number_format((int) ($retention_ltv['ltv_total'] ?? 0)); ?></span>
            <span class="ainy-dashboard-kpi-meta">円（全体・月額×継続月数）</span>
        </div>
    </div>

    <div class="ainy-dashboard-payment-tabs" style="display:flex; gap:var(--spacing-sm); margin-bottom:var(--spacing-base); border-bottom:2px solid var(--border-color); flex-wrap:wrap;">
        <button type="button" class="ainy-dashboard-tab-btn is-active" data-tab="amount-settings">金額設定</button>
        <button type="button" class="ainy-dashboard-tab-btn" data-tab="plan-settings">プラン設定</button>
        <button type="button" class="ainy-dashboard-tab-btn" data-tab="stripe-settings">Stripe</button>
    </div>

    <div class="ainy-dashboard-tab-content is-active" id="amount-settings">
        <h2 style="margin:0 0 var(--spacing-base); font-size:var(--font-size-lg);">金額設定</h2>
        <form id="amount-settings-form" class="ainy-dashboard-amount-form">
            <div class="ainy-dashboard-amount-cards">
                <div class="ainy-dashboard-amount-card">
                    <span class="ainy-dashboard-amount-card-label">Matchプラン（システム利用料・税込）</span>
                    <div class="ainy-dashboard-amount-card-input-wrap">
                        <input type="number" id="match_monthly_amount" value="<?php echo esc_attr($match_amount); ?>" min="0" class="ainy-dashboard-amount-input" aria-label="Matchプラン月額">
                        <span class="ainy-dashboard-amount-unit">円</span>
                    </div>
                </div>
                <div class="ainy-dashboard-amount-card">
                    <span class="ainy-dashboard-amount-card-label">Clubプラン（1人あたり加算・税込）</span>
                    <div class="ainy-dashboard-amount-card-input-wrap">
                        <input type="number" id="club_per_player_amount" value="<?php echo esc_attr($club_per_player); ?>" min="0" class="ainy-dashboard-amount-input" aria-label="Club人数単価">
                        <span class="ainy-dashboard-amount-unit">円</span>
                    </div>
                </div>
                <div class="ainy-dashboard-amount-card">
                    <span class="ainy-dashboard-amount-card-label">Clubプラン（最低加算額・税込）</span>
                    <div class="ainy-dashboard-amount-card-input-wrap">
                        <input type="number" id="club_minimum_addon" value="<?php echo esc_attr($club_minimum); ?>" min="0" class="ainy-dashboard-amount-input" aria-label="Club最低加算額">
                        <span class="ainy-dashboard-amount-unit">円</span>
                    </div>
                </div>
                <div class="ainy-dashboard-amount-card">
                    <span class="ainy-dashboard-amount-card-label">Founding Team 枠数</span>
                    <div class="ainy-dashboard-amount-card-input-wrap">
                        <input type="number" id="founding_max_slots" value="<?php echo esc_attr((int) ($founding['max_slots'] ?? 50)); ?>" min="0" class="ainy-dashboard-amount-input" aria-label="Founding枠">
                        <span class="ainy-dashboard-amount-unit">チーム</span>
                    </div>
                </div>
                <div class="ainy-dashboard-amount-card">
                    <span class="ainy-dashboard-amount-card-label">Founding 初年度割引</span>
                    <div class="ainy-dashboard-amount-card-input-wrap">
                        <input type="number" id="founding_discount_percent" value="<?php echo esc_attr((int) ($founding['first_year_discount_percent'] ?? 50)); ?>" min="0" max="100" class="ainy-dashboard-amount-input" aria-label="Founding割引率">
                        <span class="ainy-dashboard-amount-unit">%</span>
                    </div>
                </div>
                <div class="ainy-dashboard-amount-card">
                    <span class="ainy-dashboard-amount-card-label">2チーム目以降割引（Match）</span>
                    <div class="ainy-dashboard-amount-card-input-wrap">
                        <input type="number" id="multi_team_discount_percent" value="<?php echo esc_attr((int) ($multi_team['match_second_team_percent'] ?? 20)); ?>" min="0" max="100" class="ainy-dashboard-amount-input" aria-label="2チーム目割引率">
                        <span class="ainy-dashboard-amount-unit">%</span>
                    </div>
                </div>
            </div>
            <input type="hidden" id="personal_amount" value="<?php echo esc_attr($match_amount); ?>">
            <input type="hidden" id="corporate_amount" value="<?php echo esc_attr($match_amount); ?>">
            <button type="submit" class="ainy-dashboard-period-btn" style="cursor:pointer; margin-top:var(--spacing-base);">保存</button>
        </form>
    </div>

    <div class="ainy-dashboard-tab-content" id="plan-settings">
        <h2 style="margin:0 0 var(--spacing-base); font-size:var(--font-size-lg);">プラン設定</h2>
        <?php $plan_display_mode = function_exists('aidunite_get_plan_display_mode') ? aidunite_get_plan_display_mode() : 'coming_soon'; ?>
        <div class="ainy-dashboard-plan-display-mode" style="margin-bottom:var(--spacing-xl); padding:var(--spacing-base); background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-base);">
            <h3 style="margin:0 0 var(--spacing-sm); font-size:var(--font-size-base);">プラン表示モード（リリース前用）</h3>
            <p style="margin:0 0 var(--spacing-base); font-size:var(--font-size-sm); color:var(--text-secondary);">ユーザー向けの支払い設定・プラン情報ページで、プランカードをどう表示するかを切り替えます。</p>
            <form id="plan-display-mode-form" class="ainy-dashboard-form">
                <div style="display:flex; flex-wrap:wrap; gap:var(--spacing-base); align-items:center;">
                    <label style="display:flex; align-items:center; gap:var(--spacing-xs); cursor:pointer;">
                        <input type="radio" name="plan_display_mode" value="coming_soon" <?php checked($plan_display_mode, 'coming_soon'); ?>>
                        <span>カミングスーン（プランカードを Coming Soon 表示・選択不可）</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:var(--spacing-xs); cursor:pointer;">
                        <input type="radio" name="plan_display_mode" value="trial_card" <?php checked($plan_display_mode, 'trial_card'); ?>>
                        <span>お試しカード（2ヶ月無料カードを表示）</span>
                    </label>
                    <button type="submit" class="ainy-dashboard-period-btn" style="cursor:pointer;">保存</button>
                </div>
            </form>
        </div>
        <div class="ainy-dashboard-founding-assign" style="margin-bottom:var(--spacing-xl); padding:var(--spacing-base); background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-base);">
            <h3 style="margin:0 0 var(--spacing-sm); font-size:var(--font-size-base);">Founding Team 割当</h3>
            <p style="margin:0 0 var(--spacing-base); font-size:var(--font-size-sm); color:var(--text-secondary);">
                残枠: <strong id="founding-slots-remaining"><?php echo (int) $founding_slots_remaining; ?></strong> チーム
            </p>
            <div style="display:flex; flex-wrap:wrap; gap:var(--spacing-sm); align-items:flex-end;">
                <label style="display:flex; flex-direction:column; gap:var(--spacing-xs);">
                    <span style="font-size:var(--font-size-sm);">チームID</span>
                    <input type="number" id="founding-assign-team-id" min="1" class="ainy-dashboard-amount-input" style="width:120px;" aria-label="Founding割当チームID">
                </label>
                <button type="button" id="founding-assign-btn" class="ainy-dashboard-period-btn" style="cursor:pointer;">Founding に割当</button>
            </div>
        </div>
        <h3 style="margin:0 0 var(--spacing-sm); font-size:var(--font-size-base);">登録済みプラン一覧</h3>
        <?php
        $match_plans = $payment_config['match']['plans'] ?? [];
        foreach ($match_plans as $plan) :
            ?>
        <div style="padding:var(--spacing-base); background:var(--bg-secondary); border-radius:var(--radius-base); margin-bottom:var(--spacing-sm);">
            <strong><?php echo esc_html($plan['name'] ?? ''); ?></strong> — ID: <?php echo esc_html($plan['id'] ?? ''); ?><br>
            説明: <?php echo esc_html($plan['description'] ?? ''); ?>
            <?php if (!empty($plan['is_default'])) : ?>
            <span style="background:var(--primary-color); color:#fff; padding:2px var(--spacing-xs); border-radius:var(--radius-small); font-size:var(--font-size-xs);">デフォルト</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="ainy-dashboard-tab-content" id="stripe-settings">
        <h2 style="margin:0 0 var(--spacing-base); font-size:var(--font-size-lg);">Stripe API</h2>
        <form id="stripe-settings-form" class="ainy-dashboard-form">
            <div class="ainy-dashboard-form-group" style="margin-bottom:var(--spacing-base);">
                <label style="display:block; margin-bottom:var(--spacing-xs); font-weight:bold;">Publishable Key</label>
                <input type="text" id="publishable_key" value="<?php echo esc_attr(get_option('aidunite_stripe_publishable_key', '')); ?>" placeholder="pk_test_..." style="width:100%; max-width:500px; padding:var(--spacing-sm); border:1px solid var(--border-color); border-radius:var(--radius-small);">
            </div>
            <div class="ainy-dashboard-form-group" style="margin-bottom:var(--spacing-base);">
                <label style="display:block; margin-bottom:var(--spacing-xs); font-weight:bold;">Secret Key</label>
                <input type="password" id="secret_key" value="<?php echo esc_attr(get_option('aidunite_stripe_secret_key', '')); ?>" placeholder="sk_test_..." style="width:100%; max-width:500px; padding:var(--spacing-sm); border:1px solid var(--border-color); border-radius:var(--radius-small);">
                <p class="help-text" style="margin:var(--spacing-xs) 0 0; font-size:var(--font-size-sm); color:var(--text-muted, #666);">※ <code>whsec_</code>（Webhook Secret）はここではなく下の欄に入力してください。</p>
            </div>
            <div class="ainy-dashboard-form-group" style="margin-bottom:var(--spacing-base);">
                <label style="display:block; margin-bottom:var(--spacing-xs); font-weight:bold;">Webhook Secret</label>
                <input type="text" id="webhook_secret" value="<?php echo esc_attr(get_option('aidunite_stripe_webhook_secret', '')); ?>" placeholder="whsec_..." style="width:100%; max-width:500px; padding:var(--spacing-sm); border:1px solid var(--border-color); border-radius:var(--radius-small);">
            </div>
            <button type="submit" class="ainy-dashboard-period-btn" style="cursor:pointer;">保存</button>
        </form>
    </div>
</section>
