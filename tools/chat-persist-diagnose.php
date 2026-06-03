<?php
/**
 * chat persist 診断
 * 例: .../tools/chat-persist-diagnose.php?room_id=12
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

$room_id = isset($_GET['room_id']) ? (int) $_GET['room_id'] : 0;
if ($room_id <= 0 && PHP_SAPI === 'cli' && isset($argv[1])) {
    $room_id = (int) $argv[1];
}

$lines = [];
$lines[] = '=== chat persist diagnose ===';
$lines[] = 'persist_loaded=' . (function_exists('aidunite_chat_persist_save_message') ? 'yes' : 'no');

if ($room_id > 0 && function_exists('aidunite_chat_get_canonical_room')) {
    $lines[] = '--- room canonical ---';
    foreach (aidunite_chat_get_canonical_room($room_id) as $k => $v) {
        $lines[] = $k . '=' . var_export($v, true);
    }
} elseif ($room_id <= 0) {
    $lines[] = 'room_id クエリ（または CLI 第1引数）を指定するとルーム詳細を表示します。';
}

$lines[] = 'room_types=' . implode(',', aidunite_chat_canonical_room_types());
$lines[] = 'room_statuses=' . implode(',', aidunite_chat_canonical_room_statuses());
$lines[] = 'message_types=' . implode(',', aidunite_chat_canonical_message_types());

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
