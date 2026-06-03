<?php
/**
 * Local MySQL 接続テスト（WordPress を読まない）
 * 例: php tools/test-db-connection.php
 */

require_once __DIR__ . '/wp-bootstrap.php';

$is_cli = (PHP_SAPI === 'cli');
if (!$is_cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$wp_load = aidunite_tools_find_wp_load();
$public_dir = $wp_load !== '' ? dirname($wp_load) : '';
$site_root = $public_dir !== '' ? dirname($public_dir, 2) : '';
$detail = ['host' => '', 'source' => ''];
if ($site_root !== '' && is_readable($public_dir . '/ainy-local-db-host.php')) {
    require_once $public_dir . '/ainy-local-db-host.php';
    if (function_exists('ainy_resolve_local_wp_db_host_detail')) {
        $detail = ainy_resolve_local_wp_db_host_detail($site_root);
    }
}
$host = (string) ($detail['host'] ?? '');
echo "site_root=" . $site_root . "\n";
echo "resolved_db_host=" . ($host !== '' ? $host : '(none)') . "\n";
echo "resolved_source=" . ($detail['source'] ?? '') . "\n";
echo "LOCALAPPDATA=" . (getenv('LOCALAPPDATA') ?: ($_SERVER['LOCALAPPDATA'] ?? '')) . "\n";

$probe = aidunite_tools_probe_mysql_from_wp_config();
if (is_array($probe)) {
    echo "status=FAIL\n";
    echo ($probe['message'] ?? '') . "\n";
    echo aidunite_tools_db_connection_help() . "\n";
    exit(1);
}

echo "status=OK\n";
echo "MySQL に接続できました。run-match-board-status-repair.php を再実行してください。\n";
