<?php
/**
 * チーム申請・完了画面用の固定ページとテンプレートルーティング
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * チーム申請完了URLへのアクセスか
 */
function aidunite_is_team_registration_complete_request() {
    if (is_page('team-registration-complete')) {
        return true;
    }

    $pagename = (string) get_query_var('pagename');
    if ($pagename === 'team-registration-complete') {
        return true;
    }

    $path = aidunite_get_request_path_slug();
    return $path === 'team-registration-complete';
}

/**
 * リクエストURIから先頭スラッグを取得（サブディレクトリ設置対応）
 */
function aidunite_get_request_path_slug() {
    $request_path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if ($request_path === '') {
        return '';
    }

    $home_path = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($home_path !== '' && str_starts_with($request_path, $home_path . '/')) {
        $request_path = substr($request_path, strlen($home_path) + 1);
    } elseif ($home_path !== '' && $request_path === $home_path) {
        $request_path = '';
    }

    $parts = explode('/', $request_path);

    return $parts[0] ?? '';
}

/**
 * 完了画面用CSSを wp_enqueue_scripts で読み込む（テンプレート内 enqueue は wp_head 後で効かない）
 */
function aidunite_enqueue_team_registration_complete_assets() {
    if (!aidunite_is_team_registration_complete_request()) {
        return;
    }

    $base_css = get_stylesheet_directory() . '/assets/css/pages/registration-complete.css';
    if (is_readable($base_css)) {
        wp_enqueue_style(
            'registration-complete-style',
            get_stylesheet_directory_uri() . '/assets/css/pages/registration-complete.css',
            ['aidunite-style'],
            (string) filemtime($base_css)
        );
    }

    if (function_exists('aidunite_enqueue_application_review_assets')) {
        aidunite_enqueue_application_review_assets();
    }

    $team_complete_js = get_stylesheet_directory() . '/assets/js/team/team-registration-complete.js';
    if (is_readable($team_complete_js)) {
        wp_enqueue_script(
            'team-registration-complete',
            get_stylesheet_directory_uri() . '/assets/js/team/team-registration-complete.js',
            [],
            (string) filemtime($team_complete_js),
            true
        );
    }
}

/**
 * 完了画面用 body_class
 *
 * @param string[] $classes
 * @return string[]
 */
function aidunite_team_registration_complete_body_class($classes) {
    if (aidunite_is_team_registration_complete_request()) {
        $classes[] = 'team-registration-complete-screen';
    }

    return $classes;
}

add_filter('body_class', 'aidunite_team_registration_complete_body_class', 26);

add_action('wp_enqueue_scripts', 'aidunite_enqueue_team_registration_complete_assets', 20);

/**
 * 固定ページが無ければ作成し、テンプレートメタを揃える
 *
 * @param string $slug     post_name
 * @param string $title    管理画面用タイトル
 * @param string $template page-*.php ファイル名
 * @return int 0 = 失敗または未作成
 */
function aidunite_ensure_theme_page($slug, $title, $template) {
    $existing = get_page_by_path($slug);
    if ($existing instanceof WP_Post) {
        $current_template = (string) get_post_meta($existing->ID, '_wp_page_template', true);
        if ($current_template !== $template) {
            update_post_meta($existing->ID, '_wp_page_template', $template);
        }
        if ($existing->post_status !== 'publish') {
            wp_update_post([
                'ID'          => $existing->ID,
                'post_status' => 'publish',
            ]);
        }
        return (int) $existing->ID;
    }

    if (!is_admin() && !current_user_can('publish_pages')) {
        return 0;
    }

    $page_id = wp_insert_post([
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '',
    ], true);

    if (is_wp_error($page_id) || !$page_id) {
        return 0;
    }

    update_post_meta($page_id, '_wp_page_template', $template);
    return (int) $page_id;
}

/**
 * team-registration / team-registration-complete の固定ページを作成
 */
function aidunite_ensure_team_registration_pages() {
    aidunite_ensure_theme_page(
        'team-registration',
        'チーム作成申請',
        'page-team-registration.php'
    );
    aidunite_ensure_theme_page(
        'team-registration-complete',
        'チーム作成申請完了',
        'page-team-registration-complete.php'
    );
}

add_action('after_switch_theme', 'aidunite_ensure_team_registration_pages');
add_action('admin_init', 'aidunite_ensure_team_registration_pages');

/**
 * 固定ページがあってもテンプレートを強制（空の page.php 表示を防ぐ）
 *
 * @param string $template
 * @return string
 */
function aidunite_team_registration_template_include($template) {
    $map = [
        'team-registration'          => 'page-team-registration.php',
        'team-registration-complete' => 'page-team-registration-complete.php',
    ];

    $slug = '';
    if (is_page() && get_queried_object() instanceof WP_Post) {
        $slug = (string) get_queried_object()->post_name;
    }
    if ($slug === '') {
        $slug = aidunite_get_request_path_slug();
    }

    if (!isset($map[$slug])) {
        return $template;
    }

    $custom = get_stylesheet_directory() . '/' . $map[$slug];
    if (!is_readable($custom)) {
        return $template;
    }

    return $custom;
}

add_filter('template_include', 'aidunite_team_registration_template_include', 99);

/**
 * 固定ページ未作成時でも完了テンプレートを表示する（404 回避）
 */
function aidunite_team_registration_complete_404_fallback() {
    if (!is_404() || !aidunite_is_team_registration_complete_request()) {
        return;
    }

    $template = get_stylesheet_directory() . '/page-team-registration-complete.php';
    if (!is_readable($template)) {
        return;
    }

    global $wp_query;
    $wp_query->is_404 = false;
    $wp_query->is_page = true;
    $wp_query->is_singular = true;
    status_header(200);
    nocache_headers();

    include $template;
    exit;
}

add_action('template_redirect', 'aidunite_team_registration_complete_404_fallback', 1);

/**
 * 完了画面カードHTML
 *
 * @param string $mypage_url
 */
function aidunite_render_team_registration_complete_content($mypage_url = '') {
    if ($mypage_url === '') {
        $mypage_url = home_url('/mypage/');
    }

    if (!function_exists('aidunite_registration_complete_asset_icon')) {
        require_once get_stylesheet_directory() . '/functions/member/member-register-icons.php';
    }

    $part = get_stylesheet_directory() . '/template-parts/team/registration-complete-content.php';
    if (!is_readable($part)) {
        echo '<div class="registration-complete-card"><h1 class="registration-complete-title">チーム申請を受け付けました</h1>';
        echo '<p><a href="' . esc_url($mypage_url) . '">マイページへ</a></p></div>';
        return;
    }

    $context = function_exists('aidunite_get_team_registration_complete_context')
        ? aidunite_get_team_registration_complete_context()
        : [];

    load_template($part, false, [
        'mypage_url' => $mypage_url,
        'context'    => $context,
    ]);
}
