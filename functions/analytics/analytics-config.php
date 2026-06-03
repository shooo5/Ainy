<?php
/**
 * 分析基盤の運用定数・ファネル段階定義
 *
 * 人間向け説明: docs/reports/analytics-metrics.md
 */

if (!defined('ABSPATH')) {
    exit;
}

/** 生イベント（aidunite_page_events）保持日数 */
const AIDUNITE_ANALYTICS_RAW_EVENTS_RETENTION_DAYS = 90;

/** 日次集計（page_analytics_daily 等）保持日数 */
const AIDUNITE_ANALYTICS_DAILY_AGGREGATE_RETENTION_DAYS = 365;

/** 月次スナップショット（AIレポート用）保持月数（0 = 無期限） */
const AIDUNITE_ANALYTICS_MONTHLY_SNAPSHOT_RETENTION_MONTHS = 0;

/**
 * ファネル表示グループ（画面を区切る）
 *
 * @return array<string, array{title:string, description:string, step_ids:string[]}>
 */
function aidunite_analytics_get_team_funnel_groups() {
    return [
        'onboarding' => [
            'title' => '① オンボーディング（チーム〜募集）',
            'description' => 'チームが作られ、スケジュール・募集まで進んだかを見ます。',
            'step_ids' => ['team_created', 'team_active', 'schedule_registered', 'recruit_published'],
        ],
        'matching' => [
            'title' => '② マッチング（申請〜再利用）',
            'description' => '申請・承認・成立・リピートまで進んだかを見ます。①と独立に集計するため、件数が①を超える場合があります（レガシーデータ等）。',
            'step_ids' => ['application', 'approval', 'established', 'reuse'],
        ],
    ];
}

/**
 * チーム成立ファネル段階（プロフィール完了は含めない）
 *
 * @return array<int, array{id:string, label:string, order:int, group:string}>
 */
function aidunite_analytics_get_team_funnel_steps() {
    return [
        ['id' => 'team_created', 'label' => 'チーム作成', 'order' => 1, 'group' => 'onboarding'],
        ['id' => 'team_active', 'label' => 'チーム承認済み', 'order' => 2, 'group' => 'onboarding'],
        ['id' => 'schedule_registered', 'label' => 'スケジュール登録', 'order' => 3, 'group' => 'onboarding'],
        ['id' => 'recruit_published', 'label' => '募集公開', 'order' => 4, 'group' => 'onboarding'],
        ['id' => 'application', 'label' => '申請経験', 'order' => 5, 'group' => 'matching'],
        ['id' => 'approval', 'label' => '承認経験', 'order' => 6, 'group' => 'matching'],
        ['id' => 'established', 'label' => '試合成立', 'order' => 7, 'group' => 'matching'],
        ['id' => 'reuse', 'label' => '再利用（2回以上成立）', 'order' => 8, 'group' => 'matching'],
    ];
}

/**
 * MR status meta_value（申請経験から除外）
 *
 * @return string[]
 */
function aidunite_analytics_funnel_mr_excluded_status_meta_values() {
    return ['canceled', 'rejected', 'キャンセル', '却下'];
}

/**
 * MR status meta_value（承認経験）
 *
 * @return string[]
 */
function aidunite_analytics_funnel_mr_approval_status_meta_values() {
    return ['accepted', 'established', '承認済み', '試合確定'];
}

/**
 * MR status meta_value（試合成立）
 *
 * @return string[]
 */
function aidunite_analytics_funnel_mr_established_status_meta_values() {
    return ['established', '試合確定'];
}

/**
 * ログインユーザーの team_id（代表）
 */
function aidunite_analytics_resolve_user_team_id($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : (int) get_current_user_id();
    if ($user_id <= 0) {
        return 0;
    }
    $tid = (int) get_user_meta($user_id, 'team_id', true);
    if ($tid > 0) {
        return $tid;
    }
    if (function_exists('aidunite_read_user_managed_team_ids_meta')) {
        $managed = aidunite_read_user_managed_team_ids_meta($user_id);
        if (!empty($managed[0])) {
            return (int) $managed[0];
        }
    }
    return 0;
}
