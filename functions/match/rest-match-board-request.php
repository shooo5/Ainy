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

    // 時間設定の有無チェック
    $target_start = get_post_meta($target_schedule_id, 'schedule_start_time', true);
    $target_end = get_post_meta($target_schedule_id, 'schedule_end_time', true);
    $my_start = get_post_meta($my_schedule_id, 'schedule_start_time', true);
    $my_end = get_post_meta($my_schedule_id, 'schedule_end_time', true);

    if (empty($target_start) || empty($target_end) || empty($my_start) || empty($my_end)) {
      wp_die('❌ スケジュールの時間設定が不完全です。開始時間と終了時間を設定してください。');
    }

    // 申請先スケジュールのチームIDを取得
    $target_schedule_team_id = get_post_meta($target_schedule_id, 'team_id', true);

    // 自分自身への申請を防ぐ
    if ($my_team_id == $target_schedule_team_id) {
      wp_die('❌ 自分自身のスケジュールには申請できません。');
    }

    $target_tid = (int) $target_schedule_team_id;

    $meta_input = [
      'from_team_id'       => $my_team_id,
      'request_team_id'    => $my_team_id, // 後方互換性のため残す
      'to_schedule_id'     => $target_schedule_id,
      'my_schedule_id'     => $my_schedule_id,
      'other_team_id'      => $target_tid,
      'to_team_id'         => $target_tid,
      'status'             => 'pending',
      'request_status'     => 'pending',
    ];

    // 投稿
    $new_request_id = wp_insert_post([
      'post_type'    => 'match_request',
      'post_status'  => 'publish',
      'post_author'  => $current_user_id,
      'post_title'   => '【マッチ申請】' . date('Y-m-d H:i:s'),
      'meta_input'   => $meta_input  // meta_inputも追加
    ]);

    if ($new_request_id) {
      // メタデータを明示的に保存
      foreach ($meta_input as $key => $value) {
        update_post_meta($new_request_id, $key, $value);
      }

      if (function_exists('aidunite_match_flow_debug_log')) {
        $saved_from_team_id = get_post_meta($new_request_id, 'from_team_id', true);
        $saved_to_schedule_id = get_post_meta($new_request_id, 'to_schedule_id', true);
        aidunite_match_flow_debug_log('board_post_match_apply', [
          'request_id'       => (int) $new_request_id,
          'target_schedule_id' => (int) $target_schedule_id,
          'my_schedule_id'     => (int) $my_schedule_id,
          'from_team_id'       => $saved_from_team_id,
          'to_schedule_id'     => $saved_to_schedule_id,
        ]);
      }

      if (function_exists('aidunite_match_request_ensure_link_team_meta')) {
        aidunite_match_request_ensure_link_team_meta((int) $new_request_id);
      }

      // 🟢 ステータス更新処理（掲示板を "pending" に）
      update_board_status_on_apply($target_schedule_id);

      // ✅ ステータス同期処理を追加
      sync_board_status_with_request($new_request_id);

      // ✅ 申請受けたチームに通知を送信
      send_match_request_notification($new_request_id, $target_schedule_id, $my_team_id);

      // ✅ 申請受信チームへの通知送信
      if ($target_schedule_team_id) {
        send_match_request_received_notification($new_request_id, $target_schedule_team_id, $my_team_id);
      }

      // 成功リダイレクト
      $redirect_url = add_query_arg('match_applied', '1', wp_get_referer());
      wp_redirect($redirect_url);
      exit;
    } else {
      wp_die('❌ 申請の送信に失敗しました。');
    }
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
    $from_team_id = get_post_meta($request->ID, 'from_team_id', true);
    $from_team_name = $from_team_id ? get_the_title($from_team_id) : '不明';

    $formatted_requests[] = [
      'ID' => $request->ID,
      'from_team_id' => $from_team_id,
      'from_team_name' => $from_team_name,
      'to_schedule_id' => get_post_meta($request->ID, 'to_schedule_id', true),
      'my_schedule_id' => get_post_meta($request->ID, 'my_schedule_id', true),
      'status' => get_post_meta($request->ID, 'status', true),
      'selected_start_time' => get_post_meta($request->ID, 'selected_start_time', true),
      'selected_end_time' => get_post_meta($request->ID, 'selected_end_time', true),
      'selected_place' => get_post_meta($request->ID, 'selected_place', true),
      'selected_gender' => get_post_meta($request->ID, 'selected_gender', true),
      'post_date' => $request->post_date
    ];
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

  // メタデータを削除
  delete_post_meta($request_id, 'from_team_id');
  delete_post_meta($request_id, 'request_team_id');
  delete_post_meta($request_id, 'to_schedule_id');
  delete_post_meta($request_id, 'my_schedule_id');
  delete_post_meta($request_id, 'status');
  delete_post_meta($request_id, 'request_status');
  delete_post_meta($request_id, 'selected_start_time');
  delete_post_meta($request_id, 'selected_end_time');
  delete_post_meta($request_id, 'selected_place');
  delete_post_meta($request_id, 'selected_gender');

  // 投稿を削除
  $result = wp_delete_post($request_id, true);

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
  $match_requests = get_posts([
    'post_type' => 'match_request',
    'post_status' => 'any',
    'posts_per_page' => -1
  ]);

  $deleted_count = 0;

  foreach ($match_requests as $request) {
    // メタデータを削除
    delete_post_meta($request->ID, 'from_team_id');
    delete_post_meta($request->ID, 'request_team_id');
    delete_post_meta($request->ID, 'to_schedule_id');
    delete_post_meta($request->ID, 'my_schedule_id');
    delete_post_meta($request->ID, 'status');
    delete_post_meta($request->ID, 'request_status');
    delete_post_meta($request->ID, 'selected_start_time');
    delete_post_meta($request->ID, 'selected_end_time');
    delete_post_meta($request->ID, 'selected_place');
    delete_post_meta($request->ID, 'selected_gender');

    // 投稿を削除
    if (wp_delete_post($request->ID, true)) {
      $deleted_count++;
    }
  }

  error_log("✅ 全マッチリクエスト {$deleted_count}件を削除しました");

  return new WP_REST_Response([
    'success' => true,
    'message' => "全マッチリクエスト {$deleted_count}件を削除しました",
    'deleted_count' => $deleted_count
  ], 200);
}

// 新規申請は REST POST /aidunite/v1/match-request（aidunite_save_match_application_core）のみ
