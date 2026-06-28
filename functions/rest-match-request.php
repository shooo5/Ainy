<?php
/*--------------------------------------------------------------
  No.51 練習試合マッチング関連 REST API：マッチ申請登録 & ステータス更新（統合）
--------------------------------------------------------------*/

add_action('rest_api_init', function () {
  // マッチ申請
  register_rest_route('aidunite/v1', '/match-request', [
    'methods'  => 'POST',
    'callback' => 'aidunite_handle_match_request',
    'permission_callback' => function ($request) {
      $result = AidUniteAuthMiddleware::rest_require($request, []);
      return !is_wp_error($result);
    }
  ]);

  // ステータス更新
  register_rest_route('aidunite/v1', '/update-match-status', [
    'methods' => 'POST',
    'callback' => 'aidunite_update_match_status',
    'permission_callback' => function ($request) {
      $result = AidUniteAuthMiddleware::rest_require($request, [
          'team_id' => null,
          'redirect' => false,
      ]);
      return !is_wp_error($result);
    }
  ]);
});


/*--------------------------------------------------------------
  No.52 マッチ申請登録処理
--------------------------------------------------------------*/
function aidunite_handle_match_request($request) {
  $current_user_id = get_current_user_id();
  if (!$current_user_id) {
    return new WP_REST_Response([
      'success' => false,
      'message' => 'ログインが必要です。',
    ], 401);
  }

  $params = $request->get_json_params();
  if (!is_array($params)) {
    $params = [];
  }

  $post_like = [
    'my_schedule_id' => (int) ($params['my_schedule_id'] ?? 0),
    'other_schedule_id' => (int) ($params['other_schedule_id'] ?? 0),
    'other_team_id' => (int) ($params['other_team_id'] ?? 0),
    'selected_start_time' => sanitize_text_field($params['selected_start_time'] ?? $params['preferred_start'] ?? ''),
    'selected_end_time' => sanitize_text_field($params['selected_end_time'] ?? $params['preferred_end'] ?? ''),
    'selected_place' => sanitize_text_field($params['selected_place'] ?? $params['preferred_place'] ?? ''),
    'selected_gender' => sanitize_text_field($params['selected_gender'] ?? $params['preferred_gender'] ?? ''),
  ];

  if ($post_like['my_schedule_id'] > 0 && (empty($post_like['selected_start_time']) || empty($post_like['selected_end_time']))) {
    $sch_bundle = function_exists('aidunite_schedule_get_display_bundle')
      ? aidunite_schedule_get_display_bundle((int) $post_like['my_schedule_id'])
      : [];
    $post_like['selected_start_time'] = $post_like['selected_start_time'] ?: (string) ($sch_bundle['start_time'] ?? '');
    $post_like['selected_end_time'] = $post_like['selected_end_time'] ?: (string) ($sch_bundle['end_time'] ?? '');
  }

  if (!function_exists('aidunite_save_match_application_core')) {
    return new WP_REST_Response(['success' => false, 'message' => 'サーバー設定エラー'], 500);
  }

  $result = aidunite_save_match_application_core($post_like, $current_user_id);

  if (empty($result['success'])) {
    $code = $result['code'] ?? '';
    $msg_map = [
      'team_not_found' => 'チーム情報が見つかりません。',
      'schedule_not_found' => 'スケジュールが見つかりません。',
      'time_data_required' => '時間データが不足しています。',
      'cannot_apply_to_own_team' => '自チームへの申請はできません。',
      'application_already_exists' => 'このマッチはすでに申請済みです。',
      'creation_failed' => 'マッチ申請の作成に失敗しました。',
      'schedule_creation_failed' => '仮スケジュールの作成に失敗しました。',
    ];
    $client_codes = [
      'team_not_found', 'schedule_not_found', 'time_data_required', 'cannot_apply_to_own_team',
      'application_already_exists', 'slots_full', 'venue_conflict', 'gender_conflict',
      'time_no_overlap', 'recruit_closed', 'date_mismatch', 'venue_unfixable_without_recruit_edit',
      'same_team', 'invalid_input', 'match_label_failed', 'apply_not_allowed',
    ];
    $message = !empty($result['message']) ? (string) $result['message'] : ($msg_map[$code] ?? '申請を処理できませんでした。');
    $status = in_array($code, $client_codes, true) ? 400 : 500;
    return new WP_REST_Response([
      'success' => false,
      'message' => $message,
      'code' => $code,
    ], $status);
  }

  $rid = (int) ($result['request_id'] ?? 0);
  $is_update = ($result['message'] ?? '') === 'application_updated';

  if (function_exists('aidunite_match_flow_debug_log')) {
    aidunite_match_flow_debug_log('rest_match_request_ok', [
      'request_id' => $rid,
      'mode'       => $is_update ? 'update' : 'create',
    ]);
  }

  return new WP_REST_Response([
    'success' => true,
    'message' => $is_update ? '✅ 申請を更新しました' : '✅ 申請が送信されました！',
    'match_request_id' => $rid,
    'request_id' => $rid,
  ], 200);
}



/*--------------------------------------------------------------
  No.53 ステータス更新処理（承認／拒否／キャンセル／再申請）
  本体: match-request-persist-submit.php
--------------------------------------------------------------*/
function aidunite_update_match_status($request) {
  $params = $request->get_json_params();
  if (!is_array($params)) {
    $params = [];
  }

  $match_id = (int) ($params['match_id'] ?? 0);
  $action = sanitize_text_field((string) ($params['action'] ?? ''));
  $user_id = get_current_user_id();

  if (!function_exists('aidunite_match_request_submit_update_status')) {
    return new WP_REST_Response(['success' => false, 'message' => 'サーバー設定エラー'], 500);
  }

  $result = aidunite_match_request_submit_update_status($match_id, $action, $user_id);

  if (empty($result['ok'])) {
    $error = is_array($result['error'] ?? null) ? $result['error'] : [];
    $http_status = (int) ($result['http_status'] ?? 400);
    if (($error['type'] ?? '') === 'wp_error') {
      return new WP_Error(
        (string) ($error['code'] ?? 'error'),
        (string) ($error['message'] ?? ''),
        ['status' => $http_status]
      );
    }
    $body = ['success' => false, 'message' => (string) ($error['message'] ?? '')];
    if (!empty($error['code'])) {
      $body['code'] = (string) $error['code'];
    }
    return new WP_REST_Response($body, $http_status);
  }

  return new WP_REST_Response(
    is_array($result['body'] ?? null) ? $result['body'] : [],
    (int) ($result['http_status'] ?? 200)
  );
}
