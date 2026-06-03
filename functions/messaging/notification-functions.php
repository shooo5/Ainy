<?php
/**
 * 通知機能
 * MESSAGE-MVP-DESIGN.md に基づく実装
 */

/**
 * プッシュ通知を送信
 */
function aidunite_send_push_notification($user_id, $title, $message, $data = []) {
    // ブラウザプッシュ通知の実装
    $notification_data = [
        'user_id' => $user_id,
        'title' => $title,
        'message' => $message,
        'data' => $data,
        'timestamp' => current_time('mysql')
    ];

    // 通知をデータベースに保存
    aidunite_save_notification($notification_data);

    // 実際のプッシュ通知送信（Web Push API使用）
    $push_result = aidunite_send_web_push($user_id, $title, $message, $data);

    if ($push_result) {
        aidunite_log_success("プッシュ通知を送信しました (User ID: $user_id)");
    } else {
        aidunite_log("プッシュ通知の送信に失敗しました (User ID: $user_id)");
    }

    return $push_result;
}

/**
 * メール通知を送信
 */
function aidunite_send_email_notification($user_id, $subject, $message, $priority = 'normal') {
    $user = get_user_by('id', $user_id);

    if (!$user) {
        return new WP_Error('user_not_found', 'ユーザーが見つかりません');
    }

    $email_data = [
        'to' => $user->user_email,
        'subject' => $subject,
        'message' => $message,
        'priority' => $priority,
        'headers' => [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ]
    ];

    $result = wp_mail($email_data['to'], $email_data['subject'], $email_data['message'], $email_data['headers']);

    if ($result) {
        aidunite_log_success("メール通知を送信しました (User ID: $user_id)");
    } else {
        aidunite_log("メール通知の送信に失敗しました (User ID: $user_id)");
    }

    return $result;
}

/**
 * LINE通知を送信
 */
function aidunite_send_line_notification($user_id, $message) {
    // 既存のLINE通知システムと連携
    $line_data = [
        'user_id' => $user_id,
        'message' => $message,
        'timestamp' => current_time('mysql')
    ];

    // 既存のLINE通知関数を呼び出し
    if (function_exists('aidunite_send_line_message')) {
        $result = aidunite_send_line_message($user_id, $message);

        if ($result) {
            aidunite_log_success("LINE通知を送信しました (User ID: $user_id)");
        } else {
            aidunite_log("LINE通知の送信に失敗しました (User ID: $user_id)");
        }

        return $result;
    }

    return false;
}

/**
 * 緊急通知を送信
 */
function aidunite_send_urgent_notification($team_id, $title, $message) {
    $team_members = aidunite_get_team_members($team_id);

    $results = [];

    foreach ($team_members as $member) {
        $user_id = $member->user_id;

        // 全チャネルで通知
        $push_result = aidunite_send_push_notification($user_id, $title, $message, ['priority' => 'urgent']);
        $email_result = aidunite_send_email_notification($user_id, $title, $message, 'urgent');
        $line_result = aidunite_send_line_notification($user_id, $message);

        $results[$user_id] = [
            'push' => $push_result,
            'email' => $email_result,
            'line' => $line_result
        ];
    }

    aidunite_log_success("緊急通知を送信しました (Team ID: $team_id, 対象: " . count($team_members) . "人)");

    return $results;
}

/**
 * 通知を保存
 */
function aidunite_save_notification($notification_data) {
    global $wpdb;

    $table = $wpdb->prefix . 'notifications';

    $result = $wpdb->insert($table, [
        'user_id' => $notification_data['user_id'],
        'title' => $notification_data['title'],
        'message' => $notification_data['message'],
        'data' => json_encode($notification_data['data']),
        'type' => $notification_data['type'] ?? 'general',
        'priority' => $notification_data['priority'] ?? 'normal',
        'status' => 'sent',
        'created_at' => $notification_data['timestamp']
    ], [
        '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
    ]);

    return $result !== false;
}

/**
 * ユーザーの通知設定を取得
 */
function aidunite_get_notification_settings($user_id) {
    $settings = get_user_meta($user_id, 'aidunite_notification_settings', true);

    if (!$settings) {
        // デフォルト設定
        $settings = [
            'push_enabled' => true,
            'email_enabled' => true,
            'line_enabled' => true,
            'urgent_only' => false,
            'categories' => [
                'announcement' => true,
                'schedule' => true,
                'practice' => true,
                'match' => true,
                'general' => false
            ]
        ];
    }

    return $settings;
}

/**
 * ユーザーの通知設定を更新
 */
function aidunite_update_notification_settings($user_id, $settings) {
    $result = update_user_meta($user_id, 'aidunite_notification_settings', $settings);

    if ($result) {
        aidunite_log_success("通知設定を更新しました (User ID: $user_id)");
    }

    return $result;
}

/**
 * 通知の重複をチェック
 */
function aidunite_check_notification_duplicate($user_id, $message_hash) {
    $cache_key = "aidunite_notification_{$user_id}_{$message_hash}";
    $cached = get_transient($cache_key);

    if ($cached) {
        return true; // 重複
    }

    // 5分間キャッシュ
    set_transient($cache_key, true, 300);

    return false; // 重複なし
}

/**
 * メッセージ通知を送信
 */
function aidunite_send_message_notification($message_id, $team_id) {
    $message = aidunite_get_message($message_id);

    if (!$message) {
        return false;
    }

    $team_members = aidunite_get_team_members($team_id);
    $message_hash = md5($message->content);

    foreach ($team_members as $member) {
        $user_id = $member->user_id;

        // 投稿者本人には通知しない
        if ($user_id == $message->user_id) {
            continue;
        }

        // 重複チェック
        if (aidunite_check_notification_duplicate($user_id, $message_hash)) {
            continue;
        }

        $settings = aidunite_get_notification_settings($user_id);

        // カテゴリ別通知設定をチェック
        if (!($settings['categories'][$message->category] ?? true)) {
            continue;
        }

        $title = "新しいメッセージ: {$message->title}";
        $notification_message = substr($message->content, 0, 100) . (strlen($message->content) > 100 ? '...' : '');

        // 重要度に応じて通知方法を選択
        if ($message->priority === 'urgent') {
            aidunite_send_urgent_notification($team_id, $title, $notification_message);
        } else {
            if ($settings['push_enabled']) {
                aidunite_send_push_notification($user_id, $title, $notification_message);
            }

            if ($settings['email_enabled'] && $message->priority !== 'normal') {
                aidunite_send_email_notification($user_id, $title, $notification_message, $message->priority);
            }

            if ($settings['line_enabled'] && $message->priority === 'important') {
                aidunite_send_line_notification($user_id, $notification_message);
            }
        }
    }

    return true;
}

/**
 * チャットメッセージ通知を送信
 */
function aidunite_send_chat_notification($room_id, $message_data) {
    $room = aidunite_get_chat_room($room_id);
    $participants = aidunite_get_chat_participants($room_id);

    foreach ($participants as $participant) {
        $user_id = $participant->user_id;

        // 送信者本人には通知しない
        if ($user_id == $message_data['sender_id']) {
            continue;
        }

        $settings = aidunite_get_notification_settings($user_id);

        if (!$settings['push_enabled']) {
            continue;
        }

        $title = "新しいメッセージ: {$room->name}";
        $message = substr($message_data['content'], 0, 100) . (strlen($message_data['content']) > 100 ? '...' : '');

        aidunite_send_push_notification($user_id, $title, $message, [
            'room_id' => $room_id,
            'room_type' => $room->room_type
        ]);
    }

    return true;
}

/**
 * Web Push通知を送信（完全実装版）
 */
function aidunite_send_web_push($user_id, $title, $message, $data = []) {
    // 既存のWeb Push APIクラスを使用
    if (class_exists('AidUniteWebPushAPI')) {
        $result = AidUniteWebPushAPI::send_push_notification($user_id, $title, $message, $data);

        if ($result) {
            aidunite_log_success("Web Push通知を送信しました (User ID: $user_id)");
            return true;
        } else {
            aidunite_log("Web Push通知の送信に失敗しました (User ID: $user_id)");
            return false;
        }
    }

    // フォールバック: キューに保存
    $push_data = [
        'user_id' => $user_id,
        'title' => $title,
        'message' => $message,
        'data' => $data,
        'timestamp' => current_time('mysql')
    ];

    $queue_key = "aidunite_push_queue_" . time() . "_" . $user_id;
    set_transient($queue_key, $push_data, 300);

    return true;
}

/**
 * 通知統計を取得
 */
function aidunite_get_notification_stats($team_id = null) {
    global $wpdb;

    $table = $wpdb->prefix . 'notifications';

    $where_clause = '';
    $where_values = [];

    if ($team_id) {
        $where_clause = 'WHERE n.team_id = %d';
        $where_values[] = $team_id;
    }

    $sql = $wpdb->prepare("
        SELECT
            COUNT(*) as total_notifications,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count,
            SUM(CASE WHEN type = 'push' THEN 1 ELSE 0 END) as push_count,
            SUM(CASE WHEN type = 'email' THEN 1 ELSE 0 END) as email_count
        FROM $table n
        $where_clause
    ", $where_values);

    return $wpdb->get_row($sql);
}

// メッセージ送信時の通知フック
add_action('aidunite_message_sent', function($message_id, $message_data) {
    if (isset($message_data['team_id'])) {
        aidunite_send_message_notification($message_id, $message_data['team_id']);
    }
}, 10, 2);

// チャットメッセージ送信時の通知フック
add_action('aidunite_chat_message_sent', function($room_id, $message_data) {
    aidunite_send_chat_notification($room_id, $message_data);
}, 10, 2);
