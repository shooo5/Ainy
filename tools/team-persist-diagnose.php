<?php
/**
 * team persist 診断（Local 開発向け）
 * 例: .../tools/team-persist-diagnose.php?team_id=123
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
if ($team_id <= 0 && PHP_SAPI === 'cli' && isset($argv[1])) {
    $team_id = (int) $argv[1];
}
if ($team_id <= 0) {
    wp_die('team_id クエリ（または CLI の第1引数）を指定してください。');
}

$lines = [];
$lines[] = '=== team persist diagnose ===';
$lines[] = 'team_id=' . $team_id;
$lines[] = 'post_type=' . var_export(get_post_type($team_id), true);

if (function_exists('aidunite_team_get_canonical_meta')) {
    $lines[] = '--- canonical ---';
    foreach (aidunite_team_get_canonical_meta($team_id) as $k => $v) {
        $lines[] = $k . '=' . var_export($v, true);
    }
}

$text_keys = ['team_name', 'team_gender_option', 'team_type', 'team_status', 'payment_mode', 'sport_type'];
$lines[] = '--- raw meta ---';
foreach ($text_keys as $key) {
    $lines[] = $key . '=' . var_export(get_post_meta($team_id, $key, true), true);
}

$lines[] = 'persist_loaded=' . (function_exists('aidunite_team_write_status_meta') ? 'yes' : 'no');

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
