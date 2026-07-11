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
    $team_theme = isset($args['team_theme']) ? (string) $args['team_theme'] : '';
    if ($team_theme !== '' && !in_array($team_theme, ['boys', 'girls'], true)) {
        $team_theme = '';
    }

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

    echo '<div class="' . esc_attr(trim($wrapper_class)) . '"' . ($team_theme !== '' ? ' data-team-theme="' . esc_attr($team_theme) . '"' : '') . '>';

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

/**
 * 一体型 / ヒーローシェルを使うページで body_class を事前登録（get_header より前に呼ぶ）
 */
function aidunite_web_app_page_prepare_hero_shell_body_class() {
    $use_integrated = function_exists('aidunite_should_use_web_app_integrated_ui')
        && aidunite_should_use_web_app_integrated_ui();
    $use_hero = function_exists('aidunite_render_web_app_page_hero');

    if (!$use_integrated && !$use_hero) {
        return;
    }

    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    add_filter('body_class', static function ($classes) {
        if (!in_array('web-app-integrated-ui', $classes, true)) {
            $classes[] = 'web-app-integrated-ui';
        }
        if (!in_array('web-app-page', $classes, true)) {
            $classes[] = 'web-app-page';
        }

        return $classes;
    }, 30);
}

/**
 *
 * payment-setup 正本: integrated のときのみ shell_open。それ以外はヒーローまたはレガシー。
 *
 * @param array<string, mixed> $shell_args page_class, title, subtitle, back, back_url, active_nav, team_theme, …
 * @param array<string, mixed> $legacy legacy_container_class, legacy_back_label, legacy_back_nav_class, legacy_back_link_class, legacy_header_class
 * @return string integrated|hero|legacy
 */
function aidunite_web_app_page_shell_begin(array $shell_args, array $legacy = []) {
    $legacy = wp_parse_args($legacy, [
        'legacy_container_class' => 'page-container',
        'legacy_back_url' => (string) ($shell_args['back_url'] ?? ''),
        'legacy_back_label' => '戻る',
        'legacy_back_nav_class' => 'payment-setup-legacy-back',
        'legacy_back_link_class' => 'payment-setup-legacy-back__link',
        'legacy_header_class' => 'payment-setup-header',
    ]);

    if (!isset($shell_args['back']) && ($shell_args['back_url'] ?? '') !== '') {
        $shell_args['back'] = true;
    }

    if (
        function_exists('aidunite_should_use_web_app_integrated_ui')
        && aidunite_should_use_web_app_integrated_ui()
        && function_exists('aidunite_web_app_page_shell_open')
    ) {
        aidunite_web_app_page_shell_open($shell_args);

        return 'integrated';
    }

    if (function_exists('aidunite_render_web_app_page_hero')) {
        $hero_args = function_exists('aidunite_build_default_web_app_hero_args')
            ? aidunite_build_default_web_app_hero_args($shell_args)
            : array_merge(['size' => 'md'], $shell_args);
        $page_class = isset($shell_args['page_class'])
            ? aidunite_sanitize_html_class_list((string) $shell_args['page_class'])
            : '';
        $team_theme = isset($shell_args['team_theme']) ? (string) $shell_args['team_theme'] : '';
        if ($team_theme !== '' && !in_array($team_theme, ['boys', 'girls'], true)) {
            $team_theme = '';
        }
        $wrapper = 'team-dashboard-container ainy-webapp-page';
        if ($page_class !== '') {
            $wrapper .= ' ' . $page_class;
        }
        echo '<div class="' . esc_attr(trim($wrapper)) . '"'
            . ($team_theme !== '' ? ' data-team-theme="' . esc_attr($team_theme) . '"' : '')
            . '>';
        aidunite_render_web_app_page_hero($hero_args);
        if (function_exists('aidunite_render_web_app_content_open')) {
            aidunite_render_web_app_content_open();
        }

        return 'hero';
    }

    $container = aidunite_sanitize_html_class_list((string) $legacy['legacy_container_class']);
    if ($container === '') {
        $container = 'page-container';
    }
    echo '<div class="' . esc_attr($container) . '">';

    $back_url = (string) $legacy['legacy_back_url'];
    if ($back_url === '') {
        $back_url = (string) ($shell_args['back_url'] ?? '');
    }
    if ($back_url !== '' && !empty($shell_args['back'])) {
        echo '<nav class="' . esc_attr((string) $legacy['legacy_back_nav_class']) . '" aria-label="ページ戻る">';
        echo '<a href="' . esc_url($back_url) . '" class="' . esc_attr((string) $legacy['legacy_back_link_class']) . '">';
        if (function_exists('aidunite_render_theme_icon')) {
            echo aidunite_render_theme_icon('chevron_left', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '<span>' . esc_html((string) $legacy['legacy_back_label']) . '</span></a></nav>';
    }

    $title = (string) ($shell_args['title'] ?? '');
    $subtitle = (string) ($shell_args['subtitle'] ?? '');
    if ($title !== '') {
        echo '<div class="' . esc_attr((string) $legacy['legacy_header_class']) . '">';
        echo '<h1>' . esc_html($title) . '</h1>';
        if ($subtitle !== '') {
            echo '<p>' . esc_html($subtitle) . '</p>';
        }
        echo '</div>';
    }

    return 'legacy';
}

/**
 * @param string $mode aidunite_web_app_page_shell_begin() の戻り値
 */
function aidunite_web_app_page_shell_end($mode) {
    $mode = (string) $mode;
    if ($mode === 'integrated' && function_exists('aidunite_web_app_page_shell_close')) {
        aidunite_web_app_page_shell_close();

        return;
    }
    if ($mode === 'hero' && function_exists('aidunite_render_web_app_content_close')) {
        aidunite_render_web_app_content_close();
        echo '</div>';

        return;
    }
    echo '</div>';
}

/**
 * 決済系ページ共通: payment-setup.css
 */
function aidunite_enqueue_payment_setup_shared_styles() {
    $payment_setup_css = get_stylesheet_directory() . '/assets/css/pages/payment-setup.css';
    if (!is_readable($payment_setup_css)) {
        return;
    }
    if (wp_style_is('aidunite-payment-setup', 'enqueued')) {
        return;
    }
    wp_enqueue_style(
        'aidunite-payment-setup',
        get_stylesheet_directory_uri() . '/assets/css/pages/payment-setup.css',
        ['aidunite-style', 'button-style'],
        (string) filemtime($payment_setup_css)
    );
}
