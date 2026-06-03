<?php
/**
 * 離脱分析（ファネル離脱 + ページ離脱）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/team-funnel.php';
require_once __DIR__ . '/page-events.php';

/**
 * @return array{
 *   funnel_groups: array,
 *   page_dropoffs: array<int, array<string, mixed>>,
 *   has_events: bool
 * }
 */
function aidunite_analytics_get_churn_report() {
    $funnel = aidunite_analytics_get_team_funnel_summary();
    $page_dropoffs = aidunite_analytics_get_page_churn_rows();
    return [
        'funnel_groups' => $funnel['groups'] ?? [],
        'page_dropoffs' => $page_dropoffs,
        'has_events' => aidunite_analytics_page_events_available(),
    ];
}

function aidunite_analytics_page_events_available() {
    global $wpdb;
    $table = aidunite_analytics_page_events_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

/**
 * 直近30日: ページ別 view 数と leave（滞在のみ）比率
 *
 * @return array<int, array<string, mixed>>
 */
function aidunite_analytics_get_page_churn_rows() {
    if (!aidunite_analytics_page_events_available()) {
        return [];
    }

    global $wpdb;
    $table = aidunite_analytics_page_events_table();
    $to = current_time('Y-m-d');
    $from = date('Y-m-d', strtotime('-29 days', strtotime($to . ' 00:00:00')));

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT page_key,
                    SUM(CASE WHEN event_type = 'view' THEN 1 ELSE 0 END) AS views,
                    SUM(CASE WHEN event_type = 'leave' THEN 1 ELSE 0 END) AS leaves,
                    SUM(CASE WHEN event_type IN ('click','submit') THEN 1 ELSE 0 END) AS actions
             FROM {$table}
             WHERE metric_date >= %s AND metric_date <= %s
             GROUP BY page_key
             HAVING views > 0
             ORDER BY views DESC
             LIMIT 20",
            $from,
            $to
        ),
        ARRAY_A
    ) ?: [];

    $out = [];
    foreach ($rows as $row) {
        $views = (int) ($row['views'] ?? 0);
        $actions = (int) ($row['actions'] ?? 0);
        $cv = $views > 0 ? round(($actions / $views) * 100, 1) : 0;
        $leave_rate = $views > 0 ? round(((int) ($row['leaves'] ?? 0) / $views) * 100, 1) : 0;
        $out[] = [
            'page_key' => (string) ($row['page_key'] ?? ''),
            'label' => aidunite_analytics_page_key_label((string) ($row['page_key'] ?? '')),
            'views' => $views,
            'actions' => $actions,
            'cv_rate' => $cv,
            'leave_rate' => $leave_rate,
        ];
    }
    return $out;
}
