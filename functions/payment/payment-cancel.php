<?php
/**
 * 解約処理
 * サブスクリプションの解約、後払い方式対応
 */

require_once get_template_directory() . '/functions/payment/stripe-core.php';
require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';
require_once get_template_directory() . '/functions/common/error-handler.php';

// StripeサブスクリプションIDの取得・設定関数は payment-config.php に定義済み

/**
 * サブスクリプションを解約（後払い方式）
 * @param int $user_id ユーザーID
 * @return array|WP_Error 成功時は解約情報、失敗時はWP_Error
 */
function aidunite_cancel_subscription($user_id) {
    // Stripe SDKのチェック
    if (!class_exists('\Stripe\Stripe')) {
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKがインストールされていません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
    }

    if (!aidunite_init_stripe()) {
        return new WP_Error('stripe_init_failed', 'Stripeの初期化に失敗しました');
    }

    // StripeサブスクリプションIDを取得
    $subscription_id = aidunite_get_stripe_subscription_id($user_id);

    if (empty($subscription_id)) {
        return new WP_Error('no_subscription', 'サブスクリプションが見つかりません');
    }

    // チームID（ログ用・操作中 team を優先）
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    try {
        // Stripeサブスクリプションを取得
        $subscription = \Stripe\Subscription::retrieve($subscription_id);

        // 解約処理（即座に解約、ただしその月は利用可能）
        // 後払い方式のため、現在の請求期間の終了日まで利用可能
        $subscription->cancel();

        // 解約日を記録
        $cancelled_date = current_time('mysql');
        update_user_meta($user_id, 'subscription_cancelled_date', $cancelled_date);

        // 支払いステータスを更新
        aidunite_set_payment_status($user_id, 'cancelled');

        // 現在の請求期間の終了日を取得（後払い方式のため、その月の月末まで利用可能）
        $current_period_end = $subscription->current_period_end;
        $available_until = date('Y-m-d', $current_period_end);

        // 次回請求日を計算（後払い方式のため、翌月1日に当月分を請求）
        $next_billing_date = date('Y-m-01', strtotime('+1 month', $current_period_end));

        error_log("解約処理完了 (User ID: {$user_id}, Team ID: {$team_id}, 利用可能期間: 〜{$available_until})");

        return [
            'success' => true,
            'cancelled_date' => $cancelled_date,
            'available_until' => $available_until,
            'next_billing_date' => $next_billing_date, // 翌月1日に当月分を請求
        ];

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("解約処理エラー (User ID: {$user_id}): " . $e->getMessage());
        // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
        return new WP_Error('cancel_failed', '解約処理に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
    }
}

/**
 * Ajax: サブスクリプション解約
 */
add_action('wp_ajax_aidunite_cancel_subscription', 'aidunite_ajax_cancel_subscription');
function aidunite_ajax_cancel_subscription() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_auth();
    if (!$auth_result->is_valid()) {
        // 認証エラーはcritical
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: 'ログインが必要です',
            'authentication_required'
        );
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_payment_nonce');
    if (is_wp_error($nonce_result)) {
        // CSRFエラーはcritical
        AidUniteApiResponse::send_critical_error(
            'セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。',
            'csrf_verification_failed'
        );
        return;
    }

    $user_id = $auth_result->user_id;

    // 解約処理を実行
    $result = aidunite_cancel_subscription($user_id);

    if (is_wp_error($result)) {
        // 支払い関連のエラーはcriticalとして扱う
        AidUniteApiResponse::send_critical_error(
            $result->get_error_message(),
            'subscription_cancel_failed'
        );
        return;
    }

    wp_send_json_success([
        'message' => '解約が完了しました',
        'cancelled_date' => $result['cancelled_date'],
        'available_until' => $result['available_until'],
        'next_billing_date' => $result['next_billing_date'],
    ]);
}
