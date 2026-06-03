<?php
/**
 * 分析基盤 DB テーブル（metrics_daily / page_analytics_daily）
 */

if (!defined('ABSPATH')) {
    exit;
}

const AIDUNITE_ANALYTICS_DB_VERSION = '1.2.0';
const AIDUNITE_ANALYTICS_DB_VERSION_OPTION = 'aidunite_analytics_db_version';

/**
 * @return string
 */
function aidunite_analytics_metrics_table() {
    global $wpdb;
    return $wpdb->prefix . 'aidunite_metrics_daily';
}

/**
 * @return string
 */
function aidunite_analytics_page_table() {
    global $wpdb;
    return $wpdb->prefix . 'aidunite_page_analytics_daily';
}

/**
 * @return string
 */
function aidunite_analytics_page_events_table() {
    global $wpdb;
    return $wpdb->prefix . 'aidunite_page_events';
}

/**
 * テーブル作成・更新
 */
function aidunite_analytics_install_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $metrics = aidunite_analytics_metrics_table();
    $pages = aidunite_analytics_page_table();

    $sql_metrics = "CREATE TABLE {$metrics} (
        metric_date date NOT NULL,
        users_total int unsigned NOT NULL DEFAULT 0,
        teams_total int unsigned NOT NULL DEFAULT 0,
        parents_total int unsigned NOT NULL DEFAULT 0,
        players_total int unsigned NOT NULL DEFAULT 0,
        schedules_confirmed int unsigned NOT NULL DEFAULT 0,
        schedules_recruit int unsigned NOT NULL DEFAULT 0,
        schedules_tentative int unsigned NOT NULL DEFAULT 0,
        matches_established int unsigned NOT NULL DEFAULT 0,
        match_applications int unsigned NOT NULL DEFAULT 0,
        pending_matches int unsigned NOT NULL DEFAULT 0,
        paid_users int unsigned NOT NULL DEFAULT 0,
        revenue_estimate bigint unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (metric_date)
    ) {$charset};";

    $sql_pages = "CREATE TABLE {$pages} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        metric_date date NOT NULL,
        page_key varchar(191) NOT NULL,
        views int unsigned NOT NULL DEFAULT 0,
        time_total_sec int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY date_page (metric_date, page_key),
        KEY metric_date (metric_date)
    ) {$charset};";

    $events = aidunite_analytics_page_events_table();
    $sql_events = "CREATE TABLE {$events} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        metric_date date NOT NULL,
        created_at datetime NOT NULL,
        event_type varchar(20) NOT NULL DEFAULT 'view',
        team_id bigint unsigned NOT NULL DEFAULT 0,
        user_id bigint unsigned NOT NULL DEFAULT 0,
        page_key varchar(191) NOT NULL DEFAULT '',
        target_key varchar(191) NOT NULL DEFAULT '',
        duration_sec int unsigned NOT NULL DEFAULT 0,
        session_id varchar(64) NOT NULL DEFAULT '',
        referrer_page varchar(191) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        KEY metric_date (metric_date),
        KEY team_page (team_id, page_key, metric_date),
        KEY session_id (session_id)
    ) {$charset};";

    dbDelta($sql_metrics);
    dbDelta($sql_pages);
    dbDelta($sql_events);

    update_option(AIDUNITE_ANALYTICS_DB_VERSION_OPTION, AIDUNITE_ANALYTICS_DB_VERSION, false);
}

function aidunite_analytics_maybe_install_tables() {
    if (get_option(AIDUNITE_ANALYTICS_DB_VERSION_OPTION) === AIDUNITE_ANALYTICS_DB_VERSION) {
        return;
    }
    aidunite_analytics_install_tables();
}
add_action('init', 'aidunite_analytics_maybe_install_tables', 5);

/**
 * @param string $date Y-m-d
 * @return array<string, mixed>|null
 */
function aidunite_analytics_get_metrics_row($date) {
    global $wpdb;
    $table = aidunite_analytics_metrics_table();
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE metric_date = %s", $date),
        ARRAY_A
    );
    return is_array($row) ? $row : null;
}

/**
 * @param string $date Y-m-d
 * @param array<string, int> $data
 */
function aidunite_analytics_upsert_metrics_row($date, array $data) {
    global $wpdb;
    $table = aidunite_analytics_metrics_table();
    $existing = aidunite_analytics_get_metrics_row($date);

    $defaults = [
        'users_total' => 0,
        'teams_total' => 0,
        'parents_total' => 0,
        'players_total' => 0,
        'schedules_confirmed' => 0,
        'schedules_recruit' => 0,
        'schedules_tentative' => 0,
        'matches_established' => 0,
        'match_applications' => 0,
        'pending_matches' => 0,
        'paid_users' => 0,
        'revenue_estimate' => 0,
    ];

    $payload = array_merge($defaults, $data);
    $payload['created_at'] = current_time('mysql');

    if ($existing) {
        $wpdb->update(
            $table,
            $payload,
            ['metric_date' => $date],
            ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s'],
            ['%s']
        );
        return;
    }

    $wpdb->insert(
        $table,
        array_merge(['metric_date' => $date], $payload),
        ['%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s']
    );
}

/**
 * ページ分析を日次テーブルへ加算（UPSERT）
 *
 * @param string $date Y-m-d
 * @param string $page_key
 * @param int $views
 * @param int $time_sec
 */
function aidunite_analytics_upsert_page_row($date, $page_key, $views, $time_sec) {
    global $wpdb;
    $table = aidunite_analytics_page_table();
    $page_key = aidunite_analytics_sanitize_page_key($page_key);
    if ($page_key === '') {
        return;
    }

    $views = max(0, (int) $views);
    $time_sec = max(0, (int) $time_sec);

    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, views, time_total_sec FROM {$table} WHERE metric_date = %s AND page_key = %s",
            $date,
            $page_key
        ),
        ARRAY_A
    );

    if ($existing) {
        $wpdb->update(
            $table,
            [
                'views' => (int) $existing['views'] + $views,
                'time_total_sec' => (int) $existing['time_total_sec'] + $time_sec,
            ],
            ['id' => (int) $existing['id']],
            ['%d', '%d'],
            ['%d']
        );
        return;
    }

    $wpdb->insert(
        $table,
        [
            'metric_date' => $date,
            'page_key' => $page_key,
            'views' => $views,
            'time_total_sec' => $time_sec,
        ],
        ['%s', '%s', '%d', '%d']
    );
}

/**
 * @param string $from Y-m-d
 * @param string $to Y-m-d
 * @return array<int, array{metric_date:string, views:int, time_total_sec:int, page_key:string}>
 */
function aidunite_analytics_get_page_rows_aggregated($from, $to, $limit = 50) {
    global $wpdb;
    $table = aidunite_analytics_page_table();
    $limit = max(1, min(200, (int) $limit));

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT page_key,
                    SUM(views) AS views,
                    SUM(time_total_sec) AS time_total_sec
             FROM {$table}
             WHERE metric_date >= %s AND metric_date <= %s
             GROUP BY page_key
             ORDER BY views DESC
             LIMIT %d",
            $from,
            $to,
            $limit
        ),
        ARRAY_A
    ) ?: [];
}

/**
 * @param string $from
 * @param string $to
 * @param string $column DB カラム名
 * @return array<int, array{metric_date:string, value:int}>
 */
function aidunite_analytics_get_metrics_series($from, $to, $column) {
    global $wpdb;
    $allowed = [
        'users_total',
        'teams_total',
        'parents_total',
        'players_total',
        'schedules_confirmed',
        'schedules_recruit',
        'schedules_tentative',
        'matches_established',
        'match_applications',
        'pending_matches',
        'paid_users',
        'revenue_estimate',
    ];
    if (!in_array($column, $allowed, true)) {
        return [];
    }

    $table = aidunite_analytics_metrics_table();
    $sql = $wpdb->prepare(
        "SELECT metric_date, {$column} AS value
         FROM {$table}
         WHERE metric_date >= %s AND metric_date <= %s
         ORDER BY metric_date ASC",
        $from,
        $to
    );

    return $wpdb->get_results($sql, ARRAY_A) ?: [];
}

/**
 * ページ別の日次推移（PV・滞在）
 *
 * @return array<int, array{metric_date:string, views:int, time_total_sec:int}>
 */
function aidunite_analytics_get_page_daily_series($page_key, $from, $to) {
    global $wpdb;
    $table = aidunite_analytics_page_table();
    $page_key = aidunite_analytics_sanitize_page_key($page_key);
    if ($page_key === '') {
        return [];
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT metric_date, views, time_total_sec
             FROM {$table}
             WHERE page_key = %s AND metric_date >= %s AND metric_date <= %s
             ORDER BY metric_date ASC",
            $page_key,
            $from,
            $to
        ),
        ARRAY_A
    ) ?: [];
}

/**
 * ダッシュボードに常時表示する page_key（データ0でもカード表示）
 *
 * @return string[]
 */
function aidunite_analytics_get_pinned_dashboard_page_keys() {
    return ['home'];
}

/**
 * page_key の表示名（固定ページスラッグ等）
 */
function aidunite_analytics_page_key_label($page_key) {
    $page_key = aidunite_analytics_sanitize_page_key($page_key);
    $labels = [
        'home' => 'トップ',
        'mypage' => 'マイページ',
        'login' => 'ログイン',
        'match-request' => 'マッチ申請',
        'match-requests' => 'マッチ申請一覧',
        'match-board' => '試合掲示板',
        'schedule-registration' => 'スケジュール登録',
        'team-settings' => 'チーム設定',
        'payment-required' => '決済',
        'notification-settings' => '通知設定',
        'ainy-dashboard' => '管理者ダッシュボード',
    ];
    return $labels[$page_key] ?? $page_key;
}

/**
 * @param string $key
 * @return string
 */
function aidunite_analytics_sanitize_page_key($key) {
    $key = sanitize_key(str_replace('/', '-', trim((string) $key)));
    if ($key === '') {
        return 'home';
    }
    return substr($key, 0, 191);
}
