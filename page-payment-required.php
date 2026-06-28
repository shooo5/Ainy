<?php
/**
 * Template Name: 支払い必要ページ
 */

require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';

get_header();

// 統一認証・権限チェック
$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

$user_id = $auth_result->user_id;
$team_id = function_exists('aidunite_user_read_primary_team_id')
    ? aidunite_user_read_primary_team_id($user_id)
    : 0;
$payment_user_meta = function_exists('aidunite_payment_read_canonical_user_meta')
    ? aidunite_payment_read_canonical_user_meta($user_id)
    : [];
$status = function_exists('aidunite_get_payment_status')
    ? aidunite_get_payment_status($user_id)
    : (string) ($payment_user_meta['payment_status'] ?? '');
$last_updated = (string) ($payment_user_meta['payment_status_updated'] ?? '');

// 未払い期間を計算
$days_unpaid = 0;
if (!empty($last_updated)) {
    $days_unpaid = floor((time() - strtotime($last_updated)) / (24 * 60 * 60));
}

// 30日後の統一制限（FINAL-SPECに基づく）
$days_until_restriction = 30 - $days_unpaid;
if ($days_until_restriction < 0) {
    $days_until_restriction = 0;
}
?>

<div class="page-container">
    <div class="payment-required-container">
        <div class="payment-required-header">
            <h1>支払いが必要です</h1>
        </div>

        <div class="payment-required-content">
            <!-- 現在の状況 -->
            <div class="status-card">
                <h3>📋 現在の状況</h3>
                <div class="status-item">
                    <strong>支払いステータス:</strong>
                    <span class="status-badge status-<?php echo esc_attr($status ?: 'none'); ?>">
                        <?php
                        if ($status === 'unpaid') {
                            echo '未払い';
                        } elseif ($status === 'trial') {
                            echo 'トライアル中';
                        } else {
                            echo '不明';
                        }
                        ?>
                    </span>
                </div>
                <?php if (!empty($last_updated)): ?>
                <div class="status-item">
                    <strong>最終支払い日:</strong> <?php echo esc_html(date('Y年m月d日', strtotime($last_updated))); ?>
                </div>
                <?php endif; ?>
                <?php if ($days_unpaid > 0): ?>
                <div class="status-item">
                    <strong>未払い期間:</strong> <?php echo esc_html($days_unpaid); ?>日
                </div>
                <?php endif; ?>
            </div>

            <!-- 制限内容 -->
            <div class="restriction-card">
                <h3>⚠️ 制限内容</h3>
                <?php if ($days_until_restriction > 0): ?>
                    <div class="restriction-warning">
                        <p><strong><?php echo esc_html($days_until_restriction); ?>日後</strong>に機能制限とログイン制限が適用されます。</p>
                        <p>支払いを完了すると、すべての機能が利用可能になります。</p>
                    </div>
                <?php else: ?>
                    <div class="restriction-active">
                        <p><strong>機能制限とログイン制限が適用中です。</strong></p>
                        <p>支払いを完了すると、即座に制限が解除されます。</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- アクション -->
            <div class="action-card">
                <h3>💳 支払いを完了する</h3>
                <p>支払いを完了すると、すべての機能が利用可能になります。</p>
                <div class="action-buttons">
                    <a href="<?php echo home_url('/payment-setup'); ?>" class="btn btn-primary">
                        支払い設定へ
                    </a>
                    <a href="<?php echo home_url('/mypage'); ?>" class="btn btn-secondary">
                        マイページへ
                    </a>
                </div>
            </div>

            <div class="action-card support-card">
                <h3>❓ お困りのとき</h3>
                <p>カードエラーや請求内容のご不明点は、下記からご確認ください。</p>
                <div class="action-buttons">
                    <a href="<?php echo esc_url(home_url('/faq')); ?>" class="btn btn-secondary">FAQ（支払いトラブル）</a>
                    <a href="<?php echo esc_url(home_url('/contact')); ?>" class="btn btn-secondary">お問い合わせ</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
