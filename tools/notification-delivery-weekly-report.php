<?php
/**
 * 通知配信ログ週次レポート（Phase 2・管理者向け）
 *
 * 使用方法: WP 管理者でログイン後、ブラウザで本ファイルにアクセス
 * 例: /wp-content/themes/aidunite-original/tools/notification-delivery-weekly-report.php
 * クエリ: ?days=7
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
    $wp_load = dirname(__DIR__, 3) . '/wp-load.php';
}
require_once $wp_load;

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    status_header(403);
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$days = isset($_GET['days']) ? max(1, min(90, (int) $_GET['days'])) : 7;

if (!function_exists('aidunite_notification_delivery_get_summary')) {
    echo "notification-delivery-report module not loaded.\n";
    exit;
}

$summary = aidunite_notification_delivery_get_summary(['days' => $days]);
$period = $summary['period'] ?? [];

echo "Ainy Notification Delivery Weekly Report\n";
echo 'Generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n";
echo str_repeat('=', 60) . "\n\n";

echo "[Period]\n";
echo 'from_utc: ' . (string) ($period['from_utc'] ?? '') . "\n";
echo 'to_utc:   ' . (string) ($period['to_utc'] ?? '') . "\n";
echo 'days:     ' . (int) ($period['days'] ?? $days) . "\n";
echo 'events:   ' . (int) ($summary['event_count'] ?? 0) . "\n\n";

echo "[Overall channel rates]\n";
printf(
    "all_channels success_rate: %s\n",
    isset($summary['overall']['success_rate'])
        ? number_format((float) $summary['overall']['success_rate'] * 100, 1) . '%'
        : '—'
);
foreach ($summary['channel_rates'] ?? [] as $ch => $rate) {
    $sr = $rate['success_rate'] ?? null;
    printf(
        "  %-8s success=%4d failed=%3d skipped=%4d rate=%s\n",
        $ch,
        (int) ($rate['success'] ?? 0),
        (int) ($rate['failed'] ?? 0),
        (int) ($rate['skipped'] ?? 0),
        $sr !== null ? number_format($sr * 100, 1) . '%' : '—'
    );
}
echo "\n";

echo "[Tier1 critical types]\n";
echo 'types: ' . implode(', ', $summary['tier1_types'] ?? []) . "\n";
echo 'events: ' . (int) ($summary['tier1']['event_count'] ?? 0) . "\n";
foreach ($summary['tier1']['channel_rates'] ?? [] as $ch => $rate) {
    $sr = $rate['success_rate'] ?? null;
    printf(
        "  %-8s success=%4d failed=%3d rate=%s\n",
        $ch,
        (int) ($rate['success'] ?? 0),
        (int) ($rate['failed'] ?? 0),
        $sr !== null ? number_format($sr * 100, 1) . '%' : '—'
    );
}
echo "\n";

if (!empty($summary['by_type'])) {
    echo "[By notification type]\n";
    foreach ($summary['by_type'] as $type => $row) {
        echo '  ' . $type . ' events=' . (int) ($row['events'] ?? 0) . "\n";
        foreach ($row['channels'] ?? [] as $ch => $counts) {
            printf(
                "    %s: ok=%d fail=%d skip=%d\n",
                $ch,
                (int) ($counts['success'] ?? 0),
                (int) ($counts['failed'] ?? 0),
                (int) ($counts['skipped'] ?? 0)
            );
        }
    }
    echo "\n";
}

if (!empty($summary['push_skipped'])) {
    echo "[Push skipped reasons]\n";
    foreach ($summary['push_skipped'] as $reason => $cnt) {
        echo '  ' . $reason . ': ' . (int) $cnt . "\n";
    }
    echo "\n";
}

echo "[SLA thresholds]\n";
foreach ($summary['sla_thresholds'] ?? [] as $key => $val) {
    echo '  ' . $key . ': ' . (is_float($val) ? number_format($val * 100, 0) . '%' : (string) $val) . "\n";
}
echo "\n";

echo "[Alerts]\n";
if (empty($summary['alerts'])) {
    echo "  (none)\n";
} else {
    foreach ($summary['alerts'] as $alert) {
        echo '  [' . strtoupper((string) ($alert['level'] ?? 'info')) . '] '
            . (string) ($alert['message'] ?? '') . "\n";
    }
}
echo "\n";

if (function_exists('aidunite_notification_badge_consistency_sample')) {
    $badge = aidunite_notification_badge_consistency_sample(10);
    echo "[Badge consistency sample]\n";
    echo 'sampled: ' . (int) ($badge['sampled'] ?? 0) . "\n";
    echo 'all_ok: ' . (!empty($badge['all_ok']) ? 'yes' : 'no') . "\n";
    foreach ($badge['results'] ?? [] as $row) {
        $n = $row['notification'] ?? [];
        $c = $row['chat_timeline'] ?? null;
        printf(
            "  user %d: notif api=%d helper=%d direct=%d %s",
            (int) ($row['user_id'] ?? 0),
            (int) ($n['unread_api'] ?? 0),
            (int) ($n['unread_helper'] ?? 0),
            (int) ($n['unread_direct'] ?? 0),
            !empty($n['consistent']) ? 'OK' : 'MISMATCH'
        );
        if (is_array($c)) {
            printf(
                " | chat total=%d sum=%d %s",
                (int) ($c['total_unread'] ?? 0),
                (int) ($c['by_type_sum'] ?? 0),
                !empty($c['consistent']) ? 'OK' : 'MISMATCH'
            );
        }
        echo "\n";
    }
}
