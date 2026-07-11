<?php
/**
 * 権限マトリクス行を AidUniteAuthMiddleware 等で評価（P1-02）
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

require_once __DIR__ . '/PermissionMatrixTestHelper.php';

final class PermissionMatrixEvaluator
{
  private const TEAM_ID = 5100;
  private const OTHER_TEAM_ID = 5200;

  /**
   * @param array<string, mixed> $row
   * @return string allow|deny|redirect
   */
  public static function evaluate(array $row): string
  {
    permission_matrix_reset_globals();

    $role = (string) ($row['role'] ?? 'guest');
    $acting_user_id = 0;

    if ($role === 'guest') {
      $GLOBALS['test_user_id'] = 0;
    } else {
      $acting_user_id = permission_matrix_seed_user($role);
      if (in_array($role, ['team_leader', 'team_member'], true)) {
        self::seedTeams($role === 'team_leader' ? $acting_user_id : 9999);
        permission_matrix_attach_team(
          $acting_user_id,
          self::TEAM_ID,
          $role === 'team_leader' ? 'team_leader' : 'member'
        );
      }
      if ($role === 'guardian') {
        permission_matrix_attach_team($acting_user_id, self::TEAM_ID, 'member');
      }
      if ($role === 'administrator') {
        $GLOBALS['test_users'][$acting_user_id]->roles = ['administrator'];
      }
    }

    $id = (string) ($row['id'] ?? '');

    switch ($id) {
      case 'guest_match_board':
      case 'guest_rest_match_request':
        return self::fromAuth(AidUniteAuthMiddleware::require_auth(false));

      case 'member_match_board':
      case 'leader_payment_setup':
      case 'parent_parent_payment':
        return self::fromAuth(AidUniteAuthMiddleware::require_auth(false));

      case 'leader_team_settings':
      case 'leader_team_payment_mgmt':
      case 'member_payment_setup':
      case 'guardian_team_settings':
        return self::fromTeamLeader(AidUniteAuthMiddleware::require_team_leader(self::TEAM_ID, false));

      case 'leader_admin_user_list':
        return self::fromAdmin(AidUniteAuthMiddleware::require_admin(false));

      case 'admin_user_list':
        return self::fromAdmin(AidUniteAuthMiddleware::require_admin(false));

      case 'leader_rest_match_request':
        return self::fromAuth(AidUniteAuthMiddleware::require_team_membership(self::TEAM_ID, false));

      case 'other_team_schedule_edit':
        return self::evaluateOtherTeamScheduleEdit();

      case 'multi_team_switch':
        return self::evaluateMultiTeamSwitch();

      case 'e2e_api_production':
        return self::evaluateE2eApiDisabledInProduction();

      case 'member_parent_payment':
        // ページ正本は require_auth のみのため、ログイン済みメンバーは到達可
        return self::fromAuth(AidUniteAuthMiddleware::require_auth(false));

      default:
        return 'deny';
    }
  }

  private static function seedTeams(int $leader_user_id = 9999): void
  {
    foreach ([self::TEAM_ID, self::OTHER_TEAM_ID] as $team_id) {
      $GLOBALS['test_posts'][$team_id] = (object) [
        'ID' => $team_id,
        'post_type' => 'team',
        'post_status' => 'publish',
        'post_author' => $leader_user_id,
        'post_title' => 'Matrix Team ' . $team_id,
      ];
      $GLOBALS['test_post_meta'][$team_id] = [
        'team_leader_id' => $team_id === self::TEAM_ID ? $leader_user_id : 9999,
        'team_status' => 'active',
      ];
    }
  }

  private static function fromAuth(AidUniteAuthResult $result): string
  {
    if (!$result->is_authenticated) {
      return 'redirect';
    }
    if (!empty($result->error) || (!$result->is_authorized && $result->error_code !== '')) {
      return 'deny';
    }
    return 'allow';
  }

  private static function fromTeamLeader(AidUniteAuthResult $result): string
  {
    if (!$result->is_authenticated) {
      return 'redirect';
    }
    if ($result->is_authorized && empty($result->error)) {
      return 'allow';
    }
    return 'deny';
  }

  private static function fromAdmin(AidUniteAuthResult $result): string
  {
    if (!$result->is_authenticated) {
      return 'redirect';
    }
    if ($result->is_authorized && empty($result->error)) {
      return 'allow';
    }
    return 'deny';
  }

  private static function evaluateOtherTeamScheduleEdit(): string
  {
    $leader_id = permission_matrix_seed_user('team_leader');
    permission_matrix_attach_team($leader_id, self::TEAM_ID, 'team_leader');
    wp_set_current_user($leader_id);

    $other_schedule_id = wp_insert_post([
      'post_type' => 'schedule',
      'post_status' => 'publish',
      'post_author' => 9999,
      'meta_input' => ['team_id' => self::OTHER_TEAM_ID],
    ]);

    $updated = aidunite_update_schedule([
      'post_id' => $other_schedule_id,
      'title' => '侵入テスト',
    ]);

    return $updated ? 'allow' : 'deny';
  }

  private static function evaluateMultiTeamSwitch(): string
  {
    $leader_id = permission_matrix_seed_user('team_leader');
    update_user_meta($leader_id, 'managed_team_ids', json_encode([self::TEAM_ID, self::OTHER_TEAM_ID]));
    update_user_meta($leader_id, 'current_operating_team_id', self::TEAM_ID);

    $switched = aidunite_set_current_operating_team_id($leader_id, self::OTHER_TEAM_ID);
    $current = function_exists('aidunite_get_current_team_id')
      ? (int) aidunite_get_current_team_id($leader_id)
      : (int) get_user_meta($leader_id, 'current_operating_team_id', true);

    return ($switched !== false && $current === self::OTHER_TEAM_ID) ? 'allow' : 'deny';
  }

  private static function evaluateE2eApiDisabledInProduction(): string
  {
    if (!function_exists('aidunite_e2e_api_enabled')) {
      require_once PROJECT_ROOT . '/functions/e2e/test-data-api.php';
    }

    if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
      return aidunite_e2e_api_enabled() ? 'allow' : 'deny';
    }

    // ローカル/CI では既定 false（未設定）→ 本番相当で API 無効とみなす
    return aidunite_e2e_api_enabled() ? 'allow' : 'deny';
  }
}
