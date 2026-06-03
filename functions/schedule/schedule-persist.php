<?php
/**
 * スケジュール登録・更新の本体（normalize 経由 → postmeta 一本化）
 *
 * 正ルート: page-schedule-edit.php POST / REST register-schedule-v2 / update-schedule-v2
 * 互換: admin_post_aidunite_save_schedule / POST register-schedules（いずれも persist 経由）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 旧メタキーへの二重書き込み（Phase 4 以降は既定で無効）
 *
 * 緊急ロールバック時のみ wp-config 等で true を定義する。
 */
function aidunite_schedule_legacy_meta_writes_enabled() {
    return defined('AIDUNITE_SCHEDULE_LEGACY_META_WRITES') && AIDUNITE_SCHEDULE_LEGACY_META_WRITES;
}

/**
 * 会場条件メタ（正本: schedule_place）
 */
function aidunite_schedule_write_place_meta($post_id, $place) {
    $post_id = (int) $post_id;
    $place = sanitize_text_field((string) $place);
    if ($post_id < 1 || $place === '') {
        return;
    }
    update_post_meta($post_id, 'schedule_place', $place);
    if (aidunite_schedule_legacy_meta_writes_enabled()) {
        update_post_meta($post_id, 'schedule_place_option', $place);
    } else {
        delete_post_meta($post_id, 'schedule_place_option');
    }
}

/**
 * 性別条件メタ（正本: schedule_gender）
 */
function aidunite_schedule_write_gender_meta($post_id, $gender) {
    $post_id = (int) $post_id;
    $gender = sanitize_text_field((string) $gender);
    if ($post_id < 1 || $gender === '') {
        return;
    }
    update_post_meta($post_id, 'schedule_gender', $gender);
    if (aidunite_schedule_legacy_meta_writes_enabled()) {
        update_post_meta($post_id, 'matching_gender_condition', $gender);
    } else {
        delete_post_meta($post_id, 'matching_gender_condition');
    }
}

/**
 * 募集枠メタ（正本: male_slots / female_slots）
 *
 * @param int $male_slots
 * @param int $female_slots
 */
function aidunite_schedule_write_recruit_slot_meta($post_id, $male_slots, $female_slots) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return;
    }
    $male_slots = max(0, (int) $male_slots);
    $female_slots = max(0, (int) $female_slots);
    $capacity = $male_slots + $female_slots;
    if ($capacity < 1) {
        return;
    }
    update_post_meta($post_id, 'capacity', $capacity);
    update_post_meta($post_id, 'male_slots', $male_slots);
    update_post_meta($post_id, 'female_slots', $female_slots);
    update_post_meta($post_id, 'male_capacity', $male_slots);
    update_post_meta($post_id, 'female_capacity', $female_slots);
    if (aidunite_schedule_legacy_meta_writes_enabled()) {
        update_post_meta($post_id, 'male_teams', $male_slots);
        update_post_meta($post_id, 'female_teams', $female_slots);
    } else {
        delete_post_meta($post_id, 'male_teams');
        delete_post_meta($post_id, 'female_teams');
    }
}

/**
 * フォーム・REST 共通: 生入力を正規化済みペイロードへ
 *
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_schedule_normalize_form_input(array $raw) {
    $intent = sanitize_text_field((string) ($raw['intent'] ?? ''));
    $certainty = sanitize_text_field((string) ($raw['certainty'] ?? ''));
    if ($certainty === '' && $intent === 'tentative') {
        $certainty = 'tentative';
    }

    $venue = sanitize_text_field((string) ($raw['venue_condition'] ?? $raw['schedule_place'] ?? $raw['place_type'] ?? ''));
    $gender = sanitize_text_field((string) ($raw['gender_condition'] ?? $raw['schedule_gender'] ?? $raw['gender'] ?? ''));

    $male_slots = isset($raw['male_slots']) ? (int) $raw['male_slots'] : (int) ($raw['male_teams'] ?? 0);
    $female_slots = isset($raw['female_slots']) ? (int) $raw['female_slots'] : (int) ($raw['female_teams'] ?? 0);

    $payload = [
        'intent' => $intent,
        'certainty' => $certainty,
        'place_type' => $venue,
        'venue_condition' => $venue,
        'schedule_place' => $venue,
        'gender' => $gender,
        'gender_condition' => $gender,
        'male_slots' => $male_slots,
        'female_slots' => $female_slots,
    ];

    if (function_exists('aidunite_normalize_schedule_payload')) {
        $payload = aidunite_normalize_schedule_payload($payload);
    } elseif ($gender !== '' && function_exists('aidunite_normalize_gender_canonical')) {
        $g = aidunite_normalize_gender_canonical($gender);
        if ($g !== '') {
            $payload['gender'] = $g;
            $payload['gender_condition'] = $g;
        }
    }

    $intent_out = (string) ($payload['intent'] ?? $intent);
    if ($intent_out === '' && function_exists('aidunite_normalize_schedule_intent')) {
        $intent_out = aidunite_normalize_schedule_intent($intent, $certainty);
    }

    $place_out = (string) (
        $payload['schedule_place']
        ?? $payload['place_type']
        ?? $payload['venue_condition']
        ?? $venue
    );
    $gender_out = (string) (
        $payload['gender_condition']
        ?? $payload['gender']
        ?? $payload['matching_gender_condition']
        ?? ''
    );
    if ($gender_out !== '' && function_exists('aidunite_normalize_gender_canonical')) {
        $gender_out = aidunite_normalize_gender_canonical($gender_out);
    }

    $out = [
        'intent' => $intent_out !== '' ? $intent_out : 'confirmed',
        'schedule_type' => sanitize_text_field((string) ($raw['schedule_type'] ?? $raw['type'] ?? '')),
        'date' => sanitize_text_field((string) ($raw['date'] ?? $raw['start_date'] ?? '')),
        'start_time' => sanitize_text_field((string) ($raw['start_time'] ?? '')),
        'end_time' => sanitize_text_field((string) ($raw['end_time'] ?? '')),
        'venue_condition' => $place_out,
        'venue_name' => sanitize_text_field((string) ($raw['venue_name'] ?? '')),
        'gender_condition' => $gender_out,
        'male_slots' => max(0, (int) ($payload['male_slots'] ?? $male_slots)),
        'female_slots' => max(0, (int) ($payload['female_slots'] ?? $female_slots)),
        'team_id' => (int) ($raw['team_id'] ?? 0),
        'user_id' => (int) ($raw['user_id'] ?? get_current_user_id()),
        'is_personal' => !empty($raw['is_personal']) ? '1' : '0',
        'attendance_required' => !empty($raw['attendance_required']) ? '1' : '0',
        'schedule_quick_memo' => sanitize_textarea_field((string) ($raw['schedule_quick_memo'] ?? $raw['note'] ?? '')),
    ];

    if (!empty($raw['schedule_gender'])) {
        $override = function_exists('aidunite_normalize_gender_canonical')
            ? aidunite_normalize_gender_canonical((string) $raw['schedule_gender'])
            : sanitize_text_field((string) $raw['schedule_gender']);
        if ($override !== '') {
            $out['schedule_gender_override'] = $override;
        }
    }

    return $out;
}

/**
 * 保存前バリデーション用: intent / 会場 / 性別のみ正規化
 *
 * @param array<string, mixed> $raw intent, certainty, venue_condition, gender_condition
 * @return array{intent: string, venue_condition: string, gender_condition: string}
 */
function aidunite_schedule_normalize_recruit_fields(array $raw) {
    $norm = aidunite_schedule_normalize_form_input(array_merge([
        'date' => '',
        'start_time' => '',
        'end_time' => '',
        'schedule_type' => '',
        'team_id' => 0,
        'user_id' => 0,
    ], $raw));

    return [
        'intent' => (string) ($norm['intent'] ?? ''),
        'venue_condition' => (string) ($norm['venue_condition'] ?? ''),
        'gender_condition' => (string) ($norm['gender_condition'] ?? ''),
    ];
}

/**
 * REST update-schedule-v2 / レガシー JSON パラメータを persist 用にマージ
 *
 * @param array<string, mixed> $params
 * @param int                  $post_id 既存 schedule（0 なら新規相当）
 * @return array<string, mixed>
 */
function aidunite_schedule_merge_legacy_params_for_persist(array $params, $post_id = 0) {
    $post_id = (int) $post_id;
    $base = [
        'user_id' => (int) get_current_user_id(),
    ];

    if ($post_id > 0) {
        $male_slots = (int) get_post_meta($post_id, 'male_slots', true);
        $female_slots = (int) get_post_meta($post_id, 'female_slots', true);
        $base = array_merge($base, [
            'date' => (string) get_post_meta($post_id, 'schedule_date', true),
            'start_time' => (string) get_post_meta($post_id, 'schedule_start_time', true),
            'end_time' => (string) get_post_meta($post_id, 'schedule_end_time', true),
            'schedule_type' => (string) get_post_meta($post_id, 'schedule_type', true),
            'intent' => (string) get_post_meta($post_id, 'intent', true),
            'certainty' => (string) get_post_meta($post_id, 'certainty', true),
            'venue_condition' => (string) (get_post_meta($post_id, 'schedule_place', true)
                ?: get_post_meta($post_id, 'schedule_place_option', true)),
            'venue_name' => (string) get_post_meta($post_id, 'venue_name', true),
            'gender_condition' => (string) (get_post_meta($post_id, 'schedule_gender', true)
                ?: get_post_meta($post_id, 'matching_gender_condition', true)),
            'male_slots' => $male_slots,
            'female_slots' => $female_slots,
            'male_teams' => $male_slots,
            'female_teams' => $female_slots,
            'team_id' => (int) get_post_meta($post_id, 'team_id', true),
            'is_personal' => (string) get_post_meta($post_id, 'is_personal', true),
            'attendance_required' => (string) get_post_meta($post_id, 'attendance_required', true),
            'schedule_quick_memo' => (string) get_post_meta($post_id, 'schedule_quick_memo', true),
        ]);
    }

    if (isset($params['date'])) {
        $base['date'] = sanitize_text_field((string) $params['date']);
    }
    if (isset($params['start_date'])) {
        $base['date'] = sanitize_text_field((string) $params['start_date']);
    }
    if (isset($params['start_time'])) {
        $base['start_time'] = sanitize_text_field((string) $params['start_time']);
    }
    if (isset($params['end_time'])) {
        $base['end_time'] = sanitize_text_field((string) $params['end_time']);
    }
    if (isset($params['start_hour'], $params['start_minute'])) {
        $base['start_time'] = sprintf('%02d:%02d', (int) $params['start_hour'], (int) $params['start_minute']);
    }
    if (isset($params['end_hour'], $params['end_minute'])) {
        $base['end_time'] = sprintf('%02d:%02d', (int) $params['end_hour'], (int) $params['end_minute']);
    }
    if (isset($params['type'])) {
        $base['schedule_type'] = sanitize_text_field((string) $params['type']);
    }
    if (isset($params['schedule_type'])) {
        $base['schedule_type'] = sanitize_text_field((string) $params['schedule_type']);
    }
    if (isset($params['intent'])) {
        $base['intent'] = sanitize_text_field((string) $params['intent']);
    }
    if (!isset($base['intent']) || (string) $base['intent'] === '') {
        if (!empty($params['matching']) || (isset($params['match_request']) && (string) $params['match_request'] === 'recruit')) {
            $base['intent'] = 'recruit';
        }
    }
    if (isset($params['certainty'])) {
        $base['certainty'] = sanitize_text_field((string) $params['certainty']);
    }
    foreach (['venue_condition', 'schedule_place', 'schedule_place_option', 'place'] as $place_key) {
        if (isset($params[$place_key]) && (string) $params[$place_key] !== '') {
            $base['venue_condition'] = sanitize_text_field((string) $params[$place_key]);
            break;
        }
    }
    if (isset($params['venue_name'])) {
        $base['venue_name'] = sanitize_text_field((string) $params['venue_name']);
    }
    foreach (['gender_condition', 'schedule_gender', 'matching_gender_condition', 'gender'] as $gender_key) {
        if (isset($params[$gender_key]) && (string) $params[$gender_key] !== '') {
            $base['gender_condition'] = sanitize_text_field((string) $params[$gender_key]);
            break;
        }
    }
    if (isset($params['male_slots'])) {
        $base['male_slots'] = (int) $params['male_slots'];
        $base['male_teams'] = (int) $params['male_slots'];
    }
    if (isset($params['female_slots'])) {
        $base['female_slots'] = (int) $params['female_slots'];
        $base['female_teams'] = (int) $params['female_slots'];
    }
    if (isset($params['male_teams'])) {
        $base['male_teams'] = (int) $params['male_teams'];
    }
    if (isset($params['female_teams'])) {
        $base['female_teams'] = (int) $params['female_teams'];
    }
    if (isset($params['memo'])) {
        $base['schedule_quick_memo'] = sanitize_textarea_field((string) $params['memo']);
    }
    if (isset($params['note'])) {
        $base['schedule_quick_memo'] = sanitize_textarea_field((string) $params['note']);
    }
    if (isset($params['schedule_note'])) {
        $base['schedule_quick_memo'] = sanitize_textarea_field((string) $params['schedule_note']);
    }
    if (isset($params['schedule_quick_memo'])) {
        $base['schedule_quick_memo'] = sanitize_textarea_field((string) $params['schedule_quick_memo']);
    }
    if (isset($params['team_id'])) {
        $base['team_id'] = (int) $params['team_id'];
    }
    if (isset($params['attendance_required'])) {
        $base['attendance_required'] = !empty($params['attendance_required']) ? '1' : '0';
    }

    return aidunite_schedule_normalize_form_input($base);
}

/**
 * intent=recruit 時の male_slots / female_slots（both は保存しない）
 *
 * @return array{male_slots: int, female_slots: int}
 */
function aidunite_schedule_derive_recruit_slots($gender_condition, $male_count, $female_count) {
    $gender = function_exists('aidunite_normalize_gender_canonical')
        ? aidunite_normalize_gender_canonical((string) $gender_condition)
        : strtolower(trim((string) $gender_condition));

    $male = max(0, (int) $male_count);
    $female = max(0, (int) $female_count);

    if ($gender === 'female') {
        return ['male_slots' => 0, 'female_slots' => max(1, $female)];
    }
    if ($gender === 'male') {
        return ['male_slots' => max(1, $male), 'female_slots' => 0];
    }

    return ['male_slots' => 0, 'female_slots' => 0];
}

/**
 * 募集性別が recruit として保存可能か
 *
 * @param int    $schedule_post_id 新規は 0
 * @param string $gender_condition
 * @return true|\WP_Error
 */
function aidunite_schedule_validate_recruit_gender_for_save($schedule_post_id, $gender_condition) {
    if (function_exists('aidunite_recruit_both_gender_save_permitted')
        && !aidunite_recruit_both_gender_save_permitted((int) $schedule_post_id, $gender_condition)) {
        return new WP_Error(
            'recruit_both_not_allowed',
            '新規の募集では「男子・女子可」（both）を設定できません。'
        );
    }

    $canonical = function_exists('aidunite_normalize_gender_canonical')
        ? aidunite_normalize_gender_canonical($gender_condition)
        : strtolower(trim((string) $gender_condition));

    if ($canonical === '' && strtolower(trim((string) $gender_condition)) === 'both') {
        return new WP_Error('invalid_gender', '男子・女子可（混合）の募集は利用できません。');
    }

    return true;
}

/**
 * 会場名をチーム履歴に追記（最新10件）
 */
function aidunite_schedule_append_venue_name_history($team_id, $venue_name) {
    $team_id = (int) $team_id;
    $venue_name = trim((string) $venue_name);
    if ($team_id < 1 || $venue_name === '') {
        return;
    }
    $venue_history = get_post_meta($team_id, 'venue_name_history', true);
    if (!is_array($venue_history)) {
        $venue_history = [];
    }
    if (!in_array($venue_name, $venue_history, true)) {
        $venue_history[] = $venue_name;
        update_post_meta($team_id, 'venue_name_history', array_slice($venue_history, -10));
    }
}

/**
 * 正規化済みペイロードを postmeta に反映
 *
 * @param int                  $post_id
 * @param array<string, mixed> $data aidunite_schedule_normalize_form_input の戻り値相当
 */
function aidunite_schedule_write_post_meta($post_id, array $data) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return;
    }

    $intent = (string) ($data['intent'] ?? 'confirmed');
    $date = (string) ($data['date'] ?? '');
    $schedule_type = (string) ($data['schedule_type'] ?? '');
    $start_time = (string) ($data['start_time'] ?? '');
    $end_time = (string) ($data['end_time'] ?? '');
    $venue = (string) ($data['venue_condition'] ?? '');
    $venue_name = (string) ($data['venue_name'] ?? '');
    $gender = (string) ($data['gender_condition'] ?? '');
    $team_id = (int) ($data['team_id'] ?? 0);

    if ($date !== '' && $schedule_type !== '') {
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $date . ' ' . $schedule_type,
        ]);
    }

    if ($date !== '') {
        update_post_meta($post_id, 'schedule_date', $date);
        update_post_meta($post_id, 'schedule_end_date', $date);
    }
    if ($schedule_type !== '') {
        update_post_meta($post_id, 'schedule_type', $schedule_type);
    }

    update_post_meta($post_id, 'intent', $intent);
    update_post_meta($post_id, 'certainty', $intent === 'tentative' ? 'tentative' : 'firm');

    if ($start_time !== '') {
        update_post_meta($post_id, 'schedule_start_time', $start_time);
    }
    if ($end_time !== '') {
        update_post_meta($post_id, 'schedule_end_time', $end_time);
    }
    if ($team_id > 0) {
        update_post_meta($post_id, 'team_id', $team_id);
    }

    update_post_meta($post_id, 'is_personal', (string) ($data['is_personal'] ?? '0'));
    update_post_meta($post_id, 'attendance_required', (string) ($data['attendance_required'] ?? '0'));

    if (!empty($data['schedule_quick_memo'])) {
        update_post_meta($post_id, 'schedule_quick_memo', $data['schedule_quick_memo']);
        if (!aidunite_schedule_legacy_meta_writes_enabled()) {
            delete_post_meta($post_id, 'schedule_note');
        }
    }

    if ($intent === 'recruit') {
        update_post_meta($post_id, 'matching', '1');
        update_post_meta($post_id, 'is_match_requested', '1');

        $slots = aidunite_schedule_derive_recruit_slots(
            $gender,
            (int) ($data['male_slots'] ?? 0),
            (int) ($data['female_slots'] ?? 0)
        );

        if ($gender !== '') {
            aidunite_schedule_write_gender_meta($post_id, $gender);
        }
        if ($venue !== '') {
            aidunite_schedule_write_place_meta($post_id, $venue);
        }
        if ($venue_name !== '') {
            update_post_meta($post_id, 'venue_name', $venue_name);
            if ($team_id > 0) {
                aidunite_schedule_append_venue_name_history($team_id, $venue_name);
            }
        }

        $capacity = $slots['male_slots'] + $slots['female_slots'];
        $gender_valid = function_exists('aidunite_mvp_gender_is_valid')
            ? aidunite_mvp_gender_is_valid($gender)
            : in_array($gender, ['male', 'female'], true);
        if ($gender_valid && $capacity > 0) {
            aidunite_schedule_write_recruit_slot_meta($post_id, $slots['male_slots'], $slots['female_slots']);
        }

        if (function_exists('aidunite_apply_normalized_schedule_meta')) {
            aidunite_apply_normalized_schedule_meta($post_id, [
                'intent' => 'recruit',
                'schedule_place' => $venue,
                'schedule_gender' => $gender,
                'is_match_requested' => 1,
                'male_slots' => $slots['male_slots'],
                'female_slots' => $slots['female_slots'],
            ]);
        }
    } else {
        update_post_meta($post_id, 'matching', '0');
        delete_post_meta($post_id, 'is_match_requested');
        delete_post_meta($post_id, 'matching_gender_condition');
        delete_post_meta($post_id, 'schedule_place_option');
        delete_post_meta($post_id, 'male_teams');
        delete_post_meta($post_id, 'female_teams');

        if ($gender !== '') {
            aidunite_schedule_write_gender_meta($post_id, $gender);
        }
        if ($venue !== '') {
            aidunite_schedule_write_place_meta($post_id, $venue);
        }
        if ($venue_name !== '') {
            update_post_meta($post_id, 'venue_name', $venue_name);
            if ($team_id > 0) {
                aidunite_schedule_append_venue_name_history($team_id, $venue_name);
            }
        }
    }

    if (!empty($data['schedule_gender_override'])) {
        update_post_meta($post_id, 'schedule_gender', $data['schedule_gender_override']);
    }

    if (!function_exists('aidunite_schedule_save_registration_venue_snapshot')) {
        require_once get_template_directory() . '/functions/schedule/admin-schedule-list.php';
    }
    if (function_exists('aidunite_schedule_save_registration_venue_snapshot')) {
        aidunite_schedule_save_registration_venue_snapshot(
            $post_id,
            (string) get_post_meta($post_id, 'schedule_place', true),
            (string) get_post_meta($post_id, 'venue_name', true)
        );
    }
}

/**
 * 新規 schedule 投稿を作成してメタ保存
 *
 * @param array<string, mixed> $data
 * @return int|\WP_Error post_id
 */
function aidunite_schedule_create_published_post(array $data) {
    $user_id = (int) ($data['user_id'] ?? get_current_user_id());
    $date = (string) ($data['date'] ?? '');
    $schedule_type = (string) ($data['schedule_type'] ?? '');

    $post_id = wp_insert_post([
        'post_title' => $date . ' ' . $schedule_type,
        'post_content' => '',
        'post_status' => 'publish',
        'post_type' => 'schedule',
        'post_author' => $user_id > 0 ? $user_id : get_current_user_id(),
    ], true);

    if (is_wp_error($post_id) || !$post_id) {
        return is_wp_error($post_id)
            ? $post_id
            : new WP_Error('schedule_create_failed', 'スケジュールの作成に失敗しました');
    }

    aidunite_schedule_write_post_meta((int) $post_id, $data);

    if (($data['intent'] ?? '') === 'recruit' && function_exists('aidunite_fire_schedule_registered_hooks')) {
        aidunite_fire_schedule_registered_hooks((int) $post_id, [
            'intent' => 'recruit',
            'team_id' => (int) ($data['team_id'] ?? 0),
        ]);
    }

    return (int) $post_id;
}

/**
 * 既存 schedule を更新
 *
 * @param int                  $post_id
 * @param array<string, mixed> $data
 * @return true|\WP_Error
 */
function aidunite_schedule_update_published_post($post_id, array $data) {
    $post_id = (int) $post_id;
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'schedule') {
        return new WP_Error('invalid_schedule', '編集対象のスケジュールが見つかりません');
    }

    if (($data['intent'] ?? '') === 'recruit') {
        $gender_check = aidunite_schedule_validate_recruit_gender_for_save($post_id, (string) ($data['gender_condition'] ?? ''));
        if (is_wp_error($gender_check)) {
            return $gender_check;
        }
        $team_id = (int) ($data['team_id'] ?? 0);
        if ($team_id && function_exists('aidunite_validate_recruit_gender_for_team')) {
            $team_err = aidunite_validate_recruit_gender_for_team($team_id, (string) ($data['gender_condition'] ?? ''));
            if (is_wp_error($team_err)) {
                return $team_err;
            }
        }
    }

    aidunite_schedule_write_post_meta($post_id, $data);

    if (($data['attendance_required'] ?? '0') === '1') {
        $path = get_stylesheet_directory() . '/functions/attendance/attendance-notification.php';
        if (is_readable($path)) {
            require_once $path;
            if (function_exists('aidunite_notify_attendance_request')) {
                aidunite_notify_attendance_request($post_id);
            }
        }
    }

    return true;
}

/**
 * recruit 用 match_board 作成（register-schedule-v2 と同一形状: draft + schedule_id meta）
 *
 * @return int 0=失敗
 */
function aidunite_schedule_create_match_board_for_recruit($schedule_id, $user_id) {
    $schedule_id = (int) $schedule_id;
    $user_id = (int) $user_id;
    if ($schedule_id < 1) {
        return 0;
    }

    $team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
        ? (int) aidunite_resolve_schedule_owner_team_id($schedule_id)
        : (int) get_post_meta($schedule_id, 'team_id', true);

    $board_id = wp_insert_post([
        'post_type' => 'match_board',
        'post_title' => '自動作成-' . $schedule_id,
        'post_status' => 'draft',
        'post_author' => $user_id > 0 ? $user_id : get_current_user_id(),
        'meta_input' => [
            'team_id' => $team_id,
            'schedule_id' => $schedule_id,
        ],
    ], true);

    if (is_wp_error($board_id) || !$board_id) {
        return 0;
    }

    $board_id = (int) $board_id;
    if (function_exists('aidunite_match_board_bootstrap_open_status')) {
        aidunite_match_board_bootstrap_open_status($board_id);
    } elseif (function_exists('aidunite_match_board_write_status_meta')) {
        aidunite_match_board_write_status_meta($board_id, 'open');
    }
    if (function_exists('aidunite_match_board_sync_status_from_game')) {
        aidunite_match_board_sync_status_from_game($schedule_id);
    }

    return $board_id;
}

/**
 * 旧バッチ登録（schedule-form-template 配列1件）を persist 用 raw に変換
 *
 * @param array<string, mixed> $item
 * @return array<string, mixed> aidunite_schedule_normalize_form_input の戻り値
 */
function aidunite_schedule_merge_legacy_batch_item(array $item, $team_id, $user_id) {
    $matching = !empty($item['matching']);
    if (isset($item['match_request']) && (string) $item['match_request'] === 'recruit') {
        $matching = true;
    }

    $venue = '';
    foreach (['schedule_place_option', 'place_condition', 'schedule_place', 'venue_condition'] as $key) {
        if (!empty($item[$key])) {
            $venue = sanitize_text_field((string) $item[$key]);
            break;
        }
    }

    $gender = '';
    foreach (['matching_gender_condition', 'schedule_gender', 'gender_condition'] as $key) {
        if (!empty($item[$key])) {
            $gender = sanitize_text_field((string) $item[$key]);
            break;
        }
    }

    $male = (int) ($item['male_teams'] ?? $item['male_slots'] ?? 0);
    $female = (int) ($item['female_teams'] ?? $item['female_slots'] ?? 0);
    if ($matching) {
        if ($gender === 'male' && $male < 1) {
            $male = 1;
        }
        if ($gender === 'female' && $female < 1) {
            $female = 1;
        }
        if ($venue === 'away') {
            if ($gender === 'male' && $male < 1) {
                $male = 1;
            }
            if ($gender === 'female' && $female < 1) {
                $female = 1;
            }
        }
    }

    return aidunite_schedule_normalize_form_input([
        'date' => sanitize_text_field((string) ($item['date'] ?? $item['start_date'] ?? '')),
        'start_time' => sanitize_text_field((string) ($item['start_time'] ?? '')),
        'end_time' => sanitize_text_field((string) ($item['end_time'] ?? '')),
        'schedule_type' => sanitize_text_field((string) ($item['type'] ?? $item['schedule_type'] ?? '')),
        'intent' => $matching ? 'recruit' : 'confirmed',
        'venue_condition' => $venue,
        'venue_name' => sanitize_text_field((string) ($item['place'] ?? $item['venue_name'] ?? '')),
        'gender_condition' => $gender,
        'male_slots' => $male,
        'female_slots' => $female,
        'team_id' => (int) $team_id,
        'user_id' => (int) $user_id,
        'note' => sanitize_textarea_field((string) ($item['note'] ?? '')),
    ]);
}

/**
 * 旧 register-schedules 1件登録（persist + v2 同等の match_board）
 *
 * @param array<string, mixed> $params 単体オブジェクトまたはバッチ1要素
 * @return array{success: bool, post_id?: int, board_id?: int|null, error?: string}
 */
function aidunite_schedule_legacy_register_one(array $params, $team_id, $user_id) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;

    if (isset($params['schedule_type'])) {
        $persist = aidunite_schedule_merge_legacy_params_for_persist($params, 0);
    } else {
        $persist = aidunite_schedule_merge_legacy_batch_item($params, $team_id, $user_id);
    }

    $persist['team_id'] = $team_id;
    $persist['user_id'] = $user_id;

    $date = (string) ($persist['date'] ?? '');
    if ($date !== '') {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            return ['success' => false, 'error' => '無効な日付形式です'];
        }
        $persist['date'] = $date_obj->format('Y-m-d');
    }

    if (($persist['intent'] ?? '') === 'recruit') {
        $gender_check = aidunite_schedule_validate_recruit_gender_for_save(
            0,
            (string) ($persist['gender_condition'] ?? '')
        );
        if (is_wp_error($gender_check)) {
            return ['success' => false, 'error' => $gender_check->get_error_message()];
        }
        if ($team_id > 0 && function_exists('aidunite_validate_recruit_gender_for_team')) {
            $team_err = aidunite_validate_recruit_gender_for_team(
                $team_id,
                (string) ($persist['gender_condition'] ?? '')
            );
            if (is_wp_error($team_err)) {
                return ['success' => false, 'error' => $team_err->get_error_message()];
            }
        }
    }

    $post_id = aidunite_schedule_create_published_post($persist);
    if (is_wp_error($post_id)) {
        return ['success' => false, 'error' => $post_id->get_error_message()];
    }

    $board_id = null;
    if (($persist['intent'] ?? '') === 'recruit' && function_exists('aidunite_schedule_finalize_new_recruit')) {
        $board_id = aidunite_schedule_finalize_new_recruit((int) $post_id, $user_id, $team_id);
    }

    return [
        'success' => true,
        'post_id' => (int) $post_id,
        'board_id' => $board_id > 0 ? $board_id : null,
    ];
}

/**
 * register-schedules REST のノンス検証（wp_rest ヘッダー優先）
 */
function aidunite_schedule_rest_verify_register_nonce($request) {
    $header = $request->get_header('X-WP-Nonce');
    if ($header && wp_verify_nonce($header, 'wp_rest')) {
        return true;
    }
    $nonce = (string) $request->get_param('nonce');
    if ($nonce !== '' && wp_verify_nonce($nonce, 'aidunite_schedule_nonce')) {
        return true;
    }
    if ($nonce !== '' && wp_verify_nonce($nonce, 'wp_rest')) {
        return true;
    }

    return false;
}

/**
 * @deprecated 2026-06-02 正ルートは page-schedule-edit.php POST。互換のため persist 経由で維持。
 */
function aidunite_admin_save_schedule() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[aidunite_schedule] deprecated admin_post_aidunite_save_schedule — use page-schedule-edit or register-schedule-v2');
    }
    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('deprecated_admin_save_schedule', ['user_id' => get_current_user_id()]);
    }

    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::require_auth(true);
    if (!$auth_result->is_valid()) {
        return;
    }

    $user_id = (int) $auth_result->user_id;
    $redirect_base = home_url('/schedule-edit');

    $nonce_result = AidUniteAuthMiddleware::verify_nonce('schedule_nonce', 'aidunite_schedule_nonce');
    if (is_wp_error($nonce_result)) {
        wp_safe_redirect(add_query_arg('error', rawurlencode($nonce_result->get_error_message()), $redirect_base));
        exit;
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
    $auth_result = AidUniteAuthMiddleware::require_team_leader($team_id, false);
    if (!$auth_result->is_valid()) {
        wp_safe_redirect(add_query_arg('error', rawurlencode($auth_result->error ?: '権限がありません'), $redirect_base));
        exit;
    }

    $post_id_from_form = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if ($post_id_from_form > 0) {
        $schedule_team_id = (int) get_post_meta($post_id_from_form, 'team_id', true);
        if ($schedule_team_id > 0 && function_exists('aidunite_user_has_managed_team_access')) {
            if (!aidunite_user_has_managed_team_access($user_id, $schedule_team_id)) {
                wp_safe_redirect(add_query_arg('error', rawurlencode('このスケジュールを編集する権限がありません'), $redirect_base));
                exit;
            }
        }
    }

    $start_date = isset($_POST['start_date']) ? sanitize_text_field((string) $_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field((string) $_POST['end_date']) : '';
    $schedule_type_raw = isset($_POST['schedule_type']) ? sanitize_text_field((string) $_POST['schedule_type']) : '';
    $sh = sanitize_text_field($_POST['start_hour'] ?? '');
    $sm = sanitize_text_field($_POST['start_minute'] ?? '');
    $eh = sanitize_text_field($_POST['end_hour'] ?? '');
    $em = sanitize_text_field($_POST['end_minute'] ?? '');
    $start_time = ($sh !== '' && $sm !== '') ? sprintf('%02d:%02d', (int) $sh, (int) $sm) : '';
    $end_time = ($eh !== '' && $em !== '') ? sprintf('%02d:%02d', (int) $eh, (int) $em) : '';

    if ($start_date === '' || $schedule_type_raw === '') {
        wp_safe_redirect(add_query_arg('error', rawurlencode('必須項目が不足しています（期間/種別）'), $redirect_base));
        exit;
    }

    $type_map = [
        'practice' => '練習',
        'official_match' => '公式試合',
        'practice_match' => '練習試合',
        'joint_practice' => '合同練習',
        'rest' => '休み',
        'event' => 'イベント',
    ];
    $schedule_type = $type_map[$schedule_type_raw] ?? $schedule_type_raw;

    $persist = aidunite_schedule_normalize_form_input([
        'date' => $start_date,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'schedule_type' => $schedule_type,
        'intent' => sanitize_text_field((string) ($_POST['intent'] ?? 'confirmed')),
        'certainty' => sanitize_text_field((string) ($_POST['certainty'] ?? '')),
        'venue_condition' => sanitize_text_field((string) ($_POST['venue_condition'] ?? '')),
        'gender_condition' => sanitize_text_field((string) ($_POST['gender_condition'] ?? '')),
        'male_slots' => (int) ($_POST['male_teams'] ?? $_POST['male_slots'] ?? 0),
        'female_slots' => (int) ($_POST['female_teams'] ?? $_POST['female_slots'] ?? 0),
        'team_id' => $team_id,
        'user_id' => $user_id,
    ]);

    if ($post_id_from_form > 0) {
        $update_result = aidunite_schedule_update_published_post($post_id_from_form, $persist);
        if (is_wp_error($update_result)) {
            wp_safe_redirect(add_query_arg('error', rawurlencode($update_result->get_error_message()), $redirect_base));
            exit;
        }
        $saved_id = $post_id_from_form;
    } else {
        $created = aidunite_schedule_create_published_post($persist);
        if (is_wp_error($created)) {
            wp_safe_redirect(add_query_arg('error', rawurlencode($created->get_error_message()), $redirect_base));
            exit;
        }
        $saved_id = (int) $created;
    }

    if ($end_date !== '' && $end_date !== $start_date) {
        update_post_meta($saved_id, 'schedule_end_date', $end_date);
    }

    wp_safe_redirect(add_query_arg('saved', '1', $redirect_base));
    exit;
}

add_action('admin_post_aidunite_save_schedule', 'aidunite_admin_save_schedule');
