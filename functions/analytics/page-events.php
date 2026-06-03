<?php
/**
 * 行動イベント（aidunite_page_events）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/analytics-db.php';
require_once __DIR__ . '/analytics-config.php';
require_once __DIR__ . '/page-analytics-aggregate.php';

/**
 * @param array<string, mixed> $data
 */
function aidunite_analytics_insert_page_event(array $data) {
    global $wpdb;
    $table = aidunite_analytics_page_events_table();

    if (function_exists('aidunite_normalize_analytics_payload')) {
        $data = aidunite_normalize_analytics_payload($data);
    }

    $event_type = sanitize_key((string) ($data['event_type'] ?? 'view'));
    if (!in_array($event_type, ['view', 'click', 'submit', 'complete', 'leave'], true)) {
        $event_type = 'view';
    }

    $metric_date = current_time('Y-m-d');
    $wpdb->insert(
        $table,
        [
            'metric_date' => $metric_date,
            'created_at' => current_time('mysql'),
            'event_type' => $event_type,
            'team_id' => max(0, (int) ($data['team_id'] ?? 0)),
            'user_id' => max(0, (int) ($data['user_id'] ?? 0)),
            'page_key' => aidunite_analytics_sanitize_page_key((string) ($data['page_key'] ?? '')),
            'target_key' => sanitize_key((string) ($data['target_key'] ?? '')),
            'duration_sec' => max(0, min(AIDUNITE_ANALYTICS_MAX_DWELL_SEC, (int) ($data['duration_sec'] ?? 0))),
            'session_id' => substr(sanitize_text_field((string) ($data['session_id'] ?? '')), 0, 64),
            'referrer_page' => aidunite_analytics_sanitize_page_key((string) ($data['referrer_page'] ?? '')),
        ],
        ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
    );
}

/**
 * match-board 閲覧チーム数・申請チーム数（期間）
 *
 * @param string $from Y-m-d
 * @param string $to Y-m-d
 * @return array{board_view_teams:int, apply_teams:int, cv_rate:float|null}
 */
function aidunite_analytics_get_match_board_cv_summary($from, $to) {
    global $wpdb;
    $table = aidunite_analytics_page_events_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return ['board_view_teams' => 0, 'apply_teams' => 0, 'cv_rate' => null];
    }

    $board_views = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT team_id) FROM {$table}
             WHERE metric_date >= %s AND metric_date <= %s
             AND page_key IN ('match-board', 'match-board-own')
             AND event_type = 'view' AND team_id > 0",
            $from,
            $to
        )
    );

    $apply_teams = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT team_id) FROM {$table}
             WHERE metric_date >= %s AND metric_date <= %s
             AND event_type IN ('click', 'submit')
             AND target_key LIKE %s
             AND team_id > 0",
            $from,
            $to,
            'match_apply%'
        )
    );

    $cv = $board_views > 0 ? round(($apply_teams / $board_views) * 100, 1) : null;

    return [
        'board_view_teams' => $board_views,
        'apply_teams' => $apply_teams,
        'cv_rate' => $cv,
    ];
}
