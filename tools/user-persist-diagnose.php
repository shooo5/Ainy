<?php
/**
 * user persist 診断（Local 開発向け）
 * 例: .../tools/user-persist-diagnose.php?user_id=5
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

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($user_id <= 0 && PHP_SAPI === 'cli' && isset($argv[1])) {
    $user_id = (int) $argv[1];
}
if ($user_id <= 0) {
    wp_die('user_id クエリ（または CLI の第1引数）を指定してください。');
}

$lines = [];
$lines[] = '=== user persist diagnose ===';
$lines[] = 'user_id=' . $user_id;

if (function_exists('aidunite_user_get_canonical_meta')) {
    $lines[] = '--- canonical ---';
    foreach (aidunite_user_get_canonical_meta($user_id) as $k => $v) {
        $lines[] = $k . '=' . var_export($v, true);
    }
}

$lines[] = '--- raw meta ---';
foreach (['aidunite_role', 'user_type', 'registration_status', 'user_status', 'user_gender', 'registration_source'] as $key) {
    $lines[] = $key . '=' . var_export(get_user_meta($user_id, $key, true), true);
}

$lines[] = 'persist_loaded=' . (function_exists('aidunite_user_write_role_meta') ? 'yes' : 'no');

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
