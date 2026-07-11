<?php
/**
 * 権限マトリクス自動テスト用カタログ（P1-02）
 *
 * アサーション本体は tests/Unit/PermissionMatrixTest.php に実装する。
 * 各行: role × resource × action → expected (allow|deny|redirect)
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

final class PermissionMatrixCatalog
{
    /**
     * @return list<array{
     *   id: string,
     *   role: string,
     *   resource: string,
     *   action: string,
     *   expected: string,
     *   notes?: string
     * }>
     */
    public static function rows(): array
    {
        return [
            ['id' => 'guest_match_board', 'role' => 'guest', 'resource' => 'page', 'action' => 'match-board', 'expected' => 'redirect', 'notes' => 'ログイン必須'],
            ['id' => 'member_match_board', 'role' => 'team_member', 'resource' => 'page', 'action' => 'match-board', 'expected' => 'allow'],
            ['id' => 'leader_team_settings', 'role' => 'team_leader', 'resource' => 'page', 'action' => 'team-settings', 'expected' => 'allow'],
            ['id' => 'member_team_settings', 'role' => 'team_member', 'resource' => 'page', 'action' => 'team-settings', 'expected' => 'deny'],
            ['id' => 'leader_payment_setup', 'role' => 'team_leader', 'resource' => 'page', 'action' => 'payment-setup', 'expected' => 'allow'],
            ['id' => 'parent_parent_payment', 'role' => 'guardian', 'resource' => 'page', 'action' => 'parent-payment', 'expected' => 'allow'],
            ['id' => 'member_parent_payment', 'role' => 'team_member', 'resource' => 'page', 'action' => 'parent-payment', 'expected' => 'allow', 'notes' => 'page-parent-payment は require_auth のみ（現状）'],
            ['id' => 'member_payment_setup', 'role' => 'team_member', 'resource' => 'page', 'action' => 'payment-setup', 'expected' => 'deny'],
            ['id' => 'guardian_team_settings', 'role' => 'guardian', 'resource' => 'page', 'action' => 'team-settings', 'expected' => 'deny'],
            ['id' => 'leader_team_payment_mgmt', 'role' => 'team_leader', 'resource' => 'page', 'action' => 'team-payment-management', 'expected' => 'allow'],
            ['id' => 'admin_user_list', 'role' => 'administrator', 'resource' => 'page', 'action' => 'admin-user-list', 'expected' => 'allow'],
            ['id' => 'leader_admin_user_list', 'role' => 'team_leader', 'resource' => 'page', 'action' => 'admin-user-list', 'expected' => 'deny'],
            ['id' => 'guest_rest_match_request', 'role' => 'guest', 'resource' => 'rest', 'action' => 'POST /aidunite/v1/match-request', 'expected' => 'redirect', 'notes' => '未ログイン'],
            ['id' => 'leader_rest_match_request', 'role' => 'team_leader', 'resource' => 'rest', 'action' => 'POST /aidunite/v1/match-request', 'expected' => 'allow'],
            ['id' => 'other_team_schedule_edit', 'role' => 'team_leader', 'resource' => 'rest', 'action' => 'PUT schedule other_team', 'expected' => 'deny', 'notes' => '他チーム文脈'],
            ['id' => 'multi_team_switch', 'role' => 'team_leader', 'resource' => 'context', 'action' => 'switch_operating_team', 'expected' => 'allow', 'notes' => 'managed_team_ids 2件以上'],
            ['id' => 'e2e_api_production', 'role' => 'guest', 'resource' => 'rest', 'action' => 'POST /aidunite/v1/test-data/setup', 'expected' => 'deny', 'notes' => '本番では未登録'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return ['guest', 'team_member', 'team_leader', 'guardian', 'administrator'];
    }
}
