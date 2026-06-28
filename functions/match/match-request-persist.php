<?php
/**
 * マッチ申請の登録・更新本体（normalize 経由 → postmeta 一本化）
 *
 * 正ルート: aidunite_save_match_application_core / REST / Ajax ステータス更新
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 旧メタへの二重書き込み（緊急時のみ true）
 */
function aidunite_match_request_legacy_meta_writes_enabled() {
    return defined('AIDUNITE_MATCH_REQUEST_LEGACY_META_WRITES') && AIDUNITE_MATCH_REQUEST_LEGACY_META_WRITES;
}

/**
 * 重複 legacy メタの削除（正本が書き込まれたあと）
 */
function aidunite_match_request_purge_legacy_duplicate_meta($match_request_id) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }
    if (aidunite_match_request_legacy_meta_writes_enabled()) {
        return;
    }
    $status = (string) get_post_meta($match_request_id, 'status', true);
    if ($status !== '') {
        delete_post_meta($match_request_id, 'request_status');
    }
    $preferred_pairs = [
        'selected_start_time' => 'preferred_start',
        'selected_end_time' => 'preferred_end',
        'selected_place' => 'preferred_place',
        'selected_gender' => 'preferred_gender',
    ];
    foreach ($preferred_pairs as $canonical_key => $legacy_key) {
        if ((string) get_post_meta($match_request_id, $canonical_key, true) !== '') {
            delete_post_meta($match_request_id, $legacy_key);
        }
    }
    $code = (string) get_post_meta($match_request_id, 'cancel_reason_code', true);
    if ($code !== '') {
        delete_post_meta($match_request_id, 'canceled_reason');
        delete_post_meta($match_request_id, 'aidunite_cancel_reason');
    }
}

/**
 * 生入力を正規化済みペイロードへ
 *
 * @param array<string, mixed> $raw
 * @param bool                 $for_save
 * @return array<string, mixed>
 */
function aidunite_match_request_normalize_input(array $raw, $for_save = true) {
    if (function_exists('aidunite_normalize_match_request_payload')) {
        return aidunite_normalize_match_request_payload($raw, $for_save);
    }
    return $raw;
}

/**
 * キャンセル操作チーム ID を保存
 *
 * @param int $match_request_id
 * @param int $team_id
 */
function aidunite_match_request_persist_canceled_by_team_id($match_request_id, $team_id) {
    $match_request_id = (int) $match_request_id;
    $team_id = (int) $team_id;
    if ($match_request_id < 1 || $team_id < 1) {
        return;
    }
    update_post_meta($match_request_id, 'canceled_by_team_id', $team_id);
}

/**
 * マッチ申請・スケジュールへのチャットルーム ID リンク
 *
 * @param int $match_request_id
 * @param int $chat_room_id
 * @param int $schedule_id
 */
function aidunite_match_request_persist_chat_room_link($match_request_id, $chat_room_id, $schedule_id = 0) {
    $match_request_id = (int) $match_request_id;
    $chat_room_id = (int) $chat_room_id;
    $schedule_id = (int) $schedule_id;
    if ($match_request_id < 1 || $chat_room_id < 1) {
        return;
    }
    update_post_meta($match_request_id, 'chat_room_id', $chat_room_id);
    if ($schedule_id > 0) {
        update_post_meta($schedule_id, 'active_match_chat_room_id', $chat_room_id);
    }
}

/**
 * ログインユーザーの managed team スコープ（REST 用）
 *
 * @param int $user_id
 * @return int[]
 */
function aidunite_match_resolve_user_team_scope($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $team_scope = function_exists('aidunite_get_managed_team_ids')
        ? aidunite_get_managed_team_ids($user_id)
        : [];
    if (!empty($team_scope)) {
        return array_values(array_map('intval', $team_scope));
    }

    if (function_exists('aidunite_user_read_primary_team_id')) {
        $legacy = aidunite_user_read_primary_team_id($user_id);
        if ($legacy > 0) {
            return [$legacy];
        }
    }

    return [];
}

/**
 * 正規化済みペイロードの一部を postmeta へ（status / reason / outcome 等）
 *
 * @param int                  $match_request_id
 * @param array<string, mixed> $normalized
 */
function aidunite_match_request_write_normalized_meta($match_request_id, array $normalized) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }

    $map = [
        'status' => 'status',
        'selected_place' => 'selected_place',
        'selected_gender' => 'selected_gender',
        'cancel_reason_code' => 'cancel_reason_code',
        'outcome_code' => 'mr_outcome_code',
        'mr_outcome_code' => 'mr_outcome_code',
        'requires_reconfirm' => 'requires_reconfirm',
        'approver_type' => 'approver_type',
    ];

    foreach ($map as $key => $meta_key) {
        if (!array_key_exists($key, $normalized)) {
            continue;
        }
        $val = $normalized[$key];
        if ($val === '' || $val === null) {
            continue;
        }
        update_post_meta($match_request_id, $meta_key, $val);
    }

    if (array_key_exists('status', $normalized) && (string) $normalized['status'] !== '') {
        if (aidunite_match_request_legacy_meta_writes_enabled()) {
            update_post_meta($match_request_id, 'request_status', $normalized['status']);
        } else {
            delete_post_meta($match_request_id, 'request_status');
        }
    }

    aidunite_match_request_purge_legacy_duplicate_meta($match_request_id);
}

/**
 * 申請・再申請時のコアメタ（チーム・schedule・時間・会場・性別）
 *
 * @param int                  $match_request_id
 * @param array<string, mixed> $args
 */
function aidunite_match_request_write_application_meta($match_request_id, array $args) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }

    $int_keys = ['from_team_id', 'other_team_id', 'to_team_id', 'request_team_id', 'to_schedule_id', 'my_schedule_id'];
    foreach ($int_keys as $key) {
        if (isset($args[$key])) {
            update_post_meta($match_request_id, $key, (int) $args[$key]);
        }
    }

    if (isset($args['selected_start_time'])) {
        $v = sanitize_text_field((string) $args['selected_start_time']);
        update_post_meta($match_request_id, 'selected_start_time', $v);
        if (aidunite_match_request_legacy_meta_writes_enabled()) {
            update_post_meta($match_request_id, 'preferred_start', $v);
        }
    }
    if (isset($args['selected_end_time'])) {
        $v = sanitize_text_field((string) $args['selected_end_time']);
        update_post_meta($match_request_id, 'selected_end_time', $v);
        if (aidunite_match_request_legacy_meta_writes_enabled()) {
            update_post_meta($match_request_id, 'preferred_end', $v);
        }
    }
    if (isset($args['selected_place'])) {
        $v = sanitize_text_field((string) $args['selected_place']);
        if (function_exists('aidunite_normalize_place_payload_value')) {
            $norm = aidunite_normalize_place_payload_value($v);
            if ($norm !== '') {
                $v = $norm;
            }
        }
        update_post_meta($match_request_id, 'selected_place', $v);
        if (aidunite_match_request_legacy_meta_writes_enabled()) {
            update_post_meta($match_request_id, 'preferred_place', $v);
        }
    }
    if (isset($args['selected_gender'])) {
        $v = sanitize_text_field((string) $args['selected_gender']);
        if (function_exists('aidunite_normalize_gender_canonical')) {
            $g = aidunite_normalize_gender_canonical($v);
            if ($g !== '') {
                $v = $g;
            }
        }
        update_post_meta($match_request_id, 'selected_gender', $v);
        if (aidunite_match_request_legacy_meta_writes_enabled()) {
            update_post_meta($match_request_id, 'preferred_gender', $v);
        }
    }

    if (!empty($args['recruit_condition_fp']) && function_exists('aidunite_recruit_schedule_fingerprint')) {
        update_post_meta($match_request_id, 'recruit_condition_fp', (string) $args['recruit_condition_fp']);
    } elseif (isset($args['to_schedule_id']) && (int) $args['to_schedule_id'] > 0
        && function_exists('aidunite_recruit_schedule_fingerprint')) {
        update_post_meta(
            $match_request_id,
            'recruit_condition_fp',
            aidunite_recruit_schedule_fingerprint((int) $args['to_schedule_id'])
        );
    }

    if (isset($args['status']) && (string) $args['status'] !== '') {
        $post = get_post($match_request_id);
        aidunite_match_request_update_status_meta(
            $match_request_id,
            (string) $args['status'],
            $post ? (string) $post->post_status : ''
        );
    }

    aidunite_match_request_purge_legacy_duplicate_meta($match_request_id);
}

/**
 * status 保存（保存時 accepted → established）
 *
 * @param int    $match_request_id
 * @param string $status_raw
 * @param string $post_status
 * @return string canonical status
 */
function aidunite_match_request_update_status_meta($match_request_id, $status_raw, $post_status = '') {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return '';
    }

    $status = function_exists('aidunite_normalize_match_request_status_value')
        ? aidunite_normalize_match_request_status_value($status_raw, $post_status, true)
        : strtolower(trim((string) $status_raw));

    if ($status === '') {
        return '';
    }

    update_post_meta($match_request_id, 'status', $status);
    if (aidunite_match_request_legacy_meta_writes_enabled()) {
        update_post_meta($match_request_id, 'request_status', $status);
    } else {
        delete_post_meta($match_request_id, 'request_status');
    }

    aidunite_match_request_write_status_timestamps($match_request_id, $status, (string) $status_raw);

    return $status;
}

/**
 * ステータスに応じた *_at メタ
 */
function aidunite_match_request_write_status_timestamps($match_request_id, $canonical_status, $action_raw = '') {
    $match_request_id = (int) $match_request_id;
    $canonical_status = strtolower(trim((string) $canonical_status));
    $action_raw = strtolower(trim((string) $action_raw));
    if ($match_request_id < 1 || $canonical_status === '') {
        return;
    }

    $ts = current_time('mysql');
    $stamp_key = $canonical_status . '_at';

    if ($canonical_status === 'established') {
        update_post_meta($match_request_id, 'established_at', $ts);
        if ($action_raw === 'accepted' || $action_raw === '' || $action_raw === 'established') {
            update_post_meta($match_request_id, 'accepted_at', $ts);
        }
        update_post_meta($match_request_id, $stamp_key, $ts);
        return;
    }

    if (in_array($canonical_status, ['pending', 'rejected', 'canceled'], true)) {
        update_post_meta($match_request_id, $stamp_key, $ts);
    }
    if ($action_raw === 'accepted' && $canonical_status !== 'established') {
        update_post_meta($match_request_id, 'accepted_at', $ts);
    }
}

/**
 * cancel reason（正本: cancel_reason_code）
 */
function aidunite_match_request_update_cancel_reason_meta($match_request_id, $reason_code, $legacy_message = '') {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }
    $code = strtolower(trim((string) $reason_code));
    if (!in_array($code, ['mirror_established', 'superseded_established', 'manual_cancel'], true)) {
        return;
    }
    update_post_meta($match_request_id, 'cancel_reason_code', $code);
    if (aidunite_match_request_legacy_meta_writes_enabled()) {
        update_post_meta($match_request_id, 'canceled_reason', $code);
        update_post_meta($match_request_id, 'aidunite_cancel_reason', $code);
    } else {
        delete_post_meta($match_request_id, 'canceled_reason');
        delete_post_meta($match_request_id, 'aidunite_cancel_reason');
    }
    if ($legacy_message !== '') {
        update_post_meta($match_request_id, 'cancel_reason', $legacy_message);
    }
}

/**
 * 申請メタ以外の任意メタ（自動マッチ・招待など）
 *
 * @param int                  $match_request_id
 * @param array<string, mixed> $extra
 */
function aidunite_match_request_write_extra_meta($match_request_id, array $extra) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }
    $allowed = [
        'from_schedule_id', 'type', 'is_auto_match', 'approver_school_name', 'approver_name',
        'approver_type', 'approved_at', 'board_id',
    ];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $extra)) {
            continue;
        }
        $val = $extra[$key];
        if ($val === '' || $val === null) {
            continue;
        }
        update_post_meta($match_request_id, $key, $val);
    }
}

/**
 * 新規作成直後の共通フック（リンク・通知・掲示板はコンテキストで制御）
 *
 * @param int                  $match_request_id
 * @param array<string, mixed> $context notify, to_schedule_id, from_team_id, to_team_id
 */
function aidunite_match_request_after_create_hooks($match_request_id, array $context = []) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }

    if (function_exists('aidunite_match_request_ensure_link_team_meta')) {
        aidunite_match_request_ensure_link_team_meta($match_request_id);
    }

    do_action('aidunite_match_request_saved', $match_request_id);

    if (empty($context['notify'])) {
        return;
    }

    $to_schedule_id = (int) ($context['to_schedule_id'] ?? get_post_meta($match_request_id, 'to_schedule_id', true));
    $from_team_id = (int) ($context['from_team_id'] ?? get_post_meta($match_request_id, 'from_team_id', true));
    $to_team_id = (int) ($context['to_team_id'] ?? get_post_meta($match_request_id, 'to_team_id', true));

    if ($to_schedule_id > 0 && function_exists('update_board_status_on_apply')) {
        update_board_status_on_apply($to_schedule_id);
    }
    if (function_exists('sync_board_status_with_request')) {
        sync_board_status_with_request($match_request_id);
    } elseif ($to_schedule_id > 0 && function_exists('aidunite_sync_match_board_status_from_game')) {
        aidunite_sync_match_board_status_from_game($to_schedule_id);
    }
    $reapply_received_only = !empty($context['reapply_received_only']);
    if (!$reapply_received_only && $to_schedule_id > 0 && $from_team_id > 0 && function_exists('send_match_request_notification')) {
        send_match_request_notification($match_request_id, $to_schedule_id, $from_team_id);
    }
    if ($to_team_id > 0 && $from_team_id > 0 && function_exists('send_match_request_received_notification')) {
        send_match_request_received_notification($match_request_id, $to_team_id, $from_team_id, [
            'reapply' => $reapply_received_only,
        ]);
    }
}

/**
 * match_request 投稿作成（正本メタは write_application_meta 経由）
 *
 * @param array<string, mixed> $args
 * @return int|\WP_Error
 */
function aidunite_match_request_create_post(array $args) {
    $user_id = (int) ($args['post_author'] ?? get_current_user_id());
    $my_team_id = (int) ($args['from_team_id'] ?? 0);
    $other_schedule_id = (int) ($args['to_schedule_id'] ?? 0);
    $selected_start_time = sanitize_text_field((string) ($args['selected_start_time'] ?? ''));
    $selected_end_time = sanitize_text_field((string) ($args['selected_end_time'] ?? ''));
    $post_status = sanitize_key((string) ($args['post_status'] ?? 'publish'));
    if ($post_status === '') {
        $post_status = 'publish';
    }

    $request_id = wp_insert_post([
        'post_type' => 'match_request',
        'post_status' => $post_status,
        'post_title' => (string) ($args['post_title'] ?? "マッチ申請: チーム{$my_team_id} → スケジュール{$other_schedule_id}"),
        'post_content' => (string) ($args['post_content'] ?? "申請時間: {$selected_start_time} - {$selected_end_time}"),
        'post_author' => $user_id,
    ], true);

    if (is_wp_error($request_id)) {
        return $request_id;
    }

    $request_id = (int) $request_id;

    $write = [
        'from_team_id' => $my_team_id,
        'other_team_id' => (int) ($args['other_team_id'] ?? $args['to_team_id'] ?? 0),
        'to_team_id' => (int) ($args['to_team_id'] ?? $args['other_team_id'] ?? 0),
        'request_team_id' => (int) ($args['request_team_id'] ?? $my_team_id),
        'to_schedule_id' => $other_schedule_id,
        'my_schedule_id' => (int) ($args['my_schedule_id'] ?? 0),
        'selected_start_time' => $selected_start_time,
        'selected_end_time' => $selected_end_time,
        'selected_place' => (string) ($args['selected_place'] ?? ''),
        'selected_gender' => (string) ($args['selected_gender'] ?? ''),
        'status' => (string) ($args['status'] ?? 'pending'),
    ];
    aidunite_match_request_write_application_meta($request_id, $write);

    if (!empty($args['extra_meta']) && is_array($args['extra_meta'])) {
        aidunite_match_request_write_extra_meta($request_id, $args['extra_meta']);
    }

    if (!empty($args['after_create']) && is_array($args['after_create'])) {
        aidunite_match_request_after_create_hooks($request_id, $args['after_create']);
    }

    return $request_id;
}

/**
 * 手動申請（publish + pending）の正ルート
 *
 * @param array<string, mixed> $args
 * @return int|\WP_Error
 */
function aidunite_match_request_create_application_post(array $args) {
    $args['post_status'] = $args['post_status'] ?? 'publish';
    $args['status'] = $args['status'] ?? 'pending';
    return aidunite_match_request_create_post($args);
}

/**
 * 掲示板フォーム POST（legacy init）→ persist 経由の申請
 *
 * @return array{success:bool, request_id?:int, message?:string, code?:string}
 */
function aidunite_match_request_create_from_board_form_post($user_id, $my_team_id, $target_schedule_id, $my_schedule_id) {
    if (!function_exists('aidunite_save_match_application_core')) {
        return ['success' => false, 'message' => 'server_misconfigured', 'code' => 'server_misconfigured'];
    }
    return aidunite_save_match_application_core([
        'my_schedule_id' => (int) $my_schedule_id,
        'other_schedule_id' => (int) $target_schedule_id,
    ], (int) $user_id);
}

/**
 * 自動マッチ / 未申請プレースホルダ（draft 等）
 *
 * @param array<string, mixed> $args
 * @return int|\WP_Error
 */
function aidunite_match_request_create_placeholder_post(array $args) {
    $args['post_status'] = $args['post_status'] ?? 'draft';
    if (empty($args['status'])) {
        $args['status'] = 'draft';
    }
    $extra = $args['extra_meta'] ?? [];
    if (!empty($args['type'])) {
        $extra['type'] = $args['type'];
    }
    if (!empty($args['is_auto_match'])) {
        $extra['is_auto_match'] = $args['is_auto_match'];
    }
    if (!empty($args['from_schedule_id'])) {
        $extra['from_schedule_id'] = (int) $args['from_schedule_id'];
    }
    $args['extra_meta'] = $extra;
    unset($args['type'], $args['is_auto_match'], $args['from_schedule_id']);
    return aidunite_match_request_create_post($args);
}

/**
 * ゲスト試合招待 URL 承認用 MR 作成
 *
 * @param array<string, mixed> $args
 * @return int|\WP_Error
 */
function aidunite_match_request_create_guest_invite_post(array $args) {
    $user_id = (int) ($args['post_author'] ?? 0);
    $from_team_id = (int) ($args['from_team_id'] ?? 0);
    $to_team_id = (int) ($args['to_team_id'] ?? 0);
    $schedule_id = (int) ($args['my_schedule_id'] ?? 0);

    $request_id = aidunite_match_request_create_post([
        'post_author' => $user_id,
        'post_status' => 'publish',
        'post_title' => (string) ($args['post_title'] ?? '試合招待承認 ' . current_time('mysql')),
        'from_team_id' => $from_team_id,
        'to_team_id' => $to_team_id,
        'other_team_id' => $to_team_id,
        'request_team_id' => $from_team_id,
        'my_schedule_id' => $schedule_id,
        'to_schedule_id' => (int) ($args['to_schedule_id'] ?? 9999),
        'status' => 'established',
        'extra_meta' => [
            'approver_school_name' => sanitize_text_field((string) ($args['approver_school_name'] ?? '')),
            'approver_name' => sanitize_text_field((string) ($args['approver_name'] ?? '')),
            'approver_type' => 'guest_invite',
            'approved_at' => current_time('mysql'),
        ],
    ]);

    if (is_wp_error($request_id)) {
        return $request_id;
    }

    update_post_meta((int) $request_id, 'established_at', current_time('mysql'));

    return (int) $request_id;
}

/**
 * REST/Ajax 用: 生 params をマージして正規化
 *
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function aidunite_match_request_merge_params_for_persist(array $params) {
    $base = $params;
    foreach (['preferred_start', 'preferred_end', 'preferred_place', 'preferred_gender'] as $legacy) {
        if (!isset($base[$legacy])) {
            continue;
        }
        $map = [
            'preferred_start' => 'selected_start_time',
            'preferred_end' => 'selected_end_time',
            'preferred_place' => 'selected_place',
            'preferred_gender' => 'selected_gender',
        ];
        $canonical_key = $map[$legacy] ?? $legacy;
        if (empty($base[$canonical_key])) {
            $base[$canonical_key] = $base[$legacy];
        }
    }
    if (isset($params['request_status']) && empty($base['status'])) {
        $base['status'] = $params['request_status'];
    }
    return aidunite_match_request_normalize_input($base, true);
}

/**
 * 既存 MR: 正本メタへコピー（空のときのみ）
 *
 * @return array<string, int>
 */
function aidunite_migrate_match_request_meta_keys() {
    $results = [
        'total' => 0,
        'status_migrated' => 0,
        'place_migrated' => 0,
        'gender_migrated' => 0,
        'start_migrated' => 0,
        'end_migrated' => 0,
        'cancel_migrated' => 0,
    ];

    $ids = get_posts([
        'post_type' => 'match_request',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    $results['total'] = count($ids);

    foreach ($ids as $match_request_id) {
        $status = (string) get_post_meta($match_request_id, 'status', true);
        $legacy_status = (string) get_post_meta($match_request_id, 'request_status', true);
        if ($status === '' && $legacy_status !== '') {
            if (update_post_meta($match_request_id, 'status', $legacy_status) !== false) {
                $results['status_migrated']++;
            }
        }

        $pairs = [
            'selected_place' => 'preferred_place',
            'selected_gender' => 'preferred_gender',
            'selected_start_time' => 'preferred_start',
            'selected_end_time' => 'preferred_end',
        ];
        $result_keys = [
            'selected_place' => 'place_migrated',
            'selected_gender' => 'gender_migrated',
            'selected_start_time' => 'start_migrated',
            'selected_end_time' => 'end_migrated',
        ];
        foreach ($pairs as $canonical => $legacy) {
            if ((string) get_post_meta($match_request_id, $canonical, true) === ''
                && (string) get_post_meta($match_request_id, $legacy, true) !== '') {
                if (update_post_meta($match_request_id, $canonical, get_post_meta($match_request_id, $legacy, true)) !== false) {
                    $results[$result_keys[$canonical]]++;
                }
            }
        }

        $code = (string) get_post_meta($match_request_id, 'cancel_reason_code', true);
        if ($code === '') {
            $legacy_code = (string) (get_post_meta($match_request_id, 'canceled_reason', true)
                ?: get_post_meta($match_request_id, 'aidunite_cancel_reason', true));
            if ($legacy_code !== '' && update_post_meta($match_request_id, 'cancel_reason_code', $legacy_code) !== false) {
                $results['cancel_migrated']++;
            }
        }
    }

    return $results;
}

/**
 * 正本が存在する MR から旧メタを削除
 *
 * @return array<string, int>
 */
function aidunite_cleanup_match_request_legacy_meta_keys() {
    $results = [
        'total' => 0,
        'request_status_deleted' => 0,
        'cancel_legacy_deleted' => 0,
    ];

    $ids = get_posts([
        'post_type' => 'match_request',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    $results['total'] = count($ids);

    foreach ($ids as $match_request_id) {
        if ((string) get_post_meta($match_request_id, 'status', true) !== '') {
            if (delete_post_meta($match_request_id, 'request_status')) {
                $results['request_status_deleted']++;
            }
        }
        aidunite_match_request_purge_legacy_duplicate_meta($match_request_id);
        $code = (string) get_post_meta($match_request_id, 'cancel_reason_code', true);
        if ($code !== '') {
            if (delete_post_meta($match_request_id, 'canceled_reason')) {
                $results['cancel_legacy_deleted']++;
            }
            delete_post_meta($match_request_id, 'aidunite_cancel_reason');
        }
    }

    return $results;
}

/**
 * 成立時の申請側選択値を永続化（match-state-sync 用）
 *
 * @param int                  $match_request_id
 * @param array<string, mixed> $fields
 */
function aidunite_match_request_persist_established_selection($match_request_id, array $fields) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1 || $fields === []) {
        return;
    }

    $application = [];
    foreach (['selected_start_time', 'selected_end_time', 'selected_place', 'selected_gender'] as $key) {
        if (array_key_exists($key, $fields)) {
            $application[$key] = $fields[$key];
        }
    }
    if ($application !== []) {
        aidunite_match_request_write_application_meta($match_request_id, $application);
    }

    if (array_key_exists('established_gender_slot', $fields) && (string) $fields['established_gender_slot'] !== '') {
        update_post_meta($match_request_id, 'established_gender_slot', (string) $fields['established_gender_slot']);
    }
}

/**
 * 再確認待ちメタを永続化（match-state-sync 用）
 *
 * @param int                  $match_request_id
 * @param int                  $winner_request_id
 * @param string               $reason
 * @param array<string, mixed> $before
 */
function aidunite_match_request_persist_reconfirm_required($match_request_id, $winner_request_id, $reason, array $before = []) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }

    aidunite_match_request_write_normalized_meta($match_request_id, [
        'requires_reconfirm' => 1,
    ]);
    update_post_meta($match_request_id, 'reconfirm_reason', sanitize_text_field((string) $reason));
    update_post_meta($match_request_id, 'reconfirm_detected_at', current_time('mysql'));
    update_post_meta($match_request_id, 'superseded_by_request_id', (int) $winner_request_id);

    $map = [
        'reconfirm_before_schedule_place' => 'before_place',
        'reconfirm_before_schedule_gender' => 'before_gender',
        'reconfirm_before_male_slots' => 'male',
        'reconfirm_before_female_slots' => 'female',
        'reconfirm_before_place_lock' => 'place_lock',
    ];
    foreach ($map as $meta_key => $before_key) {
        if (!array_key_exists($before_key, $before)) {
            continue;
        }
        update_post_meta($match_request_id, $meta_key, $before[$before_key]);
    }
}

/**
 * 試合結果コードを永続化
 */
function aidunite_match_request_persist_outcome_code($match_request_id, $outcome) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }
    $outcome = sanitize_text_field((string) $outcome);
    if ($outcome === '') {
        delete_post_meta($match_request_id, 'mr_outcome_code');
        delete_post_meta($match_request_id, 'mr_outcome_updated_at');
        return;
    }
    update_post_meta($match_request_id, 'mr_outcome_code', $outcome);
    update_post_meta($match_request_id, 'mr_outcome_updated_at', current_time('mysql'));
}

/**
 * 他申請成立による自動キャンセルメタ
 */
function aidunite_match_request_persist_superseded_cancel($match_request_id, $winner_request_id, $reason_code, $legacy_message = '') {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }

    update_post_meta($match_request_id, 'canceled_at', current_time('mysql'));
    delete_post_meta($match_request_id, 'canceled_by_team_id');
    aidunite_match_request_update_cancel_reason_meta($match_request_id, (string) $reason_code, (string) $legacy_message);
    update_post_meta($match_request_id, 'superseded_by_request_id', (int) $winner_request_id);
    if ($reason_code === 'superseded_established') {
        update_post_meta($match_request_id, 'aidunite_superseded_by_request_id', (int) $winner_request_id);
    }
    if ($reason_code === 'mirror_established') {
        update_post_meta($match_request_id, 'canceled_by_system', '1');
    }
}

/**
 * 承認滞留リマインド送信済みフラグ
 */
function aidunite_match_request_persist_reminder_sent($match_request_id) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1) {
        return;
    }
    update_post_meta($match_request_id, 'reminder_sent_at', current_time('mysql'));
}

/**
 * 管理画面用: match_request を完全削除（postmeta 含む）
 *
 * @param int $match_request_id
 * @return bool
 */
function aidunite_match_request_persist_admin_delete($match_request_id) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1 || get_post_type($match_request_id) !== 'match_request') {
        return false;
    }

    return (bool) wp_delete_post($match_request_id, true);
}

/**
 * 管理画面用: 全 match_request を削除
 *
 * @return int 削除件数
 */
function aidunite_match_request_persist_admin_delete_all() {
    $ids = get_posts([
        'post_type' => 'match_request',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    $deleted = 0;
    foreach ($ids ?: [] as $match_request_id) {
        if (aidunite_match_request_persist_admin_delete((int) $match_request_id)) {
            $deleted++;
        }
    }

    return $deleted;
}
