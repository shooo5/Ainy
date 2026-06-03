<?php
/**
 * match_board_status 一括再同期（Local / CLI）
 * 例: php tools/run-match-board-status-repair.php
 * ドライラン: php tools/run-match-board-status-repair.php --dry-run
 */

require_once __DIR__ . '/wp-bootstrap.php';
if (!aidunite_tools_bootstrap_wp()) {
    exit(1);
}

if (!function_exists('aidunite_match_board_repair_all_statuses')) {
    require_once get_template_directory() . '/functions/match/match-board-persist.php';
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

$out = static function (string $line) use ($is_cli): void {
    echo $line . "\n";
};

$out('=== match_board_status repair ===');
$out('dry_run=' . ($dry_run ? 'yes' : 'no'));

$result = aidunite_match_board_repair_all_statuses($dry_run);
$out(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if (!$dry_run && class_exists('AidUniteDataIntegrityManager')) {
    $out('');
    $out('=== integrity check (board_status_drift count) ===');
    $check = AidUniteDataIntegrityManager::checkDataIntegrity();
    $drifts = 0;
    $lifecycle = $check['results']['match_lifecycle']['issues'] ?? [];
    foreach ($lifecycle as $issue) {
        if (($issue['type'] ?? '') === 'board_status_drift') {
            $drifts++;
        }
    }
    $out('remaining_board_status_drift=' . $drifts);
}
