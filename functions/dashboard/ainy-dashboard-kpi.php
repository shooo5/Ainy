<?php
/**
 * Ainy 管理者ダッシュボード KPI 取得
 * 日次スナップショット・エラー件数・やること等
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Option key prefix for daily snapshot */
const AIDUNITE_DASHBOARD_SNAPSHOT_PREFIX = 'aidunite_daily_snapshot_';
/** Transient key for error count (5 min cache) */
const AIDUNITE_DASHBOARD_ERROR_COUNT_TRANSIENT = 'aidunite_error_count_24h';

/**
 * 期間の開始日・終了日を返す（period: today, 7d, 30d, month）
 * （page-ainy-dashboard.php 等で定義されている場合は二重定義を避ける）
 */
if (!function_exists('aidunite_dashboard_get_period_dates')) {
function aidunite_dashboard_get_period_dates($period) {
    $today = current_time('Y-m-d');
    $today_ts = strtotime($today . ' 00:00:00');

    switch ($period) {
        case 'today':
            return ['from' => $today, 'to' => $today, 'label' => '今日'];
        case '7d':
            $from = date('Y-m-d', strtotime('-6 days', $today_ts));
            return ['from' => $from, 'to' => $today, 'label' => '直近7日'];
        case '30d':
            $from = date('Y-m-d', strtotime('-29 days', $today_ts));
            return ['from' => $from, 'to' => $today, 'label' => '直近30日'];
        case 'month':
            $from = date('Y-m-01', $today_ts);
            $to = $today;
            return ['from' => $from, 'to' => $to, 'label' => '今月'];
        default:
            $from = date('Y-m-d', strtotime('-6 days', $today_ts));
            return ['from' => $from, 'to' => $today, 'label' => '直近7日'];
    }
}
}

/**
 * 日次スナップショットを取得（DB 優先、旧 option フォールバック）
 */
function aidunite_dashboard_get_snapshot($date) {
    if (function_exists('aidunite_analytics_get_metrics_row')) {
        $row = aidunite_analytics_get_metrics_row($date);
        if (is_array($row)) {
            return [
                'date' => $date,
                'users' => (int) ($row['users_total'] ?? 0),
                'teams' => (int) ($row['teams_total'] ?? 0),
                'parents' => (int) ($row['parents_total'] ?? 0),
                'players' => (int) ($row['players_total'] ?? 0),
            ];
        }
    }

    $key = AIDUNITE_DASHBOARD_SNAPSHOT_PREFIX . $date;
    $raw = get_option($key, null);
    if ($raw === null || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * 日次スナップショットを保存（cron から呼ぶ）。昨日の日付で保存する（0時台実行想定）
 */
function aidunite_dashboard_save_daily_snapshot() {
    $date = date('Y-m-d', strtotime('-1 day', strtotime(current_time('Y-m-d') . ' 00:00:00')));
    if (function_exists('aidunite_metrics_collect_daily')) {
        aidunite_metrics_collect_daily($date);
        return;
    }

    $key = AIDUNITE_DASHBOARD_SNAPSHOT_PREFIX . $date;
    $users = aidunite_dashboard_count_users(null, null);
    $teams = aidunite_dashboard_count_teams(null, null);
    $parents = aidunite_dashboard_count_by_role('parent', null, null);
    $players = aidunite_dashboard_count_by_role('player', null, null);

    $snapshot = [
        'date' => $date,
        'users' => $users,
        'teams' => $teams,
        'parents' => $parents,
        'players' => $players,
    ];
    update_option($key, wp_json_encode($snapshot), false);
}

/**
 * 日次スナップショット用 cron 登録
 */
function aidunite_dashboard_schedule_snapshot_cron() {
    if (get_option('aidunite_dashboard_snapshot_cron_scheduled')) {
        return;
    }
    if (!wp_next_scheduled('aidunite_dashboard_daily_snapshot')) {
        wp_schedule_event(strtotime('tomorrow 00:05'), 'daily', 'aidunite_dashboard_daily_snapshot');
    }
    update_option('aidunite_dashboard_snapshot_cron_scheduled', true, false);
}
add_action('init', 'aidunite_dashboard_schedule_snapshot_cron', 20);
add_action('aidunite_dashboard_daily_snapshot', 'aidunite_dashboard_save_daily_snapshot');

/**
 * 登録ユーザー数（累計: 全ユーザー。期間指定時は user_registered で増分）
 */
function aidunite_dashboard_count_users($from = null, $to = null) {
    if ($from !== null && $to !== null) {
        $args = [
            'count_total' => true,
        'number' => 1,
            'fields' => 'ID',
            'date_query' => [
                ['after' => $from . ' 00:00:00', 'before' => $to . ' 23:59:59', 'inclusive' => true]
            ],
        ];
    } else {
        $args = [
            'count_total' => true,
            'number' => 1,
            'fields' => 'ID',
        ];
    }
    $q = new WP_User_Query($args);
    return $q->get_total();
}

/**
 * 登録チーム数（publish）・期間内の増分は post_date で集計
 */
function aidunite_dashboard_count_teams($from = null, $to = null) {
    $args = [
        'post_type' => 'team',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ];
    if ($from !== null && $to !== null) {
        $args['date_query'] = [
            ['after' => $from . ' 00:00:00', 'before' => $to . ' 23:59:59', 'inclusive' => true, 'column' => 'post_date']
        ];
    }
    $posts = get_posts($args);
    return count($posts);
}

/**
 * 承認待ちチーム数（pending）
 */
function aidunite_dashboard_count_pending_teams() {
    $posts = get_posts([
        'post_type' => 'team',
        'post_status' => 'pending',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    return count($posts);
}

/**
 * 保護者数・選手数（累計 or 期間内の user_registered）
 */
function aidunite_dashboard_count_by_role($role, $from = null, $to = null) {
    $args = [
        'meta_key' => 'aidunite_role',
        'meta_value' => $role,
        'count_total' => true,
        'number' => 1,
        'fields' => 'ID',
    ];
    if ($from !== null && $to !== null) {
        $args['date_query'] = [
            ['after' => $from . ' 00:00:00', 'before' => $to . ' 23:59:59', 'inclusive' => true]
        ];
    }
    $q = new WP_User_Query($args);
    return $q->get_total();
}

/**
 * アクティブチーム数（直近30日で schedule または match_request がある team_id のユニーク数）
 */
function aidunite_dashboard_count_active_teams_30d() {
    $since = date('Y-m-d H:i:s', strtotime('-30 days'));

    $team_ids = [];

    $schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'date_query' => [['after' => $since, 'inclusive' => true]],
        'fields' => 'ids',
    ]);
    foreach ($schedules as $sid) {
        $tid = get_post_meta($sid, 'team_id', true);
        if ($tid) {
            $team_ids[] = (int) $tid;
        }
    }

    $requests = get_posts([
        'post_type' => 'match_request',
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => -1,
        'date_query' => [['after' => $since, 'inclusive' => true]],
        'fields' => 'ids',
    ]);
    foreach ($requests as $rid) {
        $from_id = get_post_meta($rid, 'from_team_id', true);
        $to_id = get_post_meta($rid, 'to_team_id', true);
        if ($from_id) {
            $team_ids[] = (int) $from_id;
        }
        if ($to_id) {
            $team_ids[] = (int) $to_id;
        }
    }

    $team_ids = array_unique(array_filter($team_ids));
    return count($team_ids);
}

/**
 * 成立マッチ数（match_request の status が accepted または established、期間は post_date または established_at）
 */
function aidunite_dashboard_count_established_matches($from, $to) {
    $posts = get_posts([
        'post_type' => 'match_request',
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => -1,
        'date_query' => [
            ['after' => $from . ' 00:00:00', 'before' => $to . ' 23:59:59', 'inclusive' => true]
        ],
        'meta_query' => [
            ['key' => 'status', 'value' => ['accepted', 'established'], 'compare' => 'IN']
        ],
        'fields' => 'ids',
    ]);
    return count($posts);
}

/**
 * 期間内のマッチ申請数（post_date 基準・status 不問）
 */
function aidunite_dashboard_count_match_applications($from, $to) {
    $posts = get_posts([
        'post_type' => 'match_request',
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => -1,
        'date_query' => [
            ['after' => $from . ' 00:00:00', 'before' => $to . ' 23:59:59', 'inclusive' => true],
        ],
        'fields' => 'ids',
    ]);
    return count($posts);
}

/**
 * マッチ成立率（暫定）: 期間内成立 ÷ 期間内申請
 *
 * @return array{rate: float|null, label: string, formula: string, established: int, applications: int, beta: bool}
 */
function aidunite_dashboard_calc_match_establishment_rate($from, $to) {
    $established = aidunite_dashboard_count_established_matches($from, $to);
    $applications = aidunite_dashboard_count_match_applications($from, $to);

    if ($applications <= 0) {
        return [
            'rate' => null,
            'label' => '—',
            'formula' => $established . ' / 0',
            'established' => $established,
            'applications' => 0,
            'beta' => true,
        ];
    }

    $rate = round(($established / $applications) * 100, 1);

    return [
        'rate' => $rate,
        'label' => number_format($rate, 1) . '%',
        'formula' => $established . ' / ' . $applications,
        'established' => $established,
        'applications' => $applications,
        'beta' => true,
    ];
}

/**
 * 申請中マッチ数（status が 申請中 or publish の申請中）
 */
function aidunite_dashboard_count_pending_matches() {
    global $wpdb;
    $count = $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'status'
         WHERE p.post_type = 'match_request'
         AND p.post_status IN ('publish','draft')
         AND pm.meta_value IN ('申請中', 'publish', 'pending')"
    );
    return (int) $count;
}

/**
 * 直近24時間のエラー件数（ログファイルの ERROR/CRITICAL 行数、5分キャッシュ）
 */
function aidunite_dashboard_get_error_count_24h() {
    $cached = get_transient(AIDUNITE_DASHBOARD_ERROR_COUNT_TRANSIENT);
    if ($cached !== false) {
        return (int) $cached;
    }

    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/aidunite-logs';
    $count = 0;
    $today = current_time('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    foreach ([$yesterday, $today] as $d) {
        $file = $log_dir . '/error-' . $d . '.log';
        if (!is_readable($file)) {
            continue;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                if (stripos($line, '[ERROR]') !== false || stripos($line, '[CRITICAL]') !== false) {
                    $count++;
                }
            }
        }
    }

    set_transient(AIDUNITE_DASHBOARD_ERROR_COUNT_TRANSIENT, $count, 5 * MINUTE_IN_SECONDS);
    return $count;
}

/**
 * やること用：承認待ち・申請中などの件数とリンク
 */
function aidunite_dashboard_get_todo_items() {
    $pending_teams = aidunite_dashboard_count_pending_teams();
    $pending_matches = aidunite_dashboard_count_pending_matches();

    $items = [];
    if ($pending_teams > 0) {
        $items[] = [
            'label' => 'チーム承認待ち',
            'count' => $pending_teams,
            'url' => home_url('/team-approval'),
        ];
    }
    if ($pending_matches > 0) {
        $items[] = [
            'label' => '申請中マッチ',
            'count' => $pending_matches,
            'url' => home_url('/match-requests'),
        ];
    }
    return $items;
}

/**
 * KPI 用の前期間比を計算（前日 or 前週）
 */
function aidunite_dashboard_calc_diff($current, $prev, $format = 'number') {
    if ($prev == 0) {
        return $current > 0 ? ['dir' => 'up', 'pct' => 100, 'raw' => $current] : ['dir' => null, 'pct' => 0, 'raw' => 0];
    }
    $raw = $current - $prev;
    $pct = $prev ? round((($current - $prev) / $prev) * 100, 1) : 0;
    $dir = $raw > 0 ? 'up' : ($raw < 0 ? 'down' : null);
    return ['dir' => $dir, 'pct' => $pct, 'raw' => $raw];
}

/**
 * ダッシュボード用 KPI データを一括取得（period: today, 7d, 30d, month）
 */
function aidunite_dashboard_get_kpi_data($period = '7d') {
    $dates = aidunite_dashboard_get_period_dates($period);
    $from = $dates['from'];
    $to = $dates['to'];

    $users_now = aidunite_dashboard_count_users(null, null);
    $teams_now = aidunite_dashboard_count_teams(null, null);
    $pending_teams = aidunite_dashboard_count_pending_teams();
    $active_teams = aidunite_dashboard_count_active_teams_30d();
    $established = aidunite_dashboard_count_established_matches($from, $to);
    $pending_matches = aidunite_dashboard_count_pending_matches();
    $match_establishment_rate = aidunite_dashboard_calc_match_establishment_rate($from, $to);
    $errors_24h = aidunite_dashboard_get_error_count_24h();

    $snapshot_yesterday = aidunite_dashboard_get_snapshot(date('Y-m-d', strtotime('-1 day', strtotime($to))));
    $snapshot_prev_week = null;
    if ($period === '7d') {
        $prev_7d_end = date('Y-m-d', strtotime('-1 day', strtotime($from)));
        $snapshot_prev_week = aidunite_dashboard_get_snapshot($prev_7d_end);
    }

    $user_diff = $snapshot_yesterday ? aidunite_dashboard_calc_diff($users_now, (int) ($snapshot_yesterday['users'] ?? 0)) : ['dir' => null, 'pct' => 0, 'raw' => 0];
    $team_diff = $snapshot_yesterday ? aidunite_dashboard_calc_diff($teams_now, (int) ($snapshot_yesterday['teams'] ?? 0)) : ['dir' => null, 'pct' => 0, 'raw' => 0];

    return [
        'period' => $period,
        'label' => $dates['label'],
        'users' => $users_now,
        'users_diff' => $user_diff,
        'teams' => $teams_now,
        'teams_diff' => $team_diff,
        'pending_teams' => $pending_teams,
        'active_teams' => $active_teams,
        'established_matches' => $established,
        'pending_matches' => $pending_matches,
        'match_establishment_rate' => $match_establishment_rate,
        'errors_24h' => $errors_24h,
        'snapshot_prev_week' => $snapshot_prev_week,
    ];
}

/**
 * 支払い済みチーム数（関数名は互換のため paid_users のまま）
 */
function aidunite_dashboard_count_paid_users() {
    return function_exists('aidunite_payment_read_count_paid_teams')
        ? aidunite_payment_read_count_paid_teams()
        : 0;
}

/**
 * 全体の継続月数（有料チームの平均）と LTV を取得
 * team_payment_status_updated から現在までの経過月数の平均。
 *
 * @param int|null $monthly_amount 学校（個人契約）月額。null の場合は payment_config から取得を試みる。
 * @return array { retention_months_avg, ltv_per_user, ltv_total, paid_count }
 */
function aidunite_dashboard_get_retention_ltv($monthly_amount = null) {
    $paid_count = aidunite_dashboard_count_paid_users();
    $retention_months_avg = 0;
    if ($paid_count > 0 && function_exists('aidunite_payment_read_paid_team_retention_months')) {
        $months_list = aidunite_payment_read_paid_team_retention_months();
        if ($months_list !== []) {
            $retention_months_avg = array_sum($months_list) / count($months_list);
        }
    }

    if ($monthly_amount === null && function_exists('aidunite_get_payment_config')) {
        $config = aidunite_get_payment_config();
        $monthly_amount = (int) ($config['school']['personal_amount'] ?? 0);
    }
    $monthly_amount = (int) $monthly_amount;
    $ltv_per_user = $monthly_amount > 0 ? round($retention_months_avg * $monthly_amount) : 0;
    $ltv_total = $ltv_per_user * $paid_count;

    return [
        'retention_months_avg' => round($retention_months_avg, 1),
        'ltv_per_user' => $ltv_per_user,
        'ltv_total' => $ltv_total,
        'paid_count' => $paid_count,
    ];
}

/**
 * データ管理ショートカット（一覧ページ）
 *
 * @return array<int, array{label:string,url:string,icon:string,badge?:int}>
 */
function aidunite_dashboard_get_data_management_shortcuts() {
    return [
        ['label' => '成立ファネル一覧', 'url' => home_url('/admin-team-funnel'), 'icon' => 'bar_chart_4_bars'],
        ['label' => 'アクティブデータ一覧', 'url' => home_url('/admin-analytics-active'), 'icon' => 'mode_heat'],
        ['label' => 'PVデータ一覧', 'url' => home_url('/admin-analytics-pv'), 'icon' => 'trending_up'],
        ['label' => '離脱分析一覧', 'url' => home_url('/admin-analytics-churn'), 'icon' => 'trending_down'],
        ['label' => 'AI月次レポート', 'url' => home_url('/admin-analytics-ai-report'), 'icon' => 'robot_2'],
        ['label' => 'ユーザー一覧', 'url' => home_url('/admin-user-list'), 'icon' => 'group'],
        ['label' => 'チーム一覧', 'url' => home_url('/team-management'), 'icon' => 'basketball'],
        ['label' => 'スケジュール一覧', 'url' => home_url('/admin-schedule-list'), 'icon' => 'calendar_month'],
        ['label' => 'マッチ申請一覧', 'url' => home_url('/match-requests'), 'icon' => 'handshake'],
        ['label' => '試合後のアンケート一覧', 'url' => home_url('/admin-match-feedback-list'), 'icon' => 'list_alt_add'],
        ['label' => '決済一覧', 'url' => home_url('/admin-payment-list'), 'icon' => 'payments'],
        ['label' => '売上・見込み', 'url' => home_url('/admin-payment-revenue'), 'icon' => 'currency_yen'],
    ];
}

/**
 * 管理ショートカット（運用・設定）
 *
 * @return array<int, array{label:string,url:string,icon:string,badge?:int}>
 */
function aidunite_dashboard_get_admin_shortcuts() {
    return [
        ['label' => 'チーム承認', 'url' => home_url('/team-approval'), 'icon' => 'check_circle'],
        ['label' => '通知設定', 'url' => home_url('/notification-settings'), 'icon' => 'notification_add'],
        ['label' => '決済管理', 'url' => home_url('/admin-payment-management'), 'icon' => 'currency_yen'],
        ['label' => 'デザイン', 'url' => home_url('/design-reference'), 'icon' => 'palette'],
        ['label' => 'サンプルプレビュー', 'url' => home_url('/sample-preview'), 'icon' => 'list_alt_add'],
        ['label' => 'チャット管理', 'url' => home_url('/chat-management'), 'icon' => 'chat'],
        ['label' => '試合後モジュール', 'url' => home_url('/admin-post-match-modules'), 'icon' => 'campaign'],
        ['label' => 'WordPress管理', 'url' => admin_url(), 'icon' => 'build'],
    ];
}
