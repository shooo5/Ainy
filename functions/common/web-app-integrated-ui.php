<?php
/**
 * Webアプリ一体型上部UI（ヘッダー + ヒーロー + 白コンテンツ）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 一体型上部UIを使うか（ログイン済み Webアプリ + チームテーマ）
 */
function aidunite_should_use_web_app_integrated_ui() {
    if (!is_user_logged_in()) {
        return false;
    }
    if (!function_exists('aidunite_should_apply_team_ui_theme')) {
        return false;
    }

    return aidunite_should_apply_team_ui_theme();
}

/**
 * body_class に web-app-integrated-ui を付与
 */
function aidunite_web_app_integrated_ui_body_class($classes) {
    if (aidunite_should_use_web_app_integrated_ui()) {
        $classes[] = 'web-app-integrated-ui';
        $classes[] = 'web-app-page';
    }

    return $classes;
}
add_filter('body_class', 'aidunite_web_app_integrated_ui_body_class', 25);

/**
 * ヒーロー内一体型ヘッダーバー
 *
 * @param array $args active_nav, notification_count
 */
function aidunite_render_web_app_integrated_header_bar($args = []) {
    get_template_part('template-parts/web-app', 'integrated-header-bar', $args);
}

/**
 * ページヒーロー（タイトル行 + 任意ボディ）
 *
 * @param array $args {
 *   @type string $size          sm|md|lg
 *   @type string $title
 *   @type string $subtitle
 *   @type bool   $back
 *   @type string $back_url
 *   @type string $aria_label
 *   @type string $active_nav    mypage|notifications|schedule|none
 *   @type array  $actions       [ ['type'=>'link|button', 'href'=>'', 'label'=>'', 'aria'=>'', 'icon'=>'', 'onclick'=>''] ]
 *   @type string $hero_body     HTML
 * }
 */
function aidunite_render_web_app_page_hero($args = []) {
    get_template_part('template-parts/web-app', 'page-hero', $args);
}

/**
 * スペース区切りの class 文字列を個別に sanitize して返す
 *
 * @param string $class_string
 * @return string
 */
function aidunite_sanitize_html_class_list($class_string) {
    $class_string = trim((string) $class_string);
    if ($class_string === '') {
        return '';
    }
    $parts = preg_split('/\s+/', $class_string);
    if (!is_array($parts)) {
        return '';
    }
    $sanitized = [];
    foreach ($parts as $part) {
        $clean = sanitize_html_class($part);
        if ($clean !== '') {
            $sanitized[] = $clean;
        }
    }
    return implode(' ', array_unique($sanitized));
}

/**
 * 白コンテンツエリア開始
 *
 * @param string $extra_class
 */
function aidunite_render_web_app_content_open($extra_class = '') {
    $class = 'ainy-webapp-content';
    $extra = aidunite_sanitize_html_class_list($extra_class);
    if ($extra !== '') {
        $class .= ' ' . $extra;
    }
    echo '<main class="' . esc_attr($class) . '">';
}

/**
 * 白コンテンツエリア終了
 */
function aidunite_render_web_app_content_close() {
    echo '</main>';
}

/**
 * 固定ページの共通シェル開始（一体型ヒーロー + 白コンテンツ、またはレガシー dashboard-header）
 *
 * @param array<string, mixed> $args page_class, title, subtitle, hero_size, back, back_url, active_nav, actions, content_class, legacy_title, legacy_subtitle
 */
function aidunite_web_app_page_shell_open(array $args = []) {
    $page_class = isset($args['page_class']) ? aidunite_sanitize_html_class_list((string) $args['page_class']) : '';
    $title = isset($args['title']) ? (string) $args['title'] : '';
    $subtitle = isset($args['subtitle']) ? (string) $args['subtitle'] : '';
    $legacy_title = isset($args['legacy_title']) ? (string) $args['legacy_title'] : $title;
    $legacy_subtitle = isset($args['legacy_subtitle']) ? (string) $args['legacy_subtitle'] : $subtitle;
    $content_class = isset($args['content_class']) ? (string) $args['content_class'] : '';

    $use_integrated = function_exists('aidunite_should_use_web_app_integrated_ui')
        && aidunite_should_use_web_app_integrated_ui()
        && function_exists('aidunite_render_web_app_page_hero');

    $wrapper_class = 'team-dashboard-container';
    if ($page_class !== '') {
        $wrapper_class .= ' ' . $page_class;
    }
    if ($use_integrated) {
        $wrapper_class .= ' ainy-webapp-page';
    }

    echo '<div class="' . esc_attr(trim($wrapper_class)) . '">';

    if ($use_integrated) {
        $hero_args = isset($args['hero']) && is_array($args['hero'])
            ? $args['hero']
            : (function_exists('aidunite_build_default_web_app_hero_args')
                ? aidunite_build_default_web_app_hero_args([
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'size' => $args['hero_size'] ?? 'md',
                    'back' => $args['back'] ?? true,
                    'back_url' => $args['back_url'] ?? '',
                    'active_nav' => $args['active_nav'] ?? 'none',
                    'actions' => $args['actions'] ?? [],
                ])
                : []);
        if ($hero_args !== []) {
            aidunite_render_web_app_page_hero($hero_args);
        }
        aidunite_render_web_app_content_open($content_class);
        return;
    }

    if ($legacy_title !== '') {
        echo '<div class="dashboard-header">';
        echo '<h1>' . esc_html($legacy_title) . '</h1>';
        if ($legacy_subtitle !== '') {
            echo '<p>' . esc_html($legacy_subtitle) . '</p>';
        }
        echo '</div>';
    }
}

/**
 * 固定ページの共通シェル終了
 */
function aidunite_web_app_page_shell_close() {
    if (function_exists('aidunite_should_use_web_app_integrated_ui')
        && aidunite_should_use_web_app_integrated_ui()
        && function_exists('aidunite_render_web_app_content_close')
    ) {
        aidunite_render_web_app_content_close();
    }
    echo '</div>';
}
