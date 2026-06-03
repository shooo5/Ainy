<?php
/**
 * 行動イベント REST
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/page-events.php';

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/analytics/event', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_analytics_event',
        'permission_callback' => 'aidunite_rest_analytics_permission',
    ]);
});

/**
 * @param WP_REST_Request $request
 */
function aidunite_rest_analytics_event(WP_REST_Request $request) {
    $user_id = is_user_logged_in() ? (int) get_current_user_id() : 0;
    $team_id = aidunite_analytics_resolve_user_team_id($user_id);
    $payload = [
        'event_type' => $request->get_param('event_type'),
        'team_id' => $team_id,
        'user_id' => $user_id,
        'page_key' => $request->get_param('page_key'),
        'target_key' => $request->get_param('target_key'),
        'duration_sec' => $request->get_param('duration_sec'),
        'session_id' => $request->get_param('session_id'),
        'referrer_page' => $request->get_param('referrer_page'),
    ];
    if (function_exists('aidunite_normalize_analytics_payload')) {
        $payload = aidunite_normalize_analytics_payload($payload);
    }

    aidunite_analytics_insert_page_event($payload);

    $page_key = aidunite_analytics_sanitize_page_key((string) $request->get_param('page_key'));
    $event_type = (string) ($payload['event_type'] ?? $request->get_param('event_type'));
    if ($event_type === 'view' && $page_key !== '') {
        aidunite_analytics_buffer_add($page_key, 1, 0);
    }
    if ($event_type === 'leave') {
        $elapsed = (int) $request->get_param('duration_sec');
        if ($page_key !== '' && $elapsed > 0) {
            aidunite_analytics_buffer_add($page_key, 0, $elapsed);
        }
    }

    return new WP_REST_Response(['ok' => true], 200);
}
