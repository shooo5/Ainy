<?php
/**
 * 解約処理（team 単位・§12B 出口ゲート対応）
 */

require_once get_template_directory() . '/functions/payment/stripe-core.php';
require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';
require_once get_template_directory() . '/functions/common/error-handler.php';

/**
 * team 単位の解約手続き開始
 *
 * @param int $team_id
 * @param int $user_id 代表者
 * @return array|WP_Error
 */
function aidunite_payment_exit_start_team_cancellation($team_id, $user_id) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0) {
        return new WP_Error('team_required', 'チームが指定されていません。');
    }
    if (function_exists('aidunite_payment_exit_read_pending') && aidunite_payment_exit_read_pending($team_id) !== null) {
        return new WP_Error('exit_pending', 'すでに解約・退会・解散の手続き中です。');
    }
    $subscription_id = aidunite_get_team_stripe_subscription_id($team_id, $user_id);
    if ($subscription_id === '') {
        return new WP_Error('no_subscription', 'サブスクリプションが見つかりません。決済登録後に解約できます。');
    }
    if (!function_exists('aidunite_payment_exit_evaluate_gates')) {
        return new WP_Error('gates_unavailable', '出口ゲートが利用できません。');
    }
    $gates = aidunite_payment_exit_evaluate_gates($team_id);
    if (!$gates['can_start']) {
        return new WP_Error('gate_a_blocked', implode(' ', $gates['messages'] ?: ['翌月以降の試合を先にキャンセルしてください。']));
    }
    $complete_at = aidunite_payment_exit_compute_completion_date($team_id, 'cancel');
    if (function_exists('aidunite_payment_exit_purge_next_month_items')) {
        aidunite_payment_exit_purge_next_month_items($team_id);
    }

    if (!class_exists('\Stripe\Stripe')) {
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKがインストールされていません。');
    }
    if (!aidunite_init_stripe()) {
        return new WP_Error('stripe_init_failed', 'Stripeの初期化に失敗しました');
    }

    try {
        $subscription = \Stripe\Subscription::retrieve($subscription_id);
        $subscription->cancel();
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("解約処理エラー (Team ID: {$team_id}): " . $e->getMessage());

        return new WP_Error('cancel_failed', '解約処理に失敗しました。しばらく時間をおいて再度お試しください。');
    }

    $cancelled_date = current_time('mysql');
    if (function_exists('aidunite_user_write_subscription_cancelled_date_meta')) {
        aidunite_user_write_subscription_cancelled_date_meta($user_id, $cancelled_date);
    }
    aidunite_set_team_payment_status($team_id, 'cancelling', $user_id);
    if (function_exists('aidunite_team_write_payment_available_until_meta')) {
        aidunite_team_write_payment_available_until_meta($team_id, $complete_at);
    }
    if (function_exists('aidunite_payment_exit_write_pending')) {
        aidunite_payment_exit_write_pending($team_id, $user_id, 'cancel', $complete_at);
    }

    error_log("解約手続き開始 (Team ID: {$team_id}, User ID: {$user_id}, 完了予定: {$complete_at})");

    return [
        'success' => true,
        'cancelled_date' => $cancelled_date,
        'available_until' => $complete_at,
        'complete_at' => $complete_at,
        'gate_b_matches' => $gates['gate_b_matches'],
    ];
}

/**
 * 解散・即時退会向け: team 単位 Stripe サブスクリプションを即時解約（ゲートなし）
 *
 * @param int $team_id
 * @param int $user_id
 * @return bool Stripe 解約を試みた場合 true
 */
function aidunite_payment_cancel_team_stripe_subscription_immediate($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    $subscription_id = aidunite_get_team_stripe_subscription_id($team_id, (int) $user_id);
    if ($subscription_id === '') {
        return false;
    }
    if (class_exists('\Stripe\Stripe') && aidunite_init_stripe()) {
        try {
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            $subscription->cancel();
        } catch (\Exception $e) {
            error_log("team Stripe 即時解約 (team={$team_id}): " . $e->getMessage());
        }
    }
    if (function_exists('aidunite_payment_exit_finalize_team_cancellation')) {
        aidunite_payment_exit_finalize_team_cancellation($team_id);
    }

    return true;
}

/**
 * 完了日到達時の team 解約確定
 *
 * @param int $team_id
 */
function aidunite_payment_exit_finalize_team_cancellation($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    $user_id = function_exists('aidunite_payment_exit_resolve_leader_user_id')
        ? aidunite_payment_exit_resolve_leader_user_id($team_id)
        : 0;
    aidunite_set_team_payment_status($team_id, 'cancelled', $user_id);
    if (function_exists('aidunite_set_team_product_plan')) {
        aidunite_set_team_product_plan($team_id, 'match');
    } elseif (function_exists('aidunite_team_write_product_plan_meta')) {
        aidunite_team_write_product_plan_meta($team_id, 'match');
    }
    if (function_exists('aidunite_payment_exit_persist_clear_team_subscription_meta')) {
        aidunite_payment_exit_persist_clear_team_subscription_meta($team_id);
    }
    error_log("解約完了 (Team ID: {$team_id})");
}

/**
 * サブスクリプション解約（team 単位へ委譲）
 *
 * @param int $user_id
 * @return array|WP_Error
 */
function aidunite_cancel_subscription($user_id) {
    $user_id = (int) $user_id;
    $team_id = function_exists('aidunite_payment_resolve_user_billing_team_id')
        ? aidunite_payment_resolve_user_billing_team_id($user_id)
        : 0;
    if ($team_id > 0 && function_exists('aidunite_payment_exit_start_team_cancellation')) {
        return aidunite_payment_exit_start_team_cancellation($team_id, $user_id);
    }

    return new WP_Error('team_required', 'チームが指定されていないため解約できません。');
}

add_action('wp_ajax_aidunite_cancel_subscription', 'aidunite_ajax_cancel_subscription');
function aidunite_ajax_cancel_subscription() {
    $auth_result = AidUniteAuthMiddleware::require_auth();
    if (!$auth_result->is_valid()) {
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: 'ログインが必要です',
            'authentication_required'
        );
        return;
    }

    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_payment_nonce');
    if (is_wp_error($nonce_result)) {
        AidUniteApiResponse::send_critical_error(
            'セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。',
            'csrf_verification_failed'
        );
        return;
    }

    $user_id = $auth_result->user_id;
    $team_id = (int) ($auth_result->team_id ?? 0);
    if ($team_id <= 0 && isset($_POST['team_id'])) {
        $team_id = (int) $_POST['team_id'];
    }
    if ($team_id <= 0 && function_exists('aidunite_payment_resolve_user_billing_team_id')) {
        $team_id = aidunite_payment_resolve_user_billing_team_id($user_id);
    }
    if ($team_id > 0 && function_exists('aidunite_payment_exit_start_team_cancellation')) {
        $result = aidunite_payment_exit_start_team_cancellation($team_id, $user_id);
    } else {
        $result = new WP_Error('team_required', 'チームが指定されていないため解約できません。');
    }

    if (is_wp_error($result)) {
        AidUniteApiResponse::send_critical_error(
            $result->get_error_message(),
            'subscription_cancel_failed'
        );
        return;
    }

    wp_send_json_success([
        'message' => '解約手続きを受け付けました',
        'cancelled_date' => $result['cancelled_date'] ?? '',
        'available_until' => $result['available_until'] ?? $result['complete_at'] ?? '',
        'next_billing_date' => $result['next_billing_date'] ?? '',
        'complete_at' => $result['complete_at'] ?? $result['available_until'] ?? '',
    ]);
}
