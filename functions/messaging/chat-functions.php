<?php

/**
 * チャットルームを取得
 */
function aidunite_get_chat_room($room_id) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $room_id_int = intval($room_id);
    if (!$room_id_int) return null;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$rooms_table} WHERE id = %d", $room_id_int));
}

/**
 * ユーザーごとの非表示チャットID一覧を取得
 * @param int $user_id
 * @return int[]
 */
function aidunite_get_hidden_chat_room_ids($user_id) {
    $user_id_int = intval($user_id);
    if ($user_id_int <= 0) {
        return [];
    }

    $hidden = get_user_meta($user_id_int, 'aidunite_hidden_chat_rooms', true);
    if (!is_array($hidden)) {
        if (is_string($hidden) && $hidden !== '') {
            $hidden = explode(',', $hidden);
        } else {
            $hidden = [];
        }
    }

    $hidden = array_values(array_unique(array_filter(array_map('intval', $hidden))));
    return $hidden;
}

/**
 * ユーザーごとにチャットを非表示化（論理削除）
 * @param int $room_id
 * @param int $user_id
 * @return bool
 */
function aidunite_hide_chat_room_for_user($room_id, $user_id) {
    $room_id_int = intval($room_id);
    $user_id_int = intval($user_id);
    if ($room_id_int <= 0 || $user_id_int <= 0) {
        return false;
    }

    $hidden = aidunite_get_hidden_chat_room_ids($user_id_int);
    if (in_array($room_id_int, $hidden, true)) {
        return true;
    }
    $hidden[] = $room_id_int;
    return (bool) update_user_meta($user_id_int, 'aidunite_hidden_chat_rooms', array_values($hidden));
}

/**
 * ユーザーごとの非表示チャットから指定ルームを解除
 * @param int $room_id
 * @param int $user_id
 * @return bool
 */
/**
 * chat_rooms.merged_into_room_id カラムが利用可能か
 *
 * @return bool
 */
function aidunite_chat_rooms_has_merged_into_column() {
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$rooms_table} LIKE '%'");
    $has = is_array($columns) && in_array('merged_into_room_id', $columns, true);
    return $has;
}

/**
 * ルーム ID 一覧のうち、統合済み副ルーム（merged_into あり）の ID を返す
 *
 * @param int[] $room_ids
 * @return int[]
 */
function aidunite_get_merged_satellite_chat_room_ids($room_ids) {
    $room_ids = array_values(array_unique(array_filter(array_map('intval', (array) $room_ids))));
    if ($room_ids === [] || !aidunite_chat_rooms_has_merged_into_column()) {
        return [];
    }
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $ph = implode(',', array_fill(0, count($room_ids), '%d'));
    $sql = "SELECT id FROM {$rooms_table}
            WHERE id IN ($ph)
              AND merged_into_room_id IS NOT NULL
              AND merged_into_room_id > 0";
    $merged = $wpdb->get_col($wpdb->prepare($sql, $room_ids));
    return array_values(array_unique(array_filter(array_map('intval', (array) $merged))));
}

if (!function_exists('aidunite_resolve_canonical_chat_room_id')) {
    /**
     * 統合副ルームなら正規ルーム ID を返す（連鎖 merged_into を辿る）
     *
     * @param int $room_id
     * @return int 0=未解決
     */
    function aidunite_resolve_canonical_chat_room_id($room_id) {
        $room_id = (int) $room_id;
        if ($room_id <= 0) {
            return 0;
        }
        if (!aidunite_chat_rooms_has_merged_into_column()) {
            return $room_id;
        }
        $seen = [];
        while ($room_id > 0 && !isset($seen[$room_id])) {
            $seen[$room_id] = true;
            $room = aidunite_get_chat_room($room_id);
            if (!$room) {
                return $room_id;
            }
            $merged = (int) ($room->merged_into_room_id ?? 0);
            if ($merged <= 0 || $merged === $room_id) {
                return $room_id;
            }
            $room_id = $merged;
        }

        return $room_id;
    }
}

/**
 * チーム参加のシステムメッセージ文言を生成（create_or_extend 共通）
 *
 * @param int[] $team_ids
 * @return string
 */
function aidunite_build_chat_team_join_system_message($team_ids) {
    $team_ids = array_values(array_unique(array_filter(array_map('intval', (array) $team_ids))));
    if ($team_ids === []) {
        return 'チームが参加しました';
    }
    $names = [];
    foreach ($team_ids as $tid) {
        $name = function_exists('aidunite_get_team_name') ? aidunite_get_team_name($tid) : get_the_title($tid);
        if (!empty($name)) {
            $names[] = $name;
        }
    }
    if (count($names) === 1) {
        return $names[0] . 'が参加しました';
    }
    if (count($names) >= 2) {
        return implode('、', $names) . 'が参加しました';
    }
    return 'チームが参加しました';
}

function aidunite_unhide_chat_room_for_user($room_id, $user_id) {
    $room_id_int = intval($room_id);
    $user_id_int = intval($user_id);
    if ($room_id_int <= 0 || $user_id_int <= 0) {
        return false;
    }

    $hidden = aidunite_get_hidden_chat_room_ids($user_id_int);
    if (!in_array($room_id_int, $hidden, true)) {
        return true;
    }

    $new_hidden = array_values(array_filter($hidden, function ($id) use ($room_id_int) {
        return (int) $id !== $room_id_int;
    }));
    return (bool) update_user_meta($user_id_int, 'aidunite_hidden_chat_rooms', $new_hidden);
}

/**
 * 旧データ互換: 無効/空の room status を active に補正
 * @param int $room_id
 * @return bool
 */
function aidunite_restore_chat_room_status_if_invalid($room_id) {
    global $wpdb;
    $room_id_int = intval($room_id);
    if ($room_id_int <= 0) {
        return false;
    }

    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$rooms_table} WHERE id = %d", $room_id_int));
    $status = is_string($status) ? trim($status) : '';
    if (in_array($status, ['active', 'completed', 'archived'], true)) {
        return true;
    }

    // 旧実装の deleted / 空文字 / null を active に戻す
    $updated = $wpdb->update(
        $rooms_table,
        ['status' => 'active'],
        ['id' => $room_id_int],
        ['%s'],
        ['%d']
    );

    return $updated !== false;
}

/**
 * チームチャットルームを取得
 */
function aidunite_get_team_chat_room($team_id) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $team_id_int = intval($team_id);
    if (!$team_id_int) return null;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$rooms_table} WHERE room_type = 'team' AND team_id = %d LIMIT 1", $team_id_int));
}

/**
 * チームチャットを作成
 */
function aidunite_create_team_chat($team_id) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $participants_table = $wpdb->prefix . 'chat_participants';

    $team_id_int = intval($team_id);
    if (!$team_id_int) return new WP_Error('invalid_team_id', 'チームIDが無効です');

    // 既存があれば返す
    $existing = aidunite_get_team_chat_room($team_id_int);
    if ($existing) return intval($existing->id);

    $result = $wpdb->insert($rooms_table, [
        'room_type' => 'team',
        'name' => 'チームチャット',
        'description' => 'チーム内のコミュニケーション',
        'team_id' => $team_id_int,
        'status' => 'active',
        'created_at' => current_time('mysql')
    ], ['%s','%s','%s','%d','%s','%s']);

    if ($result === false) {
        return new WP_Error('db_error', 'チームチャットの作成に失敗しました: ' . $wpdb->last_error);
    }

    $room_id = intval($wpdb->insert_id);

    // チームメンバーを参加者に追加（存在すれば）
    if (function_exists('aidunite_get_team_members')) {
        $team_members = aidunite_get_team_members($team_id_int);
        if (!empty($team_members)) {
            foreach ($team_members as $member) {
                $wpdb->insert($participants_table, [
                    'room_id' => $room_id,
                    'user_id' => intval($member->user_id),
                    'role' => ($member->role === 'admin' ? 'admin' : 'member')
                ], ['%d','%d','%s']);
            }
        }
    }

    return $room_id;
}

/**
 * 対戦チャットルームを取得
 */
if (!function_exists('aidunite_get_match_request_bound_chat_room')) {
    /**
     * 当該 MR に直接紐づくルームのみ（共有ゲームの正規ルームは含めない）
     *
     * @param int $match_id match_request ID
     * @return object|null
     */
    function aidunite_get_match_request_bound_chat_room($match_id) {
        global $wpdb;
        $match_id_int = (int) $match_id;
        if ($match_id_int <= 0) {
            return null;
        }

        $meta_room_id = (int) get_post_meta($match_id_int, 'chat_room_id', true);
        if ($meta_room_id > 0) {
            $meta_room = aidunite_get_chat_room($meta_room_id);
            if ($meta_room) {
                return $meta_room;
            }
        }

        $rooms_table = $wpdb->prefix . 'chat_rooms';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$rooms_table}
             WHERE room_type IN ('match', 'group')
               AND match_id = %d
             ORDER BY (CASE WHEN status = 'active' THEN 1 ELSE 0 END) DESC, id DESC
             LIMIT 1",
            $match_id_int
        ));
    }
}

if (!function_exists('aidunite_get_game_chat_room_for_match_request')) {
    /**
     * 画面表示・チャット遷移用：同一募集の正規ルーム（3チーム統合後）を優先
     *
     * @param int $match_id match_request ID
     * @return object|null
     */
    function aidunite_get_game_chat_room_for_match_request($match_id) {
        $match_id_int = (int) $match_id;
        if ($match_id_int <= 0) {
            return null;
        }

        $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
            ? (int) aidunite_resolve_match_game_id_for_match_request($match_id_int)
            : (int) get_post_meta($match_id_int, 'to_schedule_id', true);
        if ($match_game_id > 0 && $match_game_id !== 9999) {
            if (function_exists('aidunite_get_active_chat_room_for_match_game')) {
                $active = aidunite_get_active_chat_room_for_match_game($match_game_id);
                if ($active && !empty($active->id)) {
                    $bound = aidunite_get_match_request_bound_chat_room($match_id_int);
                    if (!$bound || empty($bound->id) || (int) $bound->id !== (int) $active->id) {
                        return $active;
                    }

                    return $bound;
                }
            }
        }

        $bound = aidunite_get_match_request_bound_chat_room($match_id_int);
        if (!$bound || empty($bound->id)) {
            return $bound;
        }
        if (function_exists('aidunite_resolve_canonical_chat_room_id')) {
            $canonical_id = (int) aidunite_resolve_canonical_chat_room_id((int) $bound->id);
            if ($canonical_id > 0 && $canonical_id !== (int) $bound->id) {
                $canonical = aidunite_get_chat_room($canonical_id);

                return $canonical ?: $bound;
            }
        }

        return $bound;
    }
}

/**
 * @param int $match_id match_request ID
 * @return object|null
 */
function aidunite_get_match_chat_room($match_id) {
    return aidunite_get_match_request_bound_chat_room((int) $match_id);
}

/**
 * 対戦チャットルームを取得（active限定）
 * - 再申請など「新規作成/再利用判定」で利用する
 * - 履歴閲覧互換のため、既存 aidunite_get_match_chat_room は変更しない
 */
function aidunite_get_active_match_chat_room($match_id) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $match_id_int = intval($match_id);
    if (!$match_id_int) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$rooms_table}
         WHERE room_type = 'match'
           AND match_id = %d
           AND status = 'active'
         LIMIT 1",
        $match_id_int
    ));
}

/**
 * マッチチャットを完了状態へ変更する（必要時のみ）
 * @param int $match_id match_request ID
 * @param string $reason completed 理由（canceled / feedback_completed 等）
 * @return bool true: 変更あり/既に完了, false: 対象なし・更新失敗
 */
function aidunite_mark_match_chat_completed($match_id, $reason = '') {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $match_id_int = intval($match_id);
    if ($match_id_int <= 0) {
        return false;
    }

    // 試合確定中の MR のチャットを誤って閉じない（キャンセル経路では先に status が canceled へ変わる）
    $mr = get_post($match_id_int);
    if ($mr && $mr->post_type === 'match_request') {
        $mr_meta = (string) get_post_meta($match_id_int, 'status', true);
        $mr_norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($mr_meta, (string) $mr->post_status)
            : strtolower($mr_meta);
        if ($mr_norm === 'established') {
            return false;
        }
    }

    $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
        ? (int) aidunite_resolve_match_game_id_for_match_request($match_id_int)
        : (int) get_post_meta($match_id_int, 'to_schedule_id', true);
    if (
        $match_game_id > 0
        && $match_game_id !== 9999
        && function_exists('aidunite_count_game_established_match_requests')
        && aidunite_count_game_established_match_requests($match_game_id, $match_id_int) > 0
    ) {
        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('chat_mark_completed_skip_shared', [
                'match_request_id' => $match_id_int,
                'match_game_id'    => $match_game_id,
                'reason'           => (string) $reason,
            ]);
        }

        return true;
    }

    $room = function_exists('aidunite_get_match_request_bound_chat_room')
        ? aidunite_get_match_request_bound_chat_room($match_id_int)
        : aidunite_get_match_chat_room($match_id_int);
    if (!is_object($room) || empty($room->id)) {
        return false;
    }

    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('chat_mark_completed', [
            'match_request_id' => $match_id_int,
            'reason'           => (string) $reason,
            'room_id'          => isset($room->id) ? (int) $room->id : 0,
        ]);
    }

    $room_id_int = intval($room->id);
    $current_status = isset($room->status) ? (string) $room->status : '';
    if ($current_status === 'completed') {
        return true;
    }

    $updated = $wpdb->update(
        $rooms_table,
        ['status' => 'completed'],
        ['id' => $room_id_int],
        ['%s'],
        ['%d']
    );
    if ($updated === false) {
        return false;
    }

    // 完了理由を追跡しやすくするため、system メッセージで記録する
    if (function_exists('aidunite_save_chat_message')) {
        $reason_map = [
            'canceled' => 'この試合はキャンセルとなったため、チャットを完了済みにしました。',
            'feedback_completed' => '両チームの試合後アンケートが完了したため、チャットを完了済みにしました。',
        ];
        $content = isset($reason_map[$reason]) ? $reason_map[$reason] : 'このチャットは完了済みになりました。';
        aidunite_save_chat_message([
            'room_id' => $room_id_int,
            'sender_id' => 0,
            'message_type' => 'system',
            'content' => $content
        ]);
    }

    return true;
}

/**
 * 既存データ補正: 重複している active の match/group ルームを整理する
 * - 同一 match_id の active 重複: 最新IDのみ active、他は completed
 * - 同一 schedule_id + teamペアの active 重複: 最新IDのみ active、他は completed
 *
 * @return int completed へ更新した件数
 */
function aidunite_cleanup_duplicate_active_match_rooms() {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $completed_count = 0;

    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$rooms_table} LIKE '%'");
    $has_schedule_id = in_array('schedule_id', $columns, true);

    // 1) match_id 単位の重複
    $duplicate_groups = $wpdb->get_results(
        "SELECT match_id, MAX(id) AS keep_id
         FROM {$rooms_table}
         WHERE room_type IN ('match', 'group')
           AND status = 'active'
           AND match_id IS NOT NULL
           AND match_id > 0
         GROUP BY match_id
         HAVING COUNT(*) > 1"
    );
    if (!empty($duplicate_groups)) {
        foreach ($duplicate_groups as $group) {
            $match_id = (int) ($group->match_id ?? 0);
            $keep_id = (int) ($group->keep_id ?? 0);
            if ($match_id <= 0 || $keep_id <= 0) {
                continue;
            }
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$rooms_table}
                 SET status = 'completed'
                 WHERE room_type IN ('match', 'group')
                   AND status = 'active'
                   AND match_id = %d
                   AND id <> %d",
                $match_id,
                $keep_id
            ));
            if (is_numeric($updated) && (int) $updated > 0) {
                $completed_count += (int) $updated;
            }
        }
    }

    // 2) schedule_id + teamペア単位の重複（match_idが異なる重複にも対応）
    if ($has_schedule_id) {
        $pair_groups = $wpdb->get_results(
            "SELECT
                schedule_id,
                LEAST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) AS team_min,
                GREATEST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) AS team_max,
                MAX(id) AS keep_id
             FROM {$rooms_table}
             WHERE room_type IN ('match', 'group')
               AND status = 'active'
               AND schedule_id IS NOT NULL
               AND schedule_id > 0
             GROUP BY schedule_id, team_min, team_max
             HAVING COUNT(*) > 1"
        );
        if (!empty($pair_groups)) {
            foreach ($pair_groups as $group) {
                $schedule_id = (int) ($group->schedule_id ?? 0);
                $team_min = (int) ($group->team_min ?? 0);
                $team_max = (int) ($group->team_max ?? 0);
                $keep_id = (int) ($group->keep_id ?? 0);
                if ($schedule_id <= 0 || $keep_id <= 0) {
                    continue;
                }

                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$rooms_table}
                     SET status = 'completed'
                     WHERE room_type IN ('match', 'group')
                       AND status = 'active'
                       AND schedule_id = %d
                       AND LEAST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) = %d
                       AND GREATEST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) = %d
                       AND id <> %d",
                    $schedule_id,
                    $team_min,
                    $team_max,
                    $keep_id
                ));
                if (is_numeric($updated) && (int) $updated > 0) {
                    $completed_count += (int) $updated;
                }
            }
        }
    }

    // 3) teamペア単位の重複（schedule_id が異なる重複も整理）
    $team_pair_groups = $wpdb->get_results(
        "SELECT
            LEAST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) AS team_min,
            GREATEST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) AS team_max,
            MAX(id) AS keep_id
         FROM {$rooms_table}
         WHERE room_type IN ('match', 'group')
           AND status = 'active'
           AND COALESCE(team_a_id, 0) > 0
           AND COALESCE(team_b_id, 0) > 0
         GROUP BY team_min, team_max
         HAVING COUNT(*) > 1"
    );
    if (!empty($team_pair_groups)) {
        foreach ($team_pair_groups as $group) {
            $team_min = (int) ($group->team_min ?? 0);
            $team_max = (int) ($group->team_max ?? 0);
            $keep_id = (int) ($group->keep_id ?? 0);
            if ($team_min <= 0 || $team_max <= 0 || $keep_id <= 0) {
                continue;
            }

            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$rooms_table}
                 SET status = 'completed'
                 WHERE room_type IN ('match', 'group')
                   AND status = 'active'
                   AND LEAST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) = %d
                   AND GREATEST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) = %d
                   AND id <> %d",
                $team_min,
                $team_max,
                $keep_id
            ));
            if (is_numeric($updated) && (int) $updated > 0) {
                $completed_count += (int) $updated;
            }
        }
    }

    return $completed_count;
}

/**
 * 重複 match/group ルームを物理削除（管理用途）
 * - 同一チームペアの active で最新1件のみ残す
 * - keep_room_id 指定時は常に残す
 *
 * @param int $keep_room_id
 * @return array{target_room_ids:int[],deleted_rooms:int[],errors:string[]}
 */
function aidunite_delete_duplicate_match_rooms_permanently($keep_room_id = 0) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $messages_table = $wpdb->prefix . 'chat_messages';
    $participants_table = $wpdb->prefix . 'chat_participants';
    $read_status_table = $wpdb->prefix . 'chat_read_status';
    $reactions_table = $wpdb->prefix . 'chat_message_reactions';

    $keep_room_id = intval($keep_room_id);
    $delete_ids = [];

    $pair_groups = $wpdb->get_results(
        "SELECT
            LEAST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) AS team_min,
            GREATEST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) AS team_max,
            MAX(id) AS keep_id
         FROM {$rooms_table}
         WHERE room_type IN ('match', 'group')
           AND status = 'active'
           AND COALESCE(team_a_id, 0) > 0
           AND COALESCE(team_b_id, 0) > 0
         GROUP BY team_min, team_max
         HAVING COUNT(*) > 1",
        ARRAY_A
    );

    foreach ($pair_groups as $g) {
        $team_min = (int) $g['team_min'];
        $team_max = (int) $g['team_max'];
        $auto_keep_id = (int) $g['keep_id'];
        $effective_keep_id = $auto_keep_id;

        if ($keep_room_id > 0) {
            $matched = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$rooms_table}
                 WHERE id = %d
                   AND room_type IN ('match', 'group')
                   AND status = 'active'
                   AND LEAST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) = %d
                   AND GREATEST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) = %d",
                $keep_room_id,
                $team_min,
                $team_max
            ));
            if ($matched > 0) {
                $effective_keep_id = $keep_room_id;
            }
        }

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$rooms_table}
             WHERE room_type IN ('match', 'group')
               AND status = 'active'
               AND LEAST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) = %d
               AND GREATEST(COALESCE(team_a_id, 0), COALESCE(team_b_id, 0)) = %d
               AND id <> %d",
            $team_min,
            $team_max,
            $effective_keep_id
        ));
        foreach ($ids as $id) {
            $delete_ids[] = (int) $id;
        }
    }

    $delete_ids = array_values(array_unique(array_filter(array_map('intval', $delete_ids))));
    $report = [
        'target_room_ids' => $delete_ids,
        'deleted_rooms' => [],
        'errors' => [],
    ];

    foreach ($delete_ids as $room_id) {
        $result = aidunite_delete_chat_room_permanently($room_id);
        if (is_wp_error($result)) {
            $report['errors'][] = 'room_id=' . $room_id . ': ' . $result->get_error_message();
        } elseif ($result) {
            $report['deleted_rooms'][] = (int) $room_id;
        }
    }

    return $report;
}

/**
 * チャットルームを完全削除（管理用途）
 * - ルーム配下のメッセージ/リアクション/参加者/既読も併せて削除
 *
 * @param int $room_id
 * @return bool|WP_Error true:削除成功, false:対象なし, WP_Error:失敗
 */
function aidunite_delete_chat_room_permanently($room_id) {
    global $wpdb;
    $room_id_int = intval($room_id);
    if ($room_id_int <= 0) {
        return false;
    }

    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $messages_table = $wpdb->prefix . 'chat_messages';
    $participants_table = $wpdb->prefix . 'chat_participants';
    $read_status_table = $wpdb->prefix . 'chat_read_status';
    $reactions_table = $wpdb->prefix . 'chat_message_reactions';

    $room_exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$rooms_table} WHERE id = %d", $room_id_int));
    if ($room_exists <= 0) {
        return false;
    }

    $message_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$messages_table} WHERE room_id = %d", $room_id_int));
    if (!empty($message_ids)) {
        $message_ids = array_values(array_unique(array_filter(array_map('intval', $message_ids))));
        $placeholders = implode(',', array_fill(0, count($message_ids), '%d'));
        $deleted_reactions = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$reactions_table} WHERE message_id IN ({$placeholders})",
            ...$message_ids
        ));
        if ($deleted_reactions === false) {
            return new WP_Error('delete_reactions_failed', $wpdb->last_error ?: 'Failed to delete reactions');
        }
    }

    if ($wpdb->delete($read_status_table, ['room_id' => $room_id_int], ['%d']) === false) {
        return new WP_Error('delete_read_status_failed', $wpdb->last_error ?: 'Failed to delete read status');
    }
    if ($wpdb->delete($participants_table, ['room_id' => $room_id_int], ['%d']) === false) {
        return new WP_Error('delete_participants_failed', $wpdb->last_error ?: 'Failed to delete participants');
    }
    if ($wpdb->delete($messages_table, ['room_id' => $room_id_int], ['%d']) === false) {
        return new WP_Error('delete_messages_failed', $wpdb->last_error ?: 'Failed to delete messages');
    }
    $deleted_room = $wpdb->delete($rooms_table, ['id' => $room_id_int], ['%d']);
    if ($deleted_room === false) {
        return new WP_Error('delete_room_failed', $wpdb->last_error ?: 'Failed to delete room');
    }

    return true;
}

if (!function_exists('aidunite_reactivate_chat_room')) {
    /**
     * @param int $room_id
     * @return object|null
     */
    function aidunite_reactivate_chat_room($room_id) {
        global $wpdb;
        $room_id_int = (int) $room_id;
        if ($room_id_int <= 0) {
            return null;
        }
        $room = aidunite_get_chat_room($room_id_int);
        if (!$room) {
            return null;
        }
        if ((string) ($room->status ?? '') === 'completed') {
            $rooms_table = $wpdb->prefix . 'chat_rooms';
            $wpdb->update(
                $rooms_table,
                ['status' => 'active'],
                ['id' => $room_id_int],
                ['%s'],
                ['%d']
            );
            $room = aidunite_get_chat_room($room_id_int);
        }

        return $room;
    }
}

if (!function_exists('aidunite_handle_chat_on_match_request_canceled')) {
    /**
     * キャンセル時のチャット処理（共有ゲームは閉じず participants 同期に任せる）
     *
     * @param int $request_id
     * @return void
     */
    function aidunite_handle_chat_on_match_request_canceled($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }

        $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
            ? (int) aidunite_resolve_match_game_id_for_match_request($request_id)
            : (int) get_post_meta($request_id, 'to_schedule_id', true);
        if (
            $match_game_id > 0
            && $match_game_id !== 9999
            && function_exists('aidunite_count_game_established_match_requests')
            && aidunite_count_game_established_match_requests($match_game_id, $request_id) > 0
        ) {
            $actor_team_id = (int) get_post_meta($request_id, 'canceled_by_team_id', true);
            $announce_team_id = function_exists('aidunite_resolve_withdrawn_team_id_for_canceled_match_request')
                ? (int) aidunite_resolve_withdrawn_team_id_for_canceled_match_request($request_id, $actor_team_id)
                : (int) get_post_meta($request_id, 'from_team_id', true);
            if (function_exists('aidunite_sync_schedule_chat_room_members')) {
                aidunite_sync_schedule_chat_room_members($match_game_id, [
                    'announce_removed_team_ids' => $announce_team_id > 0 ? [$announce_team_id] : [],
                ]);
            }

            return;
        }

        if (function_exists('aidunite_mark_match_chat_completed')) {
            aidunite_mark_match_chat_completed($request_id, 'canceled');
        }
    }
}

/**
 * match_game_id（anchor schedule）の active チャットルームを取得（ポインタのみ・§3A.3）
 *
 * @param int $schedule_id match_game_id
 * @return object|null
 */
function aidunite_get_schedule_chat_room($schedule_id) {
    $schedule_id_int = (int) $schedule_id;
    if ($schedule_id_int <= 0) {
        return null;
    }

    $room_id = (int) get_post_meta($schedule_id_int, 'active_match_chat_room_id', true);
    if ($room_id <= 0) {
        return null;
    }

    $room = aidunite_get_chat_room($room_id);
    if (!$room || empty($room->id)) {
        return null;
    }

    $status = (string) ($room->status ?? '');
    if ($status === 'active') {
        return $room;
    }
    if ($status === 'completed'
        && function_exists('aidunite_count_game_established_match_requests')
        && aidunite_count_game_established_match_requests($schedule_id_int, 0) > 0
        && function_exists('aidunite_reactivate_chat_room')) {
        return aidunite_reactivate_chat_room((int) $room->id);
    }

    return null;
}

if (!function_exists('aidunite_resolve_team_leader_user_ids_for_chat')) {
    /**
     * チャット参加用の代表 user_id 一覧（通知の get_team_leaders を優先）
     *
     * @param int $team_id
     * @return int[]
     */
    function aidunite_resolve_team_leader_user_ids_for_chat($team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            return [];
        }

        $ids = [];
        if (function_exists('get_team_leaders')) {
            foreach (get_team_leaders($team_id) as $leader_id) {
                $leader_id = (int) $leader_id;
                if ($leader_id > 0) {
                    $ids[$leader_id] = true;
                }
            }
        }
        if ($ids === [] && function_exists('aidunite_get_team_leader')) {
            $legacy = aidunite_get_team_leader($team_id);
            $legacy_id = is_object($legacy) ? (int) ($legacy->user_id ?? $legacy->ID) : (int) $legacy;
            if ($legacy_id > 0) {
                $ids[$legacy_id] = true;
            }
        }
        if ($ids === []) {
            $users = get_users([
                'meta_key'   => 'team_id',
                'meta_value' => (string) $team_id,
                'meta_compare' => '=',
                'number'     => 1,
                'fields'     => 'ID',
            ]);
            foreach ((array) $users as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $ids[$uid] = true;
                }
            }
        }

        return array_map('intval', array_keys($ids));
    }
}

/**
 * チャットルームにチームを追加参加させる
 * @param int $room_id チャットルームID
 * @param int $team_id 追加するチームID
 * @return bool|WP_Error 成功時はtrue、失敗時はWP_Error
 */
function aidunite_add_team_to_chat_room($room_id, $team_id) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $participants_table = $wpdb->prefix . 'chat_participants';

    $room_id_int = intval($room_id);
    $team_id_int = intval($team_id);

    if (!$room_id_int || !$team_id_int) {
        return new WP_Error('invalid_params', 'room_idまたはteam_idが無効です');
    }

    // チャットルームを取得
    $room = aidunite_get_chat_room($room_id_int);
    if (!$room) {
        return new WP_Error('room_not_found', 'チャットルームが見つかりません');
    }

    $leader_ids = function_exists('aidunite_resolve_team_leader_user_ids_for_chat')
        ? aidunite_resolve_team_leader_user_ids_for_chat($team_id_int)
        : [];

    if ($leader_ids === []) {
        return new WP_Error('leader_not_found', 'チーム代表者が見つかりません');
    }

    $added_any = false;
    foreach ($leader_ids as $leader_id) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$participants_table} WHERE room_id = %d AND user_id = %d",
            $room_id_int,
            $leader_id
        ));
        if ($existing > 0) {
            $added_any = true;
            continue;
        }

        $result = $wpdb->insert($participants_table, [
            'room_id' => $room_id_int,
            'user_id' => $leader_id,
            'role'    => 'admin',
        ], ['%d', '%d', '%s']);

        if ($result !== false) {
            $added_any = true;
        }
    }

    if (!$added_any) {
        return new WP_Error('db_error', '参加者の追加に失敗しました: ' . $wpdb->last_error);
    }

    // 参加チーム数をカウント（chat_participantsテーブルから取得）
    $participant_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT u.meta_value)
         FROM {$participants_table} p
         INNER JOIN {$wpdb->usermeta} u ON p.user_id = u.user_id
         WHERE p.room_id = %d AND u.meta_key = 'team_id'",
        $room_id_int
    ));

    $participant_count = intval($participant_count);

    // 参加チーム数が3以上の場合、room_typeを'group'に変更
    if ($participant_count >= 3 && $room->room_type === 'match') {
        // チーム名を取得してルーム名を更新
        $team_names = [];
        $team_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT u.meta_value
             FROM {$participants_table} p
             INNER JOIN {$wpdb->usermeta} u ON p.user_id = u.user_id
             WHERE p.room_id = %d AND u.meta_key = 'team_id'",
            $room_id_int
        ));

        foreach ($team_ids as $tid) {
            if (function_exists('aidunite_get_team_name')) {
                $team_name = aidunite_get_team_name($tid);
            } else {
                $team_post = get_post($tid);
                $team_name = $team_post ? $team_post->post_title : 'チーム';
            }
            if ($team_name) {
                $team_names[] = $team_name;
            }
        }

        $room_name = '練習試合グループチャット（' . $participant_count . 'チーム参加）';
        if (!empty($team_names)) {
            $room_name = '練習試合グループチャット（' . implode(', ', $team_names) . '）';
        }

        $wpdb->update($rooms_table, [
            'room_type' => 'group',
            'name' => $room_name
        ], [
            'id' => $room_id_int
        ], ['%s', '%s'], ['%d']);
    }

    return true;
}

if (!function_exists('aidunite_get_chat_room_team_ids')) {
    /**
     * ルーム参加者から team_id 一覧を取得
     *
     * @param int $room_id
     * @return int[]
     */
    function aidunite_get_chat_room_team_ids($room_id) {
        global $wpdb;
        $room_id_int = (int) $room_id;
        if ($room_id_int <= 0) {
            return [];
        }
        $participants_table = $wpdb->prefix . 'chat_participants';
        $team_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT CAST(um.meta_value AS UNSIGNED) AS team_id
             FROM {$participants_table} p
             INNER JOIN {$wpdb->usermeta} um
               ON p.user_id = um.user_id
              AND um.meta_key = 'team_id'
             WHERE p.room_id = %d",
            $room_id_int
        ));
        $team_ids = array_values(array_unique(array_filter(array_map('intval', (array) $team_ids))));
        sort($team_ids, SORT_NUMERIC);
        return $team_ids;
    }
}

if (!function_exists('aidunite_sync_schedule_chat_room_members')) {
    /**
     * 同一募集（schedule）に紐づく active match/group ルームの参加者を
     * established MR 集合に合わせて再同期する。
     *
     * @param int $schedule_id match_game_id（anchor schedule）
     * @return void
     */
    function aidunite_sync_schedule_chat_room_members($schedule_id, array $options = []) {
        global $wpdb;
        $schedule_id_int = (int) $schedule_id;
        $announce_removed_team_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($options['announce_removed_team_ids'] ?? [])))));
        if ($schedule_id_int <= 0) {
            return;
        }

        $room = function_exists('aidunite_get_active_chat_room_for_match_game')
            ? aidunite_get_active_chat_room_for_match_game($schedule_id_int)
            : (function_exists('aidunite_get_schedule_chat_room') ? aidunite_get_schedule_chat_room($schedule_id_int) : null);
        if (!is_object($room) || empty($room->id)) {
            return;
        }
        $room_id_int = (int) $room->id;
        $participants_table = $wpdb->prefix . 'chat_participants';
        $rooms_table = $wpdb->prefix . 'chat_rooms';

        $team_ids = [];
        if (function_exists('aidunite_get_game_match_requests') && function_exists('aidunite_normalize_match_request_status')) {
            foreach (aidunite_get_game_match_requests($schedule_id_int) as $req) {
                $rid = (int) $req->ID;
                $meta = (string) get_post_meta($rid, 'status', true);
                $st = aidunite_normalize_match_request_status($meta, isset($req->post_status) ? (string) $req->post_status : '');
                if ($st !== 'established') {
                    continue;
                }
                $from_tid = (int) get_post_meta($rid, 'from_team_id', true);
                $to_tid = (int) get_post_meta($rid, 'to_team_id', true);
                if ($from_tid > 0) {
                    $team_ids[$from_tid] = true;
                }
                if ($to_tid > 0) {
                    $team_ids[$to_tid] = true;
                }
            }
        }

        if ($team_ids === []) {
            return;
        }
        $allowed_team_ids = array_map('intval', array_keys($team_ids));

        // 既存参加者から、現時点の established 集合に属さない team を除外
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.user_id, um.meta_value AS team_id
             FROM {$participants_table} p
             LEFT JOIN {$wpdb->usermeta} um
               ON p.user_id = um.user_id
              AND um.meta_key = 'team_id'
             WHERE p.room_id = %d",
            $room_id_int
        ));
        $removed_team_names = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $uid = (int) ($row->user_id ?? 0);
                $tid = (int) ($row->team_id ?? 0);
                if ($uid <= 0) {
                    continue;
                }

                $represents_allowed_team = ($tid > 0 && in_array($tid, $allowed_team_ids, true));
                if (!$represents_allowed_team && function_exists('get_team_leaders')) {
                    foreach ($allowed_team_ids as $allowed_tid) {
                        $leaders = array_map('intval', (array) get_team_leaders((int) $allowed_tid));
                        if (in_array($uid, $leaders, true)) {
                            $represents_allowed_team = true;
                            break;
                        }
                    }
                }

                if ($represents_allowed_team) {
                    continue;
                }

                $wpdb->delete($participants_table, ['room_id' => $room_id_int, 'user_id' => $uid], ['%d', '%d']);
                $label_tid = $tid > 0 ? $tid : 0;
                if ($label_tid > 0 && !isset($removed_team_names[$label_tid])) {
                    $removed_team_names[$label_tid] = function_exists('aidunite_get_team_name')
                        ? aidunite_get_team_name($label_tid)
                        : get_the_title($label_tid);
                }
            }
        }

        if (!empty($announce_removed_team_ids) && function_exists('aidunite_save_chat_message')) {
            foreach ($announce_removed_team_ids as $removed_tid) {
                $team_name = function_exists('aidunite_get_team_name')
                    ? aidunite_get_team_name((int) $removed_tid)
                    : get_the_title((int) $removed_tid);
                $team_name = trim((string) $team_name);
                if ($team_name === '') {
                    $team_name = '相手チーム';
                }
                aidunite_save_chat_message([
                    'room_id' => $room_id_int,
                    'sender_id' => 0,
                    'message_type' => 'system',
                    'content' => $team_name . 'チームの試合キャンセルがありました。',
                ]);
            }
        } elseif (!empty($removed_team_names) && function_exists('aidunite_save_chat_message')) {
            foreach ($removed_team_names as $name) {
                $team_name = trim((string) $name);
                if ($team_name === '') {
                    $team_name = '相手チーム';
                }
                aidunite_save_chat_message([
                    'room_id' => $room_id_int,
                    'sender_id' => 0,
                    'message_type' => 'system',
                    'content' => $team_name . 'チームの試合キャンセルがありました。',
                ]);
            }
        }

        // 不足している team を追加
        foreach ($allowed_team_ids as $tid) {
            $res = aidunite_add_team_to_chat_room($room_id_int, (int) $tid);
            if (is_wp_error($res)) {
                // 同期処理はベストエフォート
                continue;
            }
        }

        // 参加チーム数に応じて room_type / name を再調整
        $team_names = [];
        foreach ($allowed_team_ids as $tid) {
            $name = function_exists('aidunite_get_team_name') ? aidunite_get_team_name($tid) : get_the_title($tid);
            if (!empty($name)) {
                $team_names[] = $name;
            }
        }
        $team_count = count($allowed_team_ids);
        if ($team_count >= 3) {
            $new_type = 'group';
            $new_name = !empty($team_names)
                ? ('練習試合グループチャット（' . implode(', ', $team_names) . '）')
                : ('練習試合グループチャット（' . $team_count . 'チーム参加）');
        } else {
            $new_type = 'match';
            if ($team_count === 2 && !empty($team_names)) {
                $new_name = 'vs ' . ($team_names[1] ?? $team_names[0]);
            } else {
                $new_name = (string) ($room->name ?? '対戦チャット');
            }
        }
        $wpdb->update(
            $rooms_table,
            ['room_type' => $new_type, 'name' => $new_name],
            ['id' => $room_id_int],
            ['%s', '%s'],
            ['%d']
        );
    }
}

/**
 * スケジュールベースのマッチチャットを作成または拡張
 * @param int $match_request_id マッチ申請ID
 * @param int $team_a_id チームA ID
 * @param int $team_b_id チームB ID
 * @param int $schedule_id スケジュールID（to_schedule_id）
 * @param string $match_date 試合日付
 * @return int|WP_Error 成功時はroom_id、失敗時はWP_Error
 */
if (!function_exists('aidunite_bind_match_chat_room_pointer')) {
    /**
     * チャットルーム確定時の参照ポインタを保存
     * - match_request: chat_room_id
     * - recruit schedule: active_match_chat_room_id
     */
    function aidunite_bind_match_chat_room_pointer($match_request_id, $schedule_id, $room_id) {
        $match_request_id = (int) $match_request_id;
        $schedule_id = (int) $schedule_id;
        $room_id = (int) $room_id;
        if ($room_id <= 0) {
            return;
        }
        if ($match_request_id > 0) {
            update_post_meta($match_request_id, 'chat_room_id', $room_id);
        }
        if ($schedule_id > 0) {
            update_post_meta($schedule_id, 'active_match_chat_room_id', $room_id);
        }
    }
}

function aidunite_create_or_extend_match_chat($match_request_id, $team_a_id, $team_b_id, $schedule_id, $match_date) {
    global $wpdb;

    $match_request_id_int = (int) $match_request_id;
    $schedule_id_int      = (int) $schedule_id;
    if ($match_request_id_int > 0 && function_exists('aidunite_resolve_match_game_id_for_match_request')) {
        $match_game_id = (int) aidunite_resolve_match_game_id_for_match_request($match_request_id_int);
        if ($match_game_id > 0) {
            $schedule_id_int = $match_game_id;
        }
    }

    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('chat_create_or_extend', [
            'match_request_id' => $match_request_id_int,
            'team_a_id'        => (int) $team_a_id,
            'team_b_id'        => (int) $team_b_id,
            'schedule_id'      => $schedule_id_int,
            'match_date'       => (string) $match_date,
        ]);
    }

    $team_a_id_int = intval($team_a_id);
    $team_b_id_int = intval($team_b_id);

    if (!$match_request_id_int || !$team_a_id_int || !$team_b_id_int) {
        return new WP_Error('invalid_params', 'パラメータが無効です');
    }

    // §3A.1: match_game に active があれば拡張のみ（統合・副ルーム収集はしない）
    $existing_room = null;
    if ($schedule_id_int > 0 && function_exists('aidunite_get_active_chat_room_for_match_game')) {
        $existing_room = aidunite_get_active_chat_room_for_match_game($schedule_id_int);
    }

    if ($existing_room) {
        $before_team_ids = function_exists('aidunite_get_chat_room_team_ids')
            ? aidunite_get_chat_room_team_ids((int) $existing_room->id)
            : [];
        // 既存ルームがある場合：両チームを追加参加
        $result_a = aidunite_add_team_to_chat_room($existing_room->id, $team_a_id_int);
        $result_b = aidunite_add_team_to_chat_room($existing_room->id, $team_b_id_int);

        if (is_wp_error($result_a)) {
            return $result_a;
        }
        if (is_wp_error($result_b)) {
            return $result_b;
        }

        $after_team_ids = function_exists('aidunite_get_chat_room_team_ids')
            ? aidunite_get_chat_room_team_ids((int) $existing_room->id)
            : [];
        $new_team_ids = array_values(array_diff($after_team_ids, $before_team_ids));

        // システムメッセージは「今回新規参加したチーム」のみ出す（重複投稿防止）
        if (!empty($new_team_ids) && function_exists('aidunite_save_chat_message')) {
            aidunite_save_chat_message([
                'room_id' => $existing_room->id,
                'sender_id' => 0, // システム
                'message_type' => 'system',
                'content' => aidunite_build_chat_team_join_system_message($new_team_ids),
            ]);
        }

        $resolved_room_id = (int) $existing_room->id;
        if ($resolved_room_id > 0 && function_exists('aidunite_bind_match_game_chat_room')) {
            aidunite_bind_match_game_chat_room($match_request_id_int, $schedule_id_int, $resolved_room_id);
        }
        if ($resolved_room_id > 0) {
            aidunite_maybe_post_match_welcome_message($resolved_room_id);
        }

        return $resolved_room_id > 0 ? $resolved_room_id : (int) $existing_room->id;
    } else {
        // 既存ルームがない場合：新規作成
        // aidunite_create_match_chat関数を呼び出し、schedule_idを設定
        $room_id = aidunite_create_match_chat($match_request_id_int, $team_a_id_int, $team_b_id_int, $match_date, $schedule_id_int);
        if (is_wp_error($room_id) || !$room_id) {
            return $room_id;
        }
        $resolved_room_id = (int) $room_id;
        if ($resolved_room_id > 0 && function_exists('aidunite_bind_match_game_chat_room')) {
            aidunite_bind_match_game_chat_room($match_request_id_int, $schedule_id_int, $resolved_room_id);
        }
        if ($resolved_room_id > 0) {
            aidunite_maybe_post_match_welcome_message($resolved_room_id);
        }

        return $resolved_room_id > 0 ? $resolved_room_id : (int) $room_id;
    }
}

/**
 * 試合成立ウェルカムメッセージを1回だけ投稿（重複防止）
 * チャット作成/拡張直後に呼ぶ。sender_id=0 のため直接 INSERT する。
 *
 * @param int $room_id チャットルームID
 */
function aidunite_maybe_post_match_welcome_message($room_id) {
    global $wpdb;
    $room_id_int = intval($room_id);
    if ($room_id_int <= 0) {
        return;
    }
    $table = $wpdb->prefix . 'chat_messages';
    $welcome_content = "試合成立おめでとうございます。\n会場や集合時間などの確認はこのチャットをご利用ください。";
    $already = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE room_id = %d AND message_type = %s AND content = %s",
        $room_id_int,
        'system',
        $welcome_content
    ));
    if ($already > 0) {
        return;
    }
    $wpdb->insert($table, [
        'room_id'      => $room_id_int,
        'sender_id'    => 0,
        'message_type' => 'system',
        'content'      => $welcome_content,
        'created_at'   => current_time('mysql'),
    ], ['%d', '%d', '%s', '%s', '%s']);
    if ($wpdb->last_error) {
        error_log('[chat] welcome message error: ' . $wpdb->last_error);
    }
}

/**
 * 対戦チャットを作成
 */
function aidunite_create_match_chat($match_id, $team_a_id, $team_b_id, $match_date, $schedule_id = null) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $participants_table = $wpdb->prefix . 'chat_participants';

    $match_id_int = intval($match_id);
    $team_a_id_int = intval($team_a_id);
    $team_b_id_int = intval($team_b_id);
    $schedule_id_int = (int) $schedule_id;
    if (!$match_id_int || !$team_a_id_int || !$team_b_id_int) {
        return new WP_Error('invalid_params', 'パラメータが無効です');
    }
    if (function_exists('aidunite_resolve_match_game_id_for_match_request')) {
        $match_game_id = (int) aidunite_resolve_match_game_id_for_match_request($match_id_int);
        if ($match_game_id > 0) {
            $schedule_id_int = $match_game_id;
        }
    }

    // 1) match_request 単位で既存チェック（active限定）
    $existing = function_exists('aidunite_get_active_match_chat_room')
        ? aidunite_get_active_match_chat_room($match_id_int)
        : aidunite_get_match_chat_room($match_id_int);
    if ($existing) {
        return intval($existing->id);
    }

    // 1b) 同一 MR の最新ルームを再利用（completed は共有ゲーム成立中なら reactivate）
    $latest_for_mr = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$rooms_table}
         WHERE room_type IN ('match', 'group')
           AND match_id = %d
         ORDER BY (CASE WHEN status = 'active' THEN 1 ELSE 0 END) DESC, id DESC
         LIMIT 1",
        $match_id_int
    ));
    if ($latest_for_mr && !empty($latest_for_mr->id)) {
        $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
            ? (int) aidunite_resolve_match_game_id_for_match_request($match_id_int)
            : (int) $schedule_id_int;
        $shared_game = $match_game_id > 0
            && $match_game_id !== 9999
            && function_exists('aidunite_count_game_established_match_requests')
            && aidunite_count_game_established_match_requests($match_game_id, 0) > 0;
        $mr_norm = '';
        $mr_post = get_post($match_id_int);
        if ($mr_post && $mr_post->post_type === 'match_request') {
            $mr_meta = (string) get_post_meta($match_id_int, 'status', true);
            $mr_norm = function_exists('aidunite_normalize_match_request_status')
                ? aidunite_normalize_match_request_status($mr_meta, (string) $mr_post->post_status)
                : strtolower($mr_meta);
        }
        $latest_status = (string) ($latest_for_mr->status ?? '');
        if ($latest_status === 'completed' && ($shared_game || $mr_norm === 'established')) {
            if (function_exists('aidunite_reactivate_chat_room')) {
                $reactivated = aidunite_reactivate_chat_room((int) $latest_for_mr->id);
                if ($reactivated && !empty($reactivated->id)) {
                    return (int) $reactivated->id;
                }
            }
        } elseif ($latest_status === 'active') {
            return (int) $latest_for_mr->id;
        }
    }

    // 2) match_game の active ルームのみ再利用（§3A.3）
    if ($schedule_id_int > 0) {
        $existing_by_schedule = function_exists('aidunite_get_active_chat_room_for_match_game')
            ? aidunite_get_active_chat_room_for_match_game($schedule_id_int)
            : aidunite_get_schedule_chat_room($schedule_id_int);
        if ($existing_by_schedule && !empty($existing_by_schedule->id)) {
            return intval($existing_by_schedule->id);
        }
    }

    // 3) 互換ガード: 同一カード（team_a/team_b + match_date）の既存ルームを再利用
    // NOTE:
    // - schedule_id があるケースは schedule 単位で新規/再利用を判定済みのため、カード再利用は行わない。
    //   （削除済み旧スケジュール由来の古いルーム再利用を防ぐ）
    // - schedule_id がない旧フローのみ、active ルームに限定して再利用する。
    if ($schedule_id_int <= 0 && !empty($match_date)) {
        $existing_by_card = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$rooms_table}
             WHERE room_type IN ('match', 'group')
               AND status = 'active'
               AND match_date = %s
               AND (
                 (team_a_id = %d AND team_b_id = %d)
                 OR
                 (team_a_id = %d AND team_b_id = %d)
               )
             ORDER BY id ASC
             LIMIT 1",
            $match_date,
            $team_a_id_int, $team_b_id_int,
            $team_b_id_int, $team_a_id_int
        ));
        if ($existing_by_card && !empty($existing_by_card->id)) {
            return intval($existing_by_card->id);
        }
    }

    // チーム名を取得（aidunite_get_team_name() 関数を使用）
    if (function_exists('aidunite_get_team_name')) {
        $team_b_name = aidunite_get_team_name($team_b_id_int);
    } else {
        // フォールバック
        $team_b_name = get_post_meta($team_b_id_int, 'team_name', true);
        if (empty($team_b_name)) {
            $team_b_post = get_post($team_b_id_int);
            $team_b_name = $team_b_post ? $team_b_post->post_title : 'チーム';
        }
    }
    $name = sprintf('vs %s', $team_b_name);

    // schedule_idカラムの存在確認
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $rooms_table LIKE '%'");
    $has_schedule_id = in_array('schedule_id', $columns);

    $room_data = [
        'room_type' => 'match',
        'name' => $name,
        'match_id' => $match_id_int,
        'team_a_id' => $team_a_id_int,
        'team_b_id' => $team_b_id_int,
        'match_date' => $match_date,
        'status' => 'active',
        'created_at' => current_time('mysql')
    ];

    $room_format = ['%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s'];

    // schedule_idが指定されている場合、かつカラムが存在する場合は追加
    if ($schedule_id_int && $has_schedule_id) {
        $room_data['schedule_id'] = $schedule_id_int;
        $room_format[] = '%d';
    }

    $result = $wpdb->insert($rooms_table, $room_data, $room_format);

    if ($result === false) {
        return new WP_Error('db_error', '対戦チャットの作成に失敗しました: ' . $wpdb->last_error);
    }

    $room_id = intval($wpdb->insert_id);

    foreach ([$team_a_id_int, $team_b_id_int] as $tid) {
        if (function_exists('aidunite_add_team_to_chat_room')) {
            aidunite_add_team_to_chat_room($room_id, (int) $tid);
        }
    }

    return $room_id;
}

/**
 * メッセージスレッド用チャットルームを取得
 */
function aidunite_get_message_thread_room($message_id) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $message_id_int = intval($message_id);
    if (!$message_id_int) return null;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$rooms_table}
         WHERE room_type = 'message_thread'
         AND related_message_id = %d
         LIMIT 1",
        $message_id_int
    ));
}

/**
 * メッセージスレッド用チャットルームを作成
 */
function aidunite_create_message_thread_chat($message_id, $team_id) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $participants_table = $wpdb->prefix . 'chat_participants';

    $message_id_int = intval($message_id);
    $team_id_int = intval($team_id);

    if (!$message_id_int || !$team_id_int) {
        return new WP_Error('invalid_params', 'message_idまたはteam_idが無効です');
    }

    // 既存があれば返す
    $existing = aidunite_get_message_thread_room($message_id_int);
    if ($existing) {
        return intval($existing->id);
    }

    // メッセージ情報を取得（ルーム名に使用）
    $message = aidunite_get_message($message_id_int);
    $room_name = $message ? $message->title : 'メッセージスレッド';

    $result = $wpdb->insert($rooms_table, [
        'room_type' => 'message_thread',
        'name' => $room_name,
        'description' => 'メッセージ掲示板の投稿についてのチャット',
        'team_id' => $team_id_int,
        'related_message_id' => $message_id_int,
        'status' => 'active',
        'created_at' => current_time('mysql')
    ], ['%s', '%s', '%s', '%d', '%d', '%s', '%s']);

    if ($result === false) {
        return new WP_Error('db_error', 'メッセージスレッドチャットの作成に失敗しました: ' . $wpdb->last_error);
    }

    $room_id = intval($wpdb->insert_id);

    // チームメンバーを参加者に追加（存在すれば）
    if (function_exists('aidunite_get_team_members')) {
        $team_members = aidunite_get_team_members($team_id_int);
        if (!empty($team_members)) {
            foreach ($team_members as $member) {
                $wpdb->insert($participants_table, [
                    'room_id' => $room_id,
                    'user_id' => intval($member->user_id ?? $member->ID),
                    'role' => 'member'
                ], ['%d', '%d', '%s']);
            }
        }
    }

    return $room_id;
}

/**
 * システムチャットルームを取得
 */
function aidunite_get_system_chat_room($system_type, $related_id) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $system_type_safe = sanitize_text_field($system_type);
    $related_id_int = intval($related_id);

    if (!$system_type_safe || !$related_id_int) return null;

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$rooms_table}
         WHERE room_type = 'system'
         AND system_type = %s
         AND related_id = %d
         LIMIT 1",
        $system_type_safe, $related_id_int
    ));
}

/**
 * システムチャットルームを作成
 */
function aidunite_create_system_chat($system_type, $related_id, $team_id, $room_name = null, $description = null) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $participants_table = $wpdb->prefix . 'chat_participants';

    $system_type_safe = sanitize_text_field($system_type);
    $related_id_int = intval($related_id);
    $team_id_int = intval($team_id);

    if (!$system_type_safe || !$related_id_int || !$team_id_int) {
        return new WP_Error('invalid_params', 'パラメータが無効です');
    }

    // 既存があれば返す
    $existing = aidunite_get_system_chat_room($system_type_safe, $related_id_int);
    if ($existing) {
        return intval($existing->id);
    }

    // ルーム名と説明を生成
    if (!$room_name) {
        $room_name = aidunite_get_system_room_name($system_type_safe, $related_id_int);
    }
    if (!$description) {
        $description = aidunite_get_system_room_description($system_type_safe);
    }

    $result = $wpdb->insert($rooms_table, [
        'room_type' => 'system',
        'name' => $room_name,
        'description' => $description,
        'team_id' => $team_id_int,
        'system_type' => $system_type_safe,
        'related_id' => $related_id_int,
        'status' => 'active',
        'created_at' => current_time('mysql')
    ], ['%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s']);

    if ($result === false) {
        return new WP_Error('db_error', 'システムチャットの作成に失敗しました: ' . $wpdb->last_error);
    }

    $room_id = intval($wpdb->insert_id);

    // チームメンバーを参加者に追加（存在すれば）
    if (function_exists('aidunite_get_team_members')) {
        $team_members = aidunite_get_team_members($team_id_int);
        if (!empty($team_members)) {
            foreach ($team_members as $member) {
                $wpdb->insert($participants_table, [
                    'room_id' => $room_id,
                    'user_id' => intval($member->user_id ?? $member->ID),
                    'role' => 'member'
                ], ['%d', '%d', '%s']);
            }
        }
    }

    return $room_id;
}

/**
 * システムチャットルーム名を取得
 */
function aidunite_get_system_room_name($system_type, $related_id) {
    $names = [
        'schedule_created' => 'スケジュールについて',
        'schedule_updated' => 'スケジュール変更について',
        'schedule_deleted' => 'スケジュール削除について',
        'schedule_confirmed' => 'スケジュール確定について',
        'match_created' => '試合について',
        'match_confirmed' => '試合確定について'
    ];

    $base_name = $names[$system_type] ?? 'システムメッセージ';

    // 関連IDから詳細情報を取得して名前を生成（必要に応じて）
    return $base_name;
}

/**
 * システムチャットルーム説明を取得
 */
function aidunite_get_system_room_description($system_type) {
    $descriptions = [
        'schedule_created' => '新しいスケジュールについてのチャット',
        'schedule_updated' => 'スケジュール変更についてのチャット',
        'schedule_deleted' => 'スケジュール削除についてのチャット',
        'schedule_confirmed' => 'スケジュール確定についてのチャット',
        'match_created' => '試合についてのチャット',
        'match_confirmed' => '試合確定についてのチャット'
    ];

    return $descriptions[$system_type] ?? 'システムメッセージについてのチャット';
}

/**
 * グループチャットルームを作成または取得
 */
function aidunite_create_or_get_group_chat($participant_ids, $room_name = null) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $participants_table = $wpdb->prefix . 'chat_participants';

    // 参加者IDをソートしてユニークキーを生成
    $sorted_ids = array_map('intval', $participant_ids);
    sort($sorted_ids);
    $unique_key = implode('-', $sorted_ids);

    if (empty($unique_key)) {
        return new WP_Error('invalid_params', '参加者が指定されていません');
    }

    // 既存を取得
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$rooms_table}
         WHERE room_type = 'group'
         AND unique_key = %s
         LIMIT 1",
        $unique_key
    ));

    if ($existing) {
        return intval($existing->id);
    }

    // 新規作成
    if (!$room_name) {
        $room_name = 'グループチャット';
    }

    $result = $wpdb->insert($rooms_table, [
        'room_type' => 'group',
        'name' => $room_name,
        'description' => 'グループチャット',
        'unique_key' => $unique_key,
        'status' => 'active',
        'created_at' => current_time('mysql')
    ], ['%s', '%s', '%s', '%s', '%s', '%s']);

    if ($result === false) {
        return new WP_Error('db_error', 'グループチャットの作成に失敗しました: ' . $wpdb->last_error);
    }

    $room_id = intval($wpdb->insert_id);

    // 参加者を追加
    foreach ($sorted_ids as $user_id) {
        $wpdb->insert($participants_table, [
            'room_id' => $room_id,
            'user_id' => $user_id,
            'role' => 'member'
        ], ['%d', '%d', '%s']);
    }

    return $room_id;
}

/**
 * ダイレクトチャットルームを作成または取得
 */
function aidunite_create_or_get_direct_chat($user_id_1, $user_id_2) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $participants_table = $wpdb->prefix . 'chat_participants';

    $user_id_1_int = intval($user_id_1);
    $user_id_2_int = intval($user_id_2);

    if (!$user_id_1_int || !$user_id_2_int || $user_id_1_int === $user_id_2_int) {
        return new WP_Error('invalid_params', 'ユーザーIDが無効です');
    }

    // ユーザーIDをソートしてユニークキーを生成
    $user_ids = [$user_id_1_int, $user_id_2_int];
    sort($user_ids);
    $unique_key = implode('-', $user_ids);

    // 既存を取得
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$rooms_table}
         WHERE room_type = 'direct'
         AND unique_key = %s
         LIMIT 1",
        $unique_key
    ));

    if ($existing) {
        return intval($existing->id);
    }

    // ユーザー名を取得してルーム名を生成
    $user_1 = get_userdata($user_ids[0]);
    $user_2 = get_userdata($user_ids[1]);
    $room_name = ($user_1 && $user_2)
        ? $user_1->display_name . ' と ' . $user_2->display_name
        : 'ダイレクトチャット';

    // 新規作成
    $result = $wpdb->insert($rooms_table, [
        'room_type' => 'direct',
        'name' => $room_name,
        'description' => '1対1のチャット',
        'unique_key' => $unique_key,
        'status' => 'active',
        'created_at' => current_time('mysql')
    ], ['%s', '%s', '%s', '%s', '%s', '%s']);

    if ($result === false) {
        return new WP_Error('db_error', 'ダイレクトチャットの作成に失敗しました: ' . $wpdb->last_error);
    }

    $room_id = intval($wpdb->insert_id);

    // 参加者を追加
    foreach ($user_ids as $user_id) {
        $wpdb->insert($participants_table, [
            'room_id' => $room_id,
            'user_id' => $user_id,
            'role' => 'member'
        ], ['%d', '%d', '%s']);
    }

    return $room_id;
}

/**
 * 参加者ベースでチャットルームを作成または取得
 */
function aidunite_create_or_get_chat_room_by_participants($participant_ids, $room_type, $title = null) {
    if (empty($participant_ids)) {
        return new WP_Error('invalid_params', '参加者が指定されていません');
    }

    $participant_ids = array_map('intval', $participant_ids);
    $participant_ids = array_unique($participant_ids);

    $normalized_room_type = strtolower(trim((string) $room_type));
    if (function_exists('aidunite_normalize_chat_payload')) {
        $normalized_room_type = (string) (aidunite_normalize_chat_payload([
            'room_type' => $normalized_room_type,
        ])['room_type'] ?? $normalized_room_type);
    }
    if (!in_array($normalized_room_type, ['group', 'direct', 'match', 'system'], true)) {
        $normalized_room_type = 'group';
    }

    // 参加者数による自動判定
    if (count($participant_ids) === 1) {
        return new WP_Error('invalid_params', 'ダイレクトチャットには2人以上の参加者が必要です');
    } elseif (count($participant_ids) === 2) {
        if ($normalized_room_type !== 'direct') {
            $normalized_room_type = 'direct';
        }
        // ダイレクトチャット
        return aidunite_create_or_get_direct_chat($participant_ids[0], $participant_ids[1]);
    } else {
        if ($normalized_room_type !== 'group') {
            $normalized_room_type = 'group';
        }
        // グループチャット
        return aidunite_create_or_get_group_chat($participant_ids, $title);
    }
}

/**
 * チャットメッセージ保存
 */
function aidunite_save_chat_message($message_data) {
    global $wpdb;
    $table = $wpdb->prefix . 'chat_messages';

    $room_id = intval($message_data['room_id'] ?? 0);
    $sender_id = isset($message_data['sender_id']) ? intval($message_data['sender_id']) : 0;
    $parent_message_id = isset($message_data['parent_message_id']) ? intval($message_data['parent_message_id']) : null;
    $message_type = sanitize_text_field($message_data['message_type'] ?? 'text');
    $content = sanitize_textarea_field($message_data['content'] ?? '');
    $file_url = sanitize_text_field($message_data['file_url'] ?? '');
    $is_private = !empty($message_data['is_private']) ? 1 : 0;

    if (function_exists('aidunite_normalize_chat_payload')) {
        $normalized = aidunite_normalize_chat_payload([
            'message_type' => $message_type,
        ]);
        $message_type = (string) ($normalized['message_type'] ?? $message_type);
    }

    if (!$room_id) {
        return new WP_Error('invalid_params', 'room_idが無効です');
    }
    if ($sender_id < 0) {
        return new WP_Error('invalid_params', 'sender_idが無効です');
    }

    $insert_data = [
        'room_id' => $room_id,
        'sender_id' => $sender_id,
        'message_type' => $message_type,
        'content' => $content,
        'file_url' => $file_url,
        'is_private' => $is_private,
        'created_at' => current_time('mysql')
    ];
    $insert_fmt = ['%d', '%d', '%s', '%s', '%s', '%d', '%s'];

    $has_parent_col = in_array('parent_message_id', $wpdb->get_col("SHOW COLUMNS FROM {$table} LIKE 'parent_message_id'"));
    if ($has_parent_col && $parent_message_id > 0) {
        $insert_data['parent_message_id'] = $parent_message_id;
        $insert_fmt[] = '%d';
    }

    $result = $wpdb->insert($table, $insert_data, $insert_fmt);

    if ($result === false) {
        return new WP_Error('db_error', 'メッセージ保存に失敗しました: ' . $wpdb->last_error);
    }

    return intval($wpdb->insert_id);
}

/**
 * メッセージID一覧に対するリアクション集計（吹き出しに紐づく）
 */
function aidunite_get_reactions_for_messages($message_ids, $current_user_id = 0) {
    global $wpdb;
    if (empty($message_ids)) {
        return [];
    }
    $table = $wpdb->prefix . 'chat_message_reactions';
    $ids = array_map('intval', $message_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $query = $wpdb->prepare(
        "SELECT message_id, user_id, reaction_type FROM {$table} WHERE message_id IN ($placeholders)",
        ...$ids
    );
    $rows = $wpdb->get_results($query);
    $by_message = [];
    foreach ($rows as $r) {
        $mid = (int) $r->message_id;
        if (!isset($by_message[$mid])) {
            $by_message[$mid] = ['counts' => [], 'my_reactions' => []];
        }
        $type = $r->reaction_type;
        $by_message[$mid]['counts'][$type] = ($by_message[$mid]['counts'][$type] ?? 0) + 1;
        if ($current_user_id && (int) $r->user_id === (int) $current_user_id) {
            $by_message[$mid]['my_reactions'][$type] = true;
        }
    }
    return $by_message;
}

/**
 * チャットメッセージ取得（parent_message_id・リアクション付き）
 */
function aidunite_get_chat_messages_data($room_id, $page = 1, $per_page = 50) {
    global $wpdb;
    $table = $wpdb->prefix . 'chat_messages';
    $offset = ($page - 1) * $per_page;
    $room_id_int = intval($room_id);
    if (!$room_id_int) {
        return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $per_page, 'total_pages' => 0];
    }

    $has_parent = in_array('parent_message_id', $wpdb->get_col("SHOW COLUMNS FROM {$table} LIKE 'parent_message_id'"));
    $sel = $has_parent
        ? "SELECT m.id, m.room_id, m.sender_id, m.parent_message_id, m.message_type, m.content, m.file_url, m.is_edited, m.edited_at, m.is_private, m.created_at, u.display_name as sender_name, u.user_email as sender_email"
        : "SELECT m.id, m.room_id, m.sender_id, m.message_type, m.content, m.file_url, m.is_edited, m.edited_at, m.is_private, m.created_at, u.display_name as sender_name, u.user_email as sender_email";

    $messages = $wpdb->get_results($wpdb->prepare(
        "{$sel}
         FROM {$table} m
         LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
         WHERE m.room_id = %d
         ORDER BY m.created_at ASC
         LIMIT %d OFFSET %d",
        $room_id_int, $per_page, $offset
    ));

    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE room_id = %d", $room_id_int));

    if (!$messages) {
        return [
            'messages' => [],
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 1
        ];
    }

    $message_ids = array_map(function ($m) { return (int) $m->id; }, $messages);
    $reactions_map = aidunite_get_reactions_for_messages($message_ids, get_current_user_id());

    foreach ($messages as $m) {
        $m->reactions = isset($reactions_map[$m->id]) ? $reactions_map[$m->id]['counts'] : [];
        $m->my_reactions = isset($reactions_map[$m->id]) ? array_keys($reactions_map[$m->id]['my_reactions']) : [];
        if (!$has_parent) {
            $m->parent_message_id = null;
        }
        $team_meta = aidunite_resolve_sender_team_name_for_chat((int) $m->sender_id, $room_id_int);
        $m->sender_team_id = $team_meta['team_id'];
        $m->team_id = $team_meta['team_id'];
        $m->team_name = $team_meta['team_name'];
        $m->sender_team_name = $team_meta['team_name'];
    }

    return [
        'messages' => $messages,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 1
    ];
}

/**
 * 吹き出しへのリアクション追加・削除（トグル）。新規メッセージは作らない。
 */
function aidunite_toggle_chat_reaction($message_id, $user_id, $room_id, $reaction_type) {
    global $wpdb;
    $table = $wpdb->prefix . 'chat_message_reactions';
    $msg_table = $wpdb->prefix . 'chat_messages';

    $message_id = intval($message_id);
    $user_id = intval($user_id);
    $room_id = intval($room_id);
    $reaction_type = sanitize_text_field($reaction_type);

    $allowed = ['thumb_up', 'favorite', 'ok', 'laugh', 'pray', 'fire', 'clap', 'heart', 'wow', 'sad', 'angry', 'check'];
    if (!in_array($reaction_type, $allowed, true)) {
        return new WP_Error('invalid_reaction', '指定のリアクションは利用できません');
    }

    $msg = $wpdb->get_row($wpdb->prepare(
        "SELECT id, room_id FROM {$msg_table} WHERE id = %d AND room_id = %d",
        $message_id, $room_id
    ));
    if (!$msg) {
        return new WP_Error('not_found', 'メッセージが見つかりません');
    }
    if (!aidunite_check_chat_permission($room_id, $user_id)) {
        return new WP_Error('forbidden', 'このルームでリアクションできません');
    }

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE message_id = %d AND user_id = %d AND reaction_type = %s",
        $message_id, $user_id, $reaction_type
    ));

    if ($exists) {
        $wpdb->delete($table, ['message_id' => $message_id, 'user_id' => $user_id, 'reaction_type' => $reaction_type], ['%d', '%d', '%s']);
        return ['action' => 'removed', 'reaction_type' => $reaction_type];
    }

    $wpdb->insert($table, [
        'message_id' => $message_id,
        'user_id' => $user_id,
        'reaction_type' => $reaction_type
    ], ['%d', '%d', '%s']);
    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'リアクションの保存に失敗しました');
    }
    return ['action' => 'added', 'reaction_type' => $reaction_type];
}

/**
 * ルームの参加者一覧（WebPushや通知用）
 */
function aidunite_get_team_members_from_room($room_id) {
    global $wpdb;
    $participants_table = $wpdb->prefix . 'chat_participants';
    return $wpdb->get_results($wpdb->prepare("SELECT user_id, role FROM {$participants_table} WHERE room_id = %d", intval($room_id)));
}

/**
 * ルーム権限チェック
 */
function aidunite_check_chat_permission($room_id, $user_id) {
    global $wpdb;
    $room = aidunite_get_chat_room($room_id);
    if (!$room) return false;

    $user_id_int = intval($user_id);
    if (!$user_id_int) return false;

    // 管理者の場合は常にアクセス許可（テスト・確認用）
    if (current_user_can('administrator')) {
        return true;
    }

    // room_type別の簡易チェック
    if ($room->room_type === 'team') {
        return function_exists('aidunite_user_has_managed_team_access')
            ? aidunite_user_has_managed_team_access($user_id_int, (int) $room->team_id)
            : ((int) get_user_meta($user_id_int, 'team_id', true) === (int) $room->team_id && (int) $room->team_id > 0);
    }

    if ($room->room_type === 'match' || $room->room_type === 'group') {
        // schedule_idベースのチェックを追加（match/group共通）
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}chat_rooms LIKE '%'");
        $has_schedule_id = in_array('schedule_id', $columns);

        if ($has_schedule_id && !empty($room->schedule_id)) {
            // スケジュールの参加チームを取得
            $participants = get_post_meta($room->schedule_id, 'participants', true);
            if ($participants) {
                $participant_ids = array_map('intval', explode(',', $participants));
                if (function_exists('aidunite_user_has_managed_team_access')) {
                    foreach ($participant_ids as $ptid) {
                        if ($ptid > 0 && aidunite_user_has_managed_team_access($user_id_int, $ptid)) {
                            return true;
                        }
                    }
                } else {
                    $user_team_id = (int) get_user_meta($user_id_int, 'team_id', true);
                    if ($user_team_id && in_array($user_team_id, $participant_ids, true)) {
                        return true;
                    }
                }
            }
        }

        // 既存のteam_a_id/team_b_idチェック（後方互換性、matchタイプのみ）
        if ($room->room_type === 'match') {
            if (function_exists('aidunite_user_has_managed_team_access')) {
                foreach ([(int) $room->team_a_id, (int) $room->team_b_id] as $mtid) {
                    if ($mtid > 0 && aidunite_user_has_managed_team_access($user_id_int, $mtid)) {
                        return true;
                    }
                }
                return false;
            }
            $legacy_tid = (int) get_user_meta($user_id_int, 'team_id', true);
            return $legacy_tid > 0 && ($legacy_tid === (int) $room->team_a_id || $legacy_tid === (int) $room->team_b_id);
        }

        // groupタイプの場合は参加者テーブルで判定（既存ロジック）
        if ($room->room_type === 'group') {
            $participants_table = $wpdb->prefix . 'chat_participants';
            $cnt = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$participants_table} WHERE room_id = %d AND user_id = %d", intval($room_id), $user_id_int));
            return $cnt > 0;
        }
    }

    if ($room->room_type === 'message_thread') {
        // メッセージスレッドはチームメンバーならアクセス可能
        return function_exists('aidunite_user_has_managed_team_access')
            ? aidunite_user_has_managed_team_access($user_id_int, (int) $room->team_id)
            : ((int) get_user_meta($user_id_int, 'team_id', true) === (int) $room->team_id && (int) $room->team_id > 0);
    }

    if ($room->room_type === 'system') {
        // システムチャットはチームメンバーならアクセス可能
        return function_exists('aidunite_user_has_managed_team_access')
            ? aidunite_user_has_managed_team_access($user_id_int, (int) $room->team_id)
            : ((int) get_user_meta($user_id_int, 'team_id', true) === (int) $room->team_id && (int) $room->team_id > 0);
    }

    if ($room->room_type === 'group' || $room->room_type === 'direct') {
        // グループ/ダイレクトチャットは参加者テーブルで判定
        $participants_table = $wpdb->prefix . 'chat_participants';
        $cnt = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$participants_table} WHERE room_id = %d AND user_id = %d", intval($room_id), $user_id_int));
        return $cnt > 0;
    }

    // それ以外は参加者テーブルで判定
    $participants_table = $wpdb->prefix . 'chat_participants';
    $cnt = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$participants_table} WHERE room_id = %d AND user_id = %d", intval($room_id), $user_id_int));
    return $cnt > 0;
}

/**
 * チャットルームから試合情報を取得（試合情報パネル用）
 * @param int $room_id チャットルームID
 * @return array|WP_Error|null 試合情報の配列、エラー時はWP_Error、情報がない場合はnull
 */
function aidunite_get_match_info_from_chat_room($room_id) {
    global $wpdb;

    // 会場ラベル（ホーム/アウェイ等）の統一定義を使用するため読み込み
    if (!function_exists('aidunite_get_place_label')) {
        require_once get_template_directory() . '/functions/common/label-functions.php';
    }

    $room_id_int = intval($room_id);
    if (!$room_id_int) {
        return new WP_Error('invalid_room_id', 'チャットルームIDが無効です');
    }

    // チャットルームを取得
    $room = aidunite_get_chat_room($room_id_int);
    if (!$room) {
        return new WP_Error('room_not_found', 'チャットルームが見つかりません');
    }

    // matchまたはgroupタイプのみ対象
    if ($room->room_type !== 'match' && $room->room_type !== 'group') {
        return null; // 試合情報パネルを表示しない
    }

    $schedule_id = null;
    $match_request_id = 0;

    // schedule_idカラムの存在確認
    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}chat_rooms LIKE '%'");
    $has_schedule_id = in_array('schedule_id', $columns);

    // schedule_idから取得（優先）
    if ($room->room_type === 'match' && !empty($room->match_id)) {
        $match_request_id = intval($room->match_id);
    }
    if ($has_schedule_id && !empty($room->schedule_id)) {
        $schedule_id = intval($room->schedule_id);
    } elseif ($match_request_id) {
        // match_idからスケジュールIDを取得（フォールバック）
        $to_schedule_id = get_post_meta($match_request_id, 'to_schedule_id', true);
        $my_schedule_id = get_post_meta($match_request_id, 'my_schedule_id', true);
        $schedule_id = $to_schedule_id ?: $my_schedule_id;
    }

    if (!$schedule_id) {
        return null; // スケジュール情報がない場合はnullを返す
    }

    // スケジュール情報を取得
    $schedule_date = get_post_meta($schedule_id, 'schedule_date', true);
    $schedule_start_time = get_post_meta($schedule_id, 'schedule_start_time', true);
    $schedule_end_time = get_post_meta($schedule_id, 'schedule_end_time', true);
    $venue_name = get_post_meta($schedule_id, 'venue_name', true);
    $schedule_place = get_post_meta($schedule_id, 'schedule_place', true);
    if (empty($schedule_place)) {
        $schedule_place = get_post_meta($schedule_id, 'schedule_place_option', true);
    }
    $venue_address = get_post_meta($schedule_id, 'venue_address', true); // 住所（存在する場合）

    // 申請確定値（match_request）を優先取得
    $selected_start_time = $match_request_id ? get_post_meta($match_request_id, 'selected_start_time', true) : '';
    $selected_end_time = $match_request_id ? get_post_meta($match_request_id, 'selected_end_time', true) : '';
    $selected_place = $match_request_id ? get_post_meta($match_request_id, 'selected_place', true) : '';
    if ($selected_place === 'both') {
        $selected_place = 'either';
    }
    $selected_gender = $match_request_id ? get_post_meta($match_request_id, 'selected_gender', true) : '';
    $from_team_id = $match_request_id ? (int) get_post_meta($match_request_id, 'from_team_id', true) : 0;
    $to_team_id = $match_request_id ? (int) get_post_meta($match_request_id, 'to_team_id', true) : 0;
    $viewer_team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id(get_current_user_id())
        : (int) get_user_meta(get_current_user_id(), 'team_id', true);

    $place_for_viewer = function_exists('aidunite_resolve_place_for_viewer')
        ? aidunite_resolve_place_for_viewer($selected_place, $from_team_id, $to_team_id, $viewer_team_id)
        : $selected_place;

    // 会場表示（統一定義）: selected_place があれば優先表示
    if (!empty($place_for_viewer) && function_exists('aidunite_get_place_label')) {
        $venue_display = aidunite_get_place_label($place_for_viewer);
    // 会場名があればそのまま、なければ会場条件ラベル（ホーム/アウェイ/どちらでも可）
    } elseif (!empty(trim((string) $venue_name))) {
        $venue_display = $venue_name;
    } elseif (function_exists('aidunite_get_place_label')) {
        $venue_display = aidunite_get_place_label($schedule_place ?: '');
    } else {
        $venue_display = $schedule_place ?: '';
    }

    // 日付フォーマット（統一定義: Y年n月j日（曜））
    $date_formatted = '';
    $weekday = '';
    if ($schedule_date) {
        if (class_exists('AidUniteDateUtils')) {
            $date_formatted = AidUniteDateUtils::formatDateForDisplay($schedule_date);
        } else {
            $timestamp = strtotime($schedule_date);
            $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            $weekday = $weekdays[date('w', $timestamp)];
            $date_formatted = date('y/m/d', $timestamp) . '（' . $weekday . '）';
        }
        if ($weekday === '' && $schedule_date) {
            $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            $weekday = $weekdays[date('w', strtotime($schedule_date))];
        }
    }

    // 時間フォーマット
    $time_display = '';
    if ($selected_start_time && $selected_end_time) {
        $time_display = $selected_start_time . ' - ' . $selected_end_time;
    } elseif ($schedule_start_time && $schedule_end_time) {
        $time_display = $schedule_start_time . ' - ' . $schedule_end_time;
    } elseif ($selected_start_time) {
        $time_display = $selected_start_time . ' -';
    } elseif ($schedule_start_time) {
        $time_display = $schedule_start_time . ' -';
    }

    // 参加チーム情報を取得
    $participant_teams = [];
    if ($match_request_id) {
        foreach ([$from_team_id, $to_team_id] as $team_id) {
            if (!$team_id) continue;
            $team_name = aidunite_get_team_name($team_id);
            if ($team_name) {
                $participant_teams[] = ['id' => $team_id, 'name' => $team_name];
            }
        }
        if ($viewer_team_id && count($participant_teams) === 2) {
            usort($participant_teams, function($a, $b) use ($viewer_team_id) {
                if ((int) $a['id'] === $viewer_team_id) return -1;
                if ((int) $b['id'] === $viewer_team_id) return 1;
                return 0;
            });
        }
    }
    if (empty($participant_teams)) {
        $participants = get_post_meta($schedule_id, 'participants', true);
        if ($participants) {
            $participant_ids = array_map('intval', array_filter(explode(',', $participants)));
            foreach ($participant_ids as $team_id) {
                if ($team_id) {
                    $team_name = aidunite_get_team_name($team_id);
                    if ($team_name) {
                        $participant_teams[] = ['id' => $team_id, 'name' => $team_name];
                    }
                }
            }
        }
    }
    $participant_count = count($participant_teams);

    // 参加チーム名のリスト
    $participant_names = array_column($participant_teams, 'name');
    $participant_names_display = !empty($participant_names) ? implode(', ', $participant_names) : '参加チームなし';

    // Google Mapsリンク生成（住所がある場合）
    $map_link = '';
    if ($venue_address) {
        $encoded_address = urlencode($venue_address);
        $map_link = "https://www.google.com/maps/search/?api=1&query={$encoded_address}";
    } elseif ($venue_display) {
        // 会場名から検索
        $encoded_venue = urlencode($venue_display);
        $map_link = "https://www.google.com/maps/search/?api=1&query={$encoded_venue}";
    }

    // 性別は申請時の確定値（selected_gender）を優先表示
    if (!empty($selected_gender) && function_exists('aidunite_jp_gender')) {
        $gender_display = aidunite_jp_gender($selected_gender);
    } else {
        $male_slots = function_exists('aidunite_get_schedule_male_slots') ? (int) aidunite_get_schedule_male_slots($schedule_id) : (int) get_post_meta($schedule_id, 'male_slots', true);
        $female_slots = function_exists('aidunite_get_schedule_female_slots') ? (int) aidunite_get_schedule_female_slots($schedule_id) : (int) get_post_meta($schedule_id, 'female_slots', true);
        if ($male_slots > 0 && $female_slots > 0) {
            $gender_display = "男子{$male_slots}、女子{$female_slots}";
        } elseif ($male_slots > 0) {
            $gender_display = "男子:{$male_slots}";
        } elseif ($female_slots > 0) {
            $gender_display = "女子:{$female_slots}";
        } else {
            $gender_display = '—';
        }
    }

    // 天気予報（オプション、将来実装）
    $weather_info = null;
    // TODO: 天気予報API連携（試合当日の天気を取得）

    return [
        'schedule_id' => $schedule_id,
        'date' => $schedule_date,
        'date_formatted' => $date_formatted,
        'weekday' => $weekday,
        'start_time' => $selected_start_time ?: $schedule_start_time,
        'end_time' => $selected_end_time ?: $schedule_end_time,
        'time_display' => $time_display,
        'venue_name' => $venue_display,
        'venue_address' => $venue_address,
        'map_link' => $map_link,
        'gender_display' => $gender_display,
        'participants' => $participant_teams,
        'participant_count' => $participant_count,
        'participant_names_display' => $participant_names_display,
        'weather_info' => $weather_info
    ];
}

/**
 * チャットの既読状態を更新
 */
function aidunite_update_read_status($room_id, $user_id, $message_id = 0) {
    global $wpdb;

    $read_status_table = $wpdb->prefix . 'chat_read_status';
    $room_id_int = intval($room_id);
    $user_id_int = intval($user_id);
    $message_id_int = intval($message_id);

    if (!$room_id_int || !$user_id_int) {
        return new WP_Error('invalid_params', 'room_idまたはuser_idが無効です');
    }

    // 既読状態を更新または挿入
    $result = $wpdb->replace($read_status_table, [
        'room_id' => $room_id_int,
        'user_id' => $user_id_int,
        'last_read_message_id' => $message_id_int,
        'last_read_at' => current_time('mysql')
    ], [
        '%d', '%d', '%d', '%s'
    ]);

    if ($result === false) {
        return new WP_Error('db_error', '既読状態の更新に失敗しました: ' . $wpdb->last_error);
    }

    return true;
}

/**
 * チャットルームの未読数を取得
 *
 * @param int $room_id チャットルームID
 * @param int $user_id ユーザーID
 * @return int 未読メッセージ数
 */
function aidunite_get_unread_count($room_id, $user_id) {
    global $wpdb;

    $room_id_int = intval($room_id);
    $user_id_int = intval($user_id);

    if (!$room_id_int || !$user_id_int) {
        return 0;
    }

    $read_status_table = $wpdb->prefix . 'chat_read_status';
    $chat_messages_table = $wpdb->prefix . 'chat_messages';

    // ユーザーの最後に読んだメッセージIDを取得
    $last_read_message_id = $wpdb->get_var($wpdb->prepare(
        "SELECT last_read_message_id FROM {$read_status_table}
         WHERE room_id = %d AND user_id = %d",
        $room_id_int, $user_id_int
    ));

    $last_read_message_id = intval($last_read_message_id);

    // 最後に読んだメッセージIDより大きく、自分以外かつシステム(sender_id=0)以外のメッセージのみカウント
    $unread_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$chat_messages_table}
         WHERE room_id = %d AND id > %d AND sender_id != %d AND sender_id > 0",
        $room_id_int, $last_read_message_id, $user_id_int
    ));

    return intval($unread_count);
}

/**
 * 複数のチャットルームの未読数を一括取得
 *
 * @param array $room_ids チャットルームIDの配列
 * @param int $user_id ユーザーID
 * @return array ルームIDをキーとした未読数の配列
 */
function aidunite_get_unread_counts($room_ids, $user_id) {
    global $wpdb;

    $user_id_int = intval($user_id);
    if (!$user_id_int || empty($room_ids)) {
        return [];
    }

    $room_ids_int = array_map('intval', $room_ids);
    $room_ids_int = array_filter($room_ids_int);

    if (empty($room_ids_int)) {
        return [];
    }

    $read_status_table = $wpdb->prefix . 'chat_read_status';
    $chat_messages_table = $wpdb->prefix . 'chat_messages';
    $placeholders = implode(',', array_fill(0, count($room_ids_int), '%d'));

    // 各ルームの最後に読んだメッセージIDを取得
    $read_statuses = $wpdb->get_results($wpdb->prepare(
        "SELECT room_id, last_read_message_id
         FROM {$read_status_table}
         WHERE room_id IN ($placeholders) AND user_id = %d",
        ...array_merge($room_ids_int, [$user_id_int])
    ), OBJECT_K);

    // 各ルームの最新メッセージIDを取得
    $latest_messages = $wpdb->get_results($wpdb->prepare(
        "SELECT room_id, MAX(id) as max_message_id
         FROM {$chat_messages_table}
         WHERE room_id IN ($placeholders)
         GROUP BY room_id",
        ...$room_ids_int
    ), OBJECT_K);

    $unread_counts = [];

    foreach ($room_ids_int as $room_id) {
        $last_read_id = isset($read_statuses[$room_id])
            ? intval($read_statuses[$room_id]->last_read_message_id)
            : 0;

        $max_message_id = isset($latest_messages[$room_id])
            ? intval($latest_messages[$room_id]->max_message_id)
            : 0;

        if ($max_message_id > $last_read_id) {
            // 未読メッセージ数をカウント（自分が送ったメッセージとシステム通知 sender_id=0 は除外）
            $unread_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$chat_messages_table}
                 WHERE room_id = %d AND id > %d AND sender_id != %d AND sender_id > 0",
                $room_id, $last_read_id, $user_id_int
            ));
            $unread_counts[$room_id] = intval($unread_count);
        } else {
            $unread_counts[$room_id] = 0;
        }
    }

    return $unread_counts;
}

/**
 * チャットメッセージ検索
 */
function aidunite_search_chat_messages_data($room_id, $search_term, $page = 1, $per_page = 20) {
    global $wpdb;

    $table = $wpdb->prefix . 'chat_messages';
    $offset = ($page - 1) * $per_page;
    $room_id_int = intval($room_id);

    if (!$room_id_int) {
        return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $per_page, 'total_pages' => 0];
    }

    $search_like = '%' . $wpdb->esc_like($search_term) . '%';

    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, u.display_name as sender_name, u.user_email as sender_email
         FROM {$table} m
         LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
         WHERE m.room_id = %d
         AND (m.content LIKE %s OR u.display_name LIKE %s)
         ORDER BY m.created_at DESC
         LIMIT %d OFFSET %d",
        $room_id_int, $search_like, $search_like, $per_page, $offset
    ));

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} m
         LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
         WHERE m.room_id = %d
         AND (m.content LIKE %s OR u.display_name LIKE %s)",
        $room_id_int, $search_like, $search_like
    ));

    return [
        'messages' => $messages ?: [],
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 1,
        'search_term' => $search_term
    ];
}

/**
 * 操作中チーム scope に合うチャットルーム ID のみ残す（参加者テーブル横断の漏れ防止）
 *
 * @param int[] $room_ids
 * @param int[] $scope_team_ids
 * @return int[]
 */
function aidunite_filter_chat_room_ids_for_team_scope(array $room_ids, array $scope_team_ids) {
    global $wpdb;

    $room_ids = array_values(array_unique(array_filter(array_map('intval', $room_ids))));
    $scope_team_ids = array_values(array_unique(array_filter(array_map('intval', $scope_team_ids))));
    if ($room_ids === [] || $scope_team_ids === []) {
        return $room_ids;
    }

    $chat_rooms_table = $wpdb->prefix . 'chat_rooms';
    $ph_r = implode(',', array_fill(0, count($room_ids), '%d'));
    $ph_t = implode(',', array_fill(0, count($scope_team_ids), '%d'));

    $sql = "SELECT id FROM {$chat_rooms_table}
        WHERE id IN ($ph_r)
        AND (
            (room_type = 'match' AND (team_a_id IN ($ph_t) OR team_b_id IN ($ph_t)))
            OR (room_type IN ('team', 'message_thread', 'system', 'group', 'direct') AND team_id IN ($ph_t))
        )";

    $params = array_merge($room_ids, $scope_team_ids, $scope_team_ids);
    $allowed = $wpdb->get_col($wpdb->prepare($sql, $params));

    return array_values(array_map('intval', (array) $allowed));
}

/**
 * 統合タイムラインを取得
 * メッセージ掲示板の投稿とチャットルームの最新メッセージを時系列で統合
 *
 * @param int $user_id ユーザーID
 * @param int $team_id チームID。**0 以下**のときは `aidunite_get_managed_team_ids` に基づき掲示板・チームルーム・match ルームを横断する。正の ID のときはそのチームのみ（操作中チームと一致させること）。
 * @param int $page ページ番号
 * @param int $per_page 1ページあたりの件数
 * @return array{items: array, total: int, page: int, per_page: int, total_pages: float|int, total_unread: int, by_type: array{match: int, team: int, board: int}} total_unread / by_type は参加ルーム全体に基づきページングと独立
 */
/**
 * タイムライン用：閲覧チーム視点の相手チーム表示名
 *
 * @param object|null $room_row chat_rooms 行（team_a_id / team_b_id 等）
 * @param int[]       $viewer_team_ids 操作中・managed の team ID
 */
function aidunite_timeline_opponent_team_label($room_row, array $viewer_team_ids) {
    if (!$room_row || empty($viewer_team_ids)) {
        return '';
    }
    $viewer_team_ids = array_values(array_unique(array_filter(array_map('intval', $viewer_team_ids))));
    $room_type = isset($room_row->room_type) ? (string) $room_row->room_type : '';

    if ($room_type === 'match') {
        $team_a = isset($room_row->team_a_id) ? (int) $room_row->team_a_id : 0;
        $team_b = isset($room_row->team_b_id) ? (int) $room_row->team_b_id : 0;
        $opponent_ids = [];
        if ($team_a > 0 && !in_array($team_a, $viewer_team_ids, true)) {
            $opponent_ids[] = $team_a;
        }
        if ($team_b > 0 && !in_array($team_b, $viewer_team_ids, true)) {
            $opponent_ids[] = $team_b;
        }
        if ($opponent_ids === [] && $team_a > 0 && $team_b > 0) {
            foreach ([$team_a, $team_b] as $tid) {
                if (!in_array($tid, $viewer_team_ids, true)) {
                    $opponent_ids[] = $tid;
                }
            }
        }
        $names = [];
        foreach (array_unique($opponent_ids) as $tid) {
            $names[] = function_exists('aidunite_get_team_name') ? aidunite_get_team_name($tid) : get_the_title($tid);
        }
        return implode('、', array_filter($names));
    }

    if ($room_type === 'team' && !empty($room_row->team_id)) {
        $tid = (int) $room_row->team_id;
        if (!in_array($tid, $viewer_team_ids, true)) {
            return function_exists('aidunite_get_team_name') ? aidunite_get_team_name($tid) : get_the_title($tid);
        }
    }

    return '';
}

/**
 * 送信者の team 表示名（チャット吹き出し用・第一段階）
 */
function aidunite_resolve_sender_team_name_for_chat($sender_id, $room_id = 0) {
    $sender_id = (int) $sender_id;
    if ($sender_id <= 0) {
        return ['team_id' => 0, 'team_name' => ''];
    }
    $team_id = 0;
    if (function_exists('aidunite_get_current_team_id')) {
        $team_id = (int) aidunite_get_current_team_id($sender_id);
    }
    if ($team_id <= 0) {
        $team_id = (int) get_user_meta($sender_id, 'team_id', true);
    }
    if ($team_id <= 0 && function_exists('aidunite_get_managed_team_ids')) {
        $managed = aidunite_get_managed_team_ids($sender_id);
        $team_id = !empty($managed) ? (int) $managed[0] : 0;
    }
    if ($team_id <= 0) {
        return ['team_id' => 0, 'team_name' => ''];
    }
    $name = function_exists('aidunite_get_team_name') ? aidunite_get_team_name($team_id) : get_the_title($team_id);
    return ['team_id' => $team_id, 'team_name' => (string) $name];
}

/**
 * 指定ルーム ID 群のタイムライン用チャット行を組み立てる
 *
 * @param int[] $room_ids
 * @param int   $user_id_int
 * @param int[] $scope_team_ids
 * @param array $opts is_hidden(bool)
 * @return array<int, array<string, mixed>>
 */
function aidunite_build_timeline_chat_items_for_room_ids(array $room_ids, $user_id_int, array $scope_team_ids, array $opts = []) {
    global $wpdb;

    $is_hidden = !empty($opts['is_hidden']);
    $room_ids = array_values(array_unique(array_filter(array_map('intval', $room_ids))));
    if ($room_ids === []) {
        return [];
    }

    $chat_rooms_table = $wpdb->prefix . 'chat_rooms';
    $chat_messages_table = $wpdb->prefix . 'chat_messages';
    $placeholders = implode(',', array_fill(0, count($room_ids), '%d'));

    $latest_messages = $wpdb->get_results($wpdb->prepare(
        "SELECT
            m.id as message_id,
            m.room_id,
            m.content,
            m.created_at,
            m.sender_id,
            u.display_name as sender_name,
            r.name as room_name,
            r.room_type,
            r.status as room_status,
            r.team_a_id,
            r.team_b_id,
            r.team_id
         FROM {$chat_messages_table} m
         INNER JOIN (
             SELECT room_id, MAX(id) as max_message_id
             FROM {$chat_messages_table}
             WHERE room_id IN ($placeholders)
             GROUP BY room_id
         ) latest ON m.room_id = latest.room_id AND m.id = latest.max_message_id
         LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
         LEFT JOIN {$chat_rooms_table} r ON m.room_id = r.id
         WHERE m.room_id IN ($placeholders)
         AND (r.status IN ('active', 'completed', 'archived') OR r.status = '' OR r.status IS NULL)"
        . (aidunite_chat_rooms_has_merged_into_column()
            ? ' AND (r.merged_into_room_id IS NULL OR r.merged_into_room_id = 0)'
            : ''),
        ...array_merge($room_ids, $room_ids)
    ));

    if (!$latest_messages) {
        return [];
    }

    $room_ids_for_unread = array_map(static function ($msg) {
        return (int) $msg->room_id;
    }, $latest_messages);
    $unread_counts = aidunite_get_unread_counts($room_ids_for_unread, $user_id_int);

    $items = [];
    foreach ($latest_messages as $msg) {
        $room_id_int = (int) $msg->room_id;
        $unread_count = isset($unread_counts[$room_id_int]) ? (int) $unread_counts[$room_id_int] : 0;
        $opponent_label = aidunite_timeline_opponent_team_label($msg, $scope_team_ids);

        $items[] = [
            'id' => 'chat_' . $msg->room_id . '_' . $msg->message_id . ($is_hidden ? '_hidden' : ''),
            'title' => $msg->room_name,
            'content' => $msg->content,
            'category' => null,
            'priority' => null,
            'created_at' => $msg->created_at,
            'updated_at' => $msg->created_at,
            'author_name' => $msg->sender_name,
            'user_id' => isset($msg->sender_id) ? (int) $msg->sender_id : null,
            'item_type' => 'chat',
            'room_id' => $msg->room_id,
            'room_type' => $msg->room_type,
            'room_name' => $msg->room_name,
            'room_status' => (!empty($msg->room_status) ? $msg->room_status : 'active'),
            'thread_room_id' => null,
            'unread_count' => $unread_count,
            'opponent_team_label' => $opponent_label,
            'opponent_team_name' => $opponent_label,
            'is_hidden' => $is_hidden ? 1 : 0,
        ];
    }

    return $items;
}

function aidunite_get_unified_timeline($user_id, $team_id, $page = 1, $per_page = 50) {
    global $wpdb;

    $user_id_int = intval($user_id);
    $team_id_int = intval($team_id);
    $offset = ($page - 1) * $per_page;

    $messages_table = $wpdb->prefix . 'team_messages';
    $chat_rooms_table = $wpdb->prefix . 'chat_rooms';
    $chat_messages_table = $wpdb->prefix . 'chat_messages';
    $participants_table = $wpdb->prefix . 'chat_participants';

    // team_id が 0 以下のときは managed 全チーム横断（通常はフロントが操作中 team_id を渡す）
    if ($team_id_int <= 0) {
        $scope_team_ids = function_exists('aidunite_get_managed_team_ids')
            ? aidunite_get_managed_team_ids($user_id_int)
            : [];
        if (empty($scope_team_ids)) {
            $legacy = (int) get_user_meta($user_id_int, 'team_id', true);
            $scope_team_ids = $legacy > 0 ? [$legacy] : [];
        }
    } else {
        $scope_team_ids = [$team_id_int];
    }

    // 1. メッセージ掲示板の投稿を取得（関連するmessage_threadルームID・投稿者user_idも取得）
    $board_messages = [];
    if (!empty($scope_team_ids)) {
        $ph_board = implode(',', array_fill(0, count($scope_team_ids), '%d'));
        $board_messages = $wpdb->get_results($wpdb->prepare(
            "SELECT
            tm.id,
            tm.user_id,
            tm.title,
            tm.content,
            tm.category,
            tm.priority,
            tm.created_at,
            tm.updated_at,
            u.display_name as author_name,
            cr.id as thread_room_id,
            'board' as item_type,
            NULL as room_id,
            NULL as room_type,
            NULL as room_name
         FROM {$messages_table} tm
         LEFT JOIN {$wpdb->users} u ON tm.user_id = u.ID
         LEFT JOIN {$chat_rooms_table} cr
             ON cr.room_type = 'message_thread'
            AND cr.related_message_id = tm.id
         WHERE tm.team_id IN ($ph_board)
         AND tm.status = 'published'
         ORDER BY tm.created_at DESC
         LIMIT %d OFFSET %d",
            array_merge($scope_team_ids, [$per_page, $offset])
        ));
    }

    // 2. ユーザーが参加しているチャットルームを取得
    $user_rooms = [];

    // 2-1. 参加者テーブルから取得（group, direct）
    $participant_rooms = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT room_id FROM {$participants_table} WHERE user_id = %d",
        $user_id_int
    ));

    // 2-2. チーム関連ルーム（team, message_thread, system）
    if (!empty($scope_team_ids)) {
        $ph_tr = implode(',', array_fill(0, count($scope_team_ids), '%d'));
        $team_rooms = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$chat_rooms_table}
             WHERE team_id IN ($ph_tr)
             AND room_type IN ('team', 'message_thread', 'system')",
            $scope_team_ids
        ));
        $participant_rooms = array_merge($participant_rooms, $team_rooms);
    }

    // 2-3. ゲームリスト用チャットルーム（match）— 統合副ルームは除外
    if (!empty($scope_team_ids)) {
        $ph_m = implode(',', array_fill(0, count($scope_team_ids), '%d'));
        $merged_excl = aidunite_chat_rooms_has_merged_into_column()
            ? ' AND (merged_into_room_id IS NULL OR merged_into_room_id = 0)'
            : '';
        $match_rooms = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$chat_rooms_table}
             WHERE room_type = 'match'
             AND (team_a_id IN ($ph_m) OR team_b_id IN ($ph_m)){$merged_excl}",
            array_merge($scope_team_ids, $scope_team_ids)
        ));
        $participant_rooms = array_merge($participant_rooms, $match_rooms);
    }

    $participant_rooms = array_values(array_unique(array_map('intval', $participant_rooms)));
    if (!empty($participant_rooms) && !empty($scope_team_ids)) {
        $participant_rooms = aidunite_filter_chat_room_ids_for_team_scope($participant_rooms, $scope_team_ids);
    }
    $hidden_room_ids = aidunite_get_hidden_chat_room_ids($user_id_int);
    if (!empty($hidden_room_ids)) {
        $participant_rooms = array_values(array_diff($participant_rooms, $hidden_room_ids));
    }
    $merged_satellite_ids = aidunite_get_merged_satellite_chat_room_ids($participant_rooms);
    if (!empty($merged_satellite_ids)) {
        $participant_rooms = array_values(array_diff($participant_rooms, $merged_satellite_ids));
    }

    // 参加ルーム全体の未読合計・種別集計（ページング・掲示板SQLのLIMITに依存しない）
    $total_unread = 0;
    $by_type = ['match' => 0, 'team' => 0, 'board' => 0];
    if (!empty($participant_rooms)) {
        $room_unread_map = aidunite_get_unread_counts($participant_rooms, $user_id_int);
        $total_unread = array_sum($room_unread_map);
        $ph_rt = implode(',', array_fill(0, count($participant_rooms), '%d'));
        $rt_sql = "SELECT id, room_type FROM {$chat_rooms_table} WHERE id IN ($ph_rt)";
        $rt_rows = $wpdb->get_results($wpdb->prepare($rt_sql, $participant_rooms));
        $room_type_by_id = [];
        foreach ((array) $rt_rows as $rt_row) {
            $room_type_by_id[(int) $rt_row->id] = (string) $rt_row->room_type;
        }
        foreach ($participant_rooms as $prid) {
            $prid = (int) $prid;
            $uc = isset($room_unread_map[$prid]) ? (int) $room_unread_map[$prid] : 0;
            if ($uc <= 0) {
                continue;
            }
            $rt = isset($room_type_by_id[$prid]) ? $room_type_by_id[$prid] : '';
            if ($rt === 'message_thread') {
                $by_type['board'] += $uc;
            } elseif ($rt === 'match') {
                $by_type['match'] += $uc;
            } else {
                $by_type['team'] += $uc;
            }
        }
    }

    // 3. 各チャットルームの最新メッセージを取得（表示中ルーム）
    $chat_items = aidunite_build_timeline_chat_items_for_room_ids(
        $participant_rooms,
        $user_id_int,
        $scope_team_ids,
        ['is_hidden' => false]
    );

    // 3b. ユーザーが削除（非表示）したルーム
    if (!empty($hidden_room_ids)) {
        $hidden_scope_ids = $hidden_room_ids;
        if (!empty($scope_team_ids)) {
            $hidden_scope_ids = aidunite_filter_chat_room_ids_for_team_scope($hidden_room_ids, $scope_team_ids);
        }
        $hidden_chat_items = aidunite_build_timeline_chat_items_for_room_ids(
            $hidden_scope_ids,
            $user_id_int,
            $scope_team_ids,
            ['is_hidden' => true]
        );
        if (!empty($hidden_chat_items)) {
            $chat_items = array_merge($chat_items, $hidden_chat_items);
        }
    }

    // メッセージ掲示板の未読数も取得（message_threadルームがある場合）
    $thread_room_ids = array_filter(array_map(function($msg) {
        return intval($msg->thread_room_id);
    }, $board_messages));

    if (!empty($thread_room_ids)) {
        $thread_unread_counts = aidunite_get_unread_counts($thread_room_ids, $user_id_int);
    } else {
        $thread_unread_counts = [];
    }

    // 4. 統合して時系列でソート
    $timeline_items = array_merge(
        array_map(function($msg) use ($thread_unread_counts) {
            $thread_room_id = intval($msg->thread_room_id);
            $unread_count = ($thread_room_id && isset($thread_unread_counts[$thread_room_id]))
                ? $thread_unread_counts[$thread_room_id]
                : 0;

            return [
                'id' => 'board_' . $msg->id,
                'title' => $msg->title,
                'content' => $msg->content,
                'category' => $msg->category,
                'priority' => $msg->priority,
                'created_at' => $msg->created_at,
                'updated_at' => $msg->updated_at,
                'author_name' => $msg->author_name,
                'user_id' => isset($msg->user_id) ? (int) $msg->user_id : null,
                'item_type' => 'board',
                'room_id' => $msg->thread_room_id, // message_threadルームID
                'room_type' => null,
                'room_name' => null,
                'thread_room_id' => $msg->thread_room_id,
                'unread_count' => $unread_count
            ];
        }, $board_messages),
        $chat_items
    );

    // 5. 時系列でソート（最新順）
    usort($timeline_items, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // 6. ページネーション
    $total = count($timeline_items);
    $paginated_items = array_slice($timeline_items, $offset, $per_page);

    return [
        'items' => $paginated_items,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page),
        'total_unread' => $total_unread,
        'by_type' => $by_type
    ];
}
