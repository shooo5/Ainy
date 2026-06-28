<?php
/**
 * REST API: 通知を既読にする（is_read = true）
 */
add_action('rest_api_init', function () {
  register_rest_route('aidunite/v1', '/mark-read', [
    'methods' => 'POST',
    'callback' => 'aidunite_mark_notification_as_read',
    'permission_callback' => function () {
      return is_user_logged_in(); // ログインユーザーのみ許可
    }
  ]);

  register_rest_route('aidunite/v1', '/notifications/delete', [
    'methods'             => 'POST',
    'callback'            => 'aidunite_delete_user_notifications',
    'permission_callback' => function () {
      return is_user_logged_in();
    },
  ]);
});

function aidunite_mark_notification_as_read($request) {
  $current_user_id = get_current_user_id();

  if (!$current_user_id) {
    return new WP_Error('unauthorized', 'ログインが必要です。', ['status' => 401]);
  }

  // idsパラメータ（複数通知）を優先的にチェック
  $notification_ids = $request->get_param('ids');

  // idsが配列でない場合、idパラメータ（単一通知）をチェック
  if (empty($notification_ids) || !is_array($notification_ids)) {
    $single_id = intval($request->get_param('id'));
    if ($single_id > 0) {
      $notification_ids = [$single_id];
    } else {
      return new WP_Error('invalid_request', '通知IDが指定されていません。', ['status' => 400]);
    }
  }

  $success_count = 0;
  $failed_ids = [];

  foreach ($notification_ids as $notification_id) {
    $notification_id = intval($notification_id);

    if ($notification_id <= 0) {
      $failed_ids[] = $notification_id;
      continue;
    }

    // 権限チェック: 通知の対象ユーザーが現在のユーザーと一致するか確認
    $target_user_id = function_exists('aidunite_notification_read_recipient_user_id')
        ? aidunite_notification_read_recipient_user_id($notification_id)
        : 0;

    if ($target_user_id <= 0 || $target_user_id !== $current_user_id) {
      $failed_ids[] = $notification_id;
      continue;
    }

    if (!function_exists('aidunite_notification_write_is_read')) {
      $failed_ids[] = $notification_id;
      continue;
    }

    $result = aidunite_notification_write_is_read($notification_id, true);

    if ($result !== false) {
      $success_count++;
    } else {
      $failed_ids[] = $notification_id;
    }
  }

  // すべて成功した場合
  if ($success_count > 0 && empty($failed_ids)) {
    return rest_ensure_response([
      'success' => true,
      'message' => $success_count === 1 ? '通知を既読にしました。' : "{$success_count}件の通知を既読にしました。"
    ]);
  }

  // 一部失敗した場合
  if ($success_count > 0 && !empty($failed_ids)) {
    return rest_ensure_response([
      'success' => true,
      'message' => "{$success_count}件の通知を既読にしました。",
      'warning' => count($failed_ids) . '件の通知の処理に失敗しました。',
      'failed_ids' => $failed_ids
    ]);
  }

  // すべて失敗した場合
  return new WP_Error('update_failed', '通知の既読処理に失敗しました。', [
    'status' => 400,
    'failed_ids' => $failed_ids
  ]);
}

/**
 * 通知をゴミ箱へ移動（一覧から除外）。post_author が現在ユーザーのみ。
 *
 * @param WP_REST_Request $request JSON: { "ids": [1,2,3] }
 */
function aidunite_delete_user_notifications($request) {
  $current_user_id = get_current_user_id();
  if (!$current_user_id) {
    return new WP_Error('unauthorized', 'ログインが必要です。', ['status' => 401]);
  }

  $params = $request->get_json_params();
  if (!is_array($params)) {
    $params = [];
  }
  $notification_ids = isset($params['ids']) ? $params['ids'] : $request->get_param('ids');
  if (empty($notification_ids) || !is_array($notification_ids)) {
    return new WP_Error('invalid_request', '通知IDが指定されていません。', ['status' => 400]);
  }

  $success_count = 0;
  $failed_ids     = [];

  foreach ($notification_ids as $notification_id) {
    $notification_id = (int) $notification_id;
    if ($notification_id <= 0) {
      $failed_ids[] = $notification_id;
      continue;
    }
    $post = get_post($notification_id);
    if (!$post || $post->post_type !== 'notification') {
      $failed_ids[] = $notification_id;
      continue;
    }
    if ((int) $post->post_author !== (int) $current_user_id) {
      $failed_ids[] = $notification_id;
      continue;
    }
    $ok = wp_trash_post($notification_id);
    if ($ok) {
      $success_count++;
    } else {
      $failed_ids[] = $notification_id;
    }
  }

  if ($success_count > 0 && empty($failed_ids)) {
    return rest_ensure_response([
      'success' => true,
      'message' => $success_count === 1 ? '通知を削除しました。' : "{$success_count}件の通知を削除しました。",
    ]);
  }
  if ($success_count > 0 && !empty($failed_ids)) {
    return rest_ensure_response([
      'success'     => true,
      'message'     => "{$success_count}件の通知を削除しました。",
      'warning'     => count($failed_ids) . '件の処理に失敗しました。',
      'failed_ids'  => $failed_ids,
    ]);
  }
  return new WP_Error('delete_failed', '通知の削除に失敗しました。', [
    'status'     => 400,
    'failed_ids' => $failed_ids,
  ]);
}
