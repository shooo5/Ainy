<?php
/**
 * 通知配信ログの集計・週次レポート（Phase 2 到達率）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_notification_delivery_events_table')) {
    require_once __DIR__ . '/notification-delivery-log.php';
}

/**
 * Tier1 通知タイプ（試合・決済クリティカル）
 *
 * @return string[]
 */
function aidunite_notification_delivery_tier1_types() {
    $types = [
        'match_request',
        'match_request_received',
        'match_request_reminder',
        'match_established',
        'match_accepted',
        'match_rejected',
        'match_cancelled',
        'match_canceled',
        'payment_reminder',
        'payment_failed',
        'tuition_payment_failed',
    ];

    /**
     * @param string[] $types
     */
    return apply_filters('aidunite_notification_delivery_tier1_types', $types);
}

/**
 * Tier1 SLA 閾値（到達率 KPI）
 *
 * @return array<string, mixed>
 */
function aidunite_notification_delivery_sla_thresholds() {
    return [
        'app_success_rate_min'   => 0.99,
        'email_success_rate_min' => 0.95,
        'overall_delivery_min'   => 0.90,
        'tier1_app_failed_max'   => 0,
    ];
}

/**
 * 期間内の配信サマリーを取得
 *
 * @param array $args {
 *   @type int    $days     過去 N 日（既定 7）
 *   @type string $from_utc Y-m-d H:i:s（省略時は days から算出）
 *   @type string $to_utc   Y-m-d H:i:s（省略時は現在 UTC）
 *   @type bool   $tier1_only
 * }
 * @return array<string, mixed>
 */
function aidunite_notification_delivery_get_summary(array $args = []) {
    $days = max(1, (int) ($args['days'] ?? 7));
    $to_utc = isset($args['to_utc']) ? (string) $args['to_utc'] : gmdate('Y-m-d H:i:s');
    $from_utc = isset($args['from_utc'])
        ? (string) $args['from_utc']
        : gmdate('Y-m-d H:i:s', strtotime($to_utc) - ($days * DAY_IN_SECONDS));

    $full = aidunite_notification_delivery_aggregate_period($from_utc, $to_utc, false);
    $tier1 = aidunite_notification_delivery_aggregate_period($from_utc, $to_utc, true);

    $alerts = aidunite_notification_delivery_build_alerts(
        $full['channel_rates'],
        $tier1['channel_rates']
    );

    return array_merge($full, [
        'period' => [
            'from_utc' => $from_utc,
            'to_utc'   => $to_utc,
            'days'     => $days,
        ],
        'tier1_types'    => aidunite_notification_delivery_tier1_types(),
        'tier1'          => [
            'event_count'   => $tier1['event_count'],
            'channel_rates' => $tier1['channel_rates'],
            'by_type'       => $tier1['by_type'],
        ],
        'alerts'         => $alerts,
        'sla_thresholds' => aidunite_notification_delivery_sla_thresholds(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function aidunite_notification_delivery_aggregate_period($from_utc, $to_utc, $tier1_only) {
    aidunite_notification_delivery_maybe_install_tables();

    global $wpdb;
    $events_table = aidunite_notification_delivery_events_table();
    $channels_table = aidunite_notification_delivery_channels_table();

    $tier1_types = aidunite_notification_delivery_tier1_types();

    $where = ['e.created_at >= %s', 'e.created_at <= %s'];
    $params = [$from_utc, $to_utc];

    if ($tier1_only && $tier1_types !== []) {
        $placeholders = implode(',', array_fill(0, count($tier1_types), '%s'));
        $where[] = "e.notification_type IN ({$placeholders})";
        $params = array_merge($params, $tier1_types);
    }

    $where_sql = implode(' AND ', $where);

    $sql = "
        SELECT e.notification_type, c.channel, c.result, c.skip_reason, COUNT(*) AS cnt
        FROM {$channels_table} c
        INNER JOIN {$events_table} e ON e.id = c.event_id
        WHERE {$where_sql}
        GROUP BY e.notification_type, c.channel, c.result, c.skip_reason
        ORDER BY e.notification_type ASC, c.channel ASC
    ";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
    if (!is_array($rows)) {
        $rows = [];
    }

    $event_count_sql = "SELECT COUNT(*) FROM {$events_table} e WHERE {$where_sql}";
    $event_count = (int) $wpdb->get_var($wpdb->prepare($event_count_sql, $params));

    $by_channel = [];
    $by_type = [];
    $push_skipped = [];

    foreach ($rows as $row) {
        $type = (string) $row->notification_type;
        $channel = (string) $row->channel;
        $result = (string) $row->result;
        $cnt = (int) $row->cnt;
        $skip_reason = (string) ($row->skip_reason ?? '');

        if (!isset($by_channel[$channel])) {
            $by_channel[$channel] = ['success' => 0, 'failed' => 0, 'skipped' => 0];
        }
        if (isset($by_channel[$channel][$result])) {
            $by_channel[$channel][$result] += $cnt;
        }

        if (!isset($by_type[$type])) {
            $by_type[$type] = ['events' => 0, 'channels' => []];
        }
        if (!isset($by_type[$type]['channels'][$channel])) {
            $by_type[$type]['channels'][$channel] = ['success' => 0, 'failed' => 0, 'skipped' => 0];
        }
        if (isset($by_type[$type]['channels'][$channel][$result])) {
            $by_type[$type]['channels'][$channel][$result] += $cnt;
        }

        if ($channel === 'push' && $result === 'skipped') {
            $reason = $skip_reason !== '' ? $skip_reason : 'unspecified';
            if (!isset($push_skipped[$reason])) {
                $push_skipped[$reason] = 0;
            }
            $push_skipped[$reason] += $cnt;
        }
    }

    if ($event_count > 0) {
        $type_sql = "
            SELECT notification_type, COUNT(*) AS cnt
            FROM {$events_table} e
            WHERE {$where_sql}
            GROUP BY notification_type
        ";
        $type_rows = $wpdb->get_results($wpdb->prepare($type_sql, $params));
        if (is_array($type_rows)) {
            foreach ($type_rows as $tr) {
                $t = (string) $tr->notification_type;
                if (!isset($by_type[$t])) {
                    $by_type[$t] = ['events' => 0, 'channels' => []];
                }
                $by_type[$t]['events'] = (int) $tr->cnt;
            }
        }
    }

    $rates = [];
    foreach ($by_channel as $ch => $counts) {
        $attempted = $counts['success'] + $counts['failed'];
        $rates[$ch] = [
            'success'      => $counts['success'],
            'failed'       => $counts['failed'],
            'skipped'      => $counts['skipped'],
            'attempted'    => $attempted,
            'success_rate' => $attempted > 0 ? round($counts['success'] / $attempted, 4) : null,
        ];
    }

    $all_success = 0;
    $all_failed = 0;
    foreach ($by_channel as $counts) {
        $all_success += $counts['success'];
        $all_failed += $counts['failed'];
    }
    $all_attempted = $all_success + $all_failed;

    return [
        'event_count'   => $event_count,
        'by_channel'    => $by_channel,
        'channel_rates' => $rates,
        'by_type'       => $by_type,
        'overall'       => [
            'success'      => $all_success,
            'failed'       => $all_failed,
            'attempted'    => $all_attempted,
            'success_rate' => $all_attempted > 0 ? round($all_success / $all_attempted, 4) : null,
        ],
        'push_skipped'  => $push_skipped,
    ];
}

/**
 * @param array<string, array<string, mixed>> $all_rates
 * @param array<string, array<string, mixed>> $tier1_rates
 * @return array<int, array<string, string>>
 */
function aidunite_notification_delivery_build_alerts(array $all_rates, array $tier1_rates) {
    $thresholds = aidunite_notification_delivery_sla_thresholds();
    $alerts = [];

    $tier1_app_failed = (int) ($tier1_rates['app']['failed'] ?? 0);
    if ($tier1_app_failed > (int) $thresholds['tier1_app_failed_max']) {
        $alerts[] = [
            'level' => 'error',
            'code'  => 'tier1_app_failed',
            'message' => sprintf('Tier1 のアプリ内通知（app）失敗が %d 件あります。', $tier1_app_failed),
        ];
    }

    foreach (['app', 'email'] as $ch) {
        $rate_key = $ch . '_success_rate_min';
        $min = (float) ($thresholds[$rate_key] ?? 0);
        if ($min <= 0) {
            continue;
        }
        $tier_rate = $tier1_rates[$ch]['success_rate'] ?? null;
        if ($tier_rate !== null && $tier_rate < $min) {
            $alerts[] = [
                'level' => 'warning',
                'code'  => 'tier1_' . $ch . '_rate_low',
                'message' => sprintf(
                    'Tier1 %s 到達率 %.1f%% が目標 %.1f%% を下回っています。',
                    $ch,
                    $tier_rate * 100,
                    $min * 100
                ),
            ];
        }
    }

    $overall = $all_rates['app']['success_rate'] ?? null;
    $overall_min = (float) ($thresholds['overall_delivery_min'] ?? 0);
    if ($overall !== null && $overall < $overall_min) {
        $alerts[] = [
            'level' => 'warning',
            'code'  => 'overall_app_rate_low',
            'message' => sprintf(
                '全体 app 到達率 %.1f%% が KPI 目標 %.1f%% を下回っています。',
                $overall * 100,
                $overall_min * 100
            ),
        ];
    }

    $push_skipped = (int) ($all_rates['push']['skipped'] ?? 0);
    if ($push_skipped > 0) {
        $alerts[] = [
            'level' => 'info',
            'code'  => 'push_skipped',
            'message' => sprintf('Push は %d 件 skipped（未接続・未実装）。メール/app でフォールバック確認してください。', $push_skipped),
        ];
    }

    if (($all_rates['app']['attempted'] ?? 0) === 0) {
        $alerts[] = [
            'level' => 'info',
            'code'  => 'no_delivery_events',
            'message' => '期間内に配信ログがありません。テスト通知または実利用でログが蓄積されるか確認してください。',
        ];
    }

    return $alerts;
}

/**
 * お知らせ未読バッジの整合チェック（単一ユーザー）
 *
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_notification_badge_consistency_check($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'invalid_user'];
    }

    $api_count = function_exists('aidunite_notification_unread_count')
        ? (int) aidunite_notification_unread_count($user_id)
        : 0;

    $helper_count = function_exists('aidunite_get_notification_count')
        ? (int) aidunite_get_notification_count($user_id)
        : $api_count;

    $q = new WP_Query([
        'post_type'      => 'notification',
        'post_status'    => 'publish',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => 'is_read', 'compare' => 'NOT EXISTS'],
            ['key' => 'is_read', 'value' => '1', 'compare' => '!='],
        ],
    ]);
    $direct_count = (int) $q->post_count;

    $chat = null;
    if (function_exists('aidunite_get_unified_timeline')) {
        $timeline = aidunite_get_unified_timeline($user_id, 0, 1, 1);
        $by_type = $timeline['by_type'] ?? ['match' => 0, 'team' => 0, 'board' => 0];
        $sum = (int) ($by_type['match'] ?? 0) + (int) ($by_type['team'] ?? 0) + (int) ($by_type['board'] ?? 0);
        $total = (int) ($timeline['total_unread'] ?? 0);
        $chat = [
            'total_unread' => $total,
            'by_type_sum'  => $sum,
            'by_type'      => $by_type,
            'consistent'   => $total === $sum,
        ];
    }

    return [
        'user_id'       => $user_id,
        'notification'  => [
            'unread_api'    => $api_count,
            'unread_helper' => $helper_count,
            'unread_direct' => $direct_count,
            'consistent'    => ($api_count === $helper_count && $api_count === $direct_count),
        ],
        'chat_timeline' => $chat,
        'ok'            => ($api_count === $helper_count && $api_count === $direct_count)
            && ($chat === null || !empty($chat['consistent'])),
    ];
}

/**
 * 直近アクティブユーザーのバッジ整合をサンプル検査
 *
 * @param int $limit
 * @return array<string, mixed>
 */
function aidunite_notification_badge_consistency_sample($limit = 10) {
    global $wpdb;
    $limit = max(1, min(50, (int) $limit));

    $user_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT post_author FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status = %s
             ORDER BY post_date DESC
             LIMIT %d",
            'notification',
            'publish',
            $limit
        )
    );

    if (!is_array($user_ids) || $user_ids === []) {
        return ['sampled' => 0, 'results' => [], 'all_ok' => true];
    }

    $results = [];
    $all_ok = true;
    foreach ($user_ids as $uid) {
        $row = aidunite_notification_badge_consistency_check((int) $uid);
        $results[] = $row;
        if (empty($row['ok'])) {
            $all_ok = false;
        }
    }

    return [
        'sampled'  => count($results),
        'results'  => $results,
        'all_ok'   => $all_ok,
    ];
}
