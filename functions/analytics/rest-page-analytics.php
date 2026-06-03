<?php
/**
 * ページ分析 REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/page-analytics-tracker.php';

add_action('rest_api_init', function () {
    register_rest_route('aidunite/v1', '/analytics/page-view', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_analytics_page_view',
        'permission_callback' => 'aidunite_rest_analytics_permission',
    ]);

    register_rest_route('aidunite/v1', '/analytics/page-leave', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_analytics_page_leave',
        'permission_callback' => 'aidunite_rest_analytics_permission',
    ]);
});

/**
 * @return bool|WP_Error
 */
function aidunite_rest_analytics_permission() {
    if (is_user_logged_in() && aidunite_analytics_is_excluded_user(get_current_user_id())) {
        return new WP_Error('rest_forbidden', '計測対象外です', ['status' => 403]);
    }
    if (!aidunite_analytics_check_rate_limit()) {
        return new WP_Error('rate_limited', 'リクエストが多すぎます', ['status' => 429]);
    }
    return true;
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_analytics_page_view(WP_REST_Request $request) {
    $page_key = aidunite_analytics_sanitize_page_key($request->get_param('page_key') ?? '');
    if ($page_key === '') {
        return new WP_Error('invalid_page_key', 'page_key が不正です', ['status' => 400]);
    }
    aidunite_analytics_buffer_add($page_key, 1, 0);
    return new WP_REST_Response(['success' => true], 200);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_analytics_page_leave(WP_REST_Request $request) {
    $page_key = aidunite_analytics_sanitize_page_key($request->get_param('page_key') ?? '');
    $elapsed = (int) $request->get_param('elapsed_sec');
    if ($page_key === '') {
        return new WP_Error('invalid_page_key', 'page_key が不正です', ['status' => 400]);
    }
    aidunite_analytics_buffer_add($page_key, 0, $elapsed);
    return new WP_REST_Response(['success' => true], 200);
}
