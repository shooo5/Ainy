<?php
/**
 * 本登録ページ /approve-registration を専用テンプレートで必ず表示する。
 * （固定ページにテンプレート未割当だと page.php の空本文になり真っ白に見えるため）
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return bool
 */
function aidunite_is_approve_registration_request() {
    if (is_admin() || !isset($_SERVER['REQUEST_URI'])) {
        return false;
    }
    $path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = rtrim((string) $path, '/');

    return $path === '/approve-registration';
}

/**
 * /approve-registration へのアクセスを page-approve-registration.php で処理する。
 */
function aidunite_approve_registration_template_redirect() {
    if (!aidunite_is_approve_registration_request()) {
        return;
    }

    status_header(200);
    nocache_headers();

    $template = get_stylesheet_directory() . '/page-approve-registration.php';
    if (!is_readable($template)) {
        wp_die('本登録ページのテンプレートが見つかりません。', 'Error', ['response' => 500]);
    }

    require $template;
    exit;
}

add_action('template_redirect', 'aidunite_approve_registration_template_redirect', 0);