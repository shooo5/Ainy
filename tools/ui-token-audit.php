<?php
/**
 * UI 直色（#hex）監査レポート — Phase 3
 *
 * 使用方法: WP 管理者でログイン後アクセス
 * 例: /wp-content/themes/aidunite-original/tools/ui-token-audit.php
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
    $wp_load = dirname(__DIR__, 3) . '/wp-load.php';
}
require_once $wp_load;

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    status_header(403);
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$pages_dir = get_stylesheet_directory() . '/assets/css/pages';
$files = glob($pages_dir . '/*.css') ?: [];
sort($files);

echo "Ainy UI Token Audit (pages/*.css)\n";
echo 'Generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n";
echo str_repeat('=', 60) . "\n\n";

$total = 0;
$rows = [];

foreach ($files as $file) {
    $name = basename($file);
    $content = (string) file_get_contents($file);
    $naked = 0;
    if (preg_match_all('/(?<!var\([^)]{0,120}),)\s*#[0-9a-fA-F]{3,8}\b/', $content, $m)) {
        $naked = count($m[0]);
    }
  // 簡易: 行内の #hex（フォールバック var(--x, #fff) は許容）
    if (preg_match_all('/:\s*#([0-9a-fA-F]{3,8})\b/', $content, $m2)) {
        $direct = count($m2[0]);
    } else {
        $direct = 0;
    }
    $rows[] = ['file' => $name, 'direct' => $direct, 'path' => $file];
    $total += $direct;
}

usort($rows, static function ($a, $b) {
    return $b['direct'] <=> $a['direct'];
});

printf("%-40s %6s\n", 'FILE', 'DIRECT#');
echo str_repeat('-', 50) . "\n";
foreach ($rows as $row) {
    if ($row['direct'] === 0) {
        continue;
    }
    printf("%-40s %6d\n", $row['file'], $row['direct']);
}

echo "\nTotal direct hex assignments: {$total}\n";
echo "\nPriority targets: team-members, attendance, schedule, schedule-management-page, match-board, messaging\n";
echo "Goal: replace with var(--*) / color-mix; keep var(..., #fallback) only.\n";
