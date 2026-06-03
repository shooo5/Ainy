<?php
/**
 * AI月次分析レポート（数値はPHP固定・示唆のみテンプレート生成）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/team-funnel.php';
require_once __DIR__ . '/team-active.php';
require_once __DIR__ . '/pv-analytics.php';
require_once __DIR__ . '/churn-analytics.php';

/**
 * @param int|null $year
 * @param int|null $month
 * @return array<string, mixed>
 */
function aidunite_analytics_build_monthly_report($year = null, $month = null) {
    $year = $year ? (int) $year : (int) current_time('Y');
    $month = $month ? (int) $month : (int) current_time('n');
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from . ' 00:00:00'));

    $funnel = aidunite_analytics_get_team_funnel_summary();
    $active = aidunite_analytics_get_active_teams_report('', 500);
    $pv = aidunite_analytics_get_pv_by_page_report($from, $to);
    $churn = aidunite_analytics_get_churn_report();
    $board_cv = aidunite_analytics_get_match_board_cv_summary($from, $to);

    $metrics = [
        'period' => ['year' => $year, 'month' => $month, 'from' => $from, 'to' => $to],
        'funnel' => $funnel,
        'active_summary' => $active['summary'] ?? [],
        'teams_publish' => $active['total_publish_teams'] ?? 0,
        'high_value_teams' => $funnel['reference']['teams_high_value'] ?? 0,
        'established_teams' => $funnel['reference']['teams_established'] ?? 0,
        'pv_pages' => array_slice($pv, 0, 8),
        'board_cv' => $board_cv,
    ];

    $insights = aidunite_analytics_generate_insights_from_metrics($metrics, $funnel, $churn);

    return [
        'generated_at' => current_time('mysql'),
        'metrics' => $metrics,
        'insights' => $insights,
    ];
}

/**
 * @param array<string, mixed> $metrics
 * @param array<string, mixed> $funnel
 * @param array<string, mixed> $churn
 * @return string[]
 */
function aidunite_analytics_generate_insights_from_metrics(array $metrics, array $funnel, array $churn) {
    $insights = [];
    $groups = $funnel['groups'] ?? [];

    foreach ($groups as $group) {
        $steps = $group['steps'] ?? [];
        foreach ($steps as $step) {
            if ($step['leave_rate'] !== null && (float) $step['leave_rate'] >= 40.0) {
                $insights[] = sprintf(
                    '【%s】「%s」で離脱率 %.1f%% が高いです。該当画面の導線・文言・必須項目を見直してください。',
                    $group['title'] ?? '',
                    $step['label'] ?? '',
                    (float) $step['leave_rate']
                );
            }
        }
    }

    $onboarding = null;
    $matching = null;
    foreach ($groups as $g) {
        if (($g['id'] ?? '') === 'onboarding') {
            $onboarding = $g;
        }
        if (($g['id'] ?? '') === 'matching') {
            $matching = $g;
        }
    }

    if ($onboarding && !empty($onboarding['steps'])) {
        $last = end($onboarding['steps']);
        $first = $onboarding['steps'][0];
        if ((int) ($first['count'] ?? 0) > 0 && (int) ($last['count'] ?? 0) === 0) {
            $insights[] = 'オンボーディングでスケジュール・募集まで到達したチームがほぼありません。登録後のスケジュール登録導線を最優先で改善してください。';
        }
    }

    if ($matching && !empty($matching['steps'])) {
        foreach ($matching['steps'] as $step) {
            if (($step['id'] ?? '') === 'application' && ($step['count'] ?? 0) > 0) {
                $est = 0;
                foreach ($matching['steps'] as $s2) {
                    if (($s2['id'] ?? '') === 'established') {
                        $est = (int) ($s2['count'] ?? 0);
                    }
                }
                if ($est > 0 && (int) $step['count'] > $est * 2) {
                    $insights[] = '申請はあるが成立まで届いていないチームが多い可能性があります。承認フロー・マッチ詳細・日程調整UIを確認してください。';
                }
            }
        }
    }

    $high = (int) ($metrics['high_value_teams'] ?? 0);
    $total = (int) ($metrics['teams_publish'] ?? 0);
    if ($total > 0) {
        $pct = round(($high / $total) * 100, 1);
        $insights[] = sprintf(
            '公開チーム %d のうち高価値（募集・申請・成立）に近いチームは約 %s%% です。成立に近いチームを増やすことが売上・定着の核心です。',
            $total,
            number_format($pct, 1)
        );
    }

    $cv = $metrics['board_cv']['cv_rate'] ?? null;
    if ($cv !== null) {
        $insights[] = sprintf(
            '直近の match-board 閲覧→申請CV（イベント計測）は %.1f%% です。心臓指標として継続監視してください。',
            (float) $cv
        );
    } elseif (empty($churn['has_events'])) {
        $insights[] = '行動イベントデータがまだ少ないです。利用が進むと match-board のCV率・離脱分析が有効になります。';
    }

    if (empty($insights)) {
        $insights[] = '大きなボトルネックは検出されませんでした。ファネル各段階のチーム一覧から個別ケースを確認してください。';
    }

    return $insights;
}

/**
 * @param int $year
 * @param int $month
 * @return array<string, mixed>|null
 */
function aidunite_analytics_get_saved_monthly_report($year, $month) {
    $key = aidunite_analytics_monthly_report_option_key($year, $month);
    $raw = get_option($key, null);
    return is_array($raw) ? $raw : null;
}

function aidunite_analytics_save_monthly_report(array $report, $year, $month) {
    $key = aidunite_analytics_monthly_report_option_key($year, $month);
    update_option($key, $report, false);
}

function aidunite_analytics_monthly_report_option_key($year, $month) {
    return 'aidunite_ai_report_' . (int) $year . '_' . sprintf('%02d', (int) $month);
}
