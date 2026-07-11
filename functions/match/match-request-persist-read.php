<?php
/**
 * マッチ申請ドメイン read payload（canonical）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 表示・API 用: メタを1本化（旧キーはフォールバックのみ）
 *
 * @param int $match_request_id
 * @return array<string, mixed>
 */
function aidunite_match_request_read_canonical_meta($match_request_id) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1 || get_post_type($match_request_id) !== 'match_request') {
        return [];
    }

    $post = get_post($match_request_id);
    $post_status = $post ? (string) $post->post_status : '';

    $status_raw = (string) (get_post_meta($match_request_id, 'status', true)
        ?: get_post_meta($match_request_id, 'request_status', true));
    $status = function_exists('aidunite_normalize_match_request_status')
        ? (string) aidunite_normalize_match_request_status($status_raw, $post_status)
        : strtolower($status_raw);

    $place = (string) (get_post_meta($match_request_id, 'selected_place', true)
        ?: get_post_meta($match_request_id, 'preferred_place', true));
    $gender_raw = (string) (get_post_meta($match_request_id, 'selected_gender', true)
        ?: get_post_meta($match_request_id, 'preferred_gender', true));
    $gender = function_exists('aidunite_normalize_gender_canonical')
        ? aidunite_normalize_gender_canonical($gender_raw)
        : $gender_raw;

    $cancel_code = (string) (get_post_meta($match_request_id, 'cancel_reason_code', true)
        ?: get_post_meta($match_request_id, 'canceled_reason', true)
        ?: get_post_meta($match_request_id, 'aidunite_cancel_reason', true));

    return [
        'match_request_id' => $match_request_id,
        'post_status' => $post_status,
        'status' => $status,
        'from_team_id' => (int) get_post_meta($match_request_id, 'from_team_id', true),
        'to_team_id' => (int) get_post_meta($match_request_id, 'to_team_id', true),
        'other_team_id' => (int) get_post_meta($match_request_id, 'other_team_id', true),
        'request_team_id' => (int) get_post_meta($match_request_id, 'request_team_id', true),
        'to_schedule_id' => (int) get_post_meta($match_request_id, 'to_schedule_id', true),
        'my_schedule_id' => (int) get_post_meta($match_request_id, 'my_schedule_id', true),
        'from_schedule_id' => (int) get_post_meta($match_request_id, 'from_schedule_id', true),
        'approver_type' => (string) get_post_meta($match_request_id, 'approver_type', true),
        'reminder_sent_at' => (string) get_post_meta($match_request_id, 'reminder_sent_at', true),
        'selected_start_time' => (string) (get_post_meta($match_request_id, 'selected_start_time', true)
            ?: get_post_meta($match_request_id, 'preferred_start', true)),
        'selected_end_time' => (string) (get_post_meta($match_request_id, 'selected_end_time', true)
            ?: get_post_meta($match_request_id, 'preferred_end', true)),
        'selected_place' => $place,
        'selected_gender' => $gender,
        'cancel_reason_code' => $cancel_code,
        'mr_outcome_code' => (string) get_post_meta($match_request_id, 'mr_outcome_code', true),
        'requires_reconfirm' => (int) get_post_meta($match_request_id, 'requires_reconfirm', true),
        'match_game_id' => (int) (get_post_meta($match_request_id, 'match_game_id', true)
            ?: (function_exists('aidunite_resolve_match_game_id_for_match_request')
                ? aidunite_resolve_match_game_id_for_match_request($match_request_id)
                : 0)),
        'established_at' => (string) get_post_meta($match_request_id, 'established_at', true),
        'proposal_pending_accept' => (int) get_post_meta($match_request_id, 'proposal_pending_accept', true),
        'reconfirm_reason' => (string) get_post_meta($match_request_id, 'reconfirm_reason', true),
        'chat_room_id' => (int) get_post_meta($match_request_id, 'chat_room_id', true),
        'is_auto' => in_array(get_post_meta($match_request_id, 'is_auto', true), [1, '1', true], true),
        'approver_school_name' => (string) get_post_meta($match_request_id, 'approver_school_name', true),
        'approver_name' => (string) get_post_meta($match_request_id, 'approver_name', true),
        'type' => (string) get_post_meta($match_request_id, 'type', true),
        'request_message' => (string) get_post_meta($match_request_id, 'request_message', true),
        'target_schedule_id' => (int) get_post_meta($match_request_id, 'target_schedule_id', true),
        'accepted_at' => (string) get_post_meta($match_request_id, 'accepted_at', true),
    ];
}

/**
 * @param int $match_request_id
 * @return array<string, mixed>
 */
function aidunite_match_request_get_canonical_meta($match_request_id) {
    return aidunite_match_request_read_canonical_meta($match_request_id);
}

/**
 * 再確認スナップショット（成立前の募集条件）
 *
 * @param int $match_request_id
 * @return array<string, mixed>
 */
function aidunite_match_request_read_reconfirm_snapshot($match_request_id) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id < 1 || get_post_type($match_request_id) !== 'match_request') {
        return [];
    }

    return [
        'reconfirm_before_schedule_place' => (string) get_post_meta($match_request_id, 'reconfirm_before_schedule_place', true),
        'reconfirm_before_schedule_gender' => (string) get_post_meta($match_request_id, 'reconfirm_before_schedule_gender', true),
        'reconfirm_before_male_slots' => (int) get_post_meta($match_request_id, 'reconfirm_before_male_slots', true),
        'reconfirm_before_female_slots' => (int) get_post_meta($match_request_id, 'reconfirm_before_female_slots', true),
        'reconfirm_before_place_lock' => (string) get_post_meta($match_request_id, 'reconfirm_before_place_lock', true),
        'reconfirm_reason' => (string) get_post_meta($match_request_id, 'reconfirm_reason', true),
        'reconfirm_detected_at' => (string) get_post_meta($match_request_id, 'reconfirm_detected_at', true),
        'superseded_by_request_id' => (int) get_post_meta($match_request_id, 'superseded_by_request_id', true),
        'established_gender_slot' => (string) get_post_meta($match_request_id, 'established_gender_slot', true),
        'canceled_by_team_id' => (int) get_post_meta($match_request_id, 'canceled_by_team_id', true),
        'mr_outcome_updated_at' => (string) get_post_meta($match_request_id, 'mr_outcome_updated_at', true),
    ];
}

/**
 * REST / 管理一覧用の行データ
 *
 * @param int $match_request_id
 * @return array<string, mixed>
 */
function aidunite_match_request_format_rest_row($match_request_id) {
    $match_request_id = (int) $match_request_id;
    $canonical = aidunite_match_request_read_canonical_meta($match_request_id);
    if ($canonical === []) {
        return [];
    }

    $from_team_id = (int) ($canonical['from_team_id'] ?? 0);

    return [
        'ID' => $match_request_id,
        'from_team_id' => $from_team_id,
        'from_team_name' => $from_team_id > 0 ? (string) get_the_title($from_team_id) : '不明',
        'to_schedule_id' => (int) ($canonical['to_schedule_id'] ?? 0),
        'my_schedule_id' => (int) ($canonical['my_schedule_id'] ?? 0),
        'status' => (string) ($canonical['status'] ?? ''),
        'selected_start_time' => (string) ($canonical['selected_start_time'] ?? ''),
        'selected_end_time' => (string) ($canonical['selected_end_time'] ?? ''),
        'selected_place' => (string) ($canonical['selected_place'] ?? ''),
        'selected_gender' => (string) ($canonical['selected_gender'] ?? ''),
    ];
}

/**
 * 申請先チーム ID（to_schedule → メタのフォールバック順）
 *
 * @param int                  $match_request_id
 * @param array<string, mixed>|null $canonical
 * @return int
 */
function aidunite_match_request_read_recipient_team_id($match_request_id, array $canonical = null) {
    $match_request_id = (int) $match_request_id;
    if ($canonical === null) {
        $canonical = aidunite_match_request_read_canonical_meta($match_request_id);
    }
    if ($canonical === []) {
        return 0;
    }

    $to_schedule_id = (int) ($canonical['to_schedule_id'] ?? 0);
    if ($to_schedule_id > 0 && function_exists('aidunite_resolve_schedule_owner_team_id')) {
        $resolved = (int) aidunite_resolve_schedule_owner_team_id($to_schedule_id);
        if ($resolved > 0) {
            return $resolved;
        }
    }

    if ($to_schedule_id > 0) {
        $author_id = (int) get_post_field('post_author', $to_schedule_id);
        if ($author_id > 0) {
            if (function_exists('aidunite_get_current_team_id')) {
                $team_id = (int) aidunite_get_current_team_id($author_id);
                if ($team_id > 0) {
                    return $team_id;
                }
            }
            if (function_exists('aidunite_user_read_primary_team_id')) {
                $team_id = (int) aidunite_user_read_primary_team_id($author_id);
                if ($team_id > 0) {
                    return $team_id;
                }
            }
        }
    }

    $to_team_id = (int) ($canonical['to_team_id'] ?? 0);
    if ($to_team_id > 0) {
        return $to_team_id;
    }

    return (int) ($canonical['other_team_id'] ?? 0);
}

/**
 * 操作者の from/to チームアクセス（REST ステータス更新用）
 *
 * @param int $match_request_id
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_match_request_read_actor_access($match_request_id, $user_id) {
    $match_request_id = (int) $match_request_id;
    $user_id = (int) $user_id;
    $canonical = aidunite_match_request_read_canonical_meta($match_request_id);

    $from_team_id = (int) ($canonical['from_team_id'] ?? 0);
    $to_team_id = aidunite_match_request_read_recipient_team_id($match_request_id, $canonical);

    $has_from = $from_team_id > 0 && function_exists('aidunite_user_has_managed_team_access')
        && aidunite_user_has_managed_team_access($user_id, $from_team_id);
    $has_to = $to_team_id > 0 && function_exists('aidunite_user_has_managed_team_access')
        && aidunite_user_has_managed_team_access($user_id, $to_team_id);

    return [
        'canonical' => $canonical,
        'from_team_id' => $from_team_id,
        'to_team_id' => $to_team_id,
        'has_from' => $has_from,
        'has_to' => $has_to,
        'post_status' => (string) ($canonical['post_status'] ?? ''),
    ];
}
