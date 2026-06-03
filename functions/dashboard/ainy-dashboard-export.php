<?php
/**
 * Ainy ダッシュボード CSV エクスポート
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('template_redirect', 'aidunite_dashboard_maybe_export_csv', 5);

/**
 * 管理者のみ CSV 出力可能か
 */
function aidunite_dashboard_export_is_allowed() {
    if (!is_user_logged_in()) {
        return false;
    }
    if (current_user_can('administrator')) {
        return true;
    }
    return get_user_meta(get_current_user_id(), 'aidunite_role', true) === 'administrator';
}

/**
 * @return string
 */
function aidunite_dashboard_export_sanitize_period() {
    $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '7d';
    if (!in_array($period, ['today', '7d', '30d', 'month'], true)) {
        $period = '7d';
    }
    return $period;
}

/**
 * CSV レスポンス開始
 *
 * @param string $filename
 * @return resource|false
 */
function aidunite_dashboard_export_begin_csv($filename) {
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        return false;
    }
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    return $out;
}

/**
 * KPI 推移 CSV
 *
 * @param string $period
 */
function aidunite_dashboard_export_kpi_csv($period) {
    if (!function_exists('aidunite_dashboard_get_chart_data')) {
        return;
    }

    $metric = isset($_GET['metric']) ? sanitize_key(wp_unslash($_GET['metric'])) : 'users_total';
    $chart = aidunite_dashboard_get_chart_data($metric, $period);
    $filename = 'ainy-kpi-' . $metric . '-' . $period . '-' . current_time('Y-m-d') . '.csv';

    $out = aidunite_dashboard_export_begin_csv($filename);
    if ($out === false) {
        return;
    }

    fputcsv($out, ['date', 'value', 'metric', 'period']);
    $count = count($chart['labels']);
    for ($i = 0; $i < $count; $i++) {
        fputcsv($out, [
            $chart['labels'][$i] ?? '',
            $chart['values'][$i] ?? 0,
            $metric,
            $period,
        ]);
    }
    fclose($out);
}

/**
 * ページ利用状況 CSV（期間サマリ＋日次明細）
 *
 * @param string $period
 */
function aidunite_dashboard_export_page_usage_csv($period) {
    if (!function_exists('aidunite_dashboard_get_page_usage_charts_payload')) {
        return;
    }

    $payload = aidunite_dashboard_get_page_usage_charts_payload($period);
    $pages = $payload['pages'] ?? [];
    $period_label = $payload['period_label'] ?? $period;

    $filename = 'ainy-page-usage-' . $period . '-' . current_time('Y-m-d') . '.csv';
    $out = aidunite_dashboard_export_begin_csv($filename);
    if ($out === false) {
        return;
    }

    fputcsv($out, ['【期間サマリ】']);
    fputcsv($out, ['page_key', 'page_label', 'period', 'period_label', 'views', 'avg_sec']);
    foreach ($pages as $page) {
        fputcsv($out, [
            $page['page_key'] ?? '',
            $page['label'] ?? '',
            $period,
            $period_label,
            (int) ($page['views'] ?? 0),
            (int) ($page['avg_sec'] ?? 0),
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['【日次明細】']);
    fputcsv($out, ['date', 'page_key', 'page_label', 'period', 'views', 'avg_sec']);

    foreach ($pages as $page) {
        $series = $page['series'] ?? [];
        $dates = $series['labels'] ?? [];
        $views_list = $series['views'] ?? [];
        $avg_list = $series['avg_sec'] ?? [];
        $count = count($dates);

        for ($i = 0; $i < $count; $i++) {
            fputcsv($out, [
                $dates[$i] ?? '',
                $page['page_key'] ?? '',
                $page['label'] ?? '',
                $period,
                (int) ($views_list[$i] ?? 0),
                (int) ($avg_list[$i] ?? 0),
            ]);
        }
    }

    fclose($out);
}

/**
 * ?ainy_export=csv&export_type=page_usage&period=7d（管理者のみ）
 * ?ainy_export=csv&metric=users_total&period=7d（KPI推移・従来互換）
 */
function aidunite_dashboard_maybe_export_csv() {
    if (!is_page('ainy-dashboard') && !is_page_template('page-ainy-dashboard.php')) {
        return;
    }
    if (!isset($_GET['ainy_export']) || sanitize_text_field(wp_unslash($_GET['ainy_export'])) !== 'csv') {
        return;
    }
    if (!aidunite_dashboard_export_is_allowed()) {
        return;
    }

    $period = aidunite_dashboard_export_sanitize_period();
    $export_type = isset($_GET['export_type'])
        ? sanitize_key(wp_unslash($_GET['export_type']))
        : 'kpi';

    if ($export_type === 'page_usage') {
        aidunite_dashboard_export_page_usage_csv($period);
        exit;
    }

    aidunite_dashboard_export_kpi_csv($period);
    exit;
}

/**
 * ページ利用状況 CSV の URL
 *
 * @param string $period
 * @return string
 */
function aidunite_dashboard_get_page_usage_csv_url($period = '7d') {
    return add_query_arg(
        [
            'ainy_export' => 'csv',
            'export_type' => 'page_usage',
            'period' => $period,
        ],
        get_permalink()
    );
}
