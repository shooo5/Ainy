<?php
/*====================================================================
  マッチ掲示板・申請＆ステータス制御：rest-match-board-request.php
====================================================================*/

/*--------------------------------------------------------------
① マッチ申請時：掲示板ステータスを "pending" に → 自動マッチ = publish（match-request-functions.phpに統合済み）
--------------------------------------------------------------*/


/*--------------------------------------------------------------
② マッチ承認時：掲示板ステータスを "accepted" に → 自動マッチ = accepted（match-request-functions.phpに統合済み）
--------------------------------------------------------------*/

/*--------------------------------------------------------------
③④⑤ 拒否・キャンセル・再申請時：掲示板ステータスを "open" に → 自動マッチ = 各該当ステータス（match-request-functions.phpに統合済み）
--------------------------------------------------------------*/

/*--------------------------------------------------------------
⑥ マッチ申請 POST受信処理
--------------------------------------------------------------*/
add_action('init', function () {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_apply_nonce'])) {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
      wp_die('❌ ' . esc_html($auth_result->error ?: '認証が必要です'));
      return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('match_apply_nonce', 'match_apply_action');
    if (is_wp_error($nonce_result)) {
      wp_die('❌ ' . esc_html($nonce_result->get_error_message()));
      return;
    }

    $current_user_id = $auth_result->user_id;
    $my_team_id = $auth_result->team_id;
    $target_schedule_id = AidUniteAuthMiddleware::sanitize($_POST['target_schedule_id'] ?? 0, 'int');
    $my_schedule_id = AidUniteAuthMiddleware::sanitize($_POST['my_schedule_id'] ?? 0, 'int');

    if (!$target_schedule_id || !$my_schedule_id) {
      wp_die('❌ 必要な情報が不足しています。');
      return;
    }

    if (!function_exists('aidunite_match_request_create_from_board_form_post')) {
      wp_die('❌ 申請処理が利用できません。');
    }

    $result = aidunite_match_request_create_from_board_form_post(
      (int) $current_user_id,
      (int) $my_team_id,
      (int) $target_schedule_id,
      (int) $my_schedule_id
    );

    if (!empty($result['success']) && !empty($result['request_id'])) {
      if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('board_post_match_apply', [
          'request_id' => (int) $result['request_id'],
          'target_schedule_id' => (int) $target_schedule_id,
          'my_schedule_id' => (int) $my_schedule_id,
          'via' => 'persist',
        ]);
      }
      $redirect_url = add_query_arg('match_applied', '1', wp_get_referer());
      wp_redirect($redirect_url);
      exit;
    }

    $code = (string) ($result['code'] ?? '');
    $msg_map = [
      'cannot_apply_to_own_team' => '❌ 自分自身のスケジュールには申請できません。',
      'application_already_exists' => '❌ このマッチはすでに申請済みです。',
      'time_data_required' => '❌ スケジュールの時間設定が不完全です。',
      'schedule_not_found' => '❌ スケジュールが見つかりません。',
    ];
    wp_die($msg_map[$code] ?? ('❌ 申請の送信に失敗しました。' . (!empty($result['message']) ? ' ' . esc_html((string) $result['message']) : '')));
  }
});

/*--------------------------------------------------------------
⑦ match_request に連動して掲示板ステータスを同期（match-request-functions.phpに統合済み）
--------------------------------------------------------------*/

/*--------------------------------------------------------------
⑧ 申請受けたチームに通知を送信（match-notifications.phpに統合済み）
--------------------------------------------------------------*/

/*--------------------------------------------------------------
⑨ チーム代表者を取得（match-request-functions.phpに統合済み）
--------------------------------------------------------------*/

/*--------------------------------------------------------------
⑩ マッチ申請：admin-ajax は廃止（正ルート POST /aidunite/v1/match-request）
--------------------------------------------------------------*/


// マッチリクエスト一覧取得API
add_action('rest_api_init', function() {
  register_rest_route('aidunite/v1', '/match-requests', [
    'methods' => 'GET',
    'callback' => 'get_match_requests_list',
    'permission_callback' => function() {
      return current_user_can('administrator');
    }
  ]);

  register_rest_route('aidunite/v1', '/delete-match-request', [
    'methods' => 'POST',
    'callback' => 'delete_single_match_request',
    'permission_callback' => function() {
      return current_user_can('administrator');
    }
  ]);

  register_rest_route('aidunite/v1', '/delete-all-match-requests', [
    'methods' => 'POST',
    'callback' => 'delete_all_match_requests',
    'permission_callback' => function() {
      return current_user_can('administrator');
    }
  ]);
});

function get_match_requests_list($request) {
  $match_requests = get_posts([
    'post_type' => 'match_request',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC'
  ]);

  $formatted_requests = [];

  foreach ($match_requests as $request) {
    $row = function_exists('aidunite_match_request_format_rest_row')
      ? aidunite_match_request_format_rest_row((int) $request->ID)
      : [];
    if ($row === []) {
      continue;
    }
    $row['post_date'] = $request->post_date;
    $formatted_requests[] = $row;
  }

  return new WP_REST_Response([
    'success' => true,
    'match_requests' => $formatted_requests
  ], 200);
}

function delete_single_match_request($request) {
  $params = $request->get_json_params();
  $request_id = intval($params['request_id'] ?? 0);

  if (!$request_id) {
    return new WP_REST_Response([
      'success' => false,
      'message' => '申請IDが指定されていません'
    ], 400);
  }

  $result = function_exists('aidunite_match_request_persist_admin_delete')
    ? aidunite_match_request_persist_admin_delete($request_id)
    : (bool) wp_delete_post($request_id, true);

  if ($result) {
    error_log("✅ 申請ID {$request_id}を削除しました");
    return new WP_REST_Response([
      'success' => true,
      'message' => "申請ID {$request_id}を削除しました"
    ], 200);
  } else {
    error_log("❌ 申請ID {$request_id}の削除に失敗しました");
    return new WP_REST_Response([
      'success' => false,
      'message' => "申請ID {$request_id}の削除に失敗しました"
    ], 500);
  }
}

function delete_all_match_requests($request) {
  $deleted_count = function_exists('aidunite_match_request_persist_admin_delete_all')
    ? aidunite_match_request_persist_admin_delete_all()
    : 0;

  error_log("✅ 全マッチリクエスト {$deleted_count}件を削除しました");

  return new WP_REST_Response([
    'success' => true,
    'message' => "全マッチリクエスト {$deleted_count}件を削除しました",
    'deleted_count' => $deleted_count
  ], 200);
}

// 新規申請は REST POST /aidunite/v1/match-request（aidunite_save_match_application_core）のみ
