<?php
/**
 * 通知 type メタの legacy → canonical 移行
 * 例: php tools/run-notification-type-migrate.php
 * ドライラン: php tools/run-notification-type-migrate.php --dry-run
 */

require_once __DIR__ . '/wp-bootstrap.php';
if (!aidunite_tools_bootstrap_wp()) {
    exit(1);
}

if (!function_exists('aidunite_notification_migrate_legacy_type_meta')) {
    require_once get_template_directory() . '/functions/notify/notification-persist.php';
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

echo "=== notification type migrate ===\n";
echo 'dry_run=' . ($dry_run ? 'yes' : 'no') . "\n";
$result = aidunite_notification_migrate_legacy_type_meta($dry_run);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
