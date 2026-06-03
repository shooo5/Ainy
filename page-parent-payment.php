<?php
/**
 * Template Name: 保護者月謝支払いページ
 */

// functions.php で既に読み込まれているため、コメントアウト
// require_once get_template_directory() . '/functions/payment/payment-config.php';
// require_once get_template_directory() . '/functions/payment/payment-functions.php';

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

// 権限チェック（保護者・選手のみ）
$user_role = get_user_meta($user_id, 'aidunite_role', true);
if (!in_array($user_role, ['parent', 'player'])) {
    echo '<div class="page-container">';
    echo '<div class="error-message">このページは保護者・選手のみアクセス可能です。</div>';
    echo '</div>';
    get_footer();
    return;
}

$team = get_post($team_id);
$monthly_fee = get_post_meta($team_id, 'team_monthly_fee', true);
if (empty($monthly_fee)) {
    $monthly_fee = 5000; // デフォルト値
}
?>

<div class="page-container">
    <div class="parent-payment-container">
        <div class="parent-payment-header">
            <h1>月謝支払い</h1>
        </div>

        <div class="parent-payment-content">
            <!-- 支払い情報 -->
            <div class="payment-info-card">
                <h3>📊 支払い情報</h3>
                <div class="info-item">
                    <strong>チーム名:</strong> <?php echo esc_html($team->post_title); ?>
                </div>
                <div class="info-item">
                    <strong>月謝金額:</strong> ¥<?php echo number_format($monthly_fee); ?>/月
                </div>
                <div class="info-item">
                    <strong>支払い方法:</strong> サブスクリプション（毎月自動課金）
                </div>
                <div class="info-item note">
                    <p>※ チームに直接支払いが行われます</p>
                    <p>※ プラットフォーム手数料10%が差し引かれます</p>
                </div>
            </div>

            <!-- 支払い方法 -->
            <div class="payment-method-card">
                <h3>💳 支払い方法</h3>
                <div class="stripe-connect-payment">
                    <p>Stripe Connectで月謝を支払います。</p>
                    <p>クレジットカード情報は安全に処理されます。</p>
                    <button id="start-stripe-connect-checkout" class="btn btn-primary">
                        Stripe Connectで支払いを開始
                    </button>
                    <button id="cancel-stripe-connect-subscription" class="btn btn-secondary" style="margin-top:0.75rem;">
                        月謝を停止する
                    </button>
                </div>
            </div>

            <!-- 支払い履歴 -->
            <div class="payment-history-card">
                <h3>📜 支払い履歴</h3>
                <div id="payment-history-list">
                    <p class="loading-message">履歴を読み込み中...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.parent-payment-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.parent-payment-header {
    text-align: center;
    margin-bottom: 2rem;
}

.parent-payment-header h1 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.payment-info-card,
.payment-method-card,
.payment-history-card {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.payment-info-card h3,
.payment-method-card h3,
.payment-history-card h3 {
    font-size: 1.3rem;
    margin-bottom: 1rem;
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 0.5rem;
}

.info-item {
    margin-bottom: 0.75rem;
    font-size: 1rem;
}

.info-item.note {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-light);
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.stripe-connect-payment {
    margin-bottom: 1.5rem;
}

.stripe-connect-payment p {
    margin-bottom: 0.5rem;
}

#start-stripe-connect-checkout {
    width: 100%;
    padding: 1rem;
    font-size: 1.1rem;
    margin-top: 1rem;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s;
}

#start-stripe-connect-checkout:hover {
    background: var(--primary-dark);
}

#start-stripe-connect-checkout:disabled {
    background: var(--border-color);
    cursor: not-allowed;
}

.loading-message {
    text-align: center;
    color: var(--text-secondary);
    padding: 2rem;
}

.payment-history-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-history-table th,
.payment-history-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--border-light);
}

.payment-history-table th {
    background: var(--bg-secondary);
    font-weight: bold;
}

.empty-history {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

/* モバイル対応 */
@media (max-width: 768px) {
    .parent-payment-container {
        padding: 1rem;
    }

    .payment-history-table {
        font-size: 0.9rem;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // 支払い履歴を読み込む
    function loadPaymentHistory() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'aidunite_get_parent_payment_history',
                team_id: <?php echo $team_id; ?>,
                nonce: '<?php echo wp_create_nonce('aidunite_payment_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.history) {
                    displayPaymentHistory(response.data.history);
                } else {
                    $('#payment-history-list').html('<p class="empty-history">支払い履歴がありません</p>');
                }
            },
            error: function() {
                $('#payment-history-list').html('<p class="empty-history">履歴の読み込みに失敗しました</p>');
            }
        });
    }

    function displayPaymentHistory(history) {
        if (history.length === 0) {
            $('#payment-history-list').html('<p class="empty-history">支払い履歴がありません</p>');
            return;
        }

        let html = '<table class="payment-history-table">';
        html += '<thead><tr><th>支払い日</th><th>金額</th><th>ステータス</th></tr></thead>';
        html += '<tbody>';

        history.forEach(function(item) {
            html += '<tr>';
            html += '<td>' + item.payment_date + '</td>';
            html += '<td>¥' + item.amount.toLocaleString() + '</td>';
            html += '<td>' + item.status + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#payment-history-list').html(html);
    }

    // Stripe Connect Checkout開始
    $('#start-stripe-connect-checkout').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('処理中...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'aidunite_create_connect_checkout',
                team_id: <?php echo $team_id; ?>,
                nonce: '<?php echo wp_create_nonce('aidunite_payment_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    window.location.href = response.data.url;
                } else {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification(response.data.message || 'エラーが発生しました', 'error');
                    } else {
                        alert(response.data.message || 'エラーが発生しました');
                    }
                    button.prop('disabled', false).text('Stripe Connectで支払いを開始');
                }
            },
            error: function() {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('通信エラーが発生しました', 'error');
                } else {
                    alert('通信エラーが発生しました');
                }
                button.prop('disabled', false).text('Stripe Connectで支払いを開始');
            }
        });
    });

    // 月謝サブスクリプション解約
    $('#cancel-stripe-connect-subscription').on('click', function() {
        if (!confirm('月謝の自動支払いを停止しますか？\n当月分の請求タイミングなどはチーム代表者にご確認ください。')) {
            return;
        }

        const button = $(this);
        button.prop('disabled', true).text('処理中...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'aidunite_cancel_connect_subscription',
                team_id: <?php echo $team_id; ?>,
                nonce: '<?php echo wp_create_nonce('aidunite_payment_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification(response.data.message || '月謝の解約リクエストを受け付けました。', 'success');
                    } else {
                        alert(response.data.message || '月謝の解約リクエストを受け付けました。');
                    }
                    button.prop('disabled', false).text('月謝を停止する');
                    loadPaymentHistory();
                } else {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification(response.data.message || 'エラーが発生しました', 'error');
                    } else {
                        alert(response.data.message || 'エラーが発生しました');
                    }
                    button.prop('disabled', false).text('月謝を停止する');
                }
            },
            error: function() {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('通信エラーが発生しました', 'error');
                } else {
                    alert('通信エラーが発生しました');
                }
                button.prop('disabled', false).text('月謝を停止する');
            }
        });
    });

    // 初回読み込み
    loadPaymentHistory();
});
</script>

<?php get_footer(); ?>
