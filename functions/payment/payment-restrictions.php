<?php
/**
 * 決済制限機能
 * 未払い時の機能制限・ログイン制限
 */

require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';
require_once get_template_directory() . '/functions/common/auth-middleware.php';

/**
 * ユーザー向け決済導線（リダイレクト・要対応・ナビ）を有効化するか
 *
 * 当面 false — ページは残すが遷移・導線なし。再有効化時は true に変更。
 */
function aidunite_payment_user_flows_enabled() {
    return false;
}

/**
 * 支払い制限チェック
 */
function aidunite_check_payment_restrictions() {
    if (!aidunite_payment_user_flows_enabled()) {
        return;
    }
    // ログインページではスキップ（ログイン処理の干渉を防ぐ）
    if (is_page('login')) {
        return;
    }

    // 統一認証チェック（リダイレクトなし）
    $auth_result = AidUniteAuthMiddleware::require_auth(false);
    if (!$auth_result->is_valid()) {
        return;
    }

    $user_id = $auth_result->user_id;

    // 統一認証・権限チェック（管理者は制限をスキップ）
    $admin_result = AidUniteAuthMiddleware::require_admin(false);
    if ($admin_result->is_valid()) {
        return; // 管理者は制限をスキップ
    }

    // 支払いが必要かチェック
    if (!aidunite_is_payment_required($user_id)) {
        return;
    }

    $status = aidunite_get_payment_status($user_id);

    // 未払いの場合
    if ($status === 'unpaid') {
        // 最終支払い日を取得
        $last_paid_date = get_user_meta($user_id, 'payment_status_updated', true);

        if (!empty($last_paid_date)) {
            $days_unpaid = (time() - strtotime($last_paid_date)) / (24 * 60 * 60);

            // 30日経過後はログイン制限
            if ($days_unpaid >= 30) {
                // ログアウトして支払い必要ページへリダイレクト
                wp_logout();
                wp_redirect(home_url('/payment-required?reason=login_restricted'));
                exit;
            }
        }

        // 機能制限（即座に適用）
        // 支払い必要ページへリダイレクト（特定のページを除く）
        $current_page = get_queried_object();
        $allowed_pages = ['payment-required', 'payment-setup', 'mypage', 'notifications', 'login', 'profile-edit'];

        if ($current_page && !in_array($current_page->post_name, $allowed_pages)) {
            wp_redirect(home_url('/payment-required'));
            exit;
        }
    }
}

/**
 * 初回ログイン時のプラン選択リダイレクト
 *
 * 当面は hook 未登録（支払い設定への強制導線なし）。再有効化時は payment-restrictions.php 末尾の add_action を解除。
 */
function aidunite_check_first_login_plan_selection() {
    if (!aidunite_payment_user_flows_enabled()) {
        return;
    }

    // ログインページではスキップ（ログイン処理の干渉を防ぐ）
    if (is_page('login')) {
        return;
    }

    // 出欠報告ページではスキップ（フォーム送信処理の干渉を防ぐ）
    if (is_page('attendance-report')) {
        return;
    }

    // 統一認証チェック（リダイレクトなし）
    $auth_result = AidUniteAuthMiddleware::require_auth(false);
    if (!$auth_result->is_valid()) {
        return;
    }

    $user_id = $auth_result->user_id;
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    if ($team_id <= 0) {
        return;
    }

    // 統一認証・権限チェック（管理者はスキップ）
    $admin_result = AidUniteAuthMiddleware::require_admin(false);
    if ($admin_result->is_valid()) {
        return; // 管理者はスキップ
    }

    // 保護者と選手は月謝を支払うため、システム利用プラン選択は不要
    $user_role = aidunite_get_user_role($user_id);
    if (in_array($user_role, ['parent', 'player'])) {
        return; // 保護者・選手はプラン選択をスキップ
    }

    // システム利用プラン選択はチーム代表者のみ
    // チーム代表者以外はスキップ
    if ($user_role !== 'team_leader') {
        return;
    }

    // 支払い済みの場合はプラン選択チェックをスキップ
    $payment_status = aidunite_get_payment_status($user_id);
    if ($payment_status === 'paid') {
        return; // 支払い済みの場合はプラン選択を強制しない
    }

    // プラン選択済みかチェック
    $selected_plan = aidunite_get_selected_plan_id($team_id);

    if (!empty($selected_plan)) {
        return; // 既に選択済み
    }

    // プランが未設定の場合、デフォルトプランを自動適用
    $team_type = aidunite_get_team_type($team_id);
    $payment_mode = aidunite_get_team_payment_mode($team_id);

    // 教育委員会契約の場合はデフォルトプランを適用
    if ($payment_mode === 'board') {
        $config = aidunite_get_payment_config();
        $config_key = function_exists('aidunite_team_type_payment_config_key')
            ? aidunite_team_type_payment_config_key($team_id)
            : ($team_type === 'club' ? 'club' : 'school');
        $plans = $config[$config_key]['plans'];

        foreach ($plans as $plan) {
            if (!empty($plan['is_default'])) {
                aidunite_set_selected_plan_id($team_id, $plan['id']);
                $trial_start = aidunite_get_trial_start_date($team_id);
                if (empty($trial_start)) {
                    aidunite_set_trial_start_date($team_id);
                }
                $current_status = aidunite_get_payment_status($user_id);
                if (empty($current_status)) {
                    aidunite_set_payment_status($user_id, 'trial');
                }
                return;
            }
        }
        return;
    }

    // プラン未選択の場合、支払い設定ページ（統合ページ）へリダイレクト
    // 支払い設定ページでプラン選択UIを表示する
    $current_page = get_queried_object();

    if ($current_page && $current_page->post_name === 'payment-setup') {
        return; // 既に支払い設定ページにいる（プラン選択UIを表示）
    }

    // プラン選択をスキップするフラグが設定されている場合はスキップ
    if (isset($_GET['skip_plan_selection']) && $_GET['skip_plan_selection'] === '1') {
        // 一時的にプラン選択をスキップ（24時間有効）
        set_transient('skip_plan_selection_' . $user_id, true, DAY_IN_SECONDS);
                return;
            }

    // スキップフラグが設定されている場合はスキップ
    if (get_transient('skip_plan_selection_' . $user_id)) {
        return;
    }

    // 支払い設定ページ（統合ページ）へリダイレクト
    // プラン未選択の場合は、このページでプラン選択UIを表示
    wp_redirect(home_url('/payment-setup?plan_selection=required'));
    exit;
}

/**
 * 機能制限チェック（マッチ申請など）
 */
function aidunite_check_feature_restriction($feature_name = '') {
    if (!aidunite_payment_user_flows_enabled()) {
        return true;
    }

    // 統一認証チェック
    $auth_result = AidUniteAuthMiddleware::require_auth();
    if (!$auth_result->is_valid()) {
        return false;
    }

    $user_id = $auth_result->user_id;

    // 統一認証・権限チェック（管理者は制限なし）
    $admin_result = AidUniteAuthMiddleware::require_admin(false);
    if ($admin_result->is_valid()) {
        return true; // 管理者は制限なし
    }

    // 支払いが必要かチェック
    if (aidunite_is_payment_required($user_id)) {
        return false; // 機能制限
    }

    return true; // 制限なし
}

// ページ読み込み時に制限チェック
add_action('template_redirect', 'aidunite_check_payment_restrictions', 1);
// 初回プラン選択→支払い設定への強制リダイレクトは当面無効（承認後は /mypage/ 等へそのまま遷移）
// add_action('template_redirect', 'aidunite_check_first_login_plan_selection', 2);
