<?php
/**
 * チーム成立ファネル・ベースライン出力（管理者向け・Phase 0 計測）
 *
 * 使用方法: WP 管理者でログイン後、ブラウザで本ファイルにアクセス
 * 例: /wp-content/themes/aidunite-original/tools/funnel-baseline-report.php
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

require_once get_stylesheet_directory() . '/functions/analytics/team-funnel.php';

header('Content-Type: text/plain; charset=utf-8');

$summary = aidunite_analytics_get_team_funnel_summary();
$ref = $summary['reference'] ?? [];
$established_rate = function_exists('aidunite_dashboard_calc_match_establishment_rate')
    ? aidunite_dashboard_calc_match_establishment_rate(
        gmdate('Y-m-01'),
        gmdate('Y-m-d')
    )
    : null;

echo "Ainy Funnel Baseline Report\n";
echo 'Generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n";
echo str_repeat('=', 60) . "\n\n";

echo "[Reference]\n";
echo 'user_accounts: ' . (int) ($ref['user_accounts'] ?? 0) . "\n";
echo 'teams_active: ' . (int) ($ref['teams_active'] ?? 0) . "\n";
echo 'teams_established: ' . (int) ($ref['teams_established'] ?? 0) . "\n";
echo 'teams_high_value: ' . (int) ($ref['teams_high_value'] ?? 0) . "\n\n";

if (is_array($established_rate)) {
    echo "[Match establishment rate (current month)]\n";
    echo 'label: ' . (string) ($established_rate['label'] ?? '—') . "\n";
    echo 'formula: ' . (string) ($established_rate['formula'] ?? '') . "\n\n";
}

foreach ($summary['groups'] ?? [] as $group) {
    echo '[' . (string) ($group['title'] ?? '') . "]\n";
    echo (string) ($group['description'] ?? '') . "\n";
    foreach ($group['steps'] ?? [] as $step) {
        $rate = $step['step_rate'] !== null ? (string) $step['step_rate'] . '%' : '—';
        $leave = $step['leave_rate'] !== null ? (string) $step['leave_rate'] . '%' : '—';
        printf(
            "  %2d. %-22s count=%4d  step_rate=%s  leave_rate=%s\n",
            (int) ($step['order'] ?? 0),
            (string) ($step['label'] ?? ''),
            (int) ($step['count'] ?? 0),
            $rate,
            $leave
        );
    }
    echo "\n";
}

echo "Pilot success checklist (4 weeks):\n";
echo "  [ ] schedule_registered -> recruit_published\n";
echo "  [ ] application -> established (>=1)\n";
echo "  [ ] attendance or chat before match\n";
echo "  [ ] trial -> paid transition\n";
