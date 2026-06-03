<?php
/**
 * 複数チームマッチ用チャット機能
 */

/*--------------------------------------------------------------
  複数チームマッチチャット作成
--------------------------------------------------------------*/
function aidunite_create_multi_match_chat($multi_match_id) {
    // 既存のチャットがあるかチェック
    $existing_chat = get_posts([
        'post_type' => 'multi_match_chat',
        'meta_query' => [
            ['key' => 'multi_match_id', 'value' => $multi_match_id]
        ],
        'post_status' => 'publish',
        'numberposts' => 1
    ]);

    if (!empty($existing_chat)) {
        return $existing_chat[0]->ID;
    }

    // 新しいチャットを作成
    $chat_id = wp_insert_post([
        'post_type' => 'multi_match_chat',
        'post_title' => "複数チームマッチチャット #{$multi_match_id}",
        'post_status' => 'publish',
        'post_author' => get_current_user_id()
    ]);

    if ($chat_id) {
        update_post_meta($chat_id, 'multi_match_id', $multi_match_id);
        update_post_meta($chat_id, 'chat_status', 'active');
        update_post_meta($chat_id, 'created_date', current_time('mysql'));

        // システムメッセージを投稿
        aidunite_post_system_message_to_chat($chat_id, "複数チームマッチチャットが開始されました。");

        return $chat_id;
    }

    return false;
}

/*--------------------------------------------------------------
  チャットにメッセージ投稿
--------------------------------------------------------------*/
function aidunite_post_message_to_multi_match_chat($chat_id, $team_id, $message_content, $message_type = 'text') {
    $message_id = wp_insert_post([
        'post_type' => 'mm_chat_message',
        'post_title' => "メッセージ " . current_time('mysql'),
        'post_status' => 'publish',
        'post_author' => get_current_user_id()
    ]);

    if ($message_id) {
        update_post_meta($message_id, 'chat_id', $chat_id);
        update_post_meta($message_id, 'team_id', $team_id);
        update_post_meta($message_id, 'message_content', $message_content);
        if (function_exists('aidunite_chat_write_mm_message_type_meta')) {
            aidunite_chat_write_mm_message_type_meta($message_id, $message_type);
        } else {
            update_post_meta($message_id, 'message_type', $message_type);
        }
        update_post_meta($message_id, 'is_system_message', '0');
        update_post_meta($message_id, 'posted_date', current_time('mysql'));

        // 参加チームに通知
        aidunite_notify_mm_chat_message($chat_id, $team_id, $message_content);

        return $message_id;
    }

    return false;
}

/*--------------------------------------------------------------
  システムメッセージ投稿
--------------------------------------------------------------*/
function aidunite_post_system_message_to_chat($chat_id, $message_content) {
    $message_id = wp_insert_post([
        'post_type' => 'mm_chat_message',
        'post_title' => "システムメッセージ " . current_time('mysql'),
        'post_status' => 'publish',
        'post_author' => 1 // システムユーザー
    ]);

    if ($message_id) {
        update_post_meta($message_id, 'chat_id', $chat_id);
        update_post_meta($message_id, 'team_id', 0);
        update_post_meta($message_id, 'message_content', $message_content);
        if (function_exists('aidunite_chat_write_mm_message_type_meta')) {
            aidunite_chat_write_mm_message_type_meta($message_id, 'system');
        } else {
            update_post_meta($message_id, 'message_type', 'system');
        }
        update_post_meta($message_id, 'is_system_message', '1');
        update_post_meta($message_id, 'posted_date', current_time('mysql'));

        return $message_id;
    }

    return false;
}

/*--------------------------------------------------------------
  チャットメッセージ取得
--------------------------------------------------------------*/
function aidunite_get_mm_chat_messages($chat_id, $limit = 50, $offset = 0) {
    $messages = get_posts([
        'post_type' => 'mm_chat_message',
        'meta_query' => [
            ['key' => 'chat_id', 'value' => $chat_id]
        ],
        'post_status' => 'publish',
        'numberposts' => $limit,
        'offset' => $offset,
        'orderby' => 'date',
        'order' => 'ASC'
    ]);

    $formatted_messages = [];
    foreach ($messages as $message) {
        $team_id = get_post_meta($message->ID, 'team_id', true);
        $team_name = $team_id ? get_the_title($team_id) : 'システム';

        $formatted_messages[] = [
            'id' => $message->ID,
            'team_id' => $team_id,
            'team_name' => $team_name,
            'message_content' => get_post_meta($message->ID, 'message_content', true),
            'message_type' => get_post_meta($message->ID, 'message_type', true),
            'is_system_message' => get_post_meta($message->ID, 'is_system_message', true),
            'posted_date' => get_post_meta($message->ID, 'posted_date', true),
            'author_id' => $message->post_author
        ];
    }

    return $formatted_messages;
}

/*--------------------------------------------------------------
  参加チーム一覧取得
--------------------------------------------------------------*/
function aidunite_get_multi_match_participants($multi_match_id) {
    $participants = get_posts([
        'post_type' => 'mm_participant',
        'meta_query' => [
            ['key' => 'multi_match_id', 'value' => $multi_match_id],
            ['key' => 'participant_status', 'value' => 'approved']
        ],
        'post_status' => 'publish',
        'numberposts' => -1
    ]);

    $teams = [];
    foreach ($participants as $participant) {
        $team_id = get_post_meta($participant->ID, 'team_id', true);
        if ($team_id) {
            $teams[] = [
                'id' => $team_id,
                'name' => get_the_title($team_id),
                'participant_id' => $participant->ID
            ];
        }
    }

    return $teams;
}

/*--------------------------------------------------------------
  チャット参加権限チェック
--------------------------------------------------------------*/
function aidunite_can_access_multi_match_chat($chat_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $managed = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($user_id) : [];
    if (empty($managed)) {
        $legacy = (int) get_user_meta($user_id, 'team_id', true);
        if ($legacy > 0) {
            $managed = [$legacy];
        }
    }
    if (empty($managed)) {
        return false;
    }

    $multi_match_id = get_post_meta($chat_id, 'multi_match_id', true);
    if (!$multi_match_id) {
        return false;
    }

    // 参加チームかチェック
    $participants = aidunite_get_multi_match_participants($multi_match_id);
    foreach ($participants as $participant) {
        $pid = (int) $participant['id'];
        if ($pid > 0 && in_array($pid, array_map('intval', $managed), true)) {
            return true;
        }
    }

    return false;
}

/*--------------------------------------------------------------
  チャット通知送信
--------------------------------------------------------------*/
function aidunite_notify_mm_chat_message($chat_id, $sender_team_id, $message_content) {
    $multi_match_id = get_post_meta($chat_id, 'multi_match_id', true);
    $participants = aidunite_get_multi_match_participants($multi_match_id);

    $sender_team_name = get_the_title($sender_team_id);
    $match_title = get_the_title($multi_match_id);

    foreach ($participants as $participant) {
        // 送信者には通知しない
        if ($participant['id'] == $sender_team_id) {
            continue;
        }

        // チーム代表者に通知
        $team_leader = aidunite_get_team_leader($participant['id']);
        if ($team_leader) {
            $title = "📢 複数チームマッチチャット: {$match_title}";
            $message = "チーム「{$sender_team_name}」から新しいメッセージがあります。\n\n"
                    . "メッセージ: {$message_content}\n\n"
                    . "チャット画面で詳細を確認してください。";

            aidunite_notify_user($team_leader, $title, $message, 'multi_match_chat', $chat_id);
        }
    }
}

/*--------------------------------------------------------------
  チーム代表者取得
--------------------------------------------------------------*/
function aidunite_get_team_leader($team_id) {
    $users = get_users([
        'meta_query' => [
            ['key' => 'team_id', 'value' => $team_id],
            ['key' => 'aidunite_role', 'value' => 'team_leader']
        ],
        'number' => 1
    ]);

    return !empty($users) ? $users[0]->ID : null;
}

/*--------------------------------------------------------------
  複数チームマッチ成立時のチャット自動作成
--------------------------------------------------------------*/
function aidunite_create_chat_on_multi_match_confirmed($multi_match_id) {
    // 最小チーム数に達したかチェック
    $participants = aidunite_get_multi_match_participants($multi_match_id);
    $min_teams = get_post_meta($multi_match_id, 'min_teams', true);

    if (count($participants) >= $min_teams) {
        // チャットを作成
        $chat_id = aidunite_create_multi_match_chat($multi_match_id);

        if ($chat_id) {
            // 参加チームに通知
            $match_title = get_the_title($multi_match_id);
            $system_message = "🎉 複数チームマッチ「{$match_title}」が成立しました！\n"
                           . "参加チーム数: " . count($participants) . "チーム\n"
                           . "チャット機能が利用可能になりました。";

            aidunite_post_system_message_to_chat($chat_id, $system_message);

            // 各参加チームに通知
            foreach ($participants as $participant) {
                $team_leader = aidunite_get_team_leader($participant['id']);
                if ($team_leader) {
                    $title = "🎉 複数チームマッチ成立: {$match_title}";
                    $message = "複数チームマッチが成立しました！\n\n"
                            . "参加チーム数: " . count($participants) . "チーム\n"
                            . "チャット機能が利用可能になりました。\n\n"
                            . "詳細は複数チームマッチ管理画面で確認してください。";

                    aidunite_notify_user($team_leader, $title, $message, 'multi_match_confirmed', $multi_match_id);
                }
            }

            return $chat_id;
        }
    }

    return false;
}

/*--------------------------------------------------------------
  REST API: チャットメッセージ投稿
--------------------------------------------------------------*/
add_action('rest_api_init', function() {
    register_rest_route('aidunite/v1', '/multi-match-chat/(?P<chat_id>\d+)/message', [
        'methods' => 'POST',
        'callback' => 'aidunite_api_post_chat_message',
        'permission_callback' => function($request) {
            $chat_id = $request->get_param('chat_id');
            return aidunite_can_access_multi_match_chat($chat_id);
        }
    ]);

    register_rest_route('aidunite/v1', '/multi-match-chat/(?P<chat_id>\d+)/messages', [
        'methods' => 'GET',
        'callback' => 'aidunite_api_get_chat_messages',
        'permission_callback' => function($request) {
            $chat_id = $request->get_param('chat_id');
            return aidunite_can_access_multi_match_chat($chat_id);
        }
    ]);
});

function aidunite_api_post_chat_message($request) {
    $chat_id = intval($request->get_param('chat_id'));
    $params = $request->get_json_params();

    $message_content = sanitize_textarea_field($params['message_content'] ?? '');
    $message_type = sanitize_text_field($params['message_type'] ?? 'text');
    $uid = get_current_user_id();
    $managed = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($uid) : [];
    if (empty($managed)) {
        $legacy = (int) get_user_meta($uid, 'team_id', true);
        if ($legacy > 0) {
            $managed = [$legacy];
        }
    }
    $multi_match_id = (int) get_post_meta($chat_id, 'multi_match_id', true);
    $participants = $multi_match_id ? aidunite_get_multi_match_participants($multi_match_id) : [];
    $team_id = 0;
    foreach ($managed as $tid) {
        $tid = (int) $tid;
        foreach ($participants as $p) {
            if ((int) $p['id'] === $tid) {
                $team_id = $tid;
                break 2;
            }
        }
    }
    if (!$team_id) {
        $team_id = function_exists('aidunite_get_current_team_id')
            ? (int) aidunite_get_current_team_id($uid)
            : (int) get_user_meta($uid, 'team_id', true);
    }

    if (empty($message_content)) {
        return new WP_Error('empty_message', 'メッセージ内容を入力してください。', ['status' => 400]);
    }

    $message_id = aidunite_post_message_to_multi_match_chat($chat_id, $team_id, $message_content, $message_type);

    if ($message_id) {
        return new WP_REST_Response([
            'success' => true,
            'message_id' => $message_id,
            'message' => 'メッセージを投稿しました。'
        ], 200);
    } else {
        return new WP_Error('post_failed', 'メッセージの投稿に失敗しました。', ['status' => 500]);
    }
}

function aidunite_api_get_chat_messages($request) {
    $chat_id = intval($request->get_param('chat_id'));
    $limit = intval($request->get_param('limit') ?? 50);
    $offset = intval($request->get_param('offset') ?? 0);

            $messages = aidunite_get_mm_chat_messages($chat_id, $limit, $offset);

    return new WP_REST_Response([
        'success' => true,
        'messages' => $messages
    ], 200);
}
