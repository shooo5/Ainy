<?php
/**
 * PV データ一覧用集計（日・週・月）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/analytics-db.php';

/**
 * @param string $granularity day|week|month
 * @param int $limit
 * @return array{granularity:string, rows:array<int, array<string, mixed>>}
 */
function aidunite_analytics_get_pv_period_report($granularity = 'month', $limit = 12) {
    $granularity = in_array($granularity, ['day', 'week', 'month'], true) ? $granularity : 'month';
    $limit = max(1, min(90, (int) $limit));

    $to = current_time('Y-m-d');
    if ($granularity === 'day') {
        $from = date('Y-m-d', strtotime('-' . ($limit - 1) . ' days', strtotime($to . ' 00:00:00')));
    } elseif ($granularity === 'week') {
        $from = date('Y-m-d', strtotime('-' . (($limit - 1) * 7) . ' days', strtotime($to . ' 00:00:00')));
    } else {
        $from = date('Y-m-01', strtotime('-' . ($limit - 1) . ' months', strtotime($to . ' 00:00:00')));
    }

    global $wpdb;
    $table = aidunite_analytics_page_table();
    $daily = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT metric_date, SUM(views) AS views, SUM(time_total_sec) AS time_total_sec
             FROM {$table}
             WHERE metric_date >= %s AND metric_date <= %s
             GROUP BY metric_date
             ORDER BY metric_date ASC",
            $from,
            $to
        ),
        ARRAY_A
    ) ?: [];

    $buckets = [];
    foreach ($daily as $row) {
        $d = (string) ($row['metric_date'] ?? '');
        if ($d === '') {
            continue;
        }
        $key = aidunite_analytics_pv_bucket_key($d, $granularity);
        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'period_key' => $key,
                'period_label' => aidunite_analytics_pv_bucket_label($key, $granularity),
                'views' => 0,
                'time_total_sec' => 0,
                'from_date' => $d,
                'to_date' => $d,
            ];
        }
        $buckets[$key]['views'] += (int) ($row['views'] ?? 0);
        $buckets[$key]['time_total_sec'] += (int) ($row['time_total_sec'] ?? 0);
        $buckets[$key]['to_date'] = $d;
    }

    $rows = array_values($buckets);
    usort($rows, static function ($a, $b) {
        return strcmp($b['period_key'], $a['period_key']);
    });
    $rows = array_slice($rows, 0, $limit);

    $count_rows = count($rows);
    for ($i = 0; $i < $count_rows; $i++) {
        $views = (int) $rows[$i]['views'];
        $avg = $views > 0 ? (int) round($rows[$i]['time_total_sec'] / $views) : 0;
        $mom = null;
        if (isset($rows[$i + 1])) {
            $prev_views = (int) $rows[$i + 1]['views'];
            if ($prev_views > 0) {
                $mom = round((($views - $prev_views) / $prev_views) * 100, 1);
            }
        }
        $rows[$i]['avg_sec'] = $avg;
        $rows[$i]['mom_pct'] = $mom;
    }

    return [
        'granularity' => $granularity,
        'rows' => $rows,
    ];
}

/**
 * ページ別サマリ（期間内）
 *
 * @param string $from Y-m-d
 * @param string $to Y-m-d
 * @return array<int, array<string, mixed>>
 */
function aidunite_analytics_get_pv_by_page_report($from, $to) {
    $rows = aidunite_analytics_get_page_rows_aggregated($from, $to, 30);
    $out = [];
    foreach ($rows as $row) {
        $views = (int) ($row['views'] ?? 0);
        $time = (int) ($row['time_total_sec'] ?? 0);
        $out[] = [
            'page_key' => (string) ($row['page_key'] ?? ''),
            'label' => aidunite_analytics_page_key_label((string) ($row['page_key'] ?? '')),
            'views' => $views,
            'avg_sec' => $views > 0 ? (int) round($time / $views) : 0,
        ];
    }
    return $out;
}

function aidunite_analytics_pv_bucket_key($date, $granularity) {
    $ts = strtotime($date . ' 00:00:00');
    if ($granularity === 'month') {
        return date('Y-m', $ts);
    }
    if ($granularity === 'week') {
        return date('o', $ts) . '-W' . date('W', $ts);
    }
    return $date;
}

function aidunite_analytics_pv_bucket_label($key, $granularity) {
    if ($granularity === 'month') {
        return $key;
    }
    if ($granularity === 'week') {
        return $key;
    }
    return $key;
}
