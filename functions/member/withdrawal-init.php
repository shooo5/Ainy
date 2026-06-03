<?php
/**
 * 退会関連の init / template_redirect フック（名前付き関数で登録）
 * functions.php の No.9 付近から移設。withdrawal-functions.php の補完。
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 代表者退会：/confirm-withdrawal への POST（rep_withdrawal_confirm）受付
 */
function aidunite_handle_confirm_withdrawal_post() {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, 'confirm-withdrawal') === false) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['rep_withdrawal_confirm']) || $_POST['rep_withdrawal_confirm'] !== '1' || !isset($_POST['token'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_POST['token']));
    $user_id = function_exists('aidunite_verify_withdrawal_token') ? aidunite_verify_withdrawal_token($token) : false;
    if ($user_id === false) {
        wp_redirect(home_url('/confirm-withdrawal?error=invalid'));
        exit;
    }
    $user = get_user_by('id', $user_id);
    if (!$user || !function_exists('aidunite_is_representative') || !aidunite_is_representative($user_id)) {
        wp_redirect(home_url('/confirm-withdrawal?error=invalid'));
        exit;
    }

    $notify_opponents = isset($_POST['notify_opponents']) && $_POST['notify_opponents'] === '1';
    $team_ids = function_exists('aidunite_get_representative_team_ids') ? aidunite_get_representative_team_ids($user_id) : [];
    $execute_at = time() + (defined('AIDUNITE_WITHDRAWAL_REP_DELAY_SECONDS') ? AIDUNITE_WITHDRAWAL_REP_DELAY_SECONDS : 30 * 24 * 3600);

    if (function_exists('aidunite_add_scheduled_withdrawal')) {
        aidunite_add_scheduled_withdrawal($user_id, $team_ids, $execute_at);
    }
    if (function_exists('aidunite_notify_team_dissolution_scheduled')) {
        aidunite_notify_team_dissolution_scheduled($user_id, $team_ids, $notify_opponents);
    }

    wp_redirect(home_url('/confirm-withdrawal?step=rep_scheduled'));
    exit;
}

/**
 * 退会確認リンク（withdraw=1&token=）クリック時の本退会処理（代表者は rep_confirm へ）
 */
function aidunite_handle_withdraw_link_click() {
    if (!isset($_GET['withdraw']) || $_GET['withdraw'] !== '1' || !isset($_GET['token'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_GET['token']));
    $user_id = function_exists('aidunite_verify_withdrawal_token') ? aidunite_verify_withdrawal_token($token) : false;
    if ($user_id === false) {
        wp_redirect(home_url('/confirm-withdrawal?error=invalid'));
        exit;
    }

    $user = get_user_by('id', $user_id);
    if (!$user) {
        wp_redirect(home_url('/confirm-withdrawal?error=invalid'));
        exit;
    }

    if (function_exists('aidunite_is_representative') && aidunite_is_representative($user_id)) {
        wp_redirect(home_url('/confirm-withdrawal?step=rep_confirm&token=' . rawurlencode($token)));
        exit;
    }

    if (function_exists('aidunite_cancel_all_subscriptions_before_withdrawal')) {
        $theme_dir = get_stylesheet_directory();
        if (!function_exists('aidunite_cancel_subscription')) {
            require_once $theme_dir . '/functions/payment/payment-cancel.php';
        }
        if (!function_exists('aidunite_cancel_tuition_subscription')) {
            require_once $theme_dir . '/functions/payment/payment-tuition.php';
        }
        aidunite_cancel_all_subscriptions_before_withdrawal($user_id);
    }

    if (function_exists('aidunite_execute_withdrawal')) {
        aidunite_execute_withdrawal($user_id);
    }

    wp_redirect(home_url('/confirm-withdrawal'));
    exit;
}

/**
 * /confirm-withdrawal を固定ページ未作成でも表示する（template_redirect）
 */
function aidunite_confirm_withdrawal_template_redirect() {
    if (is_admin() || !isset($_SERVER['REQUEST_URI'])) {
        return;
    }
    $path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path === '' || $path === '/') {
        return;
    }
    $path = rtrim($path, '/');
    if ($path !== '/confirm-withdrawal') {
        return;
    }
    status_header(200);
    include get_stylesheet_directory() . '/page-confirm-withdrawal.php';
    exit;
}

add_action('init', 'aidunite_handle_confirm_withdrawal_post', 5);
add_action('init', 'aidunite_handle_withdraw_link_click', 10);
add_action('template_redirect', 'aidunite_confirm_withdrawal_template_redirect', 5);
