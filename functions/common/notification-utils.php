<?php
/**
 * 通知保存用ユーティリティ関数
 * 仕様: docs/spec/notification.md
 */

function save_notification($args = []) {
  if (!isset($args['user_id']) || !isset($args['title']) || !isset($args['type'])) {
    return false;
  }

  if (!function_exists('aidunite_notification_send')) {
    return false;
  }

  $data = [
    'title'   => $args['title'],
    'message' => isset($args['message']) ? $args['message'] : $args['title'],
  ];
  if (!empty($args['schedule_id'])) {
    $data['related_id'] = (int) $args['schedule_id'];
  }
  if (!empty($args['match_request_id'])) {
    $data['related_id'] = (int) $args['match_request_id'];
  }

  $result = aidunite_notification_send((int) $args['user_id'], $args['type'], $data);
  return !empty($result['success']);
}
