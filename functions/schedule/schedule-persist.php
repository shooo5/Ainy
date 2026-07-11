<?php
/**
 * スケジュール登録・更新の本体（normalize 経由 → postmeta 一本化）
 *
 * 正ルート: page-schedule-edit.php POST / REST register-schedule-v2 / update-schedule-v2 / delete-schedule-v2
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
 * intent / matching フラグのみ更新（成立・復元・招待など。日時・会場は触らない）
 *
 * @param int         $schedule_id
 * @param string      $intent confirmed|recruit|tentative
 * @param bool|null   $recruit_matching true=募集ON, false=OFF, null=変更なし
 */
function aidunite_schedule_persist_intent_flags($schedule_id, $intent, $recruit_matching = null) {
    $schedule_id = (int) $schedule_id;
    $intent = (string) $intent;
    if ($schedule_id < 1 || $intent === '') {
        return;
    }

    update_post_meta($schedule_id, 'intent', $intent);
    update_post_meta($schedule_id, 'certainty', $intent === 'tentative' ? 'tentative' : 'firm');

    if ($recruit_matching === true) {
        update_post_meta($schedule_id, 'matching', '1');
        update_post_meta($schedule_id, 'is_match_requested', '1');
    } elseif ($recruit_matching === false) {
        update_post_meta($schedule_id, 'matching', '0');
        update_post_meta($schedule_id, 'is_match_requested', '0');
    }
    // apply_normalized_schedule_meta は intent 時に本関数を呼ぶため、ここから再呼び出ししない（無限再帰→メモリ枯渇）
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
/**
 * クイックメモ（正本: schedule_quick_memo）。REST の memo / 旧 schedule_memo は読取のみ吸収。
 *
 * @param int    $post_id
 * @param string $memo
 */
function aidunite_schedule_write_quick_memo_meta($post_id, $memo) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return;
    }
    $memo = sanitize_textarea_field((string) $memo);
    update_post_meta($post_id, 'schedule_quick_memo', $memo);
    if (aidunite_schedule_legacy_meta_writes_enabled()) {
        update_post_meta($post_id, 'schedule_memo', $memo);
        return;
    }
    delete_post_meta($post_id, 'schedule_memo');
    delete_post_meta($post_id, 'schedule_note');
}

/**
 * 表示・マージ用: 正本＋旧キー（schedule_note / schedule_memo）フォールバック
 *
 * @param int $post_id
 * @return string
 */
function aidunite_schedule_read_quick_memo_meta($post_id) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return '';
    }

    return (string) (get_post_meta($post_id, 'schedule_quick_memo', true)
        ?: get_post_meta($post_id, 'schedule_note', true)
        ?: get_post_meta($post_id, 'schedule_memo', true));
}

/**
 * 終了日メタ（正本: schedule_end_date）。開始日と異なる場合のみ別日を保存。
 *
 * @param int    $post_id
 * @param string $start_date schedule_date 相当（YYYY-MM-DD）
 * @param string $end_date   フォーム end_date。空または開始日と同じなら開始日を保存
 */
function aidunite_schedule_write_end_date_meta($post_id, $start_date, $end_date = '') {
    $post_id = (int) $post_id;
    $start_date = sanitize_text_field((string) $start_date);
    $end_date = sanitize_text_field((string) $end_date);
    if ($post_id < 1 || $start_date === '') {
        return;
    }
    $resolved = ($end_date !== '' && $end_date !== $start_date) ? $end_date : $start_date;
    update_post_meta($post_id, 'schedule_end_date', $resolved);
}

/**
 * 表示・マージ用: schedule_end_date → schedule_date フォールバック
 *
 * @param int $post_id
 * @return string
 */
function aidunite_schedule_read_end_date_meta($post_id) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return '';
    }

    $end = (string) get_post_meta($post_id, 'schedule_end_date', true);
    if ($end !== '') {
        return $end;
    }

    return (string) get_post_meta($post_id, 'schedule_date', true);
}

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
        'end_date' => sanitize_text_field((string) ($raw['end_date'] ?? '')),
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
        'schedule_quick_memo' => sanitize_textarea_field((string) ($raw['schedule_quick_memo'] ?? $raw['note'] ?? $raw['memo'] ?? '')),
        'schedule_origin' => sanitize_text_field((string) ($raw['schedule_origin'] ?? '')),
        'competition_event_id' => max(0, (int) ($raw['competition_event_id'] ?? 0)),
        'competition_entry_id' => max(0, (int) ($raw['competition_entry_id'] ?? 0)),
        'all_day' => !empty($raw['all_day']) && (string) $raw['all_day'] !== '0' ? '1' : '0',
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
            'end_date' => aidunite_schedule_read_end_date_meta($post_id),
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
            'schedule_quick_memo' => aidunite_schedule_read_quick_memo_meta($post_id),
        ]);
    }

    if (isset($params['date'])) {
        $base['date'] = sanitize_text_field((string) $params['date']);
    }
    if (isset($params['start_date'])) {
        $base['date'] = sanitize_text_field((string) $params['start_date']);
    }
    if (isset($params['end_date'])) {
        $base['end_date'] = sanitize_text_field((string) $params['end_date']);
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
    if (isset($params['schedule_memo'])) {
        $base['schedule_quick_memo'] = sanitize_textarea_field((string) $params['schedule_memo']);
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
    if (array_key_exists('all_day', $params)) {
        $base['all_day'] = !empty($params['all_day']) && (string) $params['all_day'] !== '0' ? '1' : '0';
    }

    $ui_kind = sanitize_key((string) ($params['ui_schedule_kind'] ?? ''));
    $tentative_base = sanitize_key((string) ($params['tentative_base_kind'] ?? ''));
    if ($ui_kind === 'tentative' && function_exists('aidunite_schedule_resolve_tentative_schedule_type')) {
        $base['intent'] = 'tentative';
        $base['certainty'] = 'tentative';
        $base['schedule_type'] = aidunite_schedule_resolve_tentative_schedule_type(
            $tentative_base,
            (string) ($base['schedule_type'] ?? '')
        );
    } elseif ($ui_kind !== '' && function_exists('aidunite_schedule_map_ui_kind_to_meta')) {
        $kind_meta = aidunite_schedule_map_ui_kind_to_meta($ui_kind);
        if (!empty($kind_meta['schedule_type'])) {
            $base['schedule_type'] = $kind_meta['schedule_type'];
        }
        if (!empty($kind_meta['intent'])) {
            $base['intent'] = $kind_meta['intent'];
        }
        if (!empty($kind_meta['certainty'])) {
            $base['certainty'] = $kind_meta['certainty'];
        }
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
        aidunite_schedule_write_end_date_meta($post_id, $date, (string) ($data['end_date'] ?? ''));
    }
    if ($schedule_type !== '') {
        update_post_meta($post_id, 'schedule_type', $schedule_type);
    }

    update_post_meta($post_id, 'intent', $intent);
    update_post_meta($post_id, 'certainty', $intent === 'tentative' ? 'tentative' : 'firm');

    $all_day = !empty($data['all_day']) && (string) $data['all_day'] !== '0';
    $allows_all_day = function_exists('aidunite_schedule_data_allows_all_day')
        && aidunite_schedule_data_allows_all_day($data);
    $clear_times = $all_day || ($allows_all_day && $start_time === '' && $end_time === '');

    if ($clear_times) {
        delete_post_meta($post_id, 'schedule_start_time');
        delete_post_meta($post_id, 'schedule_end_time');
    } else {
        if ($start_time !== '') {
            update_post_meta($post_id, 'schedule_start_time', $start_time);
        }
        if ($end_time !== '') {
            update_post_meta($post_id, 'schedule_end_time', $end_time);
        }
    }
    if ($team_id > 0) {
        update_post_meta($post_id, 'team_id', $team_id);
    }

    update_post_meta($post_id, 'is_personal', (string) ($data['is_personal'] ?? '0'));
    update_post_meta($post_id, 'attendance_required', (string) ($data['attendance_required'] ?? '0'));

    aidunite_schedule_write_quick_memo_meta($post_id, (string) ($data['schedule_quick_memo'] ?? ''));

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

    // マッチ申請時の自動仮 schedule（intent=tentative だが matching フラグは維持）
    if ($intent === 'tentative' && (string) ($data['schedule_origin'] ?? '') === 'match_apply_tentative') {
        update_post_meta($post_id, 'matching', '1');
        update_post_meta($post_id, 'is_match_requested', '1');
        update_post_meta($post_id, 'aidunite_schedule_origin', 'match_apply_tentative');
        $tentative_male = max(0, (int) ($data['male_slots'] ?? 0));
        $tentative_female = max(0, (int) ($data['female_slots'] ?? 0));
        if ($tentative_male + $tentative_female > 0) {
            aidunite_schedule_write_recruit_slot_meta($post_id, $tentative_male, $tentative_female);
        }
    }

    if (!empty($data['schedule_gender_override'])) {
        update_post_meta($post_id, 'schedule_gender', $data['schedule_gender_override']);
    }

    $schedule_origin = sanitize_text_field((string) ($data['schedule_origin'] ?? ''));
    if ($schedule_origin !== '') {
        update_post_meta($post_id, 'aidunite_schedule_origin', $schedule_origin);
    }

    $competition_event_id = max(0, (int) ($data['competition_event_id'] ?? 0));
    $competition_entry_id = max(0, (int) ($data['competition_entry_id'] ?? 0));
    if ($competition_event_id > 0) {
        update_post_meta($post_id, 'competition_event_id', $competition_event_id);
    }
    if ($competition_entry_id > 0) {
        update_post_meta($post_id, 'competition_entry_id', $competition_entry_id);
    }

    if (!function_exists('aidunite_schedule_save_registration_venue_snapshot')) {
        require_once get_template_directory() . '/functions/schedule/admin-schedule-list.php';
    }
    if (function_exists('aidunite_schedule_save_registration_venue_snapshot')) {
        try {
            aidunite_schedule_save_registration_venue_snapshot(
                $post_id,
                (string) get_post_meta($post_id, 'schedule_place', true),
                (string) get_post_meta($post_id, 'venue_name', true)
            );
        } catch (Throwable $e) {
            if (class_exists('AidUniteErrorHandler')) {
                AidUniteErrorHandler::warning('schedule_venue_snapshot_failed', [
                    'schedule_id' => $post_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

/**
 * 新規 schedule 投稿を作成してメタ保存
 *
 * @param array<string, mixed> $data
 * @return int|\WP_Error post_id
 */
function aidunite_schedule_is_persist_write_in_progress() {
    return !empty($GLOBALS['aidunite_schedule_persist_write_in_progress']);
}

function aidunite_schedule_create_published_post(array $data) {
    $user_id = (int) ($data['user_id'] ?? get_current_user_id());
    $date = (string) ($data['date'] ?? '');
    $schedule_type = (string) ($data['schedule_type'] ?? '');

    unset($GLOBALS['aidunite_schedule_last_persist_post_id']);
    $GLOBALS['aidunite_schedule_persist_write_in_progress'] = true;
    $post_id = wp_insert_post([
        'post_title' => $date . ' ' . $schedule_type,
        'post_content' => '',
        'post_status' => 'publish',
        'post_type' => 'schedule',
        'post_author' => $user_id > 0 ? $user_id : get_current_user_id(),
    ], true);

    if (is_wp_error($post_id) || !$post_id) {
        unset($GLOBALS['aidunite_schedule_persist_write_in_progress']);
        return is_wp_error($post_id)
            ? $post_id
            : new WP_Error('schedule_create_failed', 'スケジュールの作成に失敗しました');
    }

    $post_id = (int) $post_id;
    $GLOBALS['aidunite_schedule_last_persist_post_id'] = $post_id;

    try {
        aidunite_schedule_write_post_meta($post_id, $data);
    } catch (Throwable $meta_error) {
        if (class_exists('AidUniteErrorHandler')) {
            AidUniteErrorHandler::warning('schedule_write_post_meta_failed', [
                'schedule_id' => $post_id,
                'error' => $meta_error->getMessage(),
            ]);
        }
    } finally {
        unset($GLOBALS['aidunite_schedule_persist_write_in_progress']);
    }

    if (($data['attendance_required'] ?? '0') === '1') {
        $notify_path = get_stylesheet_directory() . '/functions/attendance/attendance-notification.php';
        if (is_readable($notify_path)) {
            require_once $notify_path;
            if (function_exists('aidunite_attendance_maybe_notify_request')) {
                aidunite_attendance_maybe_notify_request($post_id, '0');
            }
        }
    }

    $defer_hooks = !empty($data['defer_registered_hooks']);
    if (
        !$defer_hooks
        && ($data['intent'] ?? '') === 'recruit'
        && function_exists('aidunite_fire_schedule_registered_hooks')
    ) {
        try {
            aidunite_fire_schedule_registered_hooks((int) $post_id, [
                'intent' => 'recruit',
                'team_id' => (int) ($data['team_id'] ?? 0),
            ]);
        } catch (Throwable $e) {
            if (class_exists('AidUniteErrorHandler')) {
                AidUniteErrorHandler::warning('schedule_create_recruit_hook_failed', [
                    'schedule_id' => (int) $post_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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

    $previous_attendance_required = (string) get_post_meta($post_id, 'attendance_required', true);
    $previous_attendance_required = ($previous_attendance_required === '1') ? '1' : '0';

    aidunite_schedule_write_post_meta($post_id, $data);

    $notify_path = get_stylesheet_directory() . '/functions/attendance/attendance-notification.php';
    if (is_readable($notify_path)) {
        require_once $notify_path;
        if (function_exists('aidunite_attendance_maybe_notify_request')) {
            aidunite_attendance_maybe_notify_request($post_id, $previous_attendance_required);
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
    try {
        if (function_exists('aidunite_match_board_bootstrap_open_status')) {
            aidunite_match_board_bootstrap_open_status($board_id);
        } elseif (function_exists('aidunite_match_board_write_status_meta')) {
            aidunite_match_board_write_status_meta($board_id, 'open');
        }
        if (function_exists('aidunite_match_board_sync_status_from_game')) {
            aidunite_match_board_sync_status_from_game($schedule_id);
        }
    } catch (Throwable $e) {
        if (class_exists('AidUniteErrorHandler')) {
            AidUniteErrorHandler::warning('match_board_bootstrap_failed', [
                'board_id' => $board_id,
                'schedule_id' => $schedule_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    return $board_id;
}

/**
 * 旧フロント保存の会場未設定補完（schedule_place 空・schedule_place_option のみ）
 *
 * チェックボックス形式（配列）の option も文字列化して schedule_place 正本へコピーする。
 * 旧実装: functions.php No.42 → persist へ集約。
 *
 * @param int $post_id
 * @return bool 補完した場合 true
 */
function aidunite_schedule_backfill_place_from_option($post_id) {
    $post_id = (int) $post_id;
    if ($post_id < 1 || get_post_type($post_id) !== 'schedule') {
        return false;
    }
    if (is_admin()) {
        return false;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return false;
    }

    $place = (string) get_post_meta($post_id, 'schedule_place', true);
    if ($place !== '') {
        return false;
    }

    $place_option = get_post_meta($post_id, 'schedule_place_option', true);
    if (is_array($place_option)) {
        $place_option = (string) ($place_option[0] ?? '');
    } else {
        $place_option = (string) $place_option;
    }
    if ($place_option === '') {
        return false;
    }

    if (function_exists('update_field')) {
        update_field('schedule_place', $place_option, $post_id);
    } else {
        aidunite_schedule_write_place_meta($post_id, $place_option);
    }

    return true;
}

add_action('save_post', 'aidunite_schedule_backfill_place_from_option_on_save', 20, 1);

/**
 * @param int $post_id
 */
function aidunite_schedule_backfill_place_from_option_on_save($post_id) {
    if (function_exists('aidunite_schedule_is_persist_write_in_progress')
        && aidunite_schedule_is_persist_write_in_progress()) {
        return;
    }
    aidunite_schedule_backfill_place_from_option($post_id);
}

/**
 * レガシー管理者UI用 accepted_count の加減算
 */
function aidunite_schedule_adjust_accepted_count_meta($schedule_id, $delta) {
    $schedule_id = (int) $schedule_id;
    $delta = (int) $delta;
    if ($schedule_id < 1 || $delta === 0 || get_post_type($schedule_id) !== 'schedule') {
        return;
    }

    $count = (int) get_post_meta($schedule_id, 'accepted_count', true);
    update_post_meta($schedule_id, 'accepted_count', max(0, $count + $delta));
}
