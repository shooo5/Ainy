<?php
/**
 * match_request persist 診断（Local 開発向け）
 * 例: .../tools/match-request-persist-diagnose.php?match_request_id=7005
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

$match_request_id = isset($_GET['match_request_id']) ? (int) $_GET['match_request_id'] : 0;
if ($match_request_id <= 0) {
    wp_die('match_request_id クエリを指定してください。');
}

$lines = [];
$lines[] = '=== match_request persist diagnose ===';
$lines[] = 'match_request_id=' . $match_request_id;

if (function_exists('aidunite_match_request_get_canonical_meta')) {
    $c = aidunite_match_request_get_canonical_meta($match_request_id);
    $lines[] = '--- canonical ---';
    foreach ($c as $k => $v) {
        $lines[] = $k . '=' . var_export($v, true);
    }
}

$legacy = ['request_status', 'preferred_start', 'preferred_end', 'preferred_place', 'preferred_gender', 'canceled_reason', 'aidunite_cancel_reason'];
$lines[] = '--- legacy meta (raw) ---';
foreach ($legacy as $key) {
    $lines[] = $key . '=' . var_export(get_post_meta($match_request_id, $key, true), true);
}

$lines[] = 'persist_loaded=' . (function_exists('aidunite_match_request_create_application_post') ? 'yes' : 'no');

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
