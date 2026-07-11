<?php
/**
 * match_board persist 診断（Local 開発向け）
 * 例: .../tools/match-board-persist-diagnose.php?schedule_id=6880
 * または: .../tools/match-board-persist-diagnose.php?match_board_id=6881
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

$schedule_id = isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 0;
$board_id = isset($_GET['match_board_id']) ? (int) $_GET['match_board_id'] : 0;

if ($board_id < 1 && $schedule_id > 0 && function_exists('aidunite_schedule_find_match_board_id_for_schedule')) {
    $board_id = (int) aidunite_schedule_find_match_board_id_for_schedule($schedule_id);
}

if ($board_id < 1) {
    wp_die('schedule_id または match_board_id を指定してください。');
}

$lines = [];
$lines[] = '=== match_board persist diagnose ===';
$lines[] = 'match_board_id=' . $board_id;
$lines[] = 'schedule_id_query=' . $schedule_id;

if (function_exists('aidunite_match_board_get_canonical_meta')) {
    $c = aidunite_match_board_get_canonical_meta($board_id);
    $lines[] = '--- canonical ---';
    foreach ($c as $k => $v) {
        $lines[] = $k . '=' . var_export($v, true);
    }
}

$lines[] = '--- raw meta ---';
$lines[] = 'match_board_status=' . var_export(get_post_meta($board_id, 'match_board_status', true), true);
$lines[] = 'schedule_id_meta=' . var_export(get_post_meta($board_id, 'schedule_id', true), true);
$lines[] = 'post_parent=' . var_export(wp_get_post_parent_id($board_id), true);

$lines[] = 'persist_loaded=' . (function_exists('aidunite_match_board_sync_status_from_game') ? 'yes' : 'no');

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
