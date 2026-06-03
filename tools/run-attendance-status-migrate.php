<?php
/**
 * attendance_data 内の legacy status → canonical 移行
 * 例: php tools/run-attendance-status-migrate.php
 * ドライラン: php tools/run-attendance-status-migrate.php --dry-run
 */

require_once __DIR__ . '/wp-bootstrap.php';
if (!aidunite_tools_bootstrap_wp()) {
    exit(1);
}

if (!function_exists('aidunite_attendance_migrate_legacy_status_in_data')) {
    require_once get_template_directory() . '/functions/attendance/attendance-persist.php';
}

$is_cli = (PHP_SAPI === 'cli');
$dry_run = $is_cli && in_array('--dry-run', $argv ?? [], true);
if (!$is_cli) {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('管理者でログインした状態で開くか、CLI で実行してください。');
    }
    header('Content-Type: text/plain; charset=utf-8');
    $dry_run = !empty($_GET['dry_run']);
}

echo "=== attendance status migrate ===\n";
echo 'dry_run=' . ($dry_run ? 'yes' : 'no') . "\n";
$result = aidunite_attendance_migrate_legacy_status_in_data($dry_run);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
