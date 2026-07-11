<?php
/**
 * マルチチーム結合テスト用フィクスチャ（P1-04）
 *
 * WordPress DB 上で手動/E2E 実行する前のシナリオ定義と PHPUnit スタブ用シード。
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

final class MultiTeamTestFixture
{
    /**
     * 代表者が2チームを管理する最小シナリオ
     *
     * @return array{leader_id: int, team_a: int, team_b: int}
     */
    public static function seed_dual_team_leader(): array
    {
        $leader_id = wp_create_user('multi_leader', 'password123', 'multi_leader@test.local');
        wp_set_current_user($leader_id);
        update_user_meta($leader_id, 'aidunite_role', 'team_leader');
        update_user_meta($leader_id, 'user_type', 'team_leader');

        $team_a = aidunite_register_team($leader_id, [
            'team_name' => 'マルチチームA_' . substr(uniqid('', true), -6),
            'sport_type' => 'soccer',
            'region' => 'tokyo',
        ]);
        $team_b = aidunite_register_team($leader_id, [
            'team_name' => 'マルチチームB_' . substr(uniqid('', true), -6),
            'sport_type' => 'soccer',
            'region' => 'tokyo',
        ]);

        if ($team_a) {
            aidunite_approve_team($team_a);
        }
        if ($team_b) {
            aidunite_approve_team($team_b);
        }

        $managed = array_values(array_filter([(int) $team_a, (int) $team_b]));
        update_user_meta($leader_id, 'team_id', $managed[0] ?? 0);
        update_user_meta($leader_id, 'managed_team_ids', json_encode($managed));
        update_user_meta($leader_id, 'current_operating_team_id', $managed[0] ?? 0);

        return [
            'leader_id' => (int) $leader_id,
            'team_a' => (int) $team_a,
            'team_b' => (int) $team_b,
        ];
    }

    /**
     * 手動検証チェックリスト（scripts/run-multi-team-scenario.php でも参照）
     *
     * @return list<string>
     */
    public static function manual_checklist(): array
    {
        return [
            'チーム切替後、スケジュール一覧が operating team のみになる',
            'マッチ申請は current_operating_team_id のチームから送信される',
            'payment-setup は operating team のサブスク状態を表示する',
            'team-payment-management は operating team の Connect 状態を表示する',
            '出口ゲート（未払い制限）は operating team の課金状態で判定される',
            'managed_team_ids に無い team_id をクエリ指定しても拒否される',
        ];
    }
}
