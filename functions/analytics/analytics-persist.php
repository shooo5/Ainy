<?php
/**
 * 行動イベント（aidunite_page_events）保存本体
 *
 * @see docs/spec/analytics.md
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @return string[] */
function aidunite_analytics_canonical_event_types() {
    return ['view', 'click', 'submit', 'complete', 'leave'];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_analytics_normalize_input(array $raw) {
    if (function_exists('aidunite_normalize_analytics_payload')) {
        $raw = aidunite_normalize_analytics_payload($raw);
    }

    if (isset($raw['event_type'])) {
        $et = sanitize_key((string) $raw['event_type']);
        if (!in_array($et, aidunite_analytics_canonical_event_types(), true)) {
            $et = 'view';
        }
        $raw['event_type'] = $et;
    }

    if (isset($raw['funnel_step_id'])) {
        $step = strtolower(trim((string) $raw['funnel_step_id']));
        if ($step === 'accepted') {
            $step = 'established';
        }
        $raw['funnel_step_id'] = $step;
    }

    return $raw;
}

/**
 * page_events テーブルへ1行挿入（唯一の書き込み口）
 *
 * @param array<string, mixed> $data event_type, team_id, user_id, page_key, target_key, duration_sec, session_id, referrer_page
 * @return bool
 */
function aidunite_analytics_persist_insert_page_event(array $data) {
    global $wpdb;

    if (!function_exists('aidunite_analytics_page_events_table')) {
        require_once __DIR__ . '/analytics-db.php';
    }
    if (!function_exists('aidunite_analytics_sanitize_page_key')) {
        require_once __DIR__ . '/analytics-config.php';
    }

    $table = aidunite_analytics_page_events_table();
    $data = aidunite_analytics_normalize_input($data);

    $event_type = (string) ($data['event_type'] ?? 'view');
    if (!in_array($event_type, aidunite_analytics_canonical_event_types(), true)) {
        $event_type = 'view';
    }

    $max_dwell = defined('AIDUNITE_ANALYTICS_MAX_DWELL_SEC') ? (int) AIDUNITE_ANALYTICS_MAX_DWELL_SEC : 86400;

    $result = $wpdb->insert(
        $table,
        [
            'metric_date' => current_time('Y-m-d'),
            'created_at' => current_time('mysql'),
            'event_type' => $event_type,
            'team_id' => max(0, (int) ($data['team_id'] ?? 0)),
            'user_id' => max(0, (int) ($data['user_id'] ?? 0)),
            'page_key' => aidunite_analytics_sanitize_page_key((string) ($data['page_key'] ?? '')),
            'target_key' => sanitize_key((string) ($data['target_key'] ?? '')),
            'duration_sec' => max(0, min($max_dwell, (int) ($data['duration_sec'] ?? 0))),
            'session_id' => substr(sanitize_text_field((string) ($data['session_id'] ?? '')), 0, 64),
            'referrer_page' => aidunite_analytics_sanitize_page_key((string) ($data['referrer_page'] ?? '')),
        ],
        ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
    );

    return $result !== false;
}

/**
 * @param int $limit
 * @return array<string, mixed>
 */
function aidunite_analytics_get_recent_page_events_sample($limit = 5) {
    global $wpdb;
    if (!function_exists('aidunite_analytics_page_events_table')) {
        require_once __DIR__ . '/analytics-db.php';
    }
    $table = aidunite_analytics_page_events_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return ['available' => false, 'rows' => []];
    }
    $limit = max(1, min(20, (int) $limit));
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, metric_date, event_type, team_id, user_id, page_key, target_key
             FROM {$table} ORDER BY id DESC LIMIT %d",
            $limit
        ),
        ARRAY_A
    );

    return [
        'available' => true,
        'rows' => $rows ?: [],
    ];
}
