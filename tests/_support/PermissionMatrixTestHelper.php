<?php
/**
 * 権限マトリクス用テストヘルパー（P1-02）
 * フィクスチャ生成のみ。アサーションは PermissionMatrixTest に実装する。
 */

if (!function_exists('permission_matrix_reset_globals')) {
    function permission_matrix_reset_globals(): void
    {
        $GLOBALS['test_users'] = [];
        $GLOBALS['test_user_id'] = 1;
        $GLOBALS['test_meta_data'] = [];
        $GLOBALS['test_posts'] = [];
        $GLOBALS['test_post_meta'] = [];
        $GLOBALS['test_current_user_can'] = false;
        $GLOBALS['test_user_capabilities'] = [];
        $GLOBALS['test_user_id'] = 0;
    }
}

if (!function_exists('permission_matrix_seed_user')) {
    /**
     * @param string $role guest|team_member|team_leader|guardian|administrator
     * @return int user_id
     */
    function permission_matrix_seed_user(string $role): int
    {
        $email = $role . '_' . uniqid('', true) . '@matrix.test';
        $user_id = wp_create_user($role . '_user', 'password123', $email);
        if ($role === 'administrator') {
            $GLOBALS['test_users'][$user_id]->roles = ['administrator'];
            update_user_meta($user_id, 'aidunite_role', 'administrator');
            update_user_meta($user_id, 'user_type', 'administrator');
        } elseif ($role === 'team_leader') {
            update_user_meta($user_id, 'aidunite_role', 'team_leader');
            update_user_meta($user_id, 'user_type', 'team_leader');
        } elseif ($role === 'team_member') {
            update_user_meta($user_id, 'aidunite_role', 'member');
            update_user_meta($user_id, 'user_type', 'member');
        } elseif ($role === 'guardian') {
            update_user_meta($user_id, 'aidunite_role', 'parent');
            update_user_meta($user_id, 'user_type', 'parent');
        }
        wp_set_current_user($user_id);
        return $user_id;
    }
}

if (!function_exists('permission_matrix_attach_team')) {
    function permission_matrix_attach_team(int $user_id, int $team_id, string $role = 'team_leader'): void
    {
        update_user_meta($user_id, 'team_id', $team_id);
        if ($role === 'team_leader') {
            update_user_meta($user_id, 'aidunite_role', 'team_leader');
            update_user_meta($user_id, 'user_type', 'team_leader');
            update_post_meta($team_id, 'team_leader_id', $user_id);
        }
        if (function_exists('aidunite_team_append_managed_team_id')) {
            aidunite_team_append_managed_team_id($user_id, $team_id);
        } else {
            update_user_meta($user_id, 'managed_team_ids', json_encode([$team_id]));
        }
    }
}
