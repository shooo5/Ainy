<?php
/**
 * payment persist 診断
 * 例: .../tools/payment-persist-diagnose.php?team_id=1&user_id=2
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

$team_id = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

$lines = [];
$lines[] = '=== payment persist diagnose ===';
$lines[] = 'persist_loaded=' . (function_exists('aidunite_team_write_payment_mode_meta') ? 'yes' : 'no');

if ($team_id > 0 && function_exists('aidunite_payment_get_canonical_team_meta')) {
    $lines[] = '--- team canonical (team_id=' . $team_id . ') ---';
    foreach (aidunite_payment_get_canonical_team_meta($team_id) as $k => $v) {
        $lines[] = $k . '=' . var_export($v, true);
    }
}

if ($user_id > 0 && function_exists('aidunite_payment_get_canonical_user_meta')) {
    $lines[] = '--- user canonical (user_id=' . $user_id . ') ---';
    foreach (aidunite_payment_get_canonical_user_meta($user_id) as $k => $v) {
        $lines[] = $k . '=' . var_export($v, true);
    }
}

if ($team_id <= 0 && $user_id <= 0) {
    $lines[] = 'team_id または user_id クエリを指定してください。';
}

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
