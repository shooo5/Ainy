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
    $post_like['selected_start_time'] = $post_like['selected_start_time'] ?: (string) get_post_meta($post_like['my_schedule_id'], 'schedule_start_time', true);
    $post_like['selected_end_time'] = $post_like['selected_end_time'] ?: (string) get_post_meta($post_like['my_schedule_id'], 'schedule_end_time', true);
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
--------------------------------------------------------------*/
function aidunite_update_match_status($request) {
  $params = $request->get_json_params();
  $match_id = isset($params['match_id']) ? intval($params['match_id']) : 0;
  $action   = isset($params['action']) ? sanitize_text_field($params['action']) : '';
  $user_id  = get_current_user_id();

  $post = get_post($match_id);
  if (!$post || $post->post_type !== 'match_request') {
    return new WP_Error('invalid_match', '無効なマッチ申請です', ['status' => 400]);
  }

  $from_team = get_post_meta($post->ID, 'from_team_id', true);
  $to_schedule_id = (int) get_post_meta($post->ID, 'to_schedule_id', true);
  $author_id = $to_schedule_id ? (int) get_post_field('post_author', $to_schedule_id) : 0;
  $to_team = '';
  if ($to_schedule_id > 0 && function_exists('aidunite_resolve_schedule_owner_team_id')) {
    $resolved_to = (int) aidunite_resolve_schedule_owner_team_id($to_schedule_id);
    if ($resolved_to > 0) {
      $to_team = (string) $resolved_to;
    }
  }
  if ($to_team === '' || $to_team === null) {
    $to_team = $author_id ? (string) (int) (function_exists('aidunite_get_current_team_id')
      ? aidunite_get_current_team_id($author_id)
      : get_user_meta($author_id, 'team_id', true)) : '';
  }
  if ($to_team === '' || $to_team === null) {
    $to_team = get_post_meta($post->ID, 'to_team_id', true);
  }
  if ($to_team === '' || $to_team === null) {
    $to_team = get_post_meta($post->ID, 'other_team_id', true);
  }

  $from_team_int = (int) $from_team;
  $to_team_int = (int) $to_team;
  $has_from = $from_team_int > 0 && aidunite_user_has_managed_team_access($user_id, $from_team_int);
  $has_to = $to_team_int > 0 && aidunite_user_has_managed_team_access($user_id, $to_team_int);

  if (!$has_from && !$has_to) {
    return new WP_Error('no_permission', 'このマッチ申請に対する操作権限がありません。', ['status' => 403]);
  }

  // 承認・拒否は申請先チーム（to_team）の代表者のみ可能
  if (in_array($action, ['approve', 'reject'], true) && !$has_to) {
    return new WP_Error('no_permission', '承認・拒否は申請先チームの代表者のみが行えます。', ['status' => 403]);
  }

  $new_status = '';
  $board_status = '';
  $to_schedule_id = (int) get_post_meta($match_id, 'to_schedule_id', true);
  $board_id = wp_get_post_parent_id($to_schedule_id);

  if ($action === 'approve') {
    if (function_exists('aidunite_match_request_guard_before_recipient_accept')) {
      $guard = aidunite_match_request_guard_before_recipient_accept($match_id);
      if ($guard !== null) {
        return new WP_REST_Response([
          'success' => false,
          'message' => $guard['message'],
          'code'    => $guard['code'],
        ], (int) $guard['http_status']);
      }
    }
    aidunite_update_match_request_status_meta($match_id, 'accepted', (string) $post->post_status);
    $new_status = '承認済';
    $board_status = 'accepted';
  } elseif ($action === 'reject') {
    aidunite_update_match_request_status_meta($match_id, 'rejected', (string) $post->post_status);
    $new_status = '拒否済';
    $board_status = 'open';
  } elseif ($action === 'cancel') {
    $cancel_scope = function_exists('aidunite_parse_cancel_scope_from_request')
      ? aidunite_parse_cancel_scope_from_request()
      : 'pair';
    $match_game_cancel_rest = function_exists('aidunite_resolve_match_game_id_for_match_request')
      ? (int) aidunite_resolve_match_game_id_for_match_request((int) $match_id)
      : (int) get_post_meta($match_id, 'to_schedule_id', true);
    $dissolved_by_host   = false;
    $actor_team_cancel   = $has_from ? $from_team_int : ($has_to ? $to_team_int : 0);
    $is_anchor_host_rest = (
      $match_game_cancel_rest > 0
      && $match_game_cancel_rest !== 9999
      && $actor_team_cancel > 0
      && function_exists('aidunite_actor_is_anchor_host_for_match_request')
      && aidunite_actor_is_anchor_host_for_match_request((int) $match_id, $actor_team_cancel)
    );
    if (
      $is_anchor_host_rest
      && $cancel_scope === 'dissolve'
      && function_exists('aidunite_host_dissolve_game_match_requests')
    ) {
      $dissolved_by_host = aidunite_host_dissolve_game_match_requests($match_id, $actor_team_cancel);
    }
    if (!$dissolved_by_host) {
      $was_est_rest = (get_post_meta($match_id, 'established_at', true) !== '');
      $other_est_rest = ($match_game_cancel_rest > 0 && function_exists('aidunite_count_game_established_match_requests'))
        ? aidunite_count_game_established_match_requests($match_game_cancel_rest, $match_id)
        : 0;
      $skip_rb_rest = false;
      if ($was_est_rest && $other_est_rest > 0 && $cancel_scope !== 'dissolve') {
        if ((int) $actor_team_cancel === (int) $from_team_int || $is_anchor_host_rest) {
          $skip_rb_rest = true;
        }
      }

      aidunite_update_match_request_status_meta($match_id, 'canceled', (string) $post->post_status);
      if ($actor_team_cancel > 0) {
        update_post_meta($match_id, 'canceled_by_team_id', $actor_team_cancel);
      }
      // 非成立キャンセル（established_at なし）ではロールバックしない
      if ($was_est_rest && !$skip_rb_rest && function_exists('aidunite_rollback_established_from_schedules')) {
        aidunite_rollback_established_from_schedules($match_id);
      } elseif ($was_est_rest && $skip_rb_rest && function_exists('aidunite_restore_established_slot_only')) {
        // 他 established 残存時は full rollback を避け、当該申請分の枠のみ復元。
        aidunite_restore_established_slot_only($match_id);
      }
      // 同一募集に他 established が残る場合は共有チャットを完了化しない。
      if ($skip_rb_rest && function_exists('aidunite_handle_chat_on_match_request_canceled')) {
        aidunite_handle_chat_on_match_request_canceled($match_id);
      } elseif (!$skip_rb_rest && function_exists('aidunite_mark_match_chat_completed')) {
        aidunite_mark_match_chat_completed($match_id, 'canceled');
      }
    }
    $new_status   = 'キャンセル済';
    $board_status = 'open';
  } elseif ($action === 'submit') {
    // フロントの2段階目：作成直後の「送信確定」。申請者のみ。
    if (!$has_from) {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'この操作は申請者のみが行えます。',
      ], 403);
    }
    $current = get_post_meta($match_id, 'status', true);
    $cur_norm = function_exists('aidunite_normalize_match_request_status')
      ? aidunite_normalize_match_request_status((string) $current, '')
      : (string) $current;
    if (!in_array($cur_norm, ['accepted', 'established'], true)) {
      if (function_exists('aidunite_reset_match_request_lifecycle_meta_for_reapply')) {
        aidunite_reset_match_request_lifecycle_meta_for_reapply($match_id);
      }
      aidunite_update_match_request_status_meta($match_id, 'pending', (string) $post->post_status);
    }
    $new_status = '申請中';
    $board_status = 'pending';
  } elseif ($action === 'apply') {
    // 再申請も通常申請も同じ処理に統一
    if (function_exists('aidunite_reset_match_request_lifecycle_meta_for_reapply')) {
      aidunite_reset_match_request_lifecycle_meta_for_reapply($match_id);
    }
    aidunite_update_match_request_status_meta($match_id, 'pending', (string) $post->post_status);
    $new_status = '申請中';
    $board_status = 'pending';
  } else {
    return new WP_REST_Response([
      'success' => false,
      'message' => '❌ 無効なアクションです。'
    ], 400);
  }

  // 承認時のチャット作成処理
  if ($action === 'approve') {
    $from_team_id = get_post_meta($match_id, 'from_team_id', true);
    // to_team_idは直接取得できないため、to_schedule_idから取得
    $to_schedule_id_for_chat = get_post_meta($match_id, 'to_schedule_id', true);
    $to_team_id = null;
    if ($to_schedule_id_for_chat) {
      if (function_exists('aidunite_resolve_schedule_owner_team_id')) {
        $to_team_id = aidunite_resolve_schedule_owner_team_id((int) $to_schedule_id_for_chat);
      } else {
        $to_schedule_author_id = (int) get_post_field('post_author', $to_schedule_id_for_chat);
        $to_team_id = function_exists('aidunite_get_current_team_id')
            ? aidunite_get_current_team_id($to_schedule_author_id)
            : get_user_meta($to_schedule_author_id, 'team_id', true);
      }
    }
    // to_team_idが取得できない場合は、メタデータから直接取得を試みる（後方互換性）
    if (!$to_team_id) {
      $to_team_id = get_post_meta($match_id, 'to_team_id', true);
    }
    $my_schedule_id = get_post_meta($match_id, 'my_schedule_id', true);

    // スケジュールベースのチャットルームを作成または拡張
    $chat_room_id = null;
    if (function_exists('aidunite_create_or_extend_match_chat') && $from_team_id && $to_team_id) {
      $target_schedule_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
          ? (int) aidunite_resolve_match_game_id_for_match_request((int) $match_id)
          : 0;
      if ($target_schedule_id <= 0) {
          $target_schedule_id = $to_schedule_id_for_chat ?: $my_schedule_id;
      }

      // スケジュールから日付を取得
      $match_date = null;
      if ($target_schedule_id) {
        $match_date = get_post_meta($target_schedule_id, 'schedule_date', true);
      }
      if (!$match_date && $my_schedule_id) {
        $match_date = get_post_meta($my_schedule_id, 'schedule_date', true);
      }

      // スケジュールベースのチャットルームを作成または拡張
      $chat_room_id = aidunite_create_or_extend_match_chat(
        $match_id,
        $from_team_id,
        $to_team_id,
        $target_schedule_id,
        $match_date
      );

      if (!is_wp_error($chat_room_id)) {
        $chat_room_id = (int) $chat_room_id;
        update_post_meta((int) $match_id, 'chat_room_id', $chat_room_id);
        if (!empty($target_schedule_id)) {
          update_post_meta((int) $target_schedule_id, 'active_match_chat_room_id', $chat_room_id);
        }
        // AidUniteErrorHandlerが存在する場合は使用、なければerror_log
        if (class_exists('AidUniteErrorHandler')) {
          AidUniteErrorHandler::info('マッチチャットルームを作成/拡張（REST API経由）', [
            'room_id' => $chat_room_id,
            'schedule_id' => $target_schedule_id,
            'match_request_id' => $match_id
          ]);
        } else {
          error_log("✅ マッチチャットルームを作成/拡張しました（REST API経由）: Room ID = " . $chat_room_id);
        }
      } else {
        // AidUniteErrorHandlerが存在する場合は使用、なければerror_log
        if (class_exists('AidUniteErrorHandler')) {
          AidUniteErrorHandler::error('マッチチャットルームの作成/拡張に失敗（REST API経由）', [
            'match_request_id' => $match_id,
            'schedule_id' => $target_schedule_id,
            'error' => $chat_room_id->get_error_message()
          ]);
        } else {
          error_log("❌ マッチチャットルームの作成/拡張に失敗（REST API経由）: " . $chat_room_id->get_error_message());
        }
      }

    }

    // 承認済み→試合確定のスケジュール反映は共通サービスに集約
    if (function_exists('aidunite_apply_established_to_schedules')) {
      aidunite_apply_established_to_schedules($match_id);
    }
    if (function_exists('aidunite_after_match_established')) {
      aidunite_after_match_established($match_id);
    }
    if (function_exists('aidunite_get_game_chat_room_for_match_request')) {
      $canonical_room = aidunite_get_game_chat_room_for_match_request((int) $match_id);
      if ($canonical_room && !empty($canonical_room->id)) {
        $canonical_id = (int) $canonical_room->id;
        $chat_room_id = $canonical_id;
        update_post_meta((int) $match_id, 'chat_room_id', $canonical_id);
        if (!empty($target_schedule_id)) {
          update_post_meta((int) $target_schedule_id, 'active_match_chat_room_id', $canonical_id);
        }
      }
    }
    $new_status = '試合確定';
  }

  // 募集ゲーム単位：participants 再構築・掲示板は MR 集合から同期（承認→確定は aidunite_after_match_established に集約済み）
  if ($action !== 'approve') {
    $game_sync_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
      ? (int) aidunite_resolve_match_game_id_for_match_request((int) $match_id)
      : (int) get_post_meta($match_id, 'to_schedule_id', true);
    if ($game_sync_id > 0) {
      if (function_exists('aidunite_rebuild_recruit_schedule_participants_from_game_match_requests')) {
        $rebuild_opts = ($action === 'cancel')
          ? ['sync_chat' => false]
          : [];
        aidunite_rebuild_recruit_schedule_participants_from_game_match_requests($game_sync_id, $rebuild_opts);
      }
      if (function_exists('aidunite_sync_match_board_status_from_game')) {
        aidunite_sync_match_board_status_from_game($game_sync_id);
      }
    }
  }

  if ($action === 'cancel' && empty($dissolved_by_host) && function_exists('aidunite_notify_match_request_canceled')) {
    $actor_team_id = isset($actor_team_cancel) ? (int) $actor_team_cancel : 0;
    if ($actor_team_id <= 0 && function_exists('aidunite_resolve_user_team_id_for_schedule_ops')) {
      $actor_team_id = (int) aidunite_resolve_user_team_id_for_schedule_ops($user_id, (int) get_post_meta($match_id, 'to_schedule_id', true));
    }
    aidunite_notify_match_request_canceled($match_id, $actor_team_id);
  }

  // 通知処理（既存）
  do_action('aidunite_notify_match_status_change', $match_id, $action, $user_id, $new_status, '');

  if (function_exists('aidunite_match_flow_debug_log')) {
    $chat_dbg = (isset($chat_room_id) && $chat_room_id && !is_wp_error($chat_room_id)) ? (int) $chat_room_id : 0;
    aidunite_match_flow_debug_log('rest_update_match_status', [
      'match_id'    => (int) $match_id,
      'action'      => $action,
      'new_status'  => $new_status,
      'board_id'    => (int) $board_id,
      'to_schedule' => (int) $to_schedule_id,
      'chat_room_id'=> $chat_dbg,
    ]);
  }

  $show_bot_chat_modal = (
    $action === 'approve'
    && function_exists('aidunite_onboarding_bot_should_show_chat_modal_after_approve')
    && aidunite_onboarding_bot_should_show_chat_modal_after_approve((int) $match_id, (int) $user_id)
  );

  // 承認成功時：チャットルームへ自動遷移できるよう redirect_url / chat_room_id を返す（オンボボットはモーダルのみ）
  if ($action === 'approve' && !$show_bot_chat_modal && isset($chat_room_id) && $chat_room_id && !is_wp_error($chat_room_id)) {
    return new WP_REST_Response([
      'success'       => true,
      'new_status'    => $new_status,
      'message'       => $new_status . ' に更新しました',
      'chat_room_id'  => (int) $chat_room_id,
      'redirect_url'  => home_url('/chat?room_id=' . (int) $chat_room_id),
      'fallback_url'  => home_url('/communication'),
    ], 200);
  }
  if ($action === 'approve') {
    $approve_payload = [
      'success'    => true,
      'new_status' => $new_status,
      'message'    => $new_status . ' に更新しました',
    ];
    if ($show_bot_chat_modal) {
      $approve_payload['show_onboarding_bot_chat_modal'] = true;
      if (function_exists('aidunite_onboarding_bot_get_chat_url_for_request')) {
        $bot_chat = aidunite_onboarding_bot_get_chat_url_for_request((int) $match_id);
        if ($bot_chat !== '') {
          $approve_payload['chat_url'] = $bot_chat;
        }
      }
    } else {
      $approve_payload['fallback_url'] = home_url('/communication');
    }
    return new WP_REST_Response($approve_payload, 200);
  }

  return new WP_REST_Response([
    'success'    => true,
    'new_status' => $new_status,
    'message'    => $new_status . ' に更新しました'
  ], 200);
}
