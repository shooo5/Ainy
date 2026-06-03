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
$team_id = get_user_meta($user_id, 'team_id', true);
$status = aidunite_get_payment_status($user_id);
$last_updated = get_user_meta($user_id, 'payment_status_updated', true);

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
        </div>
    </div>
</div>

<style>
.payment-required-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.payment-required-header {
    text-align: center;
    margin-bottom: 2rem;
}

.payment-required-header h1 {
    font-size: 2rem;
    color: var(--danger-color);
    margin-bottom: 0.5rem;
}

.status-card,
.restriction-card,
.action-card {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.status-card h3,
.restriction-card h3,
.action-card h3 {
    font-size: 1.3rem;
    margin-bottom: 1rem;
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 0.5rem;
}

.status-item {
    margin-bottom: 0.75rem;
    font-size: 1rem;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: bold;
}

.status-badge.status-unpaid {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
}

.status-badge.status-trial {
    background: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
}

.restriction-warning {
    padding: 1rem;
    background: rgba(255, 193, 7, 0.1);
    border-left: 4px solid var(--warning-color);
    border-radius: 4px;
}

.restriction-active {
    padding: 1rem;
    background: rgba(220, 53, 69, 0.1);
    border-left: 4px solid var(--danger-color);
    border-radius: 4px;
}

.restriction-warning p,
.restriction-active p {
    margin: 0.5rem 0;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}

.action-buttons .btn {
    flex: 1;
    min-width: 150px;
    padding: 0.75rem 1.5rem;
    text-align: center;
    text-decoration: none;
    border-radius: 4px;
    font-size: 1rem;
    transition: all 0.3s;
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

/* モバイル対応 */
@media (max-width: 768px) {
    .payment-required-container {
        padding: 1rem;
    }

    .action-buttons {
        flex-direction: column;
    }

    .action-buttons .btn {
        width: 100%;
    }
}
</style>

<?php get_footer(); ?>
