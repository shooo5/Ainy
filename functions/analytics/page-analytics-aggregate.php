<?php
/**
 * 日次分析集計（KPI スナップショット・ページ分析バッファ反映）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/analytics-db.php';
require_once __DIR__ . '/metrics-registry.php';

const AIDUNITE_ANALYTICS_PAGE_BUFFER_PREFIX = 'aidunite_page_buffer_';
const AIDUNITE_ANALYTICS_ROLLUP_CRON_HOOK = 'aidunite_analytics_daily_rollup';
const AIDUNITE_ANALYTICS_ROLLUP_SCHEDULED_OPTION = 'aidunite_analytics_rollup_cron_scheduled';

/**
 * @param string $date Y-m-d
 * @return int
 */
function aidunite_metrics_count_schedules_on_date($date, $intent) {
    $posts = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'date_query' => [
            [
                'after' => $date . ' 00:00:00',
                'before' => $date . ' 23:59:59',
                'inclusive' => true,
            ],
        ],
        'meta_query' => [
            [
                'key' => 'intent',
                'value' => $intent,
            ],
        ],
    ]);
    return count($posts);
}

function aidunite_metrics_collect_users_total($date) {
    unset($date);
    return function_exists('aidunite_dashboard_count_users')
        ? aidunite_dashboard_count_users(null, null)
        : 0;
}

function aidunite_metrics_collect_teams_total($date) {
    unset($date);
    return function_exists('aidunite_dashboard_count_teams')
        ? aidunite_dashboard_count_teams(null, null)
        : 0;
}

function aidunite_metrics_collect_schedules_by_intent($date) {
    return [
        'confirmed' => aidunite_metrics_count_schedules_on_date($date, 'confirmed'),
        'recruit' => aidunite_metrics_count_schedules_on_date($date, 'recruit'),
        'tentative' => aidunite_metrics_count_schedules_on_date($date, 'tentative'),
    ];
}

function aidunite_metrics_collect_matches_established($date) {
    return function_exists('aidunite_dashboard_count_established_matches')
        ? aidunite_dashboard_count_established_matches($date, $date)
        : 0;
}

function aidunite_metrics_collect_match_applications($date) {
    return function_exists('aidunite_dashboard_count_match_applications')
        ? aidunite_dashboard_count_match_applications($date, $date)
        : 0;
}

function aidunite_metrics_collect_paid_users($date) {
    unset($date);
    return function_exists('aidunite_dashboard_count_paid_users')
        ? aidunite_dashboard_count_paid_users()
        : 0;
}

function aidunite_metrics_collect_revenue_estimate($date) {
    $paid = aidunite_metrics_collect_paid_users($date);
    $amount = 0;
    if (function_exists('aidunite_get_payment_config')) {
        $config = aidunite_get_payment_config();
        $amount = (int) ($config['school']['personal_amount'] ?? 0);
    }
    return $paid * $amount;
}

/**
 * 指定日の KPI を DB に保存
 *
 * @param string|null $date Y-m-d（null のとき昨日）
 */
function aidunite_metrics_collect_daily($date = null) {
    if ($date === null) {
        $date = date('Y-m-d', strtotime('-1 day', strtotime(current_time('Y-m-d') . ' 00:00:00')));
    }

    $schedule_counts = aidunite_metrics_collect_schedules_by_intent($date);

    $row = [
        'users_total' => aidunite_metrics_collect_users_total($date),
        'teams_total' => aidunite_metrics_collect_teams_total($date),
        'parents_total' => function_exists('aidunite_dashboard_count_by_role')
            ? aidunite_dashboard_count_by_role('parent', null, null)
            : 0,
        'players_total' => function_exists('aidunite_dashboard_count_by_role')
            ? aidunite_dashboard_count_by_role('player', null, null)
            : 0,
        'schedules_confirmed' => (int) ($schedule_counts['confirmed'] ?? 0),
        'schedules_recruit' => (int) ($schedule_counts['recruit'] ?? 0),
        'schedules_tentative' => (int) ($schedule_counts['tentative'] ?? 0),
        'matches_established' => aidunite_metrics_collect_matches_established($date),
        'match_applications' => aidunite_metrics_collect_match_applications($date),
        'pending_matches' => function_exists('aidunite_dashboard_count_pending_matches')
            ? aidunite_dashboard_count_pending_matches()
            : 0,
        'paid_users' => aidunite_metrics_collect_paid_users($date),
        'revenue_estimate' => aidunite_metrics_collect_revenue_estimate($date),
    ];

    aidunite_analytics_upsert_metrics_row($date, $row);

    // 後方互換: 旧 option スナップショットも更新
    if (defined('AIDUNITE_DASHBOARD_SNAPSHOT_PREFIX')) {
        $legacy = [
            'date' => $date,
            'users' => $row['users_total'],
            'teams' => $row['teams_total'],
            'parents' => $row['parents_total'],
            'players' => $row['players_total'],
        ];
        update_option(AIDUNITE_DASHBOARD_SNAPSHOT_PREFIX . $date, wp_json_encode($legacy), false);
    }

    return $row;
}

/**
 * transient バッファを DB へ反映
 *
 * @param string $date Y-m-d
 */
function aidunite_analytics_flush_page_buffer($date) {
    $key = AIDUNITE_ANALYTICS_PAGE_BUFFER_PREFIX . $date;
    $buffer = get_transient($key);
    if (!is_array($buffer) || $buffer === []) {
        return;
    }

    foreach ($buffer as $page_key => $stats) {
        if (!is_array($stats)) {
            continue;
        }
        aidunite_analytics_upsert_page_row(
            $date,
            (string) $page_key,
            (int) ($stats['views'] ?? 0),
            (int) ($stats['time_total_sec'] ?? 0)
        );
    }

    delete_transient($key);
}

/**
 * 日次ロールアップ（cron）
 */
function aidunite_analytics_daily_rollup() {
    $yesterday = date('Y-m-d', strtotime('-1 day', strtotime(current_time('Y-m-d') . ' 00:00:00')));
    $today = current_time('Y-m-d');

    aidunite_analytics_flush_page_buffer($yesterday);
    aidunite_analytics_flush_page_buffer($today);
    aidunite_metrics_collect_daily($yesterday);

    // 当日分も暫定保存（ダッシュボードの鮮度用）
    aidunite_metrics_collect_daily($today);
}

function aidunite_analytics_schedule_rollup_cron() {
    if (get_option(AIDUNITE_ANALYTICS_ROLLUP_SCHEDULED_OPTION)) {
        return;
    }
    if (!wp_next_scheduled(AIDUNITE_ANALYTICS_ROLLUP_CRON_HOOK)) {
        wp_schedule_event(strtotime('tomorrow 00:10'), 'daily', AIDUNITE_ANALYTICS_ROLLUP_CRON_HOOK);
    }
    update_option(AIDUNITE_ANALYTICS_ROLLUP_SCHEDULED_OPTION, true, false);
}
add_action('init', 'aidunite_analytics_schedule_rollup_cron', 25);
add_action(AIDUNITE_ANALYTICS_ROLLUP_CRON_HOOK, 'aidunite_analytics_daily_rollup');

/**
 * 初回インストール後に当日分を1回収集
 */
function aidunite_analytics_bootstrap_metrics_once() {
    if (get_option('aidunite_analytics_bootstrap_done')) {
        return;
    }
    if (get_option(AIDUNITE_ANALYTICS_DB_VERSION_OPTION) !== AIDUNITE_ANALYTICS_DB_VERSION) {
        return;
    }
    aidunite_metrics_collect_daily(current_time('Y-m-d'));
    update_option('aidunite_analytics_bootstrap_done', true, false);
}
add_action('init', 'aidunite_analytics_bootstrap_metrics_once', 30);
