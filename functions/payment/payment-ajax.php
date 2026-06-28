<?php
/**
 * 決済関連Ajax処理
 * プラン選択保存、支払い方法保存など
 */

require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';
require_once get_template_directory() . '/functions/payment/payment-tuition.php';
require_once get_template_directory() . '/functions/common/error-handler.php';

/**
 * Ajax: プラン選択保存
 */
add_action('wp_ajax_aidunite_save_plan_selection', 'aidunite_ajax_save_plan_selection');
function aidunite_ajax_save_plan_selection() {
    // 統一認証・権限チェック
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        AidUniteApiResponse::send_error($auth_result->error ?: 'ログインが必要です');
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_plan_selection_nonce');
    if (is_wp_error($nonce_result)) {
        AidUniteApiResponse::send_error($nonce_result->get_error_message(), null, 'normal', 'csrf_verification_failed');
        return;
    }

    $user_id = $auth_result->user_id;
    $team_id = $auth_result->team_id;

    $plan_id = AidUniteAuthMiddleware::sanitize($_POST['plan_id'] ?? '', 'text');

    if (empty($plan_id)) {
        AidUniteApiResponse::send_validation_error(['plan_id' => 'プランIDが指定されていません']);
        return;
    }

    $product_plan = strpos($plan_id, 'club') !== false ? 'club' : 'match';
    $payload = function_exists('aidunite_payment_persist_plan_selection')
        ? aidunite_payment_persist_plan_selection($team_id, $user_id, [
            'selected_plan_id' => $plan_id,
            'product_plan' => $product_plan,
        ])
        : [];

    AidUniteApiResponse::send_success([
        'redirect_url' => home_url('/payment-setup'),
        'payload' => $payload,
    ], 'プランが選択されました');
}

/**
 * Ajax: 月謝サブスクリプションの解約
 */
add_action('wp_ajax_aidunite_cancel_connect_subscription', 'aidunite_ajax_cancel_connect_subscription');
function aidunite_ajax_cancel_connect_subscription() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        wp_send_json_error(['message' => $auth_result->error ?: 'ログインが必要です']);
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_payment_nonce');
    if (is_wp_error($nonce_result)) {
        wp_send_json_error(['message' => $nonce_result->get_error_message()]);
        return;
    }

    $parent_user_id = $auth_result->user_id;
    $team_id = AidUniteAuthMiddleware::sanitize($_POST['team_id'] ?? 0, 'int');

    if (!$team_id) {
        $team_id = $auth_result->team_id;
    }

    if (!$team_id) {
        AidUniteApiResponse::send_error('チームが見つかりません', null, 'normal', 'team_not_found');
        return;
    }

    if (function_exists('aidunite_user_has_managed_team_access') && !aidunite_user_has_managed_team_access($parent_user_id, (int) $team_id)) {
        wp_send_json_error(['message' => '指定されたチームに対する操作権限がありません。']);
        return;
    }

    $result = aidunite_cancel_tuition_subscription($parent_user_id, $team_id);

    if (is_wp_error($result)) {
        AidUniteApiResponse::send_error($result->get_error_message());
        return;
    }

    AidUniteApiResponse::send_success(null, '月謝の解約リクエストを受け付けました。');
}

/**
 * Ajax: Stripe Connectによる月謝Checkoutセッション作成
 */
add_action('wp_ajax_aidunite_create_connect_checkout', 'aidunite_ajax_create_connect_checkout');
function aidunite_ajax_create_connect_checkout() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        wp_send_json_error(['message' => $auth_result->error ?: 'ログインが必要です']);
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_payment_nonce');
    if (is_wp_error($nonce_result)) {
        wp_send_json_error(['message' => $nonce_result->get_error_message()]);
        return;
    }

    $parent_user_id = $auth_result->user_id;
    $team_id = AidUniteAuthMiddleware::sanitize($_POST['team_id'] ?? 0, 'int');

    if (!$team_id) {
        // 親ユーザーのteam_idから補完
        $team_id = $auth_result->team_id;
    }

    if (!$team_id) {
        AidUniteApiResponse::send_error('チームが見つかりません', null, 'normal', 'team_not_found');
        return;
    }

    if (function_exists('aidunite_user_has_managed_team_access') && !aidunite_user_has_managed_team_access($parent_user_id, (int) $team_id)) {
        wp_send_json_error(['message' => '指定されたチームに対する操作権限がありません。']);
        return;
    }

    $result = aidunite_create_connect_checkout_session($parent_user_id, $team_id);

    if (is_wp_error($result)) {
        AidUniteApiResponse::send_error($result->get_error_message());
        return;
    }

    AidUniteApiResponse::send_success($result, 'Checkoutセッションを作成しました');
}
