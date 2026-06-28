<?php
/**
 * チャットルーム・メッセージ保存本体（normalize 経由）
 *
 * 保存先はカスタムテーブル wp_*chat_rooms / wp_*chat_messages（postmeta ではない）。
 *
 * @see docs/spec/chat.md
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @return string[] */
function aidunite_chat_canonical_room_types() {
    return ['team', 'match', 'message_thread', 'system', 'group', 'direct'];
}

/** @return string[] */
function aidunite_chat_canonical_room_statuses() {
    return ['active', 'completed', 'archived'];
}

/** @return string[] */
function aidunite_chat_canonical_message_types() {
    return ['text', 'image', 'file', 'evaluation_request', 'system'];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_chat_normalize_input(array $raw) {
    if (function_exists('aidunite_normalize_chat_payload')) {
        return aidunite_normalize_chat_payload($raw);
    }

    return $raw;
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_chat_normalize_room_type_value($raw) {
    $t = strtolower(trim((string) $raw));
    $normalized = aidunite_chat_normalize_input(['room_type' => $t]);

    return (string) ($normalized['room_type'] ?? '');
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_chat_normalize_room_status_value($raw) {
    $s = strtolower(trim((string) $raw));
    $normalized = aidunite_chat_normalize_input(['room_status' => $s]);
    if (!empty($normalized['room_status'])) {
        return (string) $normalized['room_status'];
    }
    if (in_array($s, aidunite_chat_canonical_room_statuses(), true)) {
        return $s;
    }

    return '';
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_chat_normalize_message_type_value($raw) {
    $normalized = aidunite_chat_normalize_input(['message_type' => (string) $raw]);

    return (string) ($normalized['message_type'] ?? '');
}

/**
 * chat_rooms への insert 用行を正規化
 *
 * @param array<string, mixed> $room_data
 * @return array<string, mixed>
 */
function aidunite_chat_normalize_room_row(array $room_data) {
    $out = $room_data;

    if (isset($out['room_type'])) {
        $type = aidunite_chat_normalize_room_type_value($out['room_type']);
        if ($type !== '') {
            $out['room_type'] = $type;
        } else {
            unset($out['room_type']);
        }
    }

    $status_raw = $out['status'] ?? $out['room_status'] ?? null;
    if ($status_raw !== null && $status_raw !== '') {
        $status = aidunite_chat_normalize_room_status_value($status_raw);
        if ($status !== '') {
            $out['status'] = $status;
        }
        unset($out['room_status']);
    }

    return $out;
}

/**
 * chat_rooms に1行挿入
 *
 * @param array<string, mixed> $room_data
 * @param array<int, string>|null $format
 * @return int|\WP_Error insert_id
 */
function aidunite_chat_persist_insert_room(array $room_data, $format = null) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $room_data = aidunite_chat_normalize_room_row($room_data);

    if ($format === null) {
        $format = [];
        foreach (array_keys($room_data) as $key) {
            $format[] = is_int($room_data[$key]) ? '%d' : '%s';
        }
    }

    $result = $wpdb->insert($rooms_table, $room_data, $format);
    if ($result === false) {
        return new WP_Error('db_error', 'チャットルームの作成に失敗しました: ' . $wpdb->last_error);
    }

    return (int) $wpdb->insert_id;
}

/**
 * chat_rooms の room_type / status / name 等を更新
 *
 * @param int                  $room_id
 * @param array<string, mixed> $fields
 * @return bool
 */
function aidunite_chat_persist_update_room($room_id, array $fields) {
    global $wpdb;
    $room_id = (int) $room_id;
    if ($room_id <= 0) {
        return false;
    }

    $fields = aidunite_chat_normalize_room_row($fields);
    if ($fields === []) {
        return false;
    }

    $format = [];
    foreach ($fields as $value) {
        $format[] = is_int($value) ? '%d' : '%s';
    }

    $updated = $wpdb->update(
        $rooms_table = $wpdb->prefix . 'chat_rooms',
        $fields,
        ['id' => $room_id],
        $format,
        ['%d']
    );

    return $updated !== false;
}

/**
 * chat_messages に1行挿入（唯一の書き込み口）
 *
 * @param array<string, mixed> $message_data
 * @return int|\WP_Error message id
 */
function aidunite_chat_persist_save_message(array $message_data) {
    global $wpdb;
    $table = $wpdb->prefix . 'chat_messages';

    $room_id = (int) ($message_data['room_id'] ?? 0);
    $sender_id = isset($message_data['sender_id']) ? (int) $message_data['sender_id'] : 0;
    $parent_message_id = isset($message_data['parent_message_id']) ? (int) $message_data['parent_message_id'] : 0;
    $message_type = sanitize_text_field((string) ($message_data['message_type'] ?? 'text'));
    $normalized_type = aidunite_chat_normalize_message_type_value($message_type);
    if ($normalized_type !== '') {
        $message_type = $normalized_type;
    } elseif (!in_array($message_type, aidunite_chat_canonical_message_types(), true)) {
        $message_type = 'text';
    }

    $content = sanitize_textarea_field((string) ($message_data['content'] ?? ''));
    $file_url = sanitize_text_field((string) ($message_data['file_url'] ?? ''));
    $is_private = !empty($message_data['is_private']) ? 1 : 0;

    if ($room_id <= 0) {
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
        'created_at' => current_time('mysql'),
    ];
    $insert_fmt = ['%d', '%d', '%s', '%s', '%s', '%d', '%s'];

    $has_parent_col = in_array('parent_message_id', $wpdb->get_col("SHOW COLUMNS FROM {$table} LIKE 'parent_message_id'"), true);
    if ($has_parent_col && $parent_message_id > 0) {
        $insert_data['parent_message_id'] = $parent_message_id;
        $insert_fmt[] = '%d';
    }

    $result = $wpdb->insert($table, $insert_data, $insert_fmt);
    if ($result === false) {
        return new WP_Error('db_error', 'メッセージ保存に失敗しました: ' . $wpdb->last_error);
    }

    return (int) $wpdb->insert_id;
}

/**
 * mm_chat_message CPT の message_type（postmeta）
 *
 * @param int    $post_id
 * @param string $type_raw
 * @return string
 */
function aidunite_chat_write_mm_message_type_meta($post_id, $type_raw) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }
    $type = aidunite_chat_normalize_message_type_value($type_raw);
    if ($type === '') {
        $type = 'text';
    }
    update_post_meta($post_id, 'message_type', $type);

    return $type;
}

/**
 * @param int    $room_id
 * @param int    $user_id
 * @param string $role member|admin
 * @return bool 新規登録した場合 true
 */
function aidunite_chat_persist_ensure_participant($room_id, $user_id, $role = 'member') {
    global $wpdb;
    $room_id = (int) $room_id;
    $user_id = (int) $user_id;
    if ($room_id < 1 || $user_id < 1) {
        return false;
    }
    $role = sanitize_key($role);
    if ($role === '') {
        $role = 'member';
    }
    $table = $wpdb->prefix . 'chat_participants';
    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE room_id = %d AND user_id = %d",
        $room_id,
        $user_id
    ));
    if ($existing > 0) {
        return false;
    }
    $wpdb->insert($table, [
        'room_id' => $room_id,
        'user_id' => $user_id,
        'role' => $role,
    ], ['%d', '%d', '%s']);

    return true;
}

/**
 * @param int $room_id
 * @return array<string, mixed>
 */
function aidunite_chat_get_canonical_room($room_id) {
    global $wpdb;
    $room_id = (int) $room_id;
    if ($room_id <= 0) {
        return [];
    }
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$rooms_table} WHERE id = %d", $room_id), ARRAY_A);
    if (!$row) {
        return [];
    }

    $type_raw = (string) ($row['room_type'] ?? '');
    $status_raw = (string) ($row['status'] ?? '');

    return [
        'room_id' => $room_id,
        'room_type' => aidunite_chat_normalize_room_type_value($type_raw) ?: $type_raw,
        'room_type_raw' => $type_raw,
        'status' => aidunite_chat_normalize_room_status_value($status_raw) ?: $status_raw,
        'status_raw' => $status_raw,
        'name' => (string) ($row['name'] ?? ''),
        'team_id' => (int) ($row['team_id'] ?? 0),
        'match_id' => (int) ($row['match_id'] ?? 0),
    ];
}
