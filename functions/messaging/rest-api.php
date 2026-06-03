<?php
/**
 * メッセージ機能 REST API
 * MESSAGE-MVP-DESIGN.md に基づく実装
 */

// REST API エンドポイント登録
add_action('rest_api_init', function() {

    // メッセージ掲示板 API
    register_rest_route('aidunite/v1', '/messages', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_messages',
        'permission_callback' => 'aidunite_check_message_permission',
        'args' => [
            'team_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ],
            'category' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['announcement', 'schedule', 'practice', 'match', 'general']
            ],
            'priority' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['normal', 'important', 'urgent']
            ],
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 20
            ]
        ]
    ]);

    register_rest_route('aidunite/v1', '/messages', [
        'methods' => 'POST',
        'callback' => 'aidunite_create_message',
        'permission_callback' => 'aidunite_check_message_permission'
    ]);

    register_rest_route('aidunite/v1', '/messages/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'aidunite_update_message',
        'permission_callback' => 'aidunite_check_message_permission'
    ]);

    register_rest_route('aidunite/v1', '/messages/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'aidunite_delete_message',
        'permission_callback' => 'aidunite_check_message_permission'
    ]);

    register_rest_route('aidunite/v1', '/messages/(?P<id>\d+)/pin', [
        'methods' => 'PUT',
        'callback' => 'aidunite_toggle_message_pin',
        'permission_callback' => 'aidunite_check_message_permission'
    ]);

    // 統合タイムライン API
    register_rest_route('aidunite/v1', '/timeline', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_timeline_api',
        'permission_callback' => 'aidunite_check_message_permission',
        'args' => [
            'team_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ],
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 50
            ]
        ]
    ]);

    // チームメンバー一覧（チャットを始める用・自分を除く）
    register_rest_route('aidunite/v1', '/team-members', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_team_members_for_chat',
        'permission_callback' => 'aidunite_check_message_permission',
        'args' => [
            'team_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ]
        ]
    ]);

    // チャット API
    register_rest_route('aidunite/v1', '/chats', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_chats',
        'permission_callback' => 'aidunite_check_chat_permission_rest'
    ]);

    // 参加者ベースのルーム作成API
    register_rest_route('aidunite/v1', '/chats', [
        'methods' => 'POST',
        'callback' => 'aidunite_create_chat_room',
        'permission_callback' => 'aidunite_check_chat_permission_rest'
    ]);

    register_rest_route('aidunite/v1', '/chats/(?P<id>\d+)/messages', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_chat_messages',
        'permission_callback' => 'aidunite_check_chat_permission_rest'
    ]);

    register_rest_route('aidunite/v1', '/chats/(?P<id>\d+)/messages', [
        'methods' => 'POST',
        'callback' => 'aidunite_send_chat_message',
        'permission_callback' => 'aidunite_check_chat_permission_rest'
    ]);

    register_rest_route('aidunite/v1', '/chats/(?P<id>\d+)/messages/(?P<messageId>\d+)/reactions', [
        'methods' => 'POST',
        'callback' => 'aidunite_toggle_chat_reaction_rest',
        'permission_callback' => 'aidunite_check_chat_permission_rest',
        'args' => [
            'id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
            'messageId' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint']
        ]
    ]);

    register_rest_route('aidunite/v1', '/chats/(?P<id>\d+)/read', [
        'methods' => 'POST',
        'callback' => 'aidunite_mark_chat_read',
        'permission_callback' => 'aidunite_check_chat_permission_rest'
    ]);

    register_rest_route('aidunite/v1', '/chats/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'aidunite_delete_chat_room',
        'permission_callback' => 'aidunite_check_chat_permission_rest'
    ]);

    // 管理者専用: 重複 match/group ルームの物理削除
    register_rest_route('aidunite/v1', '/admin/chats/dedupe-match-rooms', [
        'methods' => 'POST',
        'callback' => 'aidunite_admin_dedupe_match_rooms',
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, [
                'team_id' => null,
                'redirect' => false,
            ]);
            if (is_wp_error($result)) {
                return $result;
            }
            return current_user_can('administrator');
        }
    ]);

    // チャットメッセージ検索API
    register_rest_route('aidunite/v1', '/chats/(?P<id>\d+)/search', [
        'methods' => 'GET',
        'callback' => 'aidunite_search_chat_messages',
        'permission_callback' => 'aidunite_check_chat_permission_rest',
        'args' => [
            'q' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 20
            ]
        ]
    ]);

    // 試合情報取得API（試合情報パネル用）
    register_rest_route('aidunite/v1', '/chats/(?P<id>\d+)/match-info', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_chat_match_info',
        'permission_callback' => 'aidunite_check_chat_permission_rest'
    ]);

    // メッセージ掲示板検索API
    register_rest_route('aidunite/v1', '/messages/search', [
        'methods' => 'GET',
        'callback' => 'aidunite_search_team_messages',
        'permission_callback' => 'aidunite_check_message_permission',
        'args' => [
            'team_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ],
            'q' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'category' => [
                'required' => false,
                'type' => 'string'
            ],
            'priority' => [
                'required' => false,
                'type' => 'string'
            ],
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 20
            ]
        ]
    ]);

    // SSE エンドポイント
    register_rest_route('aidunite/v1', '/messages/(?P<team_id>\d+)/stream', [
        'methods' => 'GET',
        'callback' => 'aidunite_sse_message_stream',
        'permission_callback' => 'aidunite_check_message_permission'
    ]);

    register_rest_route('aidunite/v1', '/chats/(?P<id>\d+)/stream', [
        'methods' => 'GET',
        'callback' => 'aidunite_sse_chat_stream',
        'permission_callback' => 'aidunite_check_chat_permission_rest'
    ]);

    // 評価 API
    register_rest_route('aidunite/v1', '/evaluations', [
        'methods' => 'POST',
        'callback' => 'aidunite_create_evaluation',
        'permission_callback' => 'aidunite_check_evaluation_permission'
    ]);

    register_rest_route('aidunite/v1', '/evaluations/(?P<match_id>\d+)', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_evaluation',
        'permission_callback' => 'aidunite_check_evaluation_permission'
    ]);

    // 再マッチング API
    register_rest_route('aidunite/v1', '/rematch', [
        'methods' => 'POST',
        'callback' => 'aidunite_suggest_rematch',
        'permission_callback' => 'aidunite_check_rematch_permission'
    ]);

    register_rest_route('aidunite/v1', '/rematch/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'aidunite_respond_rematch',
        'permission_callback' => 'aidunite_check_rematch_permission'
    ]);
});

// メッセージ掲示板 API 実装
function aidunite_get_messages($request) {
    $team_id = $request->get_param('team_id');
    $category = $request->get_param('category');
    $priority = $request->get_param('priority');
    $page = $request->get_param('page');
    $per_page = $request->get_param('per_page');

    $messages = aidunite_get_team_messages($team_id, $category, $priority, $page, $per_page);

    return new WP_REST_Response($messages, 200);
}

function aidunite_create_message($request) {
    $params = $request->get_json_params();

    $uid = get_current_user_id();
    $team_id = isset($params['team_id']) ? intval($params['team_id']) : 0;
    if (!$team_id) {
        $team_id = function_exists('aidunite_get_current_team_id')
            ? (int) aidunite_get_current_team_id($uid)
            : (int) get_user_meta($uid, 'team_id', true);
        if (!$team_id) {
            return new WP_REST_Response(['error' => 'チームIDが取得できません'], 400);
        }
    }

    if (function_exists('aidunite_user_has_managed_team_access') && !aidunite_user_has_managed_team_access($uid, $team_id)) {
        return new WP_REST_Response(['error' => 'そのチームとして投稿する権限がありません'], 403);
    }

    $message_data = [
        'team_id' => $team_id,
        'user_id' => get_current_user_id(),
        'title' => sanitize_text_field($params['title'] ?? ''),
        'content' => sanitize_textarea_field($params['content'] ?? ''),
        'category' => sanitize_text_field($params['category'] ?? 'general'),
        'priority' => sanitize_text_field($params['priority'] ?? 'normal'),
        'pinned' => (bool) ($params['pinned'] ?? false),
        'status' => sanitize_text_field($params['status'] ?? 'published')
    ];

    $message_id = aidunite_save_message($message_data);

    if (is_wp_error($message_id)) {
        return new WP_REST_Response(['error' => $message_id->get_error_message()], 400);
    }

    // メッセージスレッド用チャットルームを作成
    $thread_room_id = aidunite_create_message_thread_chat($message_id, $team_id);
    if (is_wp_error($thread_room_id)) {
        error_log('⚠️ メッセージスレッドチャットの作成に失敗しました: ' . $thread_room_id->get_error_message());
    }

    // 投稿内容をスレッドチャットに同期
    if (!is_wp_error($thread_room_id) && $thread_room_id) {
        $chat_sync_data = [
            'room_id' => intval($thread_room_id),
            'sender_id' => $message_data['user_id'],
            'message_type' => 'text',
            'content' => $message_data['content'],
            'file_url' => '',
            'is_private' => false
        ];
        $chat_message_id = aidunite_save_chat_message($chat_sync_data);
        if (is_wp_error($chat_message_id)) {
            error_log('⚠️ スレッドチャットへの同期に失敗しました: ' . $chat_message_id->get_error_message());
        }
    }

    // 掲示板用イベント（SSEのチャットとは切り離し）
    do_action('aidunite_team_message_created', $message_id, $message_data);

    return new WP_REST_Response([
        'id' => $message_id,
        'message' => 'メッセージを作成しました',
        'thread_room_id' => is_wp_error($thread_room_id) ? null : $thread_room_id
    ], 201);
}

function aidunite_update_message($request) {
    $message_id = $request->get_param('id');
    $params = $request->get_json_params();

    $message_data = [
        'title' => sanitize_text_field($params['title']),
        'content' => sanitize_textarea_field($params['content']),
        'category' => sanitize_text_field($params['category']),
        'priority' => sanitize_text_field($params['priority']),
        'pinned' => (bool) $params['pinned']
    ];

    $result = aidunite_update_message_data($message_id, $message_data);

    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => $result->get_error_message()], 400);
    }

    return new WP_REST_Response(['message' => 'メッセージを更新しました'], 200);
}

function aidunite_delete_message($request) {
    $message_id = intval($request->get_param('id'));
    $current_user_id = get_current_user_id();

    $message = aidunite_get_message_author_id($message_id);
    if (!$message) {
        return new WP_REST_Response(['error' => 'メッセージが見つかりません'], 404);
    }
    // 投稿者本人または管理者のみ削除可能（user_id が 0 のシステム投稿は管理者のみ）
    $author_id = (int) $message->user_id;
    $is_admin = current_user_can('administrator');
    if ($author_id !== 0 && $author_id !== $current_user_id && !$is_admin) {
        return new WP_REST_Response(['error' => 'このメッセージを削除する権限がありません'], 403);
    }
    if ($author_id === 0 && !$is_admin) {
        return new WP_REST_Response(['error' => 'システム投稿は削除できません'], 403);
    }

    $result = aidunite_delete_message_data($message_id);

    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => $result->get_error_message()], 400);
    }

    return new WP_REST_Response(['message' => 'メッセージを削除しました'], 200);
}

function aidunite_toggle_message_pin($request) {
    $message_id = $request->get_param('id');
    $params = $request->get_json_params();
    $pinned = (bool) $params['pinned'];

    $result = aidunite_toggle_message_pin_status($message_id, $pinned);

    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => $result->get_error_message()], 400);
    }

    return new WP_REST_Response(['message' => '固定状態を更新しました'], 200);
}

// 統合タイムライン API 実装
function aidunite_get_timeline_api($request) {
    $user_id = get_current_user_id();
    $team_id_raw = $request->get_param('team_id');
    $team_id = ($team_id_raw === null || $team_id_raw === '') ? 0 : (int) $team_id_raw;
    $page = $request->get_param('page') ?? 1;
    $per_page = $request->get_param('per_page') ?? 50;

    if (!function_exists('aidunite_get_unified_timeline')) {
        return new WP_REST_Response(['error' => '統合タイムライン機能が利用できません'], 500);
    }

    if ($team_id > 0 && function_exists('aidunite_user_has_managed_team_access') && !aidunite_user_has_managed_team_access($user_id, $team_id)) {
        return new WP_REST_Response(['error' => 'このチームのタイムラインを表示する権限がありません'], 403);
    }

    $timeline = aidunite_get_unified_timeline($user_id, $team_id, $page, $per_page);

    return new WP_REST_Response($timeline, 200);
}

// チャット API 実装
function aidunite_get_chats($request) {
    $user_id = get_current_user_id();
    $chats = aidunite_get_user_chats($user_id);

    return new WP_REST_Response($chats, 200);
}

function aidunite_get_chat_messages($request) {
    $chat_id = $request->get_param('id');
    $page = $request->get_param('page') ?? 1;
    $per_page = $request->get_param('per_page') ?? 50;

    // プライバシー保護: room_idの検証を厳密に
    $room_id_int = intval($chat_id);
    if (!$room_id_int || $room_id_int <= 0) {
        error_log("❌ セキュリティ: 無効なroom_idでメッセージ取得を試みました。room_id={$chat_id}, user_id=" . get_current_user_id());
        return new WP_REST_Response(['error' => '無効なチャットルームIDです'], 400);
    }

    // プライバシー保護: 権限チェック（必須）
    $user_id = get_current_user_id();
    if (!aidunite_check_chat_permission($room_id_int, $user_id)) {
        error_log("❌ セキュリティ: 権限のないユーザーがメッセージ取得を試みました。room_id={$room_id_int}, user_id={$user_id}");
        return new WP_REST_Response(['error' => 'アクセス権限がありません'], 403);
    }

    // 旧データ互換: 無効 status (deleted/空) を active に復帰
    if (function_exists('aidunite_restore_chat_room_status_if_invalid')) {
        aidunite_restore_chat_room_status_if_invalid($room_id_int);
    }

    // 申請状況等からチャットを開いた場合、非表示済みでも自動で再表示に戻す
    if (function_exists('aidunite_unhide_chat_room_for_user')) {
        aidunite_unhide_chat_room_for_user($room_id_int, $user_id);
    }

    $messages = aidunite_get_chat_messages_data($room_id_int, $page, $per_page);

    if (function_exists('aidunite_sync_chat_read_status_to_latest')) {
        aidunite_sync_chat_read_status_to_latest($room_id_int, $user_id);
    }

    // プライバシー保護: 取得結果に異なるroom_idのメッセージが含まれていないか最終確認
    if (isset($messages['messages']) && is_array($messages['messages'])) {
        $wrong_room_messages = array_filter($messages['messages'], function($msg) use ($room_id_int) {
            return !isset($msg->room_id) || intval($msg->room_id) !== $room_id_int;
        });
        if (!empty($wrong_room_messages)) {
            error_log("❌ セキュリティ: 重大なエラー！異なるroom_idのメッセージが含まれています。room_id={$room_id_int}");
            // 異なるroom_idのメッセージを除外
            $messages['messages'] = array_filter($messages['messages'], function($msg) use ($room_id_int) {
                return isset($msg->room_id) && intval($msg->room_id) === $room_id_int;
            });
            $messages['messages'] = array_values($messages['messages']);
            $messages['total'] = count($messages['messages']);
        }
    }

    return new WP_REST_Response($messages, 200);
}

function aidunite_send_chat_message($request) {
    $chat_id = $request->get_param('id');
    $params = $request->get_json_params();

    // プライバシー保護: room_idの検証を厳密に
    $room_id_int = intval($chat_id);
    if (!$room_id_int || $room_id_int <= 0) {
        error_log("❌ セキュリティ: 無効なroom_idでメッセージ送信を試みました。room_id={$chat_id}, user_id=" . get_current_user_id());
        return new WP_REST_Response(['error' => 'チャットルームIDが無効です'], 400);
    }

    // プライバシー保護: 権限チェック（必須）
    $user_id = get_current_user_id();
    if (!aidunite_check_chat_permission($room_id_int, $user_id)) {
        error_log("❌ セキュリティ: 権限のないユーザーがメッセージ送信を試みました。room_id={$room_id_int}, user_id={$user_id}");
        return new WP_REST_Response(['error' => 'アクセス権限がありません'], 403);
    }

    // プライバシー保護: パラメータのroom_idとURLのroom_idが一致することを確認
    $room_id_from_params = isset($params['room_id']) ? intval($params['room_id']) : null;
    if ($room_id_from_params && $room_id_from_params != $room_id_int) {
        error_log("❌ セキュリティ: URLパラメータのroom_id({$room_id_int})とbodyのroom_id({$room_id_from_params})が一致しません。送信を拒否します。");
        return new WP_REST_Response(['error' => 'ルームIDが一致しません'], 400);
    }

    // プライバシー保護: room_idはURLパラメータから取得した値のみを使用（bodyのroom_idは無視）
    $message_data = [
        'room_id' => $room_id_int,
        'sender_id' => get_current_user_id(),
        'message_type' => sanitize_text_field($params['message_type'] ?? 'text'),
        'content' => sanitize_textarea_field($params['content']),
        'file_url' => sanitize_url($params['file_url'] ?? ''),
        'is_private' => (bool) ($params['is_private'] ?? false)
    ];
    if (function_exists('aidunite_normalize_chat_payload')) {
        $message_data = aidunite_normalize_chat_payload($message_data);
    }
    if (!empty($params['parent_message_id'])) {
        $message_data['parent_message_id'] = absint($params['parent_message_id']);
    }

    $message_id = aidunite_save_chat_message($message_data);

    if (is_wp_error($message_id)) {
        return new WP_REST_Response(['error' => $message_id->get_error_message()], 400);
    }

    // SSE で配信
    do_action('aidunite_message_sent', intval($chat_id), $message_data);

    return new WP_REST_Response(['id' => $message_id, 'message' => 'メッセージを送信しました'], 201);
}

function aidunite_toggle_chat_reaction_rest($request) {
    $chat_id = (int) $request->get_param('id');
    $message_id = (int) $request->get_param('messageId');
    $params = $request->get_json_params() ?: [];
    $reaction_type = isset($params['reaction_type']) ? sanitize_text_field($params['reaction_type']) : '';

    if (!$chat_id || !$message_id) {
        return new WP_REST_Response(['error' => 'パラメータが無効です'], 400);
    }
    $allowed_reactions = ['thumb_up', 'favorite', 'ok', 'laugh', 'pray', 'fire', 'clap', 'heart', 'wow', 'sad', 'angry', 'check'];
    if (!in_array($reaction_type, $allowed_reactions, true)) {
        return new WP_REST_Response(['error' => 'reaction_type が不正です'], 400);
    }

    $user_id = get_current_user_id();
    if (!aidunite_check_chat_permission($chat_id, $user_id)) {
        return new WP_REST_Response(['error' => 'アクセス権限がありません'], 403);
    }

    $result = aidunite_toggle_chat_reaction($message_id, $user_id, $chat_id, $reaction_type);
    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => $result->get_error_message()], 400);
    }
    return new WP_REST_Response($result, 200);
}

function aidunite_mark_chat_read($request) {
    $chat_id = $request->get_param('id');
    $params = $request->get_json_params();

    // JSONボディから取得、なければクエリパラメータから取得
    $message_id = 0;
    if (!empty($params) && isset($params['message_id'])) {
        $message_id = intval($params['message_id']);
    } else {
        $message_id = intval($request->get_param('message_id'));
    }

    // nonce検証（クエリパラメータからも取得可能）
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce) {
        $nonce = $request->get_param('nonce');
    }

    if ($nonce && !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_REST_Response(['error' => 'セキュリティチェックに失敗しました'], 403);
    }

    $user_id = get_current_user_id();
    $room_id_int = (int) $chat_id;
    if ($room_id_int > 0 && !aidunite_check_chat_permission($room_id_int, $user_id)) {
        return new WP_REST_Response(['error' => 'アクセス権限がありません'], 403);
    }

    if ($message_id <= 0 && function_exists('aidunite_sync_chat_read_status_to_latest')) {
        $result = aidunite_sync_chat_read_status_to_latest($room_id_int, $user_id);
        if (!is_wp_error($result) && function_exists('aidunite_get_chat_room_latest_message_id')) {
            $message_id = aidunite_get_chat_room_latest_message_id($room_id_int);
        }
    } else {
        $result = aidunite_update_read_status($room_id_int, $user_id, $message_id);
    }

    if (is_wp_error($result)) {
        return new WP_REST_Response(['error' => $result->get_error_message()], 400);
    }

    // SSE で配信
    do_action('aidunite_read_status_updated', $room_id_int, $user_id, $message_id);

    return new WP_REST_Response(['message' => '既読状態を更新しました'], 200);
}

/**
 * チームメンバー一覧取得（チャットを始める用・自分を除く）
 */
function aidunite_get_team_members_for_chat($request) {
    $team_id = (int) $request->get_param('team_id');
    if (!$team_id) {
        return new WP_REST_Response(['error' => 'team_id が必要です'], 400);
    }

    $user_id = get_current_user_id();
    if (!function_exists('aidunite_check_team_membership') || !aidunite_check_team_membership($user_id, $team_id)) {
        return new WP_REST_Response(['error' => 'アクセス権限がありません'], 403);
    }

    if (!function_exists('aidunite_get_team_members_list')) {
        return new WP_REST_Response(['error' => 'チームメンバー取得機能が利用できません'], 501);
    }

    $members = aidunite_get_team_members_list($team_id);
    $list = [];
    foreach ($members as $m) {
        $uid = (int) ($m['user_id'] ?? 0);
        if ($uid <= 0 || $uid === $user_id) {
            continue;
        }
        $list[] = [
            'user_id' => $uid,
            'display_name' => isset($m['display_name']) ? $m['display_name'] : '',
        ];
    }

    return new WP_REST_Response(['members' => $list], 200);
}

/**
 * 参加者ベースのルーム作成API
 */
function aidunite_create_chat_room($request) {
    $params = $request->get_json_params();

    $participant_ids = isset($params['participants']) ? array_map('intval', $params['participants']) : [];
    $room_type = isset($params['type']) ? sanitize_text_field($params['type']) : 'group';
    $title = isset($params['title']) ? sanitize_text_field($params['title']) : null;
    if (function_exists('aidunite_normalize_chat_payload')) {
        $normalized = aidunite_normalize_chat_payload(['room_type' => $room_type]);
        $room_type = (string) ($normalized['room_type'] ?? $room_type);
    }

    if (empty($participant_ids)) {
        return new WP_REST_Response(['error' => '参加者が指定されていません'], 400);
    }

    // room_typeの検証
    $allowed_types = ['direct', 'group', 'match', 'system'];
    if (!in_array($room_type, $allowed_types)) {
        return new WP_REST_Response(['error' => '無効なルームタイプです'], 400);
    }

    // 参加者数による自動判定
    if (count($participant_ids) === 1) {
        $room_type = 'direct';
    } elseif (count($participant_ids) > 1) {
        $room_type = 'group';
    }

    $room_id = aidunite_create_or_get_chat_room_by_participants($participant_ids, $room_type, $title);

    if (is_wp_error($room_id)) {
        return new WP_REST_Response(['error' => $room_id->get_error_message()], 400);
    }

    return new WP_REST_Response(['room_id' => $room_id, 'message' => 'チャットルームを作成しました'], 201);
}

/**
 * チャットメッセージ検索API
 */
function aidunite_search_chat_messages($request) {
    $chat_id = $request->get_param('id');
    $search_term = $request->get_param('q');
    $page = $request->get_param('page') ?? 1;
    $per_page = $request->get_param('per_page') ?? 20;

    $room_id_int = intval($chat_id);
    if (!$room_id_int || $room_id_int <= 0) {
        return new WP_REST_Response(['error' => '無効なチャットルームIDです'], 400);
    }

    // 権限チェック
    $user_id = get_current_user_id();
    if (!aidunite_check_chat_permission($room_id_int, $user_id)) {
        return new WP_REST_Response(['error' => 'アクセス権限がありません'], 403);
    }

    // 検索実行
    $results = aidunite_search_chat_messages_data($room_id_int, $search_term, $page, $per_page);

    return new WP_REST_Response($results, 200);
}

/**
 * 試合情報取得API（試合情報パネル用）
 */
function aidunite_get_chat_match_info($request) {
    $chat_id = $request->get_param('id');
    $room_id_int = intval($chat_id);

    if (!$room_id_int || $room_id_int <= 0) {
        return new WP_REST_Response(['error' => '無効なチャットルームIDです'], 400);
    }

    // 権限チェック
    $user_id = get_current_user_id();
    if (!aidunite_check_chat_permission($room_id_int, $user_id)) {
        return new WP_REST_Response(['error' => 'アクセス権限がありません'], 403);
    }

    // 試合情報を取得
    $match_info = aidunite_get_match_info_from_chat_room($room_id_int);

    if (is_wp_error($match_info)) {
        return new WP_REST_Response(['error' => $match_info->get_error_message()], 400);
    }

    if (!$match_info) {
        return new WP_REST_Response(['error' => '試合情報が見つかりません'], 404);
    }

    return new WP_REST_Response($match_info, 200);
}

/**
 * メッセージ掲示板検索API
 */
function aidunite_search_team_messages($request) {
    $team_id = $request->get_param('team_id');
    $search_term = $request->get_param('q');
    $category = $request->get_param('category');
    $priority = $request->get_param('priority');
    $page = $request->get_param('page') ?? 1;
    $per_page = $request->get_param('per_page') ?? 20;

    // 権限チェック
    $user_id = get_current_user_id();
    if (!aidunite_check_team_membership($user_id, $team_id)) {
        return new WP_REST_Response(['error' => 'アクセス権限がありません'], 403);
    }

    // 検索実行
    $results = aidunite_search_messages($team_id, $search_term, $category, $priority, $page, $per_page);

    return new WP_REST_Response($results, 200);
}

// 権限チェック関数（統一ミドルウェア使用）
function aidunite_check_message_permission($request) {
    $result = AidUniteAuthMiddleware::rest_require($request, [
        'team_id' => null,
        'redirect' => false,
    ]);

    if (is_wp_error($result)) {
        return $result;
    }

    return true;
}

function aidunite_delete_chat_room($request) {
    $room_id = $request->get_param('id');
    $user_id = get_current_user_id();

    // プライバシー保護: room_idの検証を厳密に
    $room_id_int = intval($room_id);
    if (!$room_id_int || $room_id_int <= 0) {
        error_log("❌ セキュリティ: 無効なroom_idでチャットルーム削除を試みました。room_id={$room_id}, user_id={$user_id}");
        return new WP_REST_Response(['error' => 'チャットルームIDが無効です'], 400);
    }

    // プライバシー保護: 権限チェック（必須）
    if (!aidunite_check_chat_permission($room_id_int, $user_id)) {
        error_log("❌ セキュリティ: 権限のないユーザーがチャットルーム削除を試みました。room_id={$room_id_int}, user_id={$user_id}");
        return new WP_REST_Response(['error' => 'アクセス権限がありません'], 403);
    }

    // ユーザー単位の非表示化（データは保持）
    if (!function_exists('aidunite_hide_chat_room_for_user') || !aidunite_hide_chat_room_for_user($room_id_int, $user_id)) {
        return new WP_REST_Response(['error' => 'チャットルームの非表示化に失敗しました'], 500);
    }

    return new WP_REST_Response(['message' => 'チャットルームを削除しました'], 200);
}

/**
 * 管理者専用: 重複ルーム物理削除
 */
function aidunite_admin_dedupe_match_rooms($request) {
    $params = $request->get_json_params() ?: [];
    $keep_room_id = intval($params['keep_room_id'] ?? 0);
    $force_delete_room_ids = isset($params['force_delete_room_ids']) && is_array($params['force_delete_room_ids'])
        ? array_values(array_unique(array_filter(array_map('intval', $params['force_delete_room_ids']))))
        : [];

    if (!function_exists('aidunite_delete_duplicate_match_rooms_permanently')) {
        return new WP_REST_Response(['error' => 'dedupe function unavailable'], 500);
    }

    $report = aidunite_delete_duplicate_match_rooms_permanently($keep_room_id);

    // 明示指定のIDも追加削除（存在すれば）
    if (!empty($force_delete_room_ids)) {
        foreach ($force_delete_room_ids as $room_id) {
            if ($keep_room_id > 0 && $room_id === $keep_room_id) {
                continue;
            }
            $deleted = function_exists('aidunite_delete_chat_room_permanently')
                ? aidunite_delete_chat_room_permanently($room_id)
                : false;
            if (is_wp_error($deleted)) {
                $report['errors'][] = 'room_id=' . $room_id . ': ' . $deleted->get_error_message();
            } elseif ($deleted) {
                $report['deleted_rooms'][] = (int) $room_id;
            }
        }
        $report['deleted_rooms'] = array_values(array_unique(array_map('intval', $report['deleted_rooms'])));
    }

    return new WP_REST_Response([
        'success' => true,
        'report' => $report,
    ], 200);
}

/**
 * REST API用の権限チェック関数（統一ミドルウェアを使用）
 */
function aidunite_check_chat_permission_rest($request) {
    $result = AidUniteAuthMiddleware::rest_require($request, [
        'team_id' => null,
        'redirect' => false,
    ]);

    if (is_wp_error($result)) {
        return $result;
    }

    return true;
}

function aidunite_check_evaluation_permission($request) {
    $result = AidUniteAuthMiddleware::rest_require($request, [
        'team_id' => null,
        'redirect' => false,
    ]);

    if (is_wp_error($result)) {
        return $result;
    }

    return true;
}

function aidunite_check_rematch_permission($request) {
    $result = AidUniteAuthMiddleware::rest_require($request, [
        'team_id' => null,
        'redirect' => false,
    ]);

    if (is_wp_error($result)) {
        return $result;
    }

    return true;
}
