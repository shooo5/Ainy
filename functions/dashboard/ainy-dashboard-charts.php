<?php
/**
 * Ainy ダッシュボード折れ線グラフ用データ
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * KPI カードクリックで切り替えるメトリック
 *
 * @return string[]
 */
function aidunite_dashboard_get_clickable_chart_metrics() {
    return ['users_total', 'teams_total', 'matches_established', 'pending_matches'];
}

/**
 * メトリック ID → DB カラム
 *
 * @return array<string, string>
 */
function aidunite_dashboard_chart_column_map() {
    return [
        'users_total' => 'users_total',
        'teams_total' => 'teams_total',
        'schedules_confirmed' => 'schedules_confirmed',
        'schedules_recruit' => 'schedules_recruit',
        'schedules_tentative' => 'schedules_tentative',
        'matches_established' => 'matches_established',
        'match_applications' => 'match_applications',
        'pending_matches' => 'pending_matches',
        'paid_users' => 'paid_users',
        'revenue_estimate' => 'revenue_estimate',
    ];
}

/**
 * @return array<string, string>
 */
function aidunite_dashboard_chart_metric_labels() {
    return [
        'users_total' => '登録ユーザー',
        'teams_total' => '登録チーム',
        'matches_established' => '成立マッチ',
        'pending_matches' => '申請中マッチ',
    ];
}

/**
 * @param string $period today|7d|30d|month
 * @return array{from:string, to:string, label:string}
 */
function aidunite_dashboard_chart_period($period) {
    if (function_exists('aidunite_dashboard_get_period_dates')) {
        return aidunite_dashboard_get_period_dates($period);
    }
    $today = current_time('Y-m-d');
    return ['from' => $today, 'to' => $today, 'label' => '今日'];
}

/**
 * @param string $from Y-m-d
 * @param string $to Y-m-d
 * @return string[]
 */
function aidunite_dashboard_chart_date_labels($from, $to) {
    $labels = [];
    $cursor = strtotime($from . ' 00:00:00');
    $end = strtotime($to . ' 00:00:00');
    if ($cursor === false || $end === false) {
        return [$from];
    }
    while ($cursor <= $end) {
        $labels[] = date('Y-m-d', $cursor);
        $cursor = strtotime('+1 day', $cursor);
    }
    return $labels;
}

/**
 * 折れ線グラフ用データ
 *
 * @param string $metric_id
 * @param string $period
 * @return array{metric_id:string, label:string, beta:bool, labels:string[], values:int[], period_label:string}
 */
function aidunite_dashboard_get_chart_data($metric_id, $period = '7d') {
    $map = aidunite_dashboard_chart_column_map();
    $metric_id = sanitize_key($metric_id);
    if (!isset($map[$metric_id])) {
        $metric_id = 'users_total';
    }

    $column = $map[$metric_id];
    $dates = aidunite_dashboard_chart_period($period);
    $from = $dates['from'];
    $to = $dates['to'];
    $labels = aidunite_dashboard_chart_date_labels($from, $to);

    $label_map = aidunite_dashboard_chart_metric_labels();
    $registry = function_exists('aidunite_analytics_get_metric')
        ? aidunite_analytics_get_metric($metric_id)
        : null;
    $metric_label = $label_map[$metric_id] ?? ($registry['label'] ?? '登録ユーザー');
    $beta = !empty($registry['beta']);

    $values_by_date = array_fill_keys($labels, 0);
    if (function_exists('aidunite_analytics_get_metrics_series')) {
        $rows = aidunite_analytics_get_metrics_series($from, $to, $column);
        foreach ($rows as $row) {
            $d = $row['metric_date'] ?? '';
            if (isset($values_by_date[$d])) {
                $values_by_date[$d] = (int) ($row['value'] ?? 0);
            }
        }
    }

    $today = current_time('Y-m-d');
    if (isset($values_by_date[$today])) {
        if ($metric_id === 'users_total' && function_exists('aidunite_dashboard_count_users')) {
            $values_by_date[$today] = aidunite_dashboard_count_users(null, null);
        } elseif ($metric_id === 'teams_total' && function_exists('aidunite_dashboard_count_teams')) {
            $values_by_date[$today] = aidunite_dashboard_count_teams(null, null);
        } elseif ($metric_id === 'matches_established' && function_exists('aidunite_dashboard_count_established_matches')) {
            $values_by_date[$today] = aidunite_dashboard_count_established_matches($today, $today);
        } elseif ($metric_id === 'pending_matches' && function_exists('aidunite_dashboard_count_pending_matches')) {
            $values_by_date[$today] = aidunite_dashboard_count_pending_matches();
        } elseif ($metric_id === 'paid_users' && function_exists('aidunite_dashboard_count_paid_users')) {
            $values_by_date[$today] = aidunite_dashboard_count_paid_users();
        }
    }

    $values = [];
    foreach ($labels as $day) {
        $values[] = (int) ($values_by_date[$day] ?? 0);
    }

    return [
        'metric_id' => $metric_id,
        'label' => $metric_label,
        'beta' => $beta,
        'labels' => $labels,
        'values' => $values,
        'period_label' => $dates['label'],
    ];
}

/**
 * 1ページ分の利用状況エントリ
 *
 * @param string $page_key
 * @param string $from
 * @param string $to
 * @param string[] $day_labels
 * @param int|null $views_override 集計済み PV（null なら DB 日次から算出）
 * @param int|null $time_total_override
 * @return array<string, mixed>
 */
function aidunite_dashboard_build_page_usage_entry($page_key, $from, $to, array $day_labels, $views_override = null, $time_total_override = null) {
    $page_key = function_exists('aidunite_analytics_sanitize_page_key')
        ? aidunite_analytics_sanitize_page_key($page_key)
        : sanitize_key($page_key);

    $views_by_date = array_fill_keys($day_labels, 0);
    $avg_by_date = array_fill_keys($day_labels, 0);

    if (function_exists('aidunite_analytics_get_page_daily_series')) {
        $daily = aidunite_analytics_get_page_daily_series($page_key, $from, $to);
        foreach ($daily as $d) {
            $day = $d['metric_date'] ?? '';
            $v = (int) ($d['views'] ?? 0);
            $t = (int) ($d['time_total_sec'] ?? 0);
            if (isset($views_by_date[$day])) {
                $views_by_date[$day] = $v;
                $avg_by_date[$day] = $v > 0 ? (int) round($t / $v) : 0;
            }
        }
    }

    $views = $views_override;
    $time_total = $time_total_override;
    if ($views === null) {
        $views = array_sum($views_by_date);
    }
    if ($time_total === null) {
        $time_total = 0;
        if (function_exists('aidunite_analytics_get_page_daily_series')) {
            foreach (aidunite_analytics_get_page_daily_series($page_key, $from, $to) as $d) {
                $time_total += (int) ($d['time_total_sec'] ?? 0);
            }
        }
    }

    $views = (int) $views;
    $avg_sec = $views > 0 ? (int) round($time_total / $views) : 0;

    return [
        'page_key' => $page_key,
        'label' => function_exists('aidunite_analytics_page_key_label')
            ? aidunite_analytics_page_key_label($page_key)
            : $page_key,
        'views' => $views,
        'avg_sec' => $avg_sec,
        'series' => [
            'labels' => $day_labels,
            'views' => array_values($views_by_date),
            'avg_sec' => array_values($avg_by_date),
        ],
    ];
}

/**
 * ページ利用状況（カード＋折れ線用）
 *
 * @param string $period
 * @return array{period_label:string, default_page_key:string, pages:array<int, array>}
 */
function aidunite_dashboard_get_page_usage_charts_payload($period = '7d') {
    $dates = aidunite_dashboard_chart_period($period);
    $from = $dates['from'];
    $to = $dates['to'];
    $day_labels = aidunite_dashboard_chart_date_labels($from, $to);

    $rows = function_exists('aidunite_analytics_get_page_rows_aggregated')
        ? aidunite_analytics_get_page_rows_aggregated($from, $to, 12)
        : [];

    $by_key = [];
    foreach ($rows as $row) {
        $key = (string) ($row['page_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $by_key[$key] = aidunite_dashboard_build_page_usage_entry(
            $key,
            $from,
            $to,
            $day_labels,
            (int) ($row['views'] ?? 0),
            (int) ($row['time_total_sec'] ?? 0)
        );
    }

    $pinned = function_exists('aidunite_analytics_get_pinned_dashboard_page_keys')
        ? aidunite_analytics_get_pinned_dashboard_page_keys()
        : ['home'];

    $pages = [];
    foreach ($pinned as $pinned_key) {
        if (isset($by_key[$pinned_key])) {
            $pages[] = $by_key[$pinned_key];
            unset($by_key[$pinned_key]);
        } else {
            $pages[] = aidunite_dashboard_build_page_usage_entry($pinned_key, $from, $to, $day_labels, 0, 0);
        }
    }

    foreach ($by_key as $entry) {
        $pages[] = $entry;
    }

    return [
        'period_label' => $dates['label'],
        'default_page_key' => isset($pages[0]['page_key']) ? $pages[0]['page_key'] : 'home',
        'pages' => $pages,
    ];
}

/**
 * ダッシュボード表示用 JSON（wp_localize_script）
 *
 * @param string $period
 * @return array<string, mixed>
 */
function aidunite_dashboard_get_charts_payload($period = '7d') {
    $metrics = [];
    foreach (aidunite_dashboard_get_clickable_chart_metrics() as $metric_id) {
        $metrics[$metric_id] = aidunite_dashboard_get_chart_data($metric_id, $period);
    }

    return [
        'period' => $period,
        'defaultMetric' => 'users_total',
        'metrics' => $metrics,
        'pageUsage' => aidunite_dashboard_get_page_usage_charts_payload($period),
    ];
}
