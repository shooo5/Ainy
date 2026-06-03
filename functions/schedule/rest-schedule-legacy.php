<?php
/**
 * スケジュール登録・編集・削除・取得のREST API（functions.php から移設）
 *
 * 正ルート: register-schedule-v2 / update-schedule-v2 / delete-schedule-v2（schedule-persist 経由）
 * 本ファイルの register-schedules / update-schedule / delete-schedule は互換維持のみ（非推奨）
 */

if (!function_exists('aidunite_schedule_legacy_rest_deprecation_notice')) {
    /**
     * @param string $route 例: register-schedules
     */
    function aidunite_schedule_legacy_rest_deprecation_notice($route) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[aidunite_schedule] deprecated REST route used: ' . $route . ' — migrate to *-schedule-v2');
        }
    }
}
/*--------------------------------------------------------------
  No.28 練習・スケジュール登録_REST API：投稿・編集・削除処理（matching条件含む）
  ※ カレンダー表示用の取得系RESTは functions/common/common-functions.php 側に統合済み
---------------------------------------------------------------*/
add_action('rest_api_init', function () {
  require_once get_template_directory() . '/functions/common/auth-middleware.php';

  // @deprecated 2026-06-02 正ルートは POST /aidunite/v1/register-schedule-v2（persist 経由・互換維持）
  register_rest_route('aidunite/v1', '/register-schedules', array(
    'methods' => 'POST',
    'callback' => 'register_schedules_callback',
    'permission_callback' => function () {
      require_once get_template_directory() . '/functions/common/auth-middleware.php';
      $auth_result = AidUniteAuthMiddleware::require_auth(false);
      return $auth_result->is_valid();
    }
  ));

  // @deprecated 2026-05-03 正ルートは POST /aidunite/v1/update-schedule-v2（互換のため維持）
  register_rest_route('aidunite/v1', '/update-schedule', array(
    'methods' => 'POST',
    'callback' => 'aidunite_update_schedule',
    'permission_callback' => function () {
      require_once get_template_directory() . '/functions/common/auth-middleware.php';
      $auth_result = AidUniteAuthMiddleware::require_auth(false);
      return $auth_result->is_valid();
    }
  ));

  // @deprecated 2026-05-03 正ルートは POST /aidunite/v1/delete-schedule-v2（互換のため維持）
  register_rest_route('aidunite/v1', '/delete-schedule', array(
    'methods' => 'POST',
    'callback' => 'aidunite_delete_schedule',
    'permission_callback' => function () {
      require_once get_template_directory() . '/functions/common/auth-middleware.php';
      $auth_result = AidUniteAuthMiddleware::require_auth(false);
      return $auth_result->is_valid();
    }
  ));

  register_rest_route('aidunite/v1', '/schedule-dependencies/(?P<schedule_id>\\d+)', array(
    'methods' => 'GET',
    'callback' => 'aidunite_rest_get_schedule_dependencies',
    'permission_callback' => function () {
      require_once get_template_directory() . '/functions/common/auth-middleware.php';
      $auth_result = AidUniteAuthMiddleware::require_auth(false);
      return $auth_result->is_valid();
    }
  ));

  // NOTE:
  // /get-user-schedules と /get-schedules-by-date は
  // functions/common/common-functions.php 側の統一実装を利用する。
  // ここでの重複 register はルーティング競合の原因になるため登録しない。
});

function register_schedules_callback($request) {
  aidunite_schedule_legacy_rest_deprecation_notice('register-schedules');
  if (!function_exists('aidunite_schedule_legacy_register_one')) {
    require_once get_template_directory() . '/functions/schedule/schedule-persist.php';
  }

  if (!aidunite_schedule_rest_verify_register_nonce($request)) {
    return new WP_Error('invalid_nonce', 'セキュリティトークンが無効です', array('status' => 403));
  }

  $current_user_id = get_current_user_id();
  if (!$current_user_id) {
    return new WP_Error('unauthorized', 'ログインが必要です', array('status' => 401));
  }

  $team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
    ? (int) aidunite_resolve_user_team_id_for_schedule_ops($current_user_id)
    : (int) get_user_meta($current_user_id, 'team_id', true);

  if (!$team_id) {
    return new WP_Error('no_team', 'チームに所属していません', array('status' => 403));
  }

  $aidunite_role = get_user_meta($current_user_id, 'aidunite_role', true);
  if ($aidunite_role !== 'team_leader' && $aidunite_role !== 'administrator') {
    return new WP_Error('forbidden', 'その操作を実行する権限がありません。チーム代表者または管理者のみがスケジュールを登録できます。', array('status' => 403));
  }

  $params = $request->get_json_params();
  if (!is_array($params)) {
    return new WP_Error('invalid_params', '無効なパラメータ形式です', array('status' => 400));
  }

  $results = [];

  if (isset($params['schedule_type'])) {
    $results[] = aidunite_schedule_legacy_register_one($params, $team_id, $current_user_id);
    return rest_ensure_response($results);
  }

  foreach ($params as $item) {
    if (!is_array($item)) {
      continue;
    }

    $required_fields = ['date', 'start_time', 'end_time', 'type'];
    foreach ($required_fields as $field) {
      if (!isset($item[$field]) || (string) $item[$field] === '') {
        $results[] = ['success' => false, 'error' => "必須フィールド '{$field}' が不足しています"];
        continue 2;
      }
    }

    $results[] = aidunite_schedule_legacy_register_one($item, $team_id, $current_user_id);
  }

  return rest_ensure_response($results);
}

function aidunite_update_schedule($request) {
  aidunite_schedule_legacy_rest_deprecation_notice('update-schedule');
  // nonce検証（ヘッダーまたはボディから取得）
  $nonce = $request->get_header('X-WP-Nonce');
  if (!$nonce) {
    $params = $request->get_json_params();
    $nonce = isset($params['nonce']) ? $params['nonce'] : '';
  }
  if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
    return new WP_Error('invalid_nonce', 'セキュリティトークンが無効です', array('status' => 403));
  }

  // ユーザーがログインしているかチェック
  $current_user_id = get_current_user_id();
  if (!$current_user_id) {
    return new WP_Error('unauthorized', 'ログインが必要です', array('status' => 401));
  }

  $params = $request->get_json_params();

  // $paramsが配列でない場合はエラーを返す
  if (!is_array($params)) {
    return new WP_Error('invalid_params', '無効なパラメータ形式です', array('status' => 400));
  }

  // 必須フィールドの存在チェック
  if (!isset($params['post_id'])) {
    return new WP_Error('invalid_params', '必須フィールド post_id が不足しています', array('status' => 400));
  }

  $post_id = intval($params['post_id']);
  if (!$post_id || get_post_type($post_id) !== 'schedule') {
    return new WP_Error('invalid_id', '対象のスケジュールが見つかりません', array('status' => 400));
  }

  // 統一認証・権限チェック（チーム代表者のみ許可）
  require_once get_template_directory() . '/functions/common/auth-middleware.php';
  $schedule_team_id = get_post_meta($post_id, 'team_id', true);
  $auth_result = AidUniteAuthMiddleware::require_team_leader($schedule_team_id, false);
  if (!$auth_result->is_valid()) {
    // スケジュールの所有者かチェック（フォールバック）
    $schedule_author_id = get_post_field('post_author', $post_id);
    if ($schedule_author_id != $current_user_id) {
      return new WP_Error('forbidden', $auth_result->error ?: 'このスケジュールを編集する権限がありません', array('status' => 403));
    }
  }

  // match_request 依存: 申請中・承認/成立 があると敏感な項目の変更は不可（サーバーガード）
  if (function_exists('aidunite_can_update_schedule')) {
    $edit_gate = aidunite_can_update_schedule($post_id, $params);
    if (is_wp_error($edit_gate)) {
      return $edit_gate;
    }
  }

  if (!function_exists('aidunite_schedule_merge_legacy_params_for_persist')) {
    require_once get_stylesheet_directory() . '/functions/schedule/schedule-persist.php';
  }

  $persist_data = aidunite_schedule_merge_legacy_params_for_persist($params, $post_id);
  $update_result = aidunite_schedule_update_published_post($post_id, $persist_data);
  if (is_wp_error($update_result)) {
    return $update_result;
  }

  if (isset($params['memo'])) {
    $memo_value = sanitize_textarea_field((string) $params['memo']);
    update_post_meta($post_id, 'schedule_memo', $memo_value);
  }

  return rest_ensure_response(['success' => true]);
}

function aidunite_delete_schedule($request) {
  aidunite_schedule_legacy_rest_deprecation_notice('delete-schedule');
  $current_user_id = get_current_user_id();
  if (!$current_user_id) {
    return new WP_Error('unauthorized', 'ログインが必要です', array('status' => 401));
  }

  // ノンス: REST 標準（X-WP-Nonce + wp_rest）を優先。従来の aidunite_schedule_nonce も併用
  $header_nonce = $request->get_header('X-WP-Nonce');
  $param_nonce  = (string) $request->get_param('nonce');
  $nonce_ok = ($header_nonce && wp_verify_nonce($header_nonce, 'wp_rest'))
    || ($param_nonce && wp_verify_nonce($param_nonce, 'wp_rest'))
    || ($param_nonce && wp_verify_nonce($param_nonce, 'aidunite_schedule_nonce'));
  if (!$nonce_ok) {
    return new WP_Error('invalid_nonce', 'セキュリティトークンが無効です', array('status' => 403));
  }

  $params = $request->get_json_params();
  if (!is_array($params) || $params === []) {
    $params = $request->get_params();
  }
  if (!is_array($params)) {
    $params = [];
  }

  $post_id = 0;
  if (!empty($params['post_id'])) {
    $post_id = (int) $params['post_id'];
  } elseif (!empty($params['schedule_id'])) {
    $post_id = (int) $params['schedule_id'];
  } else {
    $post_id = (int) $request->get_param('post_id');
    if ($post_id <= 0) {
      $post_id = (int) $request->get_param('schedule_id');
    }
  }

  if (!$post_id || get_post_type($post_id) !== 'schedule') {
    return new WP_Error('invalid_id', '対象のスケジュールが見つかりません', array('status' => 400));
  }

  if (user_can($current_user_id, 'delete_post', $post_id)) {
    // 権限ありでも match 依存でブロック可能（承認/成立/申請中の即時物理削除はしない）
  } else {
  // フォールバック: 自チームの代表者は同一 team_id の schedule を削除可（旧挙動）
  $aidunite_role    = get_user_meta($current_user_id, 'aidunite_role', true);
  $is_leader_or_admin = in_array($aidunite_role, ['team_leader', 'administrator'], true) || current_user_can('administrator');
  if (!$is_leader_or_admin) {
    return new WP_Error('forbidden', 'その操作を実行する権限がありません。', array('status' => 403));
  }
  $schedule_author_id = (int) get_post_field('post_author', $post_id);
  $schedule_team_id  = (int) get_post_meta($post_id, 'team_id', true);
  $same_team = $schedule_team_id > 0
    && function_exists('aidunite_user_has_managed_team_access')
    && aidunite_user_has_managed_team_access($current_user_id, $schedule_team_id);
  if (!$same_team && $schedule_team_id > 0 && !function_exists('aidunite_user_has_managed_team_access')) {
    $user_team_id = (int) get_user_meta($current_user_id, 'team_id', true);
    $same_team = $user_team_id > 0 && $user_team_id === $schedule_team_id;
  }
  if ((int) $schedule_author_id !== (int) $current_user_id && !$same_team) {
    return new WP_Error('forbidden', 'このスケジュールを削除する権限がありません', array('status' => 403));
  }
  }

  if (function_exists('aidunite_can_delete_schedule') && function_exists('aidunite_perform_safe_schedule_deletion')) {
    $block = aidunite_can_delete_schedule($post_id);
    if (is_wp_error($block)) {
      return $block;
    }
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('[aidunite_schedule] REST delete 実行 user_id=' . (int) $current_user_id . ' schedule_id=' . (int) $post_id);
    }
    $result = aidunite_perform_safe_schedule_deletion($post_id);
    if (is_wp_error($result)) {
      return $result;
    }
    return rest_ensure_response(
      array(
        'success' => true,
        'data'     => is_array($result) ? $result : array(),
        'message'  => 'スケジュールを削除しました。',
      )
    );
  }

  return new WP_Error('not_available', 'スケジュール依存ガードが読み込めません。', array('status' => 500));
}

/**
 * 依存状況の参照用 GET（確認モーダル・事後拡張用。書き込みなし）
 */
function aidunite_rest_get_schedule_dependencies( $request ) {
  $schedule_id = (int) $request->get_param( 'schedule_id' );
  if ($schedule_id <= 0 || get_post_type($schedule_id) !== 'schedule') {
    return new WP_Error('invalid_id', '対象のスケジュールが見つかりません', array('status' => 400));
  }
  $uid = get_current_user_id();
  if (!$uid) {
    return new WP_Error('unauthorized', 'ログインが必要です', array('status' => 401));
  }
  if (! user_can( $uid, 'delete_post', $schedule_id ) && ! current_user_can( 'administrator' ) && ! current_user_can( 'manage_options' ) ) {
    $schedule_author_id = (int) get_post_field( 'post_author', $schedule_id );
    $schedule_team_id   = (int) get_post_meta( $schedule_id, 'team_id', true );
    $same_team = $schedule_team_id > 0
      && function_exists( 'aidunite_user_has_managed_team_access' )
      && aidunite_user_has_managed_team_access( $uid, $schedule_team_id );
    if ( ! $same_team && $schedule_team_id > 0 && ! function_exists( 'aidunite_user_has_managed_team_access' ) ) {
      $user_team_id = (int) get_user_meta( $uid, 'team_id', true );
      $same_team = $user_team_id > 0 && $user_team_id === $schedule_team_id;
    }
    if ( (int) $schedule_author_id !== (int) $uid
      && ! $same_team ) {
      return new WP_Error( 'forbidden', '閲覧権限がありません。', array( 'status' => 403 ) );
    }
  }
  if ( ! function_exists( 'aidunite_get_schedule_dependencies' ) ) {
    return new WP_Error( 'not_available', '依存関数が未読み込みです。', array( 'status' => 500 ) );
  }
  $dep = aidunite_get_schedule_dependencies( $schedule_id );
  if ( function_exists( 'aidunite_get_schedule_delete_gate_array' ) ) {
    $gate = aidunite_get_schedule_delete_gate_array( $schedule_id );
  } else {
    $gate = array( 'allowed' => true );
  }
  if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( '[aidunite_schedule] GET schedule-dependencies schedule_id=' . $schedule_id . ' by user=' . (int) $uid );
  }
  return rest_ensure_response( array( 'success' => true, 'delete_gate' => $gate, 'dependencies' => $dep ) );
}

