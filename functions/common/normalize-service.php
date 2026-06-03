<?php
/**
 * Ainy Normalize Service — payload 正規化の単一入口（設計: docs/reports/normalize-service-design.md）
 *
 * UI / REST / legacy / admin → payload → normalize → service → DB
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * payload 正規化を有効化するか（無効時は入力をそのまま返す）
 */
function aidunite_normalize_payload_enabled() {
    if (defined('AIDUNITE_NORMALIZE_PAYLOAD_ENABLED')) {
        return (bool) AIDUNITE_NORMALIZE_PAYLOAD_ENABLED;
    }
    return true;
}

/**
 * @param mixed $payload
 * @return array
 */
function aidunite_normalize_payload_ensure_array($payload) {
    return is_array($payload) ? $payload : [];
}

/**
 * 会場: both / 日本語 → home|away|either
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_normalize_place_payload_value($raw) {
    if (function_exists('aidunite_normalize_place_for_lock')) {
        return aidunite_normalize_place_for_lock($raw);
    }
    $p = trim((string) $raw);
    if ($p === 'both' || $p === 'どちらでも可' || $p === 'どちらでも') {
        return 'either';
    }
    if (in_array($p, ['home', 'away', 'either'], true)) {
        return $p;
    }
    if (function_exists('normalize_place_value')) {
        $n = normalize_place_value($p);
        return $n === 'both' ? 'either' : (string) $n;
    }
    return $p;
}

/**
 * 出欠 status: attending/not_attending/pending/空 → present/absent/no_response
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_normalize_attendance_status_value($raw) {
    $s = strtolower(trim((string) $raw));
    if ($s === '') {
        return 'no_response';
    }
    $map = [
        'attending' => 'present',
        'present' => 'present',
        'not_attending' => 'absent',
        'absent' => 'absent',
        'late' => 'late',
        'leave_early' => 'leave_early',
        'no_response' => 'no_response',
        'pending' => 'no_response',
    ];
    return $map[$s] ?? $s;
}

/**
 * boolean: TRUE/FALSE/1/0 → true/false
 *
 * @param mixed $raw
 * @return bool
 */
function aidunite_normalize_boolean_payload_value($raw) {
    if (is_bool($raw)) {
        return $raw;
    }
    $s = strtolower(trim((string) $raw));
    if ($s === 'true' || $s === '1' || $s === 'yes') {
        return true;
    }
    if ($s === 'false' || $s === '0' || $s === 'no' || $s === '') {
        return false;
    }
    return (bool) $raw;
}

/**
 * 通知 type の legacy 吸収
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_normalize_notification_type_value($raw) {
    $t = strtolower(trim((string) $raw));
    if ($t === '') {
        return '';
    }
    // 送信・保存時の canonical 名へ寄せる（DB 既存行の読取互換用エイリアス）
    $map = [
        'match_accepted' => 'match_established',
        'team_approved' => 'team_approval_completed',
        'team_approval' => 'team_approval_completed',
        'admin_team_application' => 'team_approval_request',
    ];
    return $map[$t] ?? $t;
}

/**
 * MR status 保存用（accepted は互換。$for_save=true で established へ寄せる）
 *
 * @param mixed  $raw
 * @param string $post_status
 * @param bool   $for_save 新規保存時に accepted→established
 * @return string
 */
function aidunite_normalize_match_request_status_value($raw, $post_status = '', $for_save = false) {
    if (!function_exists('aidunite_normalize_match_request_status')) {
        return strtolower(trim((string) $raw));
    }
    $norm = aidunite_normalize_match_request_status($raw, $post_status);
    if ($for_save && $norm === 'accepted') {
        return 'established';
    }
    return $norm;
}

/**
 * cancel_reason 旧3キーからコードを抽出
 *
 * @param array $payload
 * @return string
 */
function aidunite_normalize_cancel_reason_code_from_legacy_payload(array $payload) {
    foreach (['cancel_reason_code', 'cancel_reason', 'canceled_reason', 'aidunite_cancel_reason'] as $key) {
        if (!isset($payload[$key]) || $payload[$key] === '') {
            continue;
        }
        $v = strtolower(trim((string) $payload[$key]));
        if (in_array($v, ['mirror_established', 'superseded_established', 'manual_cancel'], true)) {
            return $v;
        }
        if (strpos($v, 'mirror') !== false || strpos((string) $payload[$key], '別方向') !== false) {
            return 'mirror_established';
        }
        if (strpos($v, 'superseded') !== false || strpos((string) $payload[$key], '別の申請') !== false) {
            return 'superseded_established';
        }
    }
    return '';
}

/**
 * team org_type: 日本語 → school|club|...
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_normalize_team_org_type_value($raw) {
    $v = trim((string) $raw);
    if ($v === '') {
        return '';
    }
    $map = [
        '学校' => 'school',
        'クラブ' => 'club',
        '企業' => 'corporate',
        '地域' => 'community',
        'その他' => 'other',
        'school' => 'school',
        'club' => 'club',
        'corporate' => 'corporate',
        'community' => 'community',
        'other' => 'other',
    ];
    if (isset($map[$v])) {
        return $map[$v];
    }
    $lc = strtolower($v);
    return $map[$lc] ?? $lc;
}

/**
 * payment_mode: school → business
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_normalize_payment_mode_value($raw) {
    $v = strtolower(trim((string) $raw));
    if ($v === 'school') {
        return 'business';
    }
    if (in_array($v, ['board', 'business', 'personal'], true)) {
        return $v;
    }
    return $v;
}

/**
 * registration_source
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_normalize_registration_source_value($raw) {
    $v = trim((string) $raw);
    if ($v === '' || stripos($v, 'forminator') !== false || $v === '271' || $v === 'Forminator 271') {
        if (stripos($v, 'forminator') !== false || $v === '271' || $v === 'Forminator 271') {
            return 'legacy_forminator';
        }
        return '';
    }
    if ($v === 'member_register') {
        return 'member_register';
    }
    return $v;
}

/**
 * schedule payload 正規化
 *
 * @param array $payload
 * @return array
 */
function aidunite_normalize_schedule_payload(array $payload) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;

    $intent_raw = $payload['intent'] ?? '';
    $certainty = $payload['certainty'] ?? '';
    if (function_exists('aidunite_normalize_schedule_intent')) {
        $out['intent'] = aidunite_normalize_schedule_intent($intent_raw, $certainty);
    }

    $place_raw = $payload['place_type'] ?? $payload['venue_condition'] ?? $payload['schedule_place'] ?? '';
    $place = aidunite_normalize_place_payload_value($place_raw);
    if ($place !== '') {
        $out['place_type'] = $place;
        $out['venue_condition'] = $place;
        $out['schedule_place'] = $place;
    }

    $gender_raw = $payload['gender'] ?? $payload['gender_condition'] ?? $payload['matching_gender_condition'] ?? '';
    if (function_exists('aidunite_normalize_gender_canonical')) {
        $g = aidunite_normalize_gender_canonical($gender_raw);
        if ($g !== '') {
            $out['gender'] = $g;
            $out['gender_condition'] = $g;
            $out['matching_gender_condition'] = $g;
        }
    }

    if (array_key_exists('is_match_requested', $payload)) {
        $out['is_match_requested'] = (int) $payload['is_match_requested'] ? 1 : 0;
    } elseif (($out['intent'] ?? '') === 'recruit') {
        $out['is_match_requested'] = 1;
    }

    if (isset($payload['attendance_status']) || isset($payload['status'])) {
        $att = $payload['attendance_status'] ?? $payload['status'] ?? '';
        $out['attendance_status'] = aidunite_normalize_attendance_status_value($att);
    }

    if (isset($payload['male_slots'])) {
        $out['male_slots'] = max(0, (int) $payload['male_slots']);
    } elseif (isset($payload['male_teams'])) {
        $out['male_slots'] = max(0, (int) $payload['male_teams']);
    }
    if (isset($payload['female_slots'])) {
        $out['female_slots'] = max(0, (int) $payload['female_slots']);
    } elseif (isset($payload['female_teams'])) {
        $out['female_slots'] = max(0, (int) $payload['female_teams']);
    }

    $g_out = $out['gender_condition'] ?? $out['gender'] ?? '';
    if ($g_out !== '') {
        $out['schedule_gender'] = $g_out;
    }

    return $out;
}

/**
 * match_request payload 正規化（status / reason / outcome 分離）
 *
 * @param array $payload
 * @param bool  $for_save 保存直前（accepted→established）
 * @return array
 */
function aidunite_normalize_match_request_payload(array $payload, $for_save = true) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;

    if (isset($payload['status']) || isset($payload['request_status'])) {
        $st = $payload['status'] ?? $payload['request_status'] ?? '';
        $ps = $payload['post_status'] ?? '';
        $norm = aidunite_normalize_match_request_status_value($st, $ps, $for_save);
        if ($norm !== '') {
            $out['status'] = $norm;
        }
    }

    $place_raw = $payload['selected_place'] ?? $payload['preferred_place'] ?? '';
    $place = aidunite_normalize_place_payload_value($place_raw);
    if ($place !== '') {
        $out['selected_place'] = $place;
    } elseif ($place_raw === '' && $for_save) {
        // 新規保存では空を許可しない（legacy は読取のみ）
        unset($out['selected_place']);
    }

    $gender_raw = $payload['selected_gender'] ?? $payload['preferred_gender'] ?? '';
    if (function_exists('aidunite_normalize_gender_canonical')) {
        $g = aidunite_normalize_gender_canonical($gender_raw);
        if ($g !== '') {
            $out['selected_gender'] = $g;
        }
    }

    $reason = aidunite_normalize_cancel_reason_code_from_legacy_payload($payload);
    if ($reason !== '') {
        $out['cancel_reason_code'] = $reason;
    }

    if (isset($payload['outcome_code'])) {
        $out['outcome_code'] = strtolower(trim((string) $payload['outcome_code']));
        $out['mr_outcome_code'] = $out['outcome_code'];
    }

    if (isset($payload['requires_reconfirm'])) {
        $out['requires_reconfirm'] = (int) $payload['requires_reconfirm'] ? 1 : 0;
    }

    return $out;
}

/**
 * match_board board_status 正規化
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_normalize_match_board_status_value($raw) {
    $s = strtolower(trim((string) $raw));
    if ($s === 'accepted') {
        return 'established';
    }
    if (in_array($s, ['open', 'pending', 'established'], true)) {
        return $s;
    }
    return $s;
}

/**
 * @param array $payload
 * @return array
 */
function aidunite_normalize_match_board_payload(array $payload) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;
    if (isset($payload['board_status'])) {
        $out['board_status'] = aidunite_normalize_match_board_status_value($payload['board_status']);
    }
    return $out;
}

/**
 * @param array $payload
 * @return array
 */
function aidunite_normalize_notification_payload(array $payload) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;
    if (isset($payload['type'])) {
        $out['type'] = aidunite_normalize_notification_type_value($payload['type']);
    }
    if (isset($payload['is_read'])) {
        $out['is_read'] = aidunite_normalize_boolean_payload_value($payload['is_read']);
    }
    return $out;
}

/**
 * @param array $payload
 * @return array
 */
function aidunite_normalize_team_payload(array $payload) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;

    if (isset($payload['gender']) || isset($payload['team_gender_option'])) {
        $raw = $payload['gender'] ?? $payload['team_gender_option'] ?? '';
        if (function_exists('aidunite_normalize_team_gender_option')) {
            $g = aidunite_normalize_team_gender_option($raw);
            if ($g !== '') {
                $out['gender'] = $g;
                $out['team_gender_option'] = $g;
            }
        }
    }

    if (isset($payload['org_type']) || isset($payload['team_type'])) {
        $org = aidunite_normalize_team_org_type_value($payload['org_type'] ?? $payload['team_type'] ?? '');
        if ($org !== '') {
            $out['org_type'] = $org;
        }
    }

    if (isset($payload['team_status']) && function_exists('aidunite_team_management_normalize_team_status')) {
        $out['team_status'] = aidunite_team_management_normalize_team_status($payload['team_status']);
    }

    return $out;
}

/**
 * @param array $payload
 * @return array
 */
function aidunite_normalize_user_payload(array $payload) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;

    if (isset($payload['registration_status'])) {
        $rs = strtolower(trim((string) $payload['registration_status']));
        if ($rs === 'active') {
            $out['registration_status'] = 'accepted';
        }
    }

    if (isset($payload['registration_source'])) {
        $out['registration_source'] = aidunite_normalize_registration_source_value($payload['registration_source']);
    }

    if (isset($payload['role'])) {
        $role = strtolower(trim((string) $payload['role']));
        $map = [
            'subscriber' => 'general',
            'author' => 'team_leader',
            'guardian' => 'parent',
            'athlete' => 'player',
        ];
        if (isset($map[$role])) {
            $out['role'] = $map[$role];
        }
    }

    return $out;
}

/**
 * @param array $payload
 * @return array
 */
function aidunite_normalize_payment_payload(array $payload) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;
    if (isset($payload['payment_mode'])) {
        $out['payment_mode'] = aidunite_normalize_payment_mode_value($payload['payment_mode']);
    }
    return $out;
}

/**
 * attendance_data 1ユーザー分またはフラット status
 *
 * @param array $payload
 * @return array
 */
function aidunite_normalize_attendance_payload(array $payload) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;

    if (isset($payload['attendance_status'])) {
        $out['attendance_status'] = aidunite_normalize_attendance_status_value($payload['attendance_status']);
    }
    if (isset($payload['status']) && !isset($payload['attendance_status'])) {
        $out['status'] = aidunite_normalize_attendance_status_value($payload['status']);
    }

    return $out;
}

/**
 * @param array $payload
 * @return array
 */
function aidunite_normalize_chat_payload(array $payload) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;
    $room_types = ['team', 'match', 'message_thread', 'system', 'group', 'direct'];
    $room_statuses = ['active', 'completed', 'archived'];
    $message_types = ['text', 'image', 'file', 'evaluation_request', 'system'];

    if (isset($payload['room_type']) && in_array($payload['room_type'], $room_types, true)) {
        $out['room_type'] = $payload['room_type'];
    }
    if (isset($payload['room_status']) && in_array($payload['room_status'], $room_statuses, true)) {
        $out['room_status'] = $payload['room_status'];
    }
    if (isset($payload['message_type']) && in_array($payload['message_type'], $message_types, true)) {
        $out['message_type'] = $payload['message_type'];
    }
    return $out;
}

/**
 * @param array $payload
 * @return array
 */
function aidunite_normalize_analytics_payload(array $payload) {
    if (!aidunite_normalize_payload_enabled()) {
        return $payload;
    }
    $out = $payload;
    $events = ['view', 'click', 'submit', 'complete', 'leave'];
    if (isset($payload['event_type']) && in_array($payload['event_type'], $events, true)) {
        $out['event_type'] = $payload['event_type'];
    }
    if (isset($payload['funnel_step_id'])) {
        $out['funnel_step_id'] = strtolower(trim((string) $payload['funnel_step_id']));
    }
    return $out;
}

/**
 * ドメイン別ファサード
 *
 * @param string $domain schedule|match_request|...
 * @param array  $payload
 * @param array  $options for_save 等
 * @return array
 */
function aidunite_normalize_payload_by_domain($domain, array $payload, array $options = []) {
    $domain = strtolower(trim((string) $domain));
    $for_save = !empty($options['for_save']);

    switch ($domain) {
        case 'schedule':
            return aidunite_normalize_schedule_payload($payload);
        case 'match_request':
            return aidunite_normalize_match_request_payload($payload, $for_save);
        case 'match_board':
            return aidunite_normalize_match_board_payload($payload);
        case 'notification':
            return aidunite_normalize_notification_payload($payload);
        case 'team':
            return aidunite_normalize_team_payload($payload);
        case 'user':
            return aidunite_normalize_user_payload($payload);
        case 'payment':
            return aidunite_normalize_payment_payload($payload);
        case 'attendance':
            return aidunite_normalize_attendance_payload($payload);
        case 'chat':
            return aidunite_normalize_chat_payload($payload);
        case 'analytics':
            return aidunite_normalize_analytics_payload($payload);
        default:
            return $payload;
    }
}

/**
 * 正規化済み match_request payload を postmeta に反映（保存ヘルパー）
 *
 * @param int   $match_request_id
 * @param array $normalized aidunite_normalize_match_request_payload の戻り値
 */
function aidunite_apply_normalized_match_request_meta($match_request_id, array $normalized) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id <= 0) {
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
}

/**
 * match_request status 保存ヘルパー（保存時は accepted を established へ寄せる）
 *
 * @param int    $match_request_id
 * @param string $status_raw
 * @param string $post_status
 * @return string 保存した canonical status
 */
function aidunite_update_match_request_status_meta($match_request_id, $status_raw, $post_status = '') {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id <= 0) {
        return '';
    }
    $status = aidunite_normalize_match_request_status_value($status_raw, $post_status, true);
    if ($status === '') {
        return '';
    }
    update_post_meta($match_request_id, 'status', $status);
    return $status;
}

/**
 * cancel reason を canonical key に保存（互換キーにも反映）
 *
 * @param int    $match_request_id
 * @param string $reason_code
 * @param string $legacy_message
 * @return void
 */
function aidunite_update_match_request_cancel_reason_meta($match_request_id, $reason_code, $legacy_message = '') {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id <= 0) {
        return;
    }
    $code = strtolower(trim((string) $reason_code));
    if (!in_array($code, ['mirror_established', 'superseded_established', 'manual_cancel'], true)) {
        return;
    }
    update_post_meta($match_request_id, 'cancel_reason_code', $code);
    // backward compatibility
    update_post_meta($match_request_id, 'canceled_reason', $code);
    update_post_meta($match_request_id, 'aidunite_cancel_reason', $code);
    if ($legacy_message !== '') {
        update_post_meta($match_request_id, 'cancel_reason', $legacy_message);
    }
}

/**
 * 正規化済み schedule 募集系メタを反映
 *
 * @param int   $schedule_id
 * @param array $normalized
 */
function aidunite_apply_normalized_schedule_meta($schedule_id, array $normalized) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return;
    }
    if (isset($normalized['intent'])) {
        update_post_meta($schedule_id, 'intent', $normalized['intent']);
    }
    $place = $normalized['schedule_place'] ?? $normalized['place_type'] ?? $normalized['venue_condition'] ?? null;
    if ($place !== null && $place !== '') {
        update_post_meta($schedule_id, 'schedule_place', $place);
        update_post_meta($schedule_id, 'schedule_place_option', $place);
    }
    $gender = $normalized['matching_gender_condition'] ?? $normalized['gender'] ?? $normalized['gender_condition'] ?? null;
    if ($gender !== null && $gender !== '') {
        update_post_meta($schedule_id, 'matching_gender_condition', $gender);
        update_post_meta($schedule_id, 'schedule_gender', $gender);
    }
    if (isset($normalized['is_match_requested'])) {
        $is_mr = (int) $normalized['is_match_requested'];
        update_post_meta($schedule_id, 'is_match_requested', (string) $is_mr);
        update_post_meta($schedule_id, 'matching', (string) $is_mr);
    } elseif (($normalized['intent'] ?? '') === 'recruit') {
        update_post_meta($schedule_id, 'is_match_requested', '1');
        update_post_meta($schedule_id, 'matching', '1');
    }

    if (isset($normalized['male_slots'])) {
        $ms = max(0, (int) $normalized['male_slots']);
        update_post_meta($schedule_id, 'male_slots', $ms);
        update_post_meta($schedule_id, 'male_capacity', $ms);
    }
    if (isset($normalized['female_slots'])) {
        $fs = max(0, (int) $normalized['female_slots']);
        update_post_meta($schedule_id, 'female_slots', $fs);
        update_post_meta($schedule_id, 'female_capacity', $fs);
    }
}

/**
 * team_type を canonical 化して保存
 *
 * @param int    $team_id
 * @param string $team_type_raw
 * @return string 保存した値（空なら未保存）
 */
function aidunite_update_team_type_meta($team_id, $team_type_raw) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    $normalized = aidunite_normalize_team_org_type_value($team_type_raw);
    if ($normalized === '') {
        return '';
    }
    update_post_meta($team_id, 'team_type', $normalized);
    return $normalized;
}

/**
 * payment_mode を canonical 化して保存
 *
 * @param int    $team_id
 * @param string $payment_mode_raw
 * @return string 保存した値（空なら未保存）
 */
function aidunite_update_team_payment_mode_meta($team_id, $payment_mode_raw) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    $normalized = aidunite_normalize_payment_mode_value($payment_mode_raw);
    if ($normalized === '') {
        return '';
    }
    update_post_meta($team_id, 'payment_mode', $normalized);
    return $normalized;
}

/**
 * registration_status を canonical 化して保存（active は accepted に吸収）
 *
 * @param int    $user_id
 * @param string $status_raw
 * @return string 保存した値（空なら未保存）
 */
function aidunite_update_user_registration_status_meta($user_id, $status_raw) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }
    $payload = aidunite_normalize_user_payload(['registration_status' => $status_raw]);
    $status = (string) ($payload['registration_status'] ?? '');
    if ($status === '') {
        return '';
    }
    update_user_meta($user_id, 'registration_status', $status);
    return $status;
}
