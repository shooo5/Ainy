<?php
/**
 * Server-Sent Events (SSE) 機能
 * MESSAGE-MVP-DESIGN.md に基づく実装
 */


/**
 * SSEデータを送信
 */
function aidunite_send_sse_data($event_type, $data) {
    // XSS対策: event_typeに改行文字が含まれないことを確認（SSEプロトコル要件）
    $event_type = str_replace(["\r", "\n"], '', $event_type);
    // 許可されたイベントタイプのみ許可（セキュリティ強化）
    $allowed_events = ['connected', 'heartbeat', 'message', 'chat_message', 'read_status', 'error'];
    if (!in_array($event_type, $allowed_events, true)) {
        $event_type = 'error';
    }
    echo "event: $event_type\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

/**
 * 新しいメッセージを取得
 */
function aidunite_get_new_messages($team_id, $user_id) {
    global $wpdb;

    $messages_table = $wpdb->prefix . 'team_messages';
    $read_status_table = $wpdb->prefix . 'message_read_status';

    // 最後に読み込んだ時刻を取得
    $last_read = $wpdb->get_var($wpdb->prepare("
        SELECT last_read_at FROM $read_status_table
        WHERE team_id = %d AND user_id = %d
    ", $team_id, $user_id));

    $since = $last_read ?: date('Y-m-d H:i:s', strtotime('-1 hour'));

    $sql = $wpdb->prepare("
        SELECT
            tm.*,
            u.display_name as author_name
        FROM $messages_table tm
        LEFT JOIN {$wpdb->users} u ON tm.user_id = u.ID
        WHERE tm.team_id = %d
        AND tm.status = 'published'
        AND tm.created_at > %s
        ORDER BY tm.created_at ASC
    ", $team_id, $since);

    $messages = $wpdb->get_results($sql);

    // 既読状態を更新
    if (!empty($messages)) {
        aidunite_update_message_read_status($team_id, $user_id);
    }

    return $messages;
}

/**
 * 新しいチャットメッセージを取得
 */
function aidunite_get_new_chat_messages($room_id, $user_id) {
    global $wpdb;

    $messages_table = $wpdb->prefix . 'chat_messages';
    $read_status_table = $wpdb->prefix . 'chat_read_status';

    // 最後に読み込んだメッセージIDを取得
    $last_read_id = $wpdb->get_var($wpdb->prepare("
        SELECT last_read_message_id FROM $read_status_table
        WHERE room_id = %d AND user_id = %d
    ", $room_id, $user_id));

    // room_idで確実にフィルタリング
    $sql = $wpdb->prepare("
        SELECT
            m.*,
            u.display_name as sender_name
        FROM $messages_table m
        LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
        WHERE m.room_id = %d
        AND m.id > %d
        ORDER BY m.created_at ASC
    ", $room_id, $last_read_id ?: 0);

    $messages = $wpdb->get_results($sql);

    // room_idが含まれていることを確認（念のため）
    foreach ($messages as $message) {
        if (!isset($message->room_id)) {
            $message->room_id = $room_id;
        } elseif ($message->room_id != $room_id) {
            aidunite_log("重大なエラー: データベースから取得したメッセージのroom_idが一致しません。期待: {$room_id}, 実際: {$message->room_id}", 'error');
        }
    }

    return $messages;
}

/**
 * 既読状態の更新を取得
 */
function aidunite_get_read_status_updates($room_id, $user_id) {
    global $wpdb;

    $read_status_table = $wpdb->prefix . 'chat_read_status';

    // 最後にチェックした時刻を取得（簡易実装）
    $last_check = get_transient("aidunite_read_check_{$room_id}_{$user_id}");

    if (!$last_check) {
        $last_check = date('Y-m-d H:i:s', strtotime('-1 hour'));
    }

    $sql = $wpdb->prepare("
        SELECT
            rs.*,
            u.display_name as user_name
        FROM $read_status_table rs
        LEFT JOIN {$wpdb->users} u ON rs.user_id = u.ID
        WHERE rs.room_id = %d
        AND rs.user_id != %d
        AND rs.last_read_at > %s
    ", $room_id, $user_id, $last_check);

    $updates = $wpdb->get_results($sql);

    // チェック時刻を更新
    set_transient("aidunite_read_check_{$room_id}_{$user_id}", current_time('mysql'), 300);

    return $updates;
}

/**
 * メッセージをブロードキャスト
 */
function aidunite_broadcast_message($room_id, $message_data) {
    // 接続中のクライアントにメッセージを配信
    // 実際の実装では、Redisやメッセージキューを使用することを推奨

    $broadcast_data = [
        'type' => 'message',
        'room_id' => $room_id,
        'message' => $message_data,
        'timestamp' => current_time('mysql')
    ];

    // 一時的にファイルに保存（実際の実装では適切なストレージを使用）
    $cache_key = "aidunite_broadcast_{$room_id}_" . time();
    set_transient($cache_key, $broadcast_data, 60);

    aidunite_log("メッセージをブロードキャストしました (Room ID: $room_id)");
}

/**
 * 既読状態をブロードキャスト
 */
function aidunite_broadcast_read_status($room_id, $user_id, $message_id) {
    $broadcast_data = [
        'type' => 'read_status',
        'room_id' => $room_id,
        'user_id' => $user_id,
        'message_id' => $message_id,
        'timestamp' => current_time('mysql')
    ];

    // 一時的にファイルに保存
    $cache_key = "aidunite_read_broadcast_{$room_id}_" . time();
    set_transient($cache_key, $broadcast_data, 60);

    aidunite_log("既読状態をブロードキャストしました (Room ID: $room_id, User ID: $user_id)");
}

/**
 * メッセージの既読状態を更新
 */
function aidunite_update_message_read_status($team_id, $user_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'message_read_status';

    $wpdb->replace($table, [
        'team_id' => $team_id,
        'user_id' => $user_id,
        'last_read_at' => current_time('mysql')
    ], [
        '%d', '%d', '%s'
    ]);
}

/**
 * チームメンバーシップをチェック
 */
function aidunite_check_team_membership($user_id, $team_id) {
    global $wpdb;

    $result = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->usermeta}
        WHERE user_id = %d AND meta_key = 'team_id' AND meta_value = %d
    ", $user_id, $team_id));

    return $result > 0;
}

/**
 * SSE接続統計を取得
 */
function aidunite_get_sse_stats() {
    // 簡易実装：実際の実装では適切な統計収集を行う
    return [
        'active_connections' => 0,
        'total_messages_sent' => 0,
        'last_activity' => current_time('mysql')
    ];
}

/**
 * 接続をテスト
 */
function aidunite_test_sse_connection() {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    echo "event: test\n";
    echo "data: " . json_encode(['status' => 'connected', 'timestamp' => current_time('mysql')]) . "\n\n";
    flush();

    exit;
}

/**
 * メッセージ掲示板用SSEストリーム（REST API用）
 */
function aidunite_sse_message_stream($request) {
    // 出力バッファを無効化
    if (ob_get_level()) {
        ob_end_clean();
    }

    $team_id = $request->get_param('team_id');
    $user_id = get_current_user_id();
    $nonce = $request->get_param('nonce');

    // パラメータ検証
    if (!$team_id) {
        http_response_code(400);
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'team_idが必要です']) . "\n\n";
        flush();
        exit;
    }

    // CSRF対策（統一版）
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        http_response_code(401);
        echo "event: error\n";
        echo "data: " . json_encode(['error' => '認証に失敗しました']) . "\n\n";
        flush();
        exit;
    }

    // ログインチェック
    if (!$user_id) {
        http_response_code(401);
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'ログインが必要です']) . "\n\n";
        flush();
        exit;
    }

    // 権限チェック
    if (!aidunite_check_team_membership($user_id, $team_id)) {
        http_response_code(403);
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'アクセス権限がありません']) . "\n\n";
        flush();
        exit;
    }

    // SSE用のヘッダーを設定
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Nginx用
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Cache-Control');

    // 接続開始を通知
    aidunite_send_sse_data('connected', [
        'team_id' => intval($team_id),
        'user_id' => $user_id,
        'timestamp' => current_time('mysql')
    ]);

    $heartbeat_counter = 0;

    // 接続を維持
    while (true) {
        // 接続が切れていないかチェック
        if (connection_aborted()) {
            break;
        }

        // ハートビート（30秒ごと）
        $heartbeat_counter++;
        if ($heartbeat_counter >= 30) {
            aidunite_send_sse_data('heartbeat', [
                'timestamp' => current_time('mysql')
            ]);
            $heartbeat_counter = 0;
        }

        // 新しいメッセージをチェック
        $new_messages = aidunite_get_new_messages($team_id, $user_id);

        if (!empty($new_messages)) {
            foreach ($new_messages as $message) {
                aidunite_send_sse_data('message', $message);
            }
        }

        // 1秒待機
        sleep(1);
    }

    exit;
}

/**
 * チャット用SSEストリーム（REST API用）
 */
function aidunite_sse_chat_stream($request) {
    // 出力バッファを無効化
    if (ob_get_level()) {
        ob_end_clean();
    }

    $room_id = $request->get_param('id');
    $user_id = get_current_user_id();
    $nonce = $request->get_param('nonce');

    // パラメータ検証
    if (!$room_id) {
        http_response_code(400);
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'room_idが必要です']) . "\n\n";
        flush();
        exit;
    }

    // CSRF対策（統一版）
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        http_response_code(401);
        echo "event: error\n";
        echo "data: " . json_encode(['error' => '認証に失敗しました']) . "\n\n";
        flush();
        exit;
    }

    // ログインチェック
    if (!$user_id) {
        http_response_code(401);
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'ログインが必要です']) . "\n\n";
        flush();
        exit;
    }

    // 権限チェック
    if (!aidunite_check_chat_permission($room_id, $user_id)) {
        http_response_code(403);
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'アクセス権限がありません']) . "\n\n";
        flush();
        exit;
    }

    // SSE用のヘッダーを設定
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Nginx用
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Cache-Control');

    // 接続開始を通知
    aidunite_send_sse_data('connected', [
        'room_id' => intval($room_id),
        'user_id' => $user_id,
        'timestamp' => current_time('mysql')
    ]);

    $last_message_id = 0;
    $heartbeat_counter = 0;

    // 接続を維持
    while (true) {
        // 接続が切れていないかチェック
        if (connection_aborted()) {
            break;
        }

        // ハートビート（30秒ごと）
        $heartbeat_counter++;
        if ($heartbeat_counter >= 30) {
            aidunite_send_sse_data('heartbeat', [
                'timestamp' => current_time('mysql')
            ]);
            $heartbeat_counter = 0;
        }

        // 新しいメッセージをチェック
        $new_messages = aidunite_get_new_chat_messages($room_id, $user_id);

        if (!empty($new_messages)) {
            $max_message_id = 0;
            foreach ($new_messages as $message) {
                // プライバシー保護: room_idが正しいことを厳密に確認
                if (!isset($message->room_id)) {
                    error_log("❌ セキュリティ: SSE配信時、メッセージID {$message->id} にroom_idが含まれていません。除外します。");
                    continue;
                }

                if (intval($message->room_id) !== intval($room_id)) {
                    error_log("❌ セキュリティ: SSE配信時、異なるroom_idのメッセージを検出しました。期待: {$room_id}, 実際: {$message->room_id}, message_id={$message->id}。除外します。");
                    continue;
                }

                // メッセージデータを正しい形式で送信
                $message_data = [
                    'type' => 'chat_message',
                    'message' => $message,
                    'room_id' => intval($room_id)
                ];
                aidunite_send_sse_data('chat_message', $message_data);

                // 最大メッセージIDを記録
                if (isset($message->id) && $message->id > $max_message_id) {
                    $max_message_id = $message->id;
                }
            }

            // 最後に読み込んだメッセージIDを更新
            if ($max_message_id > 0) {
                aidunite_update_read_status($room_id, $user_id, $max_message_id);
            }
        }

        // 既読状態の更新をチェック
        $read_updates = aidunite_get_read_status_updates($room_id, $user_id);

        if (!empty($read_updates)) {
            foreach ($read_updates as $update) {
                // room_idが正しいことを確認
                if (isset($update->room_id) && $update->room_id != $room_id) {
                    continue;
                }
                aidunite_send_sse_data('read_status', $update);
            }
        }

        // 1秒待機
        sleep(1);
    }

    exit;
}

// テスト用エンドポイント
add_action('wp_ajax_aidunite_test_sse', 'aidunite_test_sse_connection');
add_action('wp_ajax_nopriv_aidunite_test_sse', 'aidunite_test_sse_connection');
