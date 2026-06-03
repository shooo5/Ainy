<?php
/*
Template Name: チーム月謝管理
*/

// 統一認証・権限チェック（チーム代表者または管理者のみ）
$auth_result = AidUniteAuthMiddleware::require([
    'roles' => ['team_leader', 'administrator'],
    'team_id' => null,
    'redirect' => true,
]);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

$user_id = $auth_result->user_id;
$user_role = $auth_result->user_role;
$team_id = $auth_result->team_id;

if (!$team_id) {
    wp_die('あなたの所属チームが見つかりません。');
}

$team = get_post($team_id);
if (!$team || $team->post_type !== 'team') {
    wp_die('チーム情報が見つかりません。');
}

// 設定保存処理
$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aidunite_save_tuition_settings'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'aidunite_save_tuition_settings_' . $team_id)) {
        $message = 'セキュリティチェックに失敗しました。ページを再読み込みしてください。';
        $message_type = 'error';
    } else {
        $enabled = isset($_POST['team_tuition_enabled']) ? '1' : '0';
        $amount  = isset($_POST['team_monthly_fee']) ? (int) $_POST['team_monthly_fee'] : 0;

        update_post_meta($team_id, 'team_tuition_enabled', $enabled);

        if ($amount > 0) {
            update_post_meta($team_id, 'team_monthly_fee', $amount);
        } else {
            // 0以下の場合は未設定扱い（誤課金防止）
            delete_post_meta($team_id, 'team_monthly_fee');
        }

        $message = '月謝設定を保存しました。';
        $message_type = 'success';
    }
}

// 現在設定の取得
$tuition_enabled = get_post_meta($team_id, 'team_tuition_enabled', true) === '1';
$monthly_fee     = get_post_meta($team_id, 'team_monthly_fee', true);
$connect_acct    = get_post_meta($team_id, 'stripe_connect_account_id', true);

wp_enqueue_style(
    'aidunite-toggle-switch',
    get_stylesheet_directory_uri() . '/assets/css/components/toggle-switch.css',
    array(),
    '1.0.1'
);

get_header();

if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-team-payment-management team-payment-management-container',
        'title' => 'チーム月謝管理',
        'subtitle' => 'チーム月謝の金額設定と、Stripe Connect連携を管理します。',
    ]);
} else {
    echo '<div class="page-container team-payment-management-container"><div class="dashboard-header"><h1>チーム月謝管理</h1><p>チーム月謝の金額設定と、Stripe Connect連携を管理します。</p></div>';
}
?>

    <?php if (!empty($message)): ?>
        <div class="notice-<?php echo $message_type === 'error' ? 'error' : 'success'; ?>">
            <?php echo esc_html($message); ?>
        </div>
    <?php endif; ?>

    <div class="team-payment-management-grid">
        <!-- 月謝設定 -->
        <section class="card">
            <h2>📊 月謝設定</h2>
            <form method="post">
                <?php wp_nonce_field('aidunite_save_tuition_settings_' . $team_id); ?>
                <div class="form-group current-team-group">
                    <p class="current-team-text">現在のチーム: <strong><?php echo esc_html($team->post_title); ?></strong></p>
                </div>

                <div class="form-group tuition-toggle-row">
                    <span class="tuition-label">月謝機能:</span>
                    <span class="tuition-status-text" id="tuition-status-text"><?php echo $tuition_enabled ? '有効' : '無効'; ?></span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="team_tuition_enabled_toggle" name="team_tuition_enabled" value="1" <?php checked($tuition_enabled); ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <p class="help-text">有効にすると、保護者マイページから月謝支払いページ（/parent-payment）へ誘導されます。</p>

                <div class="form-group" id="tuition-amount-section">
                    <div class="current-setting-display">
                        <strong>現在の設定金額:</strong>
                        <span class="current-amount-value">
                            <?php if ($monthly_fee > 0): ?>
                                <?php echo number_format($monthly_fee); ?>円
                            <?php else: ?>
                                未設定
                            <?php endif; ?>
                        </span>
                    </div>
                    <label for="team_monthly_fee">月謝金額（円）</label>
                    <input
                        type="number"
                        id="team_monthly_fee"
                        name="team_monthly_fee"
                        min="0"
                        step="100"
                        value="<?php echo esc_attr($monthly_fee); ?>"
                        placeholder="例: 5000"
                    >
                    <p class="help-text">0または未入力の場合は「未設定」となり、月謝のCheckoutはエラーになります（誤課金防止）。</p>
                </div>

                <div class="form-actions">
                    <button type="submit" name="aidunite_save_tuition_settings" value="1" class="btn btn-primary">
                        設定を保存
                    </button>
                </div>
            </form>
        </section>

        <!-- Stripe Connect 連携 -->
        <section class="card">
            <h2>🔗 Stripe Connect 連携</h2>
            <div class="form-group">
                <p><strong>現在の連携状態:</strong></p>
                <?php if ($connect_acct): ?>
                    <p class="status-badge status-connected">
                        連携済み（アカウントID: <?php echo esc_html($connect_acct); ?>）
                    </p>
                <?php else: ?>
                    <p class="status-badge status-not-connected">
                        未連携（Stripe連携を開始してください）
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <p class="help-text">
                    「Stripe連携を開始」ボタンを押すと、Stripeの画面に移動します。<br>
                    口座情報や本人確認を完了すると、このチームの月謝を直接受け取れるようになります。
                </p>
                <div class="form-actions">
                    <button id="aidunite-start-connect-onboarding" class="btn btn-secondary">
                        Stripe連携を開始
                    </button>
                </div>
            </div>

            <div id="aidunite-connect-onboarding-message" class="inline-message"></div>
        </section>
    </div>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<style>
body.web-app-integrated-ui .page-team-payment-management .team-payment-management-grid {
    margin-top: var(--spacing-base, 1rem);
}

.team-payment-management-container {
    max-width: 960px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

body.web-app-integrated-ui .page-team-payment-management.team-payment-management-container,
body.web-app-integrated-ui .page-team-payment-management .ainy-webapp-content {
    padding-top: 0;
}

body.web-app-integrated-ui .page-team-payment-management .team-payment-management-container {
    max-width: none;
    margin: 0;
    padding: 0;
}
.team-payment-management-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
    align-items: stretch;
}
.card {
    background: var(--bg-primary);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    display: flex;
    flex-direction: column;
}
.card h2 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1.3rem;
}
.form-group {
    margin-bottom: 1.25rem;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.3rem;
}
.form-group input[type="number"] {
    width: 100%;
    padding: 0.6rem 0.8rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    font-size: 1rem;
}
.help-text {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 0.3rem;
}
.form-actions {
    margin-top: 1rem;
    text-align: right;
}
/* カード下部にボタンを揃える */
.card .form-actions {
    margin-top: auto;
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.6rem 1.4rem;
    border-radius: 999px;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.2s ease;
}
.btn-primary {
    background: var(--primary-color);
    color: white;
}
.btn-primary:hover {
    background: var(--primary-dark);
}
.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
}
.btn-secondary:hover {
    background: var(--border-light);
}

/* 月謝金額ブロック（機能OFF時はグレーアウト） */
.tuition-amount-disabled {
    opacity: 0.5;
    pointer-events: none;
}
.current-setting-display {
    margin-bottom: 0.75rem;
    padding: 0.6rem 0.8rem;
    background: var(--bg-secondary);
    border-radius: 8px;
    border: 1px solid var(--border-light);
    font-size: 0.95rem;
}
.current-setting-display strong {
    color: var(--text-primary);
    margin-right: 0.5rem;
}
.current-amount-value {
    color: var(--success-color);
    font-weight: 600;
}
.tuition-amount-disabled .current-setting-display {
    background: var(--bg-secondary);
    border-color: var(--border-color);
}
.tuition-amount-disabled .current-amount-value {
    color: var(--text-secondary);
}

.current-team-group {
    margin-bottom: 0.75rem;
}
.current-team-text {
    font-size: 0.95rem;
    color: var(--text-primary);
}
.tuition-toggle-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.tuition-label {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text-primary);
}
.tuition-status-text {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
}

/* トグルスイッチ: assets/css/components/toggle-switch.css */

.status-badge {
    display: inline-block;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
}
.status-connected {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}
.status-not-connected {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
}
.notice-success,
.notice-error {
    margin: 1rem 0;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.95rem;
}
.notice-success {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}
.notice-error {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
    border: 1px solid var(--danger-color);
}
.inline-message {
    margin-top: 0.5rem;
    font-size: 0.9rem;
}
.inline-message.error {
    color: var(--danger-color);
}
.inline-message.success {
    color: var(--success-color);
}

@media (max-width: 768px) {
    .team-payment-management-container {
        padding: 1.5rem 1rem 2rem;
    }
}
</style>

<script>
jQuery(function($) {
    // 月謝トグルに応じて金額入力ブロックをON/OFF（グレーアウト）
    function aiduniteUpdateTuitionUI() {
        const enabled = $('#team_tuition_enabled_toggle').is(':checked');
        const $section = $('#tuition-amount-section');
        const $input = $('#team_monthly_fee');
        const $status = $('#tuition-status-text');

        if (enabled) {
            $section.removeClass('tuition-amount-disabled');
            $input.prop('disabled', false);
            $status.text('有効');
        } else {
            $section.addClass('tuition-amount-disabled');
            $input.prop('disabled', true);
            $status.text('無効');
        }
    }

    aiduniteUpdateTuitionUI();
    $('#team_tuition_enabled_toggle').on('change', aiduniteUpdateTuitionUI);

    $('#aidunite-start-connect-onboarding').on('click', function() {
        const $btn = $(this);
        const $msg = $('#aidunite-connect-onboarding-message');

        $btn.prop('disabled', true).text('処理中...');
        $msg.removeClass('error success').text('');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'aidunite_create_connect_onboarding_link'
            }
        }).done(function(response) {
            if (response.success && response.data && response.data.url) {
                window.location.href = response.data.url;
            } else {
                const msg = (response.data && response.data.message) ? response.data.message : 'エラーが発生しました。';
                $msg.addClass('error').text(msg);
                $btn.prop('disabled', false).text('Stripe連携を開始');
            }
        }).fail(function() {
            $msg.addClass('error').text('通信エラーが発生しました。時間をおいて再度お試しください。');
            $btn.prop('disabled', false).text('Stripe連携を開始');
        });
    });
});
</script>

<?php get_footer(); ?>
