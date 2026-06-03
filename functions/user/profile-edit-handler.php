<?php
/**
 * プロフィール編集ページのフォーム送信処理（init フックで早期実行）
 * functions.php の No.10 付近から移設
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST/GET で update_profile またはプロフィールフィールドが来た場合に更新処理を実行
 */
function aidunite_handle_profile_edit() {
    if (is_admin()) {
        return;
    }

    $has_update_profile = isset($_POST['update_profile']) || isset($_GET['update_profile']);
    $has_profile_fields = false;
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $profile_fields = ['first_name', 'last_name', 'display_name', 'user_email', 'user_phone', 'user_birth_date', 'user_gender', 'user_address', 'user_bio', 'new_password', 'confirm_password'];
        foreach ($profile_fields as $field) {
            if (isset($_GET[$field])) {
                $has_profile_fields = true;
                break;
            }
        }
    }

    $is_post = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    $is_get = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';

    if (!$has_update_profile && !$has_profile_fields) {
        return;
    }

    if ($is_get && ($has_update_profile || $has_profile_fields)) {
        $_POST = array_merge($_POST ?? [], $_GET);
        if (!isset($_POST['update_profile'])) {
            $_POST['update_profile'] = '1';
        }
    }

    if (!$is_post && !$is_get) {
        return;
    }
    if (!isset($_POST['update_profile'])) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    $current_user = wp_get_current_user();
    $nonce = $_POST['profile_edit_nonce'] ?? '';
    $skip_nonce_check = false;
    if ($is_get && empty($nonce)) {
        $skip_nonce_check = true;
    }

    if (!$skip_nonce_check && !wp_verify_nonce($nonce, 'update_profile_info')) {
        wp_safe_redirect(add_query_arg('error', 'security', home_url('/profile-edit')));
        exit;
    }

    $user_email = !empty($_POST['user_email'])
        ? (function_exists('aidunite_normalize_email') ? aidunite_normalize_email($_POST['user_email']) : sanitize_email($_POST['user_email']))
        : $current_user->user_email;

    $user_data = [
        'ID' => $current_user->ID,
        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
        'display_name' => sanitize_text_field($_POST['display_name'] ?? ''),
        'user_email' => $user_email,
    ];

    if (!empty($_POST['user_email']) && $user_email !== $current_user->user_email) {
        $email_exists = email_exists($user_email);
        if ($email_exists && (int) $email_exists !== (int) $current_user->ID) {
            wp_safe_redirect(add_query_arg('error', 'email_exists', home_url('/profile-edit')));
            exit;
        }
    }

    if (!empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            wp_safe_redirect(add_query_arg('error', 'password_mismatch', home_url('/profile-edit')));
            exit;
        }
        if (strlen($_POST['new_password']) < 8) {
            wp_safe_redirect(add_query_arg('error', 'password_short', home_url('/profile-edit')));
            exit;
        }
    }

    $result = wp_update_user($user_data);

    if (is_wp_error($result)) {
        wp_safe_redirect(add_query_arg('error', 'update_failed', home_url('/profile-edit')));
        exit;
    }

    $custom_fields = [
        'user_phone' => sanitize_text_field($_POST['user_phone'] ?? ''),
        'user_birth_date' => sanitize_text_field($_POST['user_birth_date'] ?? ''),
        'user_gender' => sanitize_text_field($_POST['user_gender'] ?? ''),
        'user_address' => sanitize_textarea_field($_POST['user_address'] ?? ''),
        'user_bio' => sanitize_textarea_field($_POST['user_bio'] ?? ''),
        'user_avatar_type' => 'emoji',
        'user_avatar_emoji' => sanitize_text_field($_POST['user_avatar_emoji'] ?? '👤'),
    ];
    foreach ($custom_fields as $key => $value) {
        update_user_meta($current_user->ID, $key, $value);
    }

    if (!empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        wp_set_password($_POST['new_password'], $current_user->ID);
        wp_set_current_user($current_user->ID);
        wp_set_auth_cookie($current_user->ID, true);
    }

    wp_safe_redirect(add_query_arg('updated', '1', home_url('/profile-edit')));
    exit;
}

add_action('init', 'aidunite_handle_profile_edit', 1);
