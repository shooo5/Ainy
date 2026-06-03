<?php
/**
 * スケジュール・マッチ情報取得ユーティリティ（新バージョン）
 * マッチ管理ページ用のデータ取得関数群
 */

/**
 * スケジュール種別の日本語表示を取得
 */
function get_schedule_type_display($schedule_type, $intent = '') {
  // 基本の種別マップ
  $type_map = [
    'practice' => '練習',
    'official_match' => '公式試合',
    'practice_match' => '練習試合',
    'joint_practice' => '合同練習',
    'rest' => '休み',
    'event' => 'イベント',
    'meeting' => 'ミーティング'
  ];

  // 意図に応じた表示
  if ($intent === 'recruit') {
    // マッチ希望の場合
    if ($schedule_type === 'practice_match') {
      return '練習試合（募集）';
    } elseif ($schedule_type === 'joint_practice') {
      return '合同練習（募集）';
    }
  } elseif ($intent === 'tentative') {
    // 仮押さえの場合
    if ($schedule_type === 'practice') {
      return '練習（仮）';
    } elseif ($schedule_type === 'practice_match') {
      return '練習試合（仮）';
    } elseif ($schedule_type === 'joint_practice') {
      return '合同練習（仮）';
    }
  }

  // デフォルト表示
  return $type_map[$schedule_type] ?? $schedule_type;
}

/**
 * スケジュールとマッチの統合データを取得
 */
function get_schedule_match_data() {
  // スケジュールを取得
  $schedules = get_posts([
    'post_type'      => 'schedule',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value',
    'meta_key'       => 'schedule_date',
    'order'          => 'ASC',
  ]);

  $result = [];
  $stats = [
    'total_schedules' => 0,
    'total_match_requests' => 0,
    'auto_requests' => 0,
    'manual_requests' => 0,
    'accepted_requests' => 0,
    'pending_requests' => 0,
    'rejected_requests' => 0,
    'canceled_requests' => 0
  ];

  foreach ($schedules as $schedule) {
    $schedule_id = $schedule->ID;

    // スケジュール基本情報
    $schedule_date_raw = get_post_meta($schedule_id, 'schedule_date', true);
    $team_id = get_post_meta($schedule_id, 'team_id', true);
    $team_name = get_the_title($team_id);

    // 日付計算
    $schedule_date = $schedule_date_raw ? date('y/m/d', strtotime($schedule_date_raw)) : '-';
    $cancel_deadline = $schedule_date_raw ? date('y/m/d', strtotime($schedule_date_raw . ' -3 days')) : '-';
    $days_remaining = $schedule_date_raw ? floor((strtotime($schedule_date_raw) - time()) / (60 * 60 * 24)) : '-';

    // マッチボード情報
    $board_id = get_board_id_from_schedule($schedule_id);
    $board_status = $board_id ? get_post_meta($board_id, 'match_board_status', true) : '-';

    // マッチリクエスト情報
    $match_requests = get_match_requests_by_schedule($schedule_id);
    $accepted_count = 0;
    $match_requests_data = [];

    foreach ($match_requests as $request) {
      $request_id = $request->ID;
      $from_team_id = get_post_meta($request_id, 'from_team_id', true);
      $from_team_name = get_the_title($from_team_id);
      $from_schedule_id = get_post_meta($request_id, 'from_schedule_id', true);
      $to_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);
      $status = get_post_meta($request_id, 'status', true);
      $type = get_post_meta($request_id, 'type', true);

      if ($status === 'accepted' || $status === 'established') {
        $accepted_count++;
      }

      // 統計更新
      $stats['total_match_requests']++;
      if ($type === 'auto' || $type === '') {
        $stats['auto_requests']++;
      } else {
        $stats['manual_requests']++;
      }

      switch ($status) {
        case 'accepted':
        case 'established':
          $stats['accepted_requests']++;
          break;
        case 'pending':
          $stats['pending_requests']++;
          break;
        case 'rejected':
          $stats['rejected_requests']++;
          break;
        case 'canceled':
          $stats['canceled_requests']++;
          break;
      }

      $match_requests_data[] = [
        'request_id' => $request_id,
        'from_team_id' => $from_team_id,
        'from_team_name' => $from_team_name,
        'from_schedule_id' => $from_schedule_id,
        'to_schedule_id' => $to_schedule_id,
        'status' => $status,
        'type' => $type,
        'match_type' => $type === 'auto' ? '自動' : ($type === 'manual' ? '手動' : '未設定'),
        'board_status_from' => get_board_status_label($status, 'self'),
        'board_status_to' => get_board_status_label($status, 'receiver'),
        'auto_status_from' => $type === 'auto' ? $status : (function_exists('get_match_status_label') ? get_match_status_label('not_applied', 'text') : '未申請'),
        'auto_status_to' => $type === 'auto' ? $status : (function_exists('get_match_status_label') ? get_match_status_label('not_applied', 'text') : '未申請'),
        'auto_flag' => $type === 'auto' ? '✓' : '-',
        'is_auto' => ($type === 'auto' || $type === ''),
        'is_canceled' => ($status === 'canceled')
      ];
    }

    $stats['total_schedules']++;

         // 閲覧数情報を取得
     $view_stats = get_view_statistics($schedule_id);

     // スケジュールの詳細情報を取得
     $schedule_post = get_post($schedule_id);
     $created_date = $schedule_post ? $schedule_post->post_date : '';
     $modified_date = $schedule_post ? $schedule_post->post_modified : '';
     $author_id = $schedule_post ? $schedule_post->post_author : 0;
     $author_name = $author_id ? get_the_author_meta('display_name', $author_id) : '';

     // チームの詳細情報
     $team_area = get_post_meta($team_id, 'team_area', true);
     $sport_type = get_post_meta($team_id, 'sport_type', true);
     $team_level = get_post_meta($team_id, 'team_level', true);

     // 複数チーム対応の情報
     $participants = get_post_meta($schedule_id, 'participants', true);
     $participants_data = [];
     $participant_count = 0;
     $male_participants = 0;
     $female_participants = 0;

     if ($participants) {
       $participant_ids = explode(',', $participants);
       foreach ($participant_ids as $participant_id) {
         $participant_id = trim($participant_id);
         if ($participant_id) {
           $participant_name = get_the_title($participant_id);
           $participant_gender = get_post_meta($participant_id, 'team_gender_option', true);
           if (function_exists('aidunite_normalize_team_gender_option')) {
             $participant_gender = aidunite_normalize_team_gender_option((string) $participant_gender);
           }

           $participants_data[] = [
             'id' => $participant_id,
             'name' => $participant_name,
             'gender' => $participant_gender
           ];

           $participant_count++;

           if ($participant_gender === 'male') {
             $male_participants++;
           } elseif ($participant_gender === 'female') {
             $female_participants++;
           }
         }
       }
     }

     // 定員情報
     $male_capacity = get_post_meta($schedule_id, 'male_capacity', true) ?: 0;
     $female_capacity = get_post_meta($schedule_id, 'female_capacity', true) ?: 0;

     // 定員充足率の計算
     $male_capacity_rate = $male_capacity > 0 ? round(($male_participants / $male_capacity) * 100, 1) : 0;
     $female_capacity_rate = $female_capacity > 0 ? round(($female_participants / $female_capacity) * 100, 1) : 0;
     $total_capacity_rate = ($male_capacity + $female_capacity) > 0 ? round(($participant_count / ($male_capacity + $female_capacity)) * 100, 1) : 0;

     // スケジュールの詳細情報
     $start_time = get_post_meta($schedule_id, 'schedule_start_time', true);
     $end_time = get_post_meta($schedule_id, 'schedule_end_time', true);
     $place = get_post_meta($schedule_id, 'schedule_place', true);
     // Phase 2: 統一メタキーを優先、後方互換性のために旧キーもフォールバック
     $place_condition = get_post_meta($schedule_id, 'schedule_place', true);
     if (empty($place_condition)) {
         $place_condition = get_post_meta($schedule_id, 'schedule_place_option', true);
     }

     // Phase 2: 統一メタキーを優先、後方互換性のために旧キーもフォールバック
     $gender_condition = get_post_meta($schedule_id, 'schedule_gender', true);
     if (empty($gender_condition)) {
         $gender_condition = get_post_meta($schedule_id, 'matching_gender_condition', true);
     }
     $participation_fee = get_post_meta($schedule_id, 'participation_fee', true);
     $notes = get_post_meta($schedule_id, 'schedule_note', true);

     // 種別情報の取得と表示変換
     $schedule_type = get_post_meta($schedule_id, 'schedule_type', true);
     $intent = get_post_meta($schedule_id, 'intent', true);
     $schedule_type_display = get_schedule_type_display($schedule_type, $intent);

     $result[] = [
       'schedule_id' => $schedule_id,
       'created_date' => $created_date,
       'modified_date' => $modified_date,
       'schedule_date' => $schedule_date,
       'schedule_date_raw' => $schedule_date_raw,
       'cancel_deadline' => $cancel_deadline,
       'days_remaining' => $days_remaining,
       'team_id' => $team_id,
       'team_name' => $team_name,
       'team_area' => $team_area,
       'sport_type' => $sport_type,
       'team_level' => $team_level,
       'author_id' => $author_id,
       'author_name' => $author_name,
       'board_id' => $board_id,
       'board_status' => $board_status,
       'accepted_count' => $accepted_count,
       'match_requests' => $match_requests_data,
       'has_matches' => !empty($match_requests_data),
       'match_count' => count($match_requests_data),
       'start_time' => $start_time,
       'end_time' => $end_time,
       'place' => $place,
       'place_condition' => $place_condition,
       'gender_condition' => $gender_condition,
       'participation_fee' => $participation_fee,
       'notes' => $notes,
       'view_count' => $view_stats['total_views'],
       'unique_visitors' => $view_stats['unique_visitors'],
       'today_views' => $view_stats['today_views'],
       'week_views' => $view_stats['week_views'],
       // 複数チーム対応の情報
       'participants' => $participants_data,
       'participant_count' => $participant_count,
       'male_participants' => $male_participants,
       'female_participants' => $female_participants,
       'male_capacity' => $male_capacity,
       'female_capacity' => $female_capacity,
       'male_capacity_rate' => $male_capacity_rate,
       'female_capacity_rate' => $female_capacity_rate,
       'total_capacity_rate' => $total_capacity_rate,
       'participants_list' => implode(', ', array_column($participants_data, 'name')),
       // 種別情報
       'schedule_type' => $schedule_type,
       'intent' => $intent,
       'schedule_type_display' => $schedule_type_display
     ];
  }

  return [
    'schedules' => $result,
    'stats' => $stats
  ];
}

/**
 * スケジュールを削除
 */
function delete_schedule($schedule_id) {
  $schedule_id = intval($schedule_id);
  if (!$schedule_id) {
    return ['success' => false, 'message' => '無効なスケジュールIDです'];
  }

  $schedule = get_post($schedule_id);
  if (!$schedule || $schedule->post_type !== 'schedule') {
    return ['success' => false, 'message' => 'スケジュールが見つかりません'];
  }

  // 関連するマッチリクエストも削除
  $match_requests = get_match_requests_by_schedule($schedule_id);
  $deleted_requests = 0;

  foreach ($match_requests as $request) {
    if (wp_delete_post($request->ID, true)) {
      $deleted_requests++;
    }
  }

  // スケジュールを削除
  $result = wp_delete_post($schedule_id, true);

  if ($result) {
    $message = "スケジュールID {$schedule_id} を削除しました";
    if ($deleted_requests > 0) {
      $message .= "（関連するマッチリクエスト {$deleted_requests} 件も削除）";
    }
    return ['success' => true, 'message' => $message, 'deleted_requests' => $deleted_requests];
  } else {
    return ['success' => false, 'message' => 'スケジュールの削除に失敗しました'];
  }
}

/**
 * マッチリクエストを削除
 */
function delete_match_request($request_id) {
  $request_id = intval($request_id);
  if (!$request_id) {
    return ['success' => false, 'message' => '無効なリクエストIDです'];
  }

  $request = get_post($request_id);
  if (!$request || $request->post_type !== 'match_request') {
    return ['success' => false, 'message' => 'マッチリクエストが見つかりません'];
  }

  $result = wp_delete_post($request_id, true);

  if ($result) {
    return ['success' => true, 'message' => "マッチリクエストID {$request_id} を削除しました"];
  } else {
    return ['success' => false, 'message' => 'マッチリクエストの削除に失敗しました'];
  }
}

/**
 * 複数のアイテムを削除（スケジュールとマッチリクエストの混合）
 */
function delete_multiple_items($items) {
  if (empty($items) || !is_array($items)) {
    return ['success' => false, 'message' => '削除する項目が指定されていません'];
  }

  $deleted_schedules = 0;
  $deleted_requests = 0;
  $errors = [];

  foreach ($items as $item) {
    $type = $item['type'] ?? '';
    $id = intval($item['id'] ?? 0);

    if (!$id) continue;

    if ($type === 'schedule') {
      $result = delete_schedule($id);
      if ($result['success']) {
        $deleted_schedules++;
        if (isset($result['deleted_requests'])) {
          $deleted_requests += $result['deleted_requests'];
        }
      } else {
        $errors[] = $result['message'];
      }
    } elseif ($type === 'match_request') {
      $result = delete_match_request($id);
      if ($result['success']) {
        $deleted_requests++;
      } else {
        $errors[] = $result['message'];
      }
    }
  }

  $message_parts = [];
  if ($deleted_schedules > 0) {
    $message_parts[] = "スケジュール {$deleted_schedules} 件";
  }
  if ($deleted_requests > 0) {
    $message_parts[] = "マッチリクエスト {$deleted_requests} 件";
  }

  $message = implode('、', $message_parts) . 'を削除しました';
  if (!empty($errors)) {
    $message .= '。エラー: ' . implode(', ', $errors);
  }

  return [
    'success' => ($deleted_schedules > 0 || $deleted_requests > 0),
    'deleted_schedules' => $deleted_schedules,
    'deleted_requests' => $deleted_requests,
    'message' => $message,
    'errors' => $errors
  ];
}
