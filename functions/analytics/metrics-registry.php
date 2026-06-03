<?php
/**
 * 分析メトリック定義レジストリ（コード上の単一の正）
 * 正式仕様は安定後に docs/spec へ移植。人間向け説明は docs/reports/analytics-metrics.md
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<string, array{
 *   label: string,
 *   beta: bool,
 *   description: string,
 *   chart_enabled: bool,
 *   chart_series: string,
 *   collector: callable|null
 * }>
 */
function aidunite_analytics_get_metric_registry() {
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }

    $registry = [
        'users_total' => [
            'label' => '登録ユーザー',
            'beta' => false,
            'description' => '全 WordPress ユーザー累計（user_registered 基準の日次スナップショット）',
            'chart_enabled' => true,
            'chart_series' => 'cumulative',
            'collector' => 'aidunite_metrics_collect_users_total',
        ],
        'teams_total' => [
            'label' => '登録チーム',
            'beta' => false,
            'description' => 'post_type=team, post_status=publish の累計',
            'chart_enabled' => false,
            'chart_series' => 'cumulative',
            'collector' => 'aidunite_metrics_collect_teams_total',
        ],
        'schedules_confirmed' => [
            'label' => 'スケジュール（確定）',
            'beta' => true,
            'description' => 'intent=confirmed の schedule 登録数（当日 post_date）',
            'chart_enabled' => false,
            'chart_series' => 'daily_increment',
            'collector' => 'aidunite_metrics_collect_schedules_by_intent',
        ],
        'schedules_recruit' => [
            'label' => 'スケジュール（マッチ募集）',
            'beta' => true,
            'description' => 'intent=recruit の schedule 登録数（当日 post_date）',
            'chart_enabled' => false,
            'chart_series' => 'daily_increment',
            'collector' => null,
        ],
        'schedules_tentative' => [
            'label' => 'スケジュール（仮押さえ）',
            'beta' => true,
            'description' => 'intent=tentative の schedule 登録数（当日 post_date）',
            'chart_enabled' => false,
            'chart_series' => 'daily_increment',
            'collector' => null,
        ],
        'matches_established' => [
            'label' => '成立マッチ',
            'beta' => true,
            'description' => 'match_request status IN (accepted, established)、post_date が当日',
            'chart_enabled' => false,
            'chart_series' => 'daily_increment',
            'collector' => 'aidunite_metrics_collect_matches_established',
        ],
        'match_applications' => [
            'label' => 'マッチ申請',
            'beta' => true,
            'description' => 'match_request 新規件数（status 不問、post_date が当日）',
            'chart_enabled' => false,
            'chart_series' => 'daily_increment',
            'collector' => 'aidunite_metrics_collect_match_applications',
        ],
        'paid_users' => [
            'label' => '有料会員',
            'beta' => true,
            'description' => 'user_meta payment_status=paid の累計',
            'chart_enabled' => false,
            'chart_series' => 'cumulative',
            'collector' => 'aidunite_metrics_collect_paid_users',
        ],
        'revenue_estimate' => [
            'label' => '売上目安',
            'beta' => true,
            'description' => '有料会員数 × 学校（個人契約）月額。Stripe 実績ではない',
            'chart_enabled' => false,
            'chart_series' => 'cumulative',
            'collector' => 'aidunite_metrics_collect_revenue_estimate',
        ],
        'match_establishment_rate' => [
            'label' => 'マッチ成立率',
            'beta' => true,
            'description' => '期間内成立数 ÷ 期間内申請数（暫定。申請中件数は分母に含めない）',
            'chart_enabled' => false,
            'chart_series' => 'derived',
            'collector' => null,
        ],
    ];

    return $registry;
}

/**
 * @param string $metric_id
 * @return array|null
 */
function aidunite_analytics_get_metric($metric_id) {
    $registry = aidunite_analytics_get_metric_registry();
    return $registry[$metric_id] ?? null;
}

/**
 * フェーズ1でチャート表示可能なメトリック ID 一覧
 *
 * @return string[]
 */
function aidunite_analytics_get_chart_metric_ids() {
    $ids = [];
    foreach (aidunite_analytics_get_metric_registry() as $id => $def) {
        if (!empty($def['chart_enabled'])) {
            $ids[] = $id;
        }
    }
    return $ids;
}
