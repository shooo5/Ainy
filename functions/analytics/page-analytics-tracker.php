<?php
/**
 * ページ分析トラッキング（バッファ・除外判定・フロント enqueue）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/analytics-db.php';
require_once __DIR__ . '/analytics-config.php';

const AIDUNITE_ANALYTICS_RATE_LIMIT_PREFIX = 'aidunite_analytics_rl_';
const AIDUNITE_ANALYTICS_MAX_DWELL_SEC = 1800;
const AIDUNITE_ANALYTICS_RATE_MAX = 120;
const AIDUNITE_ANALYTICS_RATE_WINDOW = 60;

/**
 * 計測対象外か（管理者・ダッシュボード・WP管理画面）
 */
function aidunite_analytics_should_track() {
    if (is_admin()) {
        return false;
    }
    if (is_page('ainy-dashboard') || is_page_template('page-ainy-dashboard.php')) {
        return false;
    }

    // トップページは未ログインでも計測（page_key = home）
    if (is_front_page()) {
        if (is_user_logged_in() && aidunite_analytics_is_excluded_user(get_current_user_id())) {
            return false;
        }
        return true;
    }

    if (!is_user_logged_in()) {
        return false;
    }
    return !aidunite_analytics_is_excluded_user(get_current_user_id());
}

/**
 * @param int $user_id
 */
function aidunite_analytics_is_excluded_user($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return true;
    }
    if (user_can($user_id, 'administrator')) {
        return true;
    }
    $role = get_user_meta($user_id, 'aidunite_role', true);
    return $role === 'administrator';
}

/**
 * 現在ページの page_key
 */
function aidunite_analytics_resolve_page_key() {
    if (is_front_page()) {
        return 'home';
    }
    if (is_singular()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            if ($post->post_type === 'page' && $post->post_name !== '') {
                return aidunite_analytics_sanitize_page_key($post->post_name);
            }
            return aidunite_analytics_sanitize_page_key($post->post_type . '-' . $post->ID);
        }
    }
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = trim((string) $path, '/');
    if ($path === '') {
        return 'home';
    }
    return aidunite_analytics_sanitize_page_key(str_replace('/', '-', $path));
}

/**
 * @param string $date Y-m-d
 * @return string
 */
function aidunite_analytics_page_buffer_key($date) {
    return AIDUNITE_ANALYTICS_PAGE_BUFFER_PREFIX . $date;
}

/**
 * @param string $page_key
 * @param int $views
 * @param int $time_sec
 */
function aidunite_analytics_buffer_add($page_key, $views = 0, $time_sec = 0) {
    $date = current_time('Y-m-d');
    $key = aidunite_analytics_page_buffer_key($date);
    $buffer = get_transient($key);
    if (!is_array($buffer)) {
        $buffer = [];
    }
    $page_key = aidunite_analytics_sanitize_page_key($page_key);
    if (!isset($buffer[$page_key])) {
        $buffer[$page_key] = ['views' => 0, 'time_total_sec' => 0];
    }
    $buffer[$page_key]['views'] += max(0, (int) $views);
    $buffer[$page_key]['time_total_sec'] += max(0, min(AIDUNITE_ANALYTICS_MAX_DWELL_SEC, (int) $time_sec));
    set_transient($key, $buffer, DAY_IN_SECONDS);
}

/**
 * IP レート制限
 */
function aidunite_analytics_check_rate_limit() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $hash = md5($ip);
    $tkey = AIDUNITE_ANALYTICS_RATE_LIMIT_PREFIX . $hash;
    $count = (int) get_transient($tkey);
    if ($count >= AIDUNITE_ANALYTICS_RATE_MAX) {
        return false;
    }
    set_transient($tkey, $count + 1, AIDUNITE_ANALYTICS_RATE_WINDOW);
    return true;
}

function aidunite_analytics_enqueue_tracker_script() {
    if (!aidunite_analytics_should_track()) {
        return;
    }

    $path = get_stylesheet_directory() . '/assets/js/admin/page-analytics-tracker.js';
    if (!is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'aidunite-page-analytics-tracker',
        get_stylesheet_directory_uri() . '/assets/js/admin/page-analytics-tracker.js',
        array(),
        (string) filemtime($path),
        true
    );

    $session_id = '';
    if (is_user_logged_in()) {
        $session_id = wp_hash(wp_json_encode([
            'u' => get_current_user_id(),
            'd' => current_time('Y-m-d'),
        ]));
    }

    wp_localize_script('aidunite-page-analytics-tracker', 'aidunitePageAnalytics', [
        'restUrl' => esc_url_raw(rest_url('aidunite/v1/analytics/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'pageKey' => aidunite_analytics_resolve_page_key(),
        'maxDwellSec' => AIDUNITE_ANALYTICS_MAX_DWELL_SEC,
        'teamId' => aidunite_analytics_resolve_user_team_id(),
        'userId' => is_user_logged_in() ? (int) get_current_user_id() : 0,
        'sessionId' => $session_id,
        'referrerPage' => isset($_SERVER['HTTP_REFERER']) ? aidunite_analytics_sanitize_page_key(wp_parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '') : '',
    ]);
}
add_action('wp_enqueue_scripts', 'aidunite_analytics_enqueue_tracker_script', 30);
