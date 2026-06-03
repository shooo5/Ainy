<?php
/**
 * ログインページのフォーム送信処理（init フックで実行）
 * functions.php の No.10 付近から移設
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ログインフォーム送信時のみ処理。管理画面では実行しない。
 */
function aidunite_handle_login_form() {
    if (is_admin()) {
        return;
    }
    if (!isset($_POST['aidunite_login_submit'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['aidunite_login_nonce'] ?? '', 'aidunite_login_nonce')) {
        wp_safe_redirect(add_query_arg('login_error', urlencode('セキュリティチェックに失敗しました。'), home_url('/login')));
        exit;
    }

    $username = function_exists('aidunite_normalize_email') ? aidunite_normalize_email($_POST['user_email'] ?? '') : sanitize_email($_POST['user_email'] ?? '');
    $password = $_POST['user_pass'] ?? '';
    $remember = isset($_POST['rememberme']);

    $login_result = aidunite_custom_login($username, $password, $remember);

    if ($login_result['success']) {
        $redirect_url = $login_result['redirect_url'] ?? home_url('/mypage?login=success');
        wp_safe_redirect($redirect_url);
        exit;
    }

    wp_safe_redirect(add_query_arg('login_error', urlencode($login_result['message'] ?? ''), home_url('/login')));
    exit;
}

add_action('init', 'aidunite_handle_login_form', 0);
