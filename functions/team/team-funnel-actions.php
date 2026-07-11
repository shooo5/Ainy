<?php
/**
 * ファネル離脱向け「次にやること」アクション（代表者・実ユーザー不要で表示）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 代表者向けオンボーディング / reuse 導線
 *
 * @param int   $user_id
 * @param int[] $team_scope
 * @return array<int, array<string, mixed>>
 */
function aidunite_funnel_read_leader_action_items($user_id, array $team_scope) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || $team_scope === []) {
        return [];
    }

    $items = [];
    $match_board = home_url('/match-board-own');
    $recruit_board_url = $match_board . (strpos($match_board, '?') === false ? '?' : '&') . 'market_tab=recruit';

    foreach ($team_scope as $scope_tid) {
        $team_id = (int) $scope_tid;
        if ($team_id <= 0) {
            continue;
        }

        $stage = function_exists('aidunite_get_team_activation_stage')
            ? aidunite_get_team_activation_stage($team_id)
            : 'recruit_pending';

        if ($stage === 'recruit_pending') {
            $recruit_url = function_exists('aidunite_get_activation_recruit_edit_url')
                ? aidunite_get_activation_recruit_edit_url()
                : home_url('/mypage/?open_recruit=1');
            $items[] = [
                'type' => 'funnel_onboarding',
                'id' => 'funnel_recruit_' . $team_id,
                'title' => '試合募集をはじめましょう',
                'description' => 'スケジュールを登録し、練習試合の募集を公開してください。',
                'deadline' => null,
                'link_url' => $recruit_url,
                'actions' => [
                    ['label' => '募集を作成', 'action' => 'open', 'type' => 'primary'],
                ],
            ];
            continue;
        }

        if (in_array($stage, ['recruit_published', 'first_application'], true)) {
            $items[] = [
                'type' => 'funnel_onboarding',
                'id' => 'funnel_board_' . $team_id,
                'title' => '対戦相手を探しましょう',
                'description' => 'マッチボードから条件の合うチームに申請できます。',
                'deadline' => null,
                'link_url' => $recruit_board_url,
                'actions' => [
                    ['label' => '相手を探す', 'action' => 'open', 'type' => 'primary'],
                ],
            ];
            continue;
        }

        if ($stage !== 'first_established') {
            continue;
        }

        $established_count = function_exists('aidunite_team_count_real_established_matches')
            ? (int) aidunite_team_count_real_established_matches($team_id)
            : 0;
        if ($established_count < 1 || $established_count >= 2) {
            continue;
        }

        $recruiting = function_exists('aidunite_mypage_joy_count_recruiting')
            ? (int) aidunite_mypage_joy_count_recruiting($team_id)
            : 0;
        $pending_sent = function_exists('aidunite_mypage_joy_count_pending_sent')
            ? (int) aidunite_mypage_joy_count_pending_sent($team_id)
            : 0;

        if ($recruiting > 0 || $pending_sent > 0) {
            continue;
        }

        $items[] = [
            'type' => 'funnel_reuse',
            'id' => 'funnel_reuse_' . $team_id,
            'title' => '次の試合を探しましょう',
            'description' => '1件目の試合が成立しました。新しい募集を公開するか、相手チームに申請してみましょう。',
            'deadline' => null,
            'link_url' => $recruit_board_url,
            'actions' => [
                ['label' => '相手を探す', 'action' => 'open', 'type' => 'primary'],
                ['label' => '試合を募集', 'action' => 'open_recruit', 'type' => 'secondary'],
            ],
        ];
    }

    return $items;
}
