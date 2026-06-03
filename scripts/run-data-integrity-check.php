<?php
/**
 * ローカル用: データ整合性チェック（CLI）
 *
 * 用法（テーマルートから・Local の Open site shell 推奨）:
 *   php scripts/run-data-integrity-check.php          … 件数サマリのみ（巨大JSONは出さない）
 *   php scripts/run-data-integrity-check.php --full    … 全件JSON（調査用・画面が埋まるので通常不要）
 *   php scripts/run-data-integrity-check.php --repair  … 掲示板ステータス同期のみ
 *
 * 接続エラーが出る場合:
 * - Local でサイトを「Started」にする
 * - ブラウザでサイトが開けることを先に確認
 * - Local の「Open site shell」から実行する（推奨・Local 付属 PHP / DB 設定が揃う）
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
    fwrite(STDERR, "wp-load.php が見つかりません: {$wp_load}\n");
    exit(1);
}

define('WP_USE_THEMES', false);

ob_start();
require $wp_load;
$boot_output = (string) ob_get_clean();

if (
    $boot_output !== ''
    && (
        stripos($boot_output, 'Error establishing a database connection') !== false
        || stripos($boot_output, 'Database Error') !== false
    )
) {
    fwrite(STDERR, "WordPress がデータベースに接続できません（CLI から wp-load 失敗）。\n\n");
    fwrite(STDERR, "確認手順:\n");
    fwrite(STDERR, "  1. Local アプリでサイト「aidunite-local」を Started（緑）にする\n");
    fwrite(STDERR, "  2. ブラウザで http://aidunite-d.local が表示されるか確認\n");
    fwrite(STDERR, "  3. 同じコマンドを Local の「Open site shell」で実行（PowerShell 直叩きより確実）\n");
    fwrite(STDERR, "  4. それでもダメなら wp-config.php の DB_HOST（現在 localhost）を Local の MySQL ポートに合わせる\n\n");
    fwrite(STDERR, "代替: サイト起動後、管理者ログイン状態で REST\n");
    fwrite(STDERR, "  GET http://aidunite-d.local/wp-json/aidunite/v1/data-integrity-check-v2\n");
    exit(1);
}

if (!defined('ABSPATH') || !class_exists('AidUniteDataIntegrityManager')) {
    fwrite(STDERR, "AidUniteDataIntegrityManager が読み込まれていません。functions.php の require を確認してください。\n");
    exit(1);
}

$repair = in_array('--repair', $argv ?? [], true);
$full_json = in_array('--full', $argv ?? [], true);
// 互換: --summary は従来どおりサマリのみ（--full なしと同じ）
if (in_array('--summary', $argv ?? [], true)) {
    $full_json = false;
}

$result = AidUniteDataIntegrityManager::checkDataIntegrity();

$lifecycle_issues = $result['results']['match_lifecycle']['issues'] ?? [];
$by_type = [];
foreach ($lifecycle_issues as $issue) {
    $t = $issue['type'] ?? 'unknown';
    if (!isset($by_type[$t])) {
        $by_type[$t] = 0;
    }
    $by_type[$t]++;
}

fwrite(STDERR, "\n=== 整合性チェック サマリ ===\n");
fwrite(STDERR, 'total_issues: ' . (int) ($result['total_issues'] ?? 0) . "\n");
foreach ($result['results'] as $key => $block) {
    $cnt = (int) ($block['issue_count'] ?? 0);
    if ($cnt > 0) {
        fwrite(STDERR, "  {$key}: {$cnt}\n");
    }
}
if (!empty($by_type)) {
    arsort($by_type);
    fwrite(STDERR, "\n[match_lifecycle 内訳]\n");
    foreach ($by_type as $type => $count) {
        fwrite(STDERR, "  {$type}: {$count}\n");
    }
}
fwrite(STDERR, "詳細一覧が必要なときだけ: php scripts/run-data-integrity-check.php --full\n");
fwrite(STDERR, "---\n");

if (!$full_json) {
    exit(($result['total_issues'] ?? 0) > 0 ? 2 : 0);
}

echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if ($repair && ($result['total_issues'] ?? 0) > 0) {
    echo "\n--- repair ---\n";
    $repaired = AidUniteDataIntegrityManager::autoRepair([
        'sync_match_board_status' => true,
    ]);
    echo wp_json_encode($repaired, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    $after = AidUniteDataIntegrityManager::checkDataIntegrity();
    echo "\n--- after repair ---\n";
    echo wp_json_encode([
        'total_issues' => $after['total_issues'] ?? 0,
        'match_lifecycle' => $after['results']['match_lifecycle']['issue_count'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

exit(($result['total_issues'] ?? 0) > 0 ? 2 : 0);
