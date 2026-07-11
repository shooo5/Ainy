<?php
/**
 * attendance persist 診断
 * 例: .../tools/attendance-persist-diagnose.php?schedule_id=100
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
if ($schedule_id <= 0 && PHP_SAPI === 'cli' && isset($argv[1])) {
    $schedule_id = (int) $argv[1];
}
if ($schedule_id <= 0) {
    wp_die('schedule_id クエリ（または CLI 第1引数）を指定してください。');
}

$lines = [];
$lines[] = '=== attendance persist diagnose ===';
$lines[] = 'schedule_id=' . $schedule_id;
$lines[] = 'persist_loaded=' . (function_exists('aidunite_attendance_persist_save_data') ? 'yes' : 'no');

if (function_exists('aidunite_attendance_get_canonical_data')) {
    $c = aidunite_attendance_get_canonical_data($schedule_id);
    $lines[] = 'attendance_required=' . var_export($c['attendance_required'] ?? '', true);
    $lines[] = 'respondent_count=' . var_export($c['respondent_count'] ?? 0, true);
    $lines[] = '--- normalized attendance_data ---';
    foreach (($c['attendance_data'] ?? []) as $uid => $row) {
        $lines[] = 'user_' . $uid . '=' . wp_json_encode($row, JSON_UNESCAPED_UNICODE);
    }
    $lines[] = '--- raw status sample (first 5) ---';
    $raw = $c['attendance_data_raw'] ?? [];
    $n = 0;
    foreach ($raw as $uid => $row) {
        if ($n++ >= 5) {
            break;
        }
        $lines[] = 'raw_user_' . $uid . '_status=' . var_export(is_array($row) ? ($row['status'] ?? '') : '', true);
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
