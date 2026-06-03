<?php
/**
 * テーマ静的アセット（CSS / JS）のパスヘルパー
 *
 * 正本:
 * - CSS … assets/css/（components / pages / layout）
 * - JS  … assets/js/（common / pages / schedule / match / team / admin）
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $relative assets/js/ からの相対パス（例: common/dom-utils.js）
 * @return string
 */
function aidunite_theme_js_path($relative) {
    return get_stylesheet_directory() . '/assets/js/' . ltrim($relative, '/');
}

/**
 * @param string $relative
 * @return string
 */
function aidunite_theme_js_uri($relative) {
    return get_stylesheet_directory_uri() . '/assets/js/' . ltrim($relative, '/');
}

/**
 * @param string $relative assets/css/ からの相対パス（例: pages/front-page.css）
 * @return string
 */
function aidunite_theme_css_path($relative) {
    return get_stylesheet_directory() . '/assets/css/' . ltrim($relative, '/');
}

/**
 * @param string $relative
 * @return string
 */
function aidunite_theme_css_uri($relative) {
    return get_stylesheet_directory_uri() . '/assets/css/' . ltrim($relative, '/');
}

/**
 * JS を enqueue（filemtime バージョン）
 *
 * @param string $handle
 * @param string $relative assets/js/ からの相対パス
 * @param array  $deps
 * @param bool   $in_footer
 */
function aidunite_enqueue_theme_script($handle, $relative, $deps = [], $in_footer = true) {
    $path = aidunite_theme_js_path($relative);
    if (!is_readable($path)) {
        return;
    }
    wp_enqueue_script(
        $handle,
        aidunite_theme_js_uri($relative),
        $deps,
        (string) filemtime($path),
        $in_footer
    );
}
