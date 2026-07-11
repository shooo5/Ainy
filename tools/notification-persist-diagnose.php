<?php
/**
 * notification persist 診断
 * 例: .../tools/notification-persist-diagnose.php?notification_id=123
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "wp-load.php not found: {$wp_load}\n";
    exit(1);
}

require_once $wp_load;

if (!is_user_logged_in() && PHP_SAPI !== 'cli') {
    wp_die('ログインが必要です。');
}

$notification_id = isset($_GET['notification_id']) ? (int) $_GET['notification_id'] : 0;
if ($notification_id <= 0) {
    wp_die('notification_id を指定してください。');
}

$lines = [];
$lines[] = '=== notification persist diagnose ===';
$lines[] = 'notification_id=' . $notification_id;

if (function_exists('aidunite_notification_get_canonical_meta')) {
    $c = aidunite_notification_get_canonical_meta($notification_id);
    $lines[] = '--- canonical ---';
    foreach ($c as $k => $v) {
        $lines[] = $k . '=' . var_export($v, true);
    }
}

$lines[] = 'persist_loaded=' . (function_exists('aidunite_notification_write_post_meta') ? 'yes' : 'no');

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
