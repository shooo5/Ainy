<?php
/**
 * お知らせ・チャット未読バッジ整合チェック（Phase 2）
 *
 * 使用方法: WP 管理者でログイン後、ブラウザで本ファイルにアクセス
 * クエリ: ?user_id=123 （省略時は直近通知ユーザー10件をサンプル）
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

if (!function_exists('aidunite_notification_badge_consistency_check')) {
    echo "notification-delivery-report module not loaded.\n";
    exit;
}

$target_user = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

echo "Ainy Notification Badge Consistency Check\n";
echo 'Generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n";
echo str_repeat('=', 60) . "\n\n";

echo "[What this checks]\n";
echo "- Notification CPT: aidunite_notification_unread_count vs aidunite_get_notification_count vs direct WP_Query\n";
echo "- Chat timeline: total_unread vs sum(by_type) from aidunite_get_unified_timeline\n";
echo "- Note: Header badge uses notification CPT only (not chat). Chat badges are on /communication.\n\n";

if ($target_user > 0) {
    $row = aidunite_notification_badge_consistency_check($target_user);
    echo "[User {$target_user}]\n";
    print_r($row);
    exit;
}

$sample = aidunite_notification_badge_consistency_sample(15);
echo "[Sample users: " . (int) ($sample['sampled'] ?? 0) . "]\n";
echo 'all_ok: ' . (!empty($sample['all_ok']) ? 'yes' : 'NO — investigate mismatches') . "\n\n";

foreach ($sample['results'] ?? [] as $row) {
    $uid = (int) ($row['user_id'] ?? 0);
    $n = $row['notification'] ?? [];
    echo "user_id={$uid}\n";
    echo '  notification: api=' . (int) ($n['unread_api'] ?? 0)
        . ' helper=' . (int) ($n['unread_helper'] ?? 0)
        . ' direct=' . (int) ($n['unread_direct'] ?? 0)
        . ' => ' . (!empty($n['consistent']) ? 'OK' : 'MISMATCH') . "\n";

    $c = $row['chat_timeline'] ?? null;
    if (is_array($c)) {
        echo '  chat: total_unread=' . (int) ($c['total_unread'] ?? 0)
            . ' by_type_sum=' . (int) ($c['by_type_sum'] ?? 0)
            . ' match=' . (int) ($c['by_type']['match'] ?? 0)
            . ' team=' . (int) ($c['by_type']['team'] ?? 0)
            . ' board=' . (int) ($c['by_type']['board'] ?? 0)
            . ' => ' . (!empty($c['consistent']) ? 'OK' : 'MISMATCH') . "\n";
    } else {
        echo "  chat: (timeline unavailable)\n";
    }
    echo "\n";
}

echo "[Push fallback note]\n";
echo "Web Push is not wired in aidunite_notification_send. When enabled, verify push skipped\n";
echo "users still receive app (CPT) + email per notification_settings.\n";
