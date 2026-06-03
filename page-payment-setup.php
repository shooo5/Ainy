<?php
/**
 * Template Name: 支払い設定ページ（統合ページ）
 * プラン選択 + 支払い設定を1ページで完結
 */

// functions.php で既に読み込まれているため、コメントアウト
// require_once get_template_directory() . '/functions/payment/payment-config.php';
// require_once get_template_directory() . '/functions/payment/payment-functions.php';
// require_once get_template_directory() . '/functions/payment/stripe-core.php';

get_header();

// 統一認証・権限チェック
$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

$user_id = $auth_result->user_id;
$team_id = get_user_meta($user_id, 'team_id', true);

if (empty($team_id)) {
    echo '<div class="page-container">';
    echo '<div class="error-message">チームが見つかりません。</div>';
    echo '</div>';
    get_footer();
    return;
}

$team = get_post($team_id);
$team_type = aidunite_get_team_type($team_id);
$payment_mode = aidunite_get_team_payment_mode($team_id);
$current_plan_id = aidunite_get_selected_plan_id($team_id);
$selected_plan = aidunite_get_plan_info($team_id);
$monthly_fee = aidunite_calculate_monthly_fee($team_id);
$trial_end_date = aidunite_calculate_trial_end_date($team_id);
$is_trial = aidunite_is_trial_period($team_id);
$config = aidunite_get_payment_config();
$plan_display_mode = function_exists('aidunite_get_plan_display_mode') ? aidunite_get_plan_display_mode() : 'coming_soon';

// プラン未選択かチェック
$plan_selection_required = empty($current_plan_id);

// チームタイプに応じたプランを取得（プラン未選択の場合に使用）
$plans = [];
if ($plan_selection_required) {
    $config_key = function_exists('aidunite_team_type_payment_config_key')
        ? aidunite_team_type_payment_config_key($team_id)
        : ($team_type === 'club' ? 'club' : 'school');
    $plans = isset($config[$config_key]['plans']) ? $config[$config_key]['plans'] : [];

    // プランが取得できない場合のフォールバック
    if (empty($plans)) {
        $default_config = aidunite_get_default_payment_config();
        $plans = isset($default_config[$config_key]['plans']) ? $default_config[$config_key]['plans'] : [];
    }
}

// 支払い方法を取得（登録時に選択した方法）
$payment_method = get_post_meta($team_id, 'selected_payment_method', true);
if (empty($payment_method)) {
    // デフォルトはStripe決済
    $payment_method = 'stripe';
}
?>

<div class="page-container">
    <div class="payment-setup-container">
        <div class="payment-setup-header">
            <h1>支払い設定</h1>
            <p>プランと支払い方法を設定してください</p>
        </div>

        <div class="payment-setup-content">
            <!-- 選択済みプラン表示（常時表示） -->
            <?php if ($selected_plan): ?>
            <div class="selected-plan-banner">
                <div class="selected-plan-banner-content">
                    <div class="selected-plan-icon"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '32', 'height' => '32'], 'aidunite-icon--success'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <div class="selected-plan-info">
                        <h3>選択済みプラン: <?php echo esc_html($selected_plan['name']); ?></h3>
                        <p>
                            <?php echo esc_html($selected_plan['description']); ?>
                            <?php if ($selected_plan['trial_type'] === 'days'): ?>
                                | トライアル: <?php echo esc_html($selected_plan['trial_value']); ?>日間
                            <?php elseif ($selected_plan['trial_type'] === 'free_months'): ?>
                                | 特典: <?php echo esc_html($selected_plan['trial_value']); ?>か月分無料
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="selected-plan-price">
                        <div class="price-amount">¥<?php echo number_format($monthly_fee); ?>/月</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- トライアル中の表示 -->
            <?php if ($is_trial): ?>
                <?php
                $trial_start_date = aidunite_get_trial_start_date($team_id);
                $bonus = aidunite_calculate_early_payment_bonus($team_id);
                $days_elapsed = 0;
                $days_remaining = 30;

                if (!empty($trial_start_date)) {
                    $days_elapsed = floor((time() - strtotime($trial_start_date)) / (24 * 60 * 60));
                    $days_remaining = 30 - $days_elapsed;
                }
                ?>
                <div class="trial-status-banner">
                    <h3><?php echo aidunite_render_theme_icon('campaign', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> トライアル中（残り<?php echo max(0, $days_remaining); ?>日）</h3>
                    <p class="trial-description">トライアル期間中は無料で利用できます</p>

                    <?php if ($bonus && $bonus['bonus_months'] > 0): ?>
                    <div class="early-bonus-card <?php echo $bonus['bonus_months'] === 2 ? 'premium' : ''; ?>">
                        <div class="bonus-icon"><?php
                            if ($days_elapsed <= 10) {
                                echo aidunite_render_theme_icon('mode_heat', ['width' => '32', 'height' => '32']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            } elseif ($days_elapsed <= 20) {
                                echo '✨';
                            } else {
                                echo aidunite_render_theme_icon('currency_yen', ['width' => '32', 'height' => '32']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                        ?></div>
                        <div class="bonus-content">
                            <h4><?php echo esc_html($bonus['message']); ?></h4>
                            <p>
                                <?php if ($bonus['bonus_months'] === 2): ?>
                                    今すぐ本格スタートすると、追加で2か月間無料で利用できます！
                                <?php else: ?>
                                    今すぐ本格スタートすると、追加で1か月間無料で利用できます！
                                <?php endif; ?>
                            </p>
                            <p class="bonus-deadline">
                                <?php if ($days_elapsed <= 20): ?>
                                    あと<?php echo max(0, 20 - $days_elapsed); ?>日で2か月無料特典が終了します
                                <?php elseif ($days_elapsed <= 30): ?>
                                    あと<?php echo max(0, 30 - $days_elapsed); ?>日で1か月無料特典が終了します
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$plan_selection_required && $payment_method === 'stripe'): ?>
                    <div class="trial-action" style="margin-top: 1.5rem;">
                        <button id="start-stripe-checkout" class="btn btn-primary btn-large">
                            <span class="btn-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('start', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            本格スタートする<?php echo $bonus && $bonus['bonus_months'] > 0 ? '（' . esc_html($bonus['message']) . '）' : ''; ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- チーム情報 -->
            <div class="payment-info-card">
                <h3><?php echo aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> チーム情報</h3>
                <div class="info-item">
                    <strong>チーム名:</strong> <?php echo esc_html($team->post_title); ?>
                </div>
                <div class="info-item">
                    <strong>チームタイプ:</strong> <?php echo esc_html(function_exists('aidunite_team_type_label') ? aidunite_team_type_label($team_type) : $team_type); ?>
                </div>
                <div class="info-item">
                    <strong>月額料金:</strong> ¥<?php echo number_format($monthly_fee); ?>/月
                </div>
                <?php if (function_exists('aidunite_team_type_is_club') ? aidunite_team_type_is_club($team_id) : $team_type === 'club'): ?>
                <div class="info-item note">
                    <p>※ クラブチームは登録選手数 × 1人あたり金額で計算されます</p>
                </div>
                <?php endif; ?>
                <?php if ($is_trial && $trial_end_date): ?>
                <div class="info-item">
                    <strong>トライアル終了日:</strong> <?php echo esc_html(date('Y年m月d日', strtotime($trial_end_date))); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- プラン選択（プラン未選択の場合のみ表示） -->
            <?php if ($plan_selection_required): ?>
            <?php
            $default_plan_id_for_trial = null;
            if (!empty($plans)) {
                foreach ($plans as $p) {
                    if (!empty($p['is_default'])) {
                        $default_plan_id_for_trial = $p['id'];
                        break;
                    }
                }
                if (!$default_plan_id_for_trial) {
                    $default_plan_id_for_trial = $plans[0]['id'];
                }
            }
            ?>
            <div class="plan-selection-card" id="plan-selection-section">
                <h3><?php echo aidunite_render_theme_icon('list_alt_add', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> プランを選択</h3>
                <p class="section-description">チームに最適なプランを選択してください</p>

                <?php if (empty($plans)): ?>
                    <div class="error-message" style="padding: 1rem; background: rgba(220, 53, 69, 0.1); border-radius: 4px; color: var(--danger-color);">
                        <p><strong>エラー:</strong> プランが見つかりませんでした。</p>
                        <p>チームタイプ: <?php echo esc_html($team_type !== '' ? (function_exists('aidunite_team_type_label') ? aidunite_team_type_label($team_type) : $team_type) : '未設定'); ?></p>
                        <p>管理者にお問い合わせください。</p>
                    </div>
                <?php elseif ($plan_display_mode === 'trial_card'): ?>
                <!-- お試しカード（リリース前モード） -->
                <div class="plan-selection-grid plan-selection-grid--trial-card">
                    <div class="plan-option-card plan-option-card--trial-card" id="trial-card">
                        <div class="trial-card-inner">
                            <div class="trial-card-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('redeem', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            <h4 class="trial-card-title">お試しで始める</h4>
                            <p class="trial-card-description">30日間無料でAniyの機能をお試しいただけます。チーム登録後、すぐにスケジュールやマッチングをご利用いただけます。</p>
                            <ul class="trial-card-features">
                                <li>30日間無料トライアル</li>
                                <li>本格スタート時は早期決済特典あり</li>
                            </ul>
                            <button type="button" id="start-trial-btn" class="btn btn-primary btn-large" data-default-plan-id="<?php echo esc_attr($default_plan_id_for_trial ?? ''); ?>">
                                <span class="btn-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('start', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                お試しで始める
                            </button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- カミングスーン（プランカードは選択不可） -->
                <div class="plan-selection-grid plan-selection-grid--coming-soon">
                    <?php foreach ($plans as $index => $plan): ?>
                        <div class="plan-option-card plan-option-card--coming-soon" data-plan-id="<?php echo esc_attr($plan['id']); ?>">
                            <div class="plan-coming-soon-overlay" aria-hidden="true">
                                <span class="plan-coming-soon-text">Coming Soon</span>
                                <span class="plan-coming-soon-sub">準備中です</span>
                            </div>
                            <div class="plan-label plan-label--disabled">
                                <div class="plan-header">
                                    <h4><?php echo esc_html($plan['name']); ?></h4>
                                    <?php if (!empty($plan['is_default'])): ?>
                                        <span class="default-badge">デフォルト</span>
                                    <?php endif; ?>
                                </div>
                                <div class="plan-description">
                                    <?php echo esc_html($plan['description']); ?>
                                </div>
                                <div class="plan-trial">
                                    <?php if ($plan['trial_type'] === 'days'): ?>
                                        <span class="trial-badge">
                                            <span class="trial-icon"><?php echo aidunite_render_theme_icon('redeem', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                            トライアル: <?php echo esc_html($plan['trial_value']); ?>日間
                                        </span>
                                    <?php elseif ($plan['trial_type'] === 'free_months'): ?>
                                        <span class="trial-badge premium">
                                            <span class="trial-icon">✨</span>
                                            特典: <?php echo esc_html($plan['trial_value']); ?>か月分無料
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="plan-coming-soon-notice">プランの提供準備が整い次第、選択可能になります。</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 支払い方法 -->
            <div class="payment-method-card">
                <h3><?php echo aidunite_render_theme_icon('currency_yen', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 支払い方法</h3>
                <p class="section-description">支払い方法を選択してください</p>

                <?php if ($payment_mode === 'board'): ?>
                    <!-- 教育委員会契約 -->
                    <div class="invoice-notice">
                        <p><strong>教育委員会契約</strong></p>
                        <p>請求書による支払いが設定されています。</p>
                        <p>請求書の発行をお待ちください。</p>
                        <p class="invoice-note">※ 請求書は支払い設定完了後、自動で生成・送信されます。</p>
                    </div>
                <?php elseif ($payment_mode === 'school' || $payment_method === 'invoice'): ?>
                    <!-- 学校契約（請求書） -->
                    <div class="invoice-notice">
                        <p><strong>請求書による支払い</strong></p>
                        <p>請求書による支払いが設定されています。</p>
                        <p>請求書の発行をお待ちください。</p>
                        <p class="invoice-note">※ 請求書は支払い設定完了後、自動で生成・送信されます。</p>
                    </div>
                <?php else: ?>
                    <!-- Stripe決済 -->
                    <div class="payment-method-selection">
                        <div class="payment-method-option <?php echo $payment_method === 'stripe' ? 'selected' : ''; ?>">
                            <input type="radio"
                                   name="payment_method"
                                   id="payment_stripe"
                                   value="stripe"
                                   class="payment-radio"
                                   <?php echo $payment_method === 'stripe' ? 'checked' : ''; ?>>
                            <label for="payment_stripe" class="payment-label">
                                <h4><?php echo aidunite_render_theme_icon('payments', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Stripe決済（クレジットカード）</h4>
                                <p>毎月自動で課金されます</p>
                            </label>
                        </div>

                        <?php if (function_exists('aidunite_team_type_is_school') ? aidunite_team_type_is_school($team_id) : $team_type === 'school'): ?>
                        <div class="payment-method-option <?php echo $payment_method === 'invoice' ? 'selected' : ''; ?>">
                            <input type="radio"
                                   name="payment_method"
                                   id="payment_invoice"
                                   value="invoice"
                                   class="payment-radio"
                                   <?php echo $payment_method === 'invoice' ? 'checked' : ''; ?>>
                            <label for="payment_invoice" class="payment-label">
                                <h4><?php echo aidunite_render_theme_icon('attach_file', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 請求書</h4>
                                <p>請求書PDFを発行します（学校契約のみ）</p>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stripe決済開始ボタン（プラン選択済みかつトライアル中でない場合のみ表示） -->
                    <?php if (!$plan_selection_required && !$is_trial && $payment_method === 'stripe'): ?>
                    <div class="stripe-payment" style="margin-top: 1.5rem;">
                        <button id="start-stripe-checkout" class="btn btn-primary btn-large">
                            <span class="btn-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('payments', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            Stripe決済で支払いを開始
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="security-info">
                        <p><?php echo aidunite_render_theme_icon('lock', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> セキュリティ</p>
                        <p>クレジットカード情報はStripeにより安全に処理されます。</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.payment-setup-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem;
}

.payment-setup-header {
    text-align: center;
    margin-bottom: 2rem;
}

.payment-setup-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
}

.payment-setup-header p {
    font-size: 1.1rem;
    color: var(--text-secondary);
}

.payment-setup-content {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

/* 選択済みプランバナー（常時表示） */
.selected-plan-banner {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    border-radius: 12px;
    padding: 1.5rem 2rem;
    color: white;
    box-shadow: var(--shadow-lg);
}

.selected-plan-banner-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.selected-plan-icon {
    font-size: 2.5rem;
    flex-shrink: 0;
}

.selected-plan-info {
    flex: 1;
}

.selected-plan-info h3 {
    font-size: 1.3rem;
    margin: 0 0 0.5rem 0;
    color: white;
}

.selected-plan-info p {
    margin: 0;
    font-size: 1rem;
    opacity: 0.9;
}

.selected-plan-price {
    text-align: right;
    flex-shrink: 0;
}

.price-amount {
    font-size: 1.8rem;
    font-weight: bold;
    color: white;
}

.payment-info-card,
.plan-selection-card,
.payment-method-card {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    padding: 2rem;
    box-shadow: var(--shadow-sm);
}

.payment-info-card h3,
.plan-selection-card h3,
.payment-method-card h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 0.5rem;
    color: var(--primary-color);
}

.section-description {
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

.info-item {
    margin-bottom: 0.75rem;
    font-size: 1rem;
    line-height: 1.6;
}

.info-item strong {
    color: var(--text-primary);
    margin-right: 0.5rem;
}

.info-item.note {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-light);
    font-size: 0.9rem;
    color: var(--text-secondary);
}

/* プラン選択グリッド */
.plan-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.plan-option-card {
    position: relative;
    border: 2px solid var(--border-light);
    border-radius: 12px;
    padding: 0;
    transition: all 0.3s ease;
    cursor: pointer;
    background: white;
}

.plan-option-card:hover {
    border-color: var(--primary-color);
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.plan-option-card.selected {
    border-color: var(--primary-color);
    background: rgba(23, 162, 184, 0.05);
    box-shadow: var(--shadow-lg);
}

.plan-label {
    display: block;
    padding: 1.5rem;
    cursor: pointer;
    width: 100%;
}

.plan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.plan-header h4 {
    font-size: 1.3rem;
    margin: 0;
    color: var(--primary-color);
    font-weight: bold;
}

.default-badge {
    padding: 0.25rem 0.75rem;
    background: var(--primary-color);
    color: white;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: bold;
}

.plan-description {
    font-size: 1rem;
    color: var(--text-secondary);
    margin-bottom: 1rem;
    line-height: 1.6;
}

.plan-trial {
    margin-top: 1rem;
}

.trial-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
}

.trial-badge.premium {
    background: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
}

.trial-icon {
    font-size: 1.1rem;
}

/* 支払い方法選択 */
.payment-method-selection {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
}

.payment-method-option {
    position: relative;
    border: 2px solid var(--border-light);
    border-radius: 12px;
    padding: 0;
    transition: all 0.3s ease;
    cursor: pointer;
    background: white;
}

.payment-method-option:hover {
    border-color: var(--primary-color);
    box-shadow: var(--shadow-md);
}

.payment-method-option.selected {
    border-color: var(--primary-color);
    background: rgba(23, 162, 184, 0.05);
}

.payment-radio {
    position: absolute;
    opacity: 0;
    pointer-events: none;
    width: 0;
    height: 0;
}

.payment-label {
    display: block;
    padding: 1.5rem;
    cursor: pointer;
}

.payment-label h4 {
    font-size: 1.2rem;
    margin: 0 0 0.5rem 0;
    color: var(--primary-color);
}

.payment-label p {
    margin: 0;
    font-size: 0.95rem;
    color: var(--text-secondary);
}

.plan-selection-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.invoice-notice {
    padding: 1.5rem;
    background: var(--bg-secondary);
    border-radius: 8px;
    margin-top: 1rem;
}

.invoice-notice p {
    margin: 0.5rem 0;
}

.invoice-note {
    margin-top: 1rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.stripe-payment {
    margin-top: 1.5rem;
}

.btn {
    padding: 0.75rem 2rem;
    font-size: 1rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
    font-weight: 500;
    justify-content: center;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-xl);
}

.btn-primary:disabled {
    background: var(--border-color);
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-large {
    padding: 1rem 2.5rem;
    font-size: 1.1rem;
    width: 100%;
}

.btn-icon {
    font-size: 1.2rem;
}

.security-info {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: 4px;
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-top: 1rem;
}

/* 画面中央のトースト通知 */
.payment-setup-toast {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    animation: fadeIn 0.3s ease-out;
}

.payment-setup-toast-card {
    background: white;
    padding: 2.5rem 3rem;
    border-radius: 20px;
    box-shadow: var(--shadow-xl);
    text-align: center;
    max-width: 400px;
    animation: slideIn 0.5s ease-out;
}

.payment-setup-toast-icon {
    font-size: 3.5rem;
    margin-bottom: 1rem;
    line-height: 1;
}

.payment-setup-toast-message {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.6;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* トライアル中の表示 */
.trial-status-banner {
    background: linear-gradient(135deg, var(--success-color) 0%, var(--success-color) 100%);
    border-radius: 12px;
    padding: 2rem;
    color: white;
    box-shadow: var(--shadow-lg);
}

.trial-status-banner h3 {
    font-size: 1.5rem;
    margin: 0 0 0.5rem 0;
    color: white;
}

.trial-description {
    font-size: 1rem;
    margin: 0 0 1.5rem 0;
    opacity: 0.9;
}

.early-bonus-card {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    backdrop-filter: blur(10px);
}

.early-bonus-card.premium {
    background: rgba(255, 193, 7, 0.2);
    border: 2px solid rgba(255, 193, 7, 0.5);
}

.bonus-icon {
    font-size: 3rem;
    flex-shrink: 0;
}

.bonus-content {
    flex: 1;
}

.bonus-content h4 {
    font-size: 1.3rem;
    margin: 0 0 0.5rem 0;
    color: white;
    font-weight: bold;
}

.bonus-content p {
    margin: 0.5rem 0;
    font-size: 1rem;
    line-height: 1.6;
    opacity: 0.95;
}

.bonus-deadline {
    margin-top: 0.75rem;
    font-weight: bold;
    font-size: 0.95rem;
}

.trial-action {
    text-align: center;
}

/* モバイル対応 */
@media (max-width: 768px) {
    .payment-setup-container {
        padding: 1rem;
    }

    .payment-setup-header h1 {
        font-size: 2rem;
    }

    .selected-plan-banner-content {
        flex-direction: column;
        text-align: center;
    }

    .selected-plan-price {
        text-align: center;
    }

    .plan-selection-grid {
        grid-template-columns: 1fr;
    }

    .payment-method-selection {
        flex-direction: column;
    }

    .payment-setup-toast-card {
        padding: 2rem 1.5rem;
        max-width: 90%;
    }

    .early-bonus-card {
        flex-direction: column;
        text-align: center;
    }

    .bonus-icon {
        font-size: 2.5rem;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    function getToastIconHtml(type) {
        const iconMap = { success: 'check_circle', error: 'brightness_alert', info: 'info' };
        if (typeof AidUniteThemeIcons !== 'undefined') {
            return AidUniteThemeIcons.html(iconMap[type] || 'info', 28);
        }
        return '';
    }

    function getBtnIconHtml(basename) {
        if (typeof AidUniteThemeIcons !== 'undefined') {
            return '<span class="btn-icon" aria-hidden="true">' + AidUniteThemeIcons.html(basename, 20) + '</span>';
        }
        return '';
    }

    // 画面中央のトースト通知を表示する関数
    function showCenterToast(message, type = 'success') {
        const existingToast = document.querySelector('.payment-setup-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = 'payment-setup-toast';
        toast.innerHTML = `
            <div class="payment-setup-toast-card">
                <div class="payment-setup-toast-icon">${getToastIconHtml(type)}</div>
                <div class="payment-setup-toast-message">${message}</div>
            </div>
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            }
        }, 3000);
    }

    // お試しカード「お試しで始める」ボタン（trial_card モード時のみ）
    <?php if ($plan_selection_required && $plan_display_mode === 'trial_card'): ?>
    $('#start-trial-btn').on('click', function() {
        var btn = $(this);
        var planId = btn.data('default-plan-id');
        if (!planId) {
            showCenterToast('プラン情報の取得に失敗しました', 'error');
            return;
        }
        btn.prop('disabled', true).html(getBtnIconHtml('hourglass_empty') + ' 処理中...');
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            type: 'POST',
            data: {
                action: 'aidunite_save_plan_selection',
                plan_id: planId,
                nonce: '<?php echo wp_create_nonce('aidunite_plan_selection_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showCenterToast('お試しを開始しました', 'success');
                    setTimeout(function() { window.location.reload(); }, 1200);
                } else {
                    showCenterToast((response.data && response.data.message) || 'エラーが発生しました', 'error');
                    btn.prop('disabled', false).html(getBtnIconHtml('start') + ' お試しで始める');
                }
            },
            error: function() {
                showCenterToast('通信エラーが発生しました', 'error');
                btn.prop('disabled', false).html(getBtnIconHtml('start') + ' お試しで始める');
            }
        });
    });
    <?php endif; ?>

    // プラン選択時の処理（プラン未選択かつ従来のラジオ選択がある場合のみ）
    <?php if ($plan_selection_required && $plan_display_mode !== 'coming_soon' && $plan_display_mode !== 'trial_card'): ?>
    $(document).on('change', '.plan-radio', function() {
        const selectedPlanId = $(this).val();
        const selectedPlanCard = $(this).closest('.plan-option-card');

        $('.plan-option-card').removeClass('selected');
        selectedPlanCard.addClass('selected');

        $('#save-plan-selection').prop('disabled', false);
    });

    $(document).on('click', '.plan-label', function(e) {
        e.preventDefault();
        const radio = $(this).siblings('.plan-radio').first();
        if (radio.length) {
            radio.prop('checked', true).trigger('change');
        }
    });

    $(document).on('click', '.plan-option-card', function(e) {
        if (!$(e.target).is('input') && !$(e.target).closest('label').length) {
            const radio = $(this).find('.plan-radio').first();
            if (radio.length) {
                radio.prop('checked', true).trigger('change');
            }
        }
    });

    // プラン選択保存
    $('#save-plan-selection').on('click', function() {
        const button = $(this);
        const selectedPlanId = $('.plan-radio:checked').val();

        if (!selectedPlanId) {
            showCenterToast('プランを選択してください', 'error');
            return;
        }

        button.prop('disabled', true).html(getBtnIconHtml('hourglass_empty') + ' 保存中...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'aidunite_save_plan_selection',
                plan_id: selectedPlanId,
                nonce: '<?php echo wp_create_nonce('aidunite_plan_selection_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showCenterToast('プランが選択されました', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showCenterToast(response.data.message || 'エラーが発生しました', 'error');
                    button.prop('disabled', false).html(getBtnIconHtml('save') + ' プランを選択');
                }
            },
            error: function() {
                showCenterToast('通信エラーが発生しました', 'error');
                button.prop('disabled', false).html(getBtnIconHtml('save') + ' プランを選択');
            }
        });
    });

    // 初期表示時に選択されているプランがあれば有効化
    const initialSelectedPlan = $('.plan-radio:checked').val();
    if (initialSelectedPlan) {
        $('#save-plan-selection').prop('disabled', false);
    }
    <?php endif; ?>

    // 支払い方法変更時の処理
    $(document).on('change', '.payment-radio', function() {
        const selectedMethod = $(this).val();
        $('.payment-method-option').removeClass('selected');
        $(this).closest('.payment-method-option').addClass('selected');

        // 支払い方法を保存
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'aidunite_save_payment_method',
                payment_method: selectedMethod,
                nonce: '<?php echo wp_create_nonce('aidunite_payment_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // ページをリロードしてUIを更新
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            }
        });
    });

    // Stripe決済開始
    $('#start-stripe-checkout').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        button.prop('disabled', true).html(getBtnIconHtml('hourglass_empty') + ' 処理中...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'aidunite_create_checkout',
                nonce: '<?php echo wp_create_nonce('aidunite_payment_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    window.location.href = response.data.url;
                } else {
                    const errorMessage = response.data && response.data.message ? response.data.message : 'エラーが発生しました';
                    showCenterToast(errorMessage, 'error');
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = '通信エラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                showCenterToast(errorMessage, 'error');
                button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php get_footer(); ?>
