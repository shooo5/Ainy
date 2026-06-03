<?php
/**
 * メッセージ機能コア関数
 * MESSAGE-MVP-DESIGN.md に基づく実装
 */

// メッセージ掲示板関連関数

/**
 * チームメッセージを取得
 */
function aidunite_get_team_messages($team_id, $category = null, $priority = null, $page = 1, $per_page = 20) {
    global $wpdb;

    $table = $wpdb->prefix . 'team_messages';
    $offset = ($page - 1) * $per_page;

    $where_conditions = ['tm.team_id = %d', 'tm.status = %s'];
    $where_values = [$team_id, 'published'];

    if ($category) {
        $where_conditions[] = 'tm.category = %s';
        $where_values[] = $category;
    }

    if ($priority) {
        $where_conditions[] = 'tm.priority = %s';
        $where_values[] = $priority;
    }

    $where_clause = implode(' AND ', $where_conditions);

    $chat_rooms_table = $wpdb->prefix . 'chat_rooms';

    $sql = $wpdb->prepare("
        SELECT tm.*,
               u.display_name as author_name,
               cr.id as thread_room_id
        FROM $table tm
        LEFT JOIN {$wpdb->users} u ON tm.user_id = u.ID
        LEFT JOIN $chat_rooms_table cr
            ON cr.room_type = 'message_thread'
           AND cr.related_message_id = tm.id
        WHERE $where_clause
        ORDER BY tm.pinned DESC, tm.created_at DESC
        LIMIT %d OFFSET %d
    ", array_merge($where_values, [$per_page, $offset]));

    $messages = $wpdb->get_results($sql);

    // 総数を取得
    $count_sql = $wpdb->prepare("
        SELECT COUNT(*)
        FROM $table tm
        WHERE $where_clause
    ", $where_values);

    $total = $wpdb->get_var($count_sql);

    return [
        'messages' => $messages,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ];
}

/**
 * メッセージを保存
 */
function aidunite_save_message($message_data) {
    global $wpdb;

    $table = $wpdb->prefix . 'team_messages';

    $result = $wpdb->insert($table, [
        'team_id' => $message_data['team_id'],
        'user_id' => $message_data['user_id'],
        'title' => $message_data['title'],
        'content' => $message_data['content'],
        'category' => $message_data['category'],
        'priority' => $message_data['priority'],
        'pinned' => $message_data['pinned'],
        'status' => $message_data['status']
    ], [
        '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s'
    ]);

    if ($result === false) {
        return new WP_Error('db_error', 'メッセージの保存に失敗しました');
    }

    return $wpdb->insert_id;
}

/**
 * メッセージを更新
 */
function aidunite_update_message_data($message_id, $message_data) {
    global $wpdb;

    $table = $wpdb->prefix . 'team_messages';

    $result = $wpdb->update($table, [
        'title' => $message_data['title'],
        'content' => $message_data['content'],
        'category' => $message_data['category'],
        'priority' => $message_data['priority'],
        'pinned' => $message_data['pinned'],
        'updated_at' => current_time('mysql')
    ], [
        'id' => $message_id
    ], [
        '%s', '%s', '%s', '%s', '%d', '%s'
    ], [
        '%d'
    ]);

    if ($result === false) {
        return new WP_Error('db_error', 'メッセージの更新に失敗しました');
    }

    return true;
}

/**
 * メッセージ1件を取得（IDとuser_idのみ。権限チェック用）
 *
 * @param int $message_id メッセージID
 * @return object|null id, user_id を持つオブジェクト。存在しなければ null
 */
function aidunite_get_message_author_id($message_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'team_messages';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id FROM {$table} WHERE id = %d",
        intval($message_id)
    ));
}

/**
 * メッセージを削除
 */
function aidunite_delete_message_data($message_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'team_messages';

    $result = $wpdb->delete($table, ['id' => $message_id], ['%d']);

    if ($result === false) {
        return new WP_Error('db_error', 'メッセージの削除に失敗しました');
    }

    return true;
}

/**
 * メッセージの固定状態を切り替え
 */
function aidunite_toggle_message_pin_status($message_id, $pinned) {
    global $wpdb;

    $table = $wpdb->prefix . 'team_messages';

    $result = $wpdb->update($table, [
        'pinned' => $pinned,
        'updated_at' => current_time('mysql')
    ], [
        'id' => $message_id
    ], [
        '%d', '%s'
    ], [
        '%d'
    ]);

    if ($result === false) {
        return new WP_Error('db_error', '固定状態の更新に失敗しました');
    }

    return true;
}

/**
 * 自動メッセージ生成
 */
function aidunite_create_auto_message($team_id, $type, $action, $data) {
    $titles = [
        'schedule' => [
            'created' => '新しい練習スケジュールが登録されました',
            'updated' => '練習スケジュールが変更されました',
            'deleted' => '練習スケジュールが削除されました',
            'confirmed' => '練習スケジュールが確定されました'
        ],
        'match' => [
            'created' => 'vs' . $data['opponent_team'] . 'との試合が確定しました',
            'updated' => '試合情報が更新されました',
            'cancelled' => '試合がキャンセルされました'
        ],
        'result' => [
            'created' => '試合結果が登録されました',
            'updated' => '試合結果が更新されました'
        ],
        'attendance' => [
            'deadline' => '出欠確認の締切が近づいています',
            'unanswered' => '出欠確認が未回答の選手がいます',
            'completed' => '出欠確認が完了しました'
        ]
    ];

    $categories = [
        'schedule' => 'schedule',
        'match' => 'match',
        'result' => 'match',
        'attendance' => 'announcement'
    ];

    $priorities = [
        'schedule' => 'important',
        'match' => 'important',
        'result' => 'normal',
        'attendance' => 'urgent'
    ];

    $title = $titles[$type][$action] ?? 'システム通知';
    $category = $categories[$type] ?? 'announcement';
    $priority = $priorities[$type] ?? 'normal';

    $content = aidunite_generate_auto_message_content($type, $action, $data);

    $message_data = [
        'team_id' => $team_id,
        'user_id' => 0, // システム
        'title' => $title,
        'content' => $content,
        'category' => $category,
        'priority' => $priority,
        'pinned' => false,
        'status' => 'published'
    ];

    return aidunite_save_message($message_data);
}

/**
 * 自動メッセージの内容を生成
 */
function aidunite_generate_auto_message_content($type, $action, $data) {
    switch ($type) {
        case 'schedule':
            return sprintf(
                "練習日: %s\n時間: %s\n場所: %s\n詳細: %s",
                $data['date'] ?? '',
                $data['time'] ?? '',
                $data['venue'] ?? '',
                $data['description'] ?? ''
            );

        case 'match':
            return sprintf(
                "対戦相手: %s\n試合日: %s\n時間: %s\n会場: %s",
                $data['opponent_team'] ?? '',
                $data['match_date'] ?? '',
                $data['match_time'] ?? '',
                $data['venue'] ?? ''
            );

        case 'result':
            return sprintf(
                "試合結果: %s\nスコア: %s\n詳細: %s",
                $data['result'] ?? '',
                $data['score'] ?? '',
                $data['details'] ?? ''
            );

        case 'attendance':
            return sprintf(
                "対象: %s\n締切: %s\n未回答者: %s",
                $data['event'] ?? '',
                $data['deadline'] ?? '',
                $data['unanswered_count'] ?? 0
            );

        default:
            return 'システムからの通知です。';
    }
}

/**
 * メッセージ検索
 */
function aidunite_search_messages($team_id, $search_term, $category = null, $priority = null, $page = 1, $per_page = 20) {
    global $wpdb;

    $table = $wpdb->prefix . 'team_messages';
    $offset = ($page - 1) * $per_page;

    $where_conditions = [
        'team_id = %d',
        'status = %s',
        '(title LIKE %s OR content LIKE %s)'
    ];

    $where_values = [
        $team_id,
        'published',
        '%' . $wpdb->esc_like($search_term) . '%',
        '%' . $wpdb->esc_like($search_term) . '%'
    ];

    if ($category) {
        $where_conditions[] = 'category = %s';
        $where_values[] = $category;
    }

    if ($priority) {
        $where_conditions[] = 'priority = %s';
        $where_values[] = $priority;
    }

    $where_clause = implode(' AND ', $where_conditions);

    $sql = $wpdb->prepare("
        SELECT tm.*, u.display_name as author_name
        FROM $table tm
        LEFT JOIN {$wpdb->users} u ON tm.user_id = u.ID
        WHERE $where_clause
        ORDER BY tm.pinned DESC, tm.created_at DESC
        LIMIT %d OFFSET %d
    ", array_merge($where_values, [$per_page, $offset]));

    $messages = $wpdb->get_results($sql);

    // 総数を取得
    $count_sql = $wpdb->prepare("
        SELECT COUNT(*)
        FROM $table
        WHERE $where_clause
    ", $where_values);

    $total = $wpdb->get_var($count_sql);

    return [
        'messages' => $messages,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page),
        'search_term' => $search_term
    ];
}

/**
 * メッセージ統計を取得
 */
function aidunite_get_message_stats($team_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'team_messages';

    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT
            COUNT(*) as total_messages,
            SUM(CASE WHEN pinned = 1 THEN 1 ELSE 0 END) as pinned_messages,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_messages,
            SUM(CASE WHEN priority = 'important' THEN 1 ELSE 0 END) as important_messages,
            SUM(CASE WHEN category = 'schedule' THEN 1 ELSE 0 END) as schedule_messages,
            SUM(CASE WHEN category = 'match' THEN 1 ELSE 0 END) as match_messages,
            SUM(CASE WHEN category = 'practice' THEN 1 ELSE 0 END) as practice_messages,
            SUM(CASE WHEN category = 'announcement' THEN 1 ELSE 0 END) as announcement_messages
        FROM $table
        WHERE team_id = %d AND status = 'published'
    ", $team_id));

    return $stats;
}

/**
 * メッセージを取得
 */
function aidunite_get_message($message_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'team_messages';

    $sql = $wpdb->prepare("
        SELECT tm.*, u.display_name as author_name
        FROM $table tm
        LEFT JOIN {$wpdb->users} u ON tm.user_id = u.ID
        WHERE tm.id = %d
    ", $message_id);

    return $wpdb->get_row($sql);
}
