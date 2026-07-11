<?php
/**
 * 出欠 attendance_data 保存本体（normalize 経由）
 *
 * attendance_required は schedule-persist が正本。
 *
 * @see docs/spec/schedule.md（出欠・attendance_data）
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var string[] */
function aidunite_attendance_canonical_statuses() {
    return ['present', 'absent', 'late', 'leave_early', 'no_response'];
}

/**
 * 任意 status を canonical に正規化
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_attendance_normalize_status($raw) {
    if (function_exists('aidunite_normalize_attendance_status_value')) {
        return aidunite_normalize_attendance_status_value($raw);
    }
    return strtolower(trim((string) $raw));
}

/**
 * 未回答か（no_response / pending / 空）
 *
 * @param mixed $raw
 * @return bool
 */
function aidunite_attendance_status_is_unanswered($raw) {
    $status = aidunite_attendance_normalize_status($raw);
    return $status === '' || $status === 'no_response';
}

/**
 * 参加（present / attending）
 *
 * @param mixed $raw
 * @return bool
 */
function aidunite_attendance_status_is_present($raw) {
    return aidunite_attendance_normalize_status($raw) === 'present';
}

/**
 * 不参加（absent / not_attending）
 *
 * @param mixed $raw
 * @return bool
 */
function aidunite_attendance_status_is_absent($raw) {
    return aidunite_attendance_normalize_status($raw) === 'absent';
}

/**
 * フィルター用: UI legacy 値と canonical の一致判定
 *
 * @param mixed  $stored_status attendance_data 内の status
 * @param string $filter        attending|not_attending|pending
 * @return bool
 */
function aidunite_attendance_status_matches_filter($stored_status, $filter) {
    $filter = strtolower(trim((string) $filter));
    if ($filter === 'attending') {
        return aidunite_attendance_status_is_present($stored_status);
    }
    if ($filter === 'not_attending') {
        return aidunite_attendance_status_is_absent($stored_status);
    }
    if ($filter === 'pending') {
        $raw = trim((string) $stored_status);
        if ($raw === '') {
            return true;
        }

        return aidunite_attendance_normalize_status($stored_status) === 'pending';
    }
    if ($filter === 'maybe') {
        return aidunite_attendance_normalize_status($stored_status) === 'no_response';
    }

    return aidunite_attendance_normalize_status($stored_status) === $filter;
}

/**
 * UI 表示用ラベル（参加 / 不参加 / 遅刻 等）
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_attendance_status_ui_label($raw) {
    $status = aidunite_attendance_normalize_status($raw);
    $labels = [
        'present' => '参加',
        'absent' => '不参加',
        'late' => '遅刻',
        'leave_early' => '早退',
        'no_response' => '未回答',
    ];

    return $labels[$status] ?? '未回答';
}

/**
 * フォーム POST 用 legacy 値。no_response かつ回答済み行は maybe（未定）
 *
 * @param mixed $raw
 * @param bool  $has_row attendance_data に行があるか
 * @return string
 */
function aidunite_attendance_status_form_value($raw, $has_row = false) {
    if (aidunite_attendance_status_is_present($raw)) {
        return 'attending';
    }
    if (aidunite_attendance_status_is_absent($raw)) {
        return 'not_attending';
    }
    if ($has_row && aidunite_attendance_status_is_unanswered($raw)) {
        return 'maybe';
    }

    return '';
}

/**
 * UI 表示用ステータスキー
 *
 * @param mixed $raw
 * @param bool  $has_row
 * @return string
 */
function aidunite_attendance_status_ui_key($raw, $has_row = false) {
    $form = aidunite_attendance_status_form_value($raw, $has_row);
    return $form !== '' ? $form : 'unanswered';
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_attendance_normalize_input(array $raw) {
    if (function_exists('aidunite_normalize_attendance_payload')) {
        return aidunite_normalize_attendance_payload($raw);
    }

    return $raw;
}

/**
 * 1ユーザー分の attendance_data 行を正規化
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>|null 不正 status は null
 */
function aidunite_attendance_normalize_user_row(array $row) {
    $status_raw = (string) ($row['status'] ?? $row['attendance_status'] ?? '');
    $normalized = aidunite_attendance_normalize_input(['status' => $status_raw]);
    $status = (string) ($normalized['status'] ?? $normalized['attendance_status'] ?? '');
    if ($status === '' && function_exists('aidunite_normalize_attendance_status_value')) {
        $status = aidunite_normalize_attendance_status_value($status_raw);
    }
    if (!in_array($status, aidunite_attendance_canonical_statuses(), true)) {
        return null;
    }

    $out = [
        'status' => $status,
        'note' => isset($row['note']) ? sanitize_textarea_field((string) $row['note']) : '',
        'user_type' => isset($row['user_type']) ? sanitize_text_field((string) $row['user_type']) : '',
        'response_date' => (string) ($row['response_date'] ?? ''),
        'response_time' => (string) ($row['response_time'] ?? ''),
    ];
    if ($out['response_date'] === '') {
        $out['response_date'] = current_time('mysql');
    }
    if ($out['response_time'] === '') {
        $out['response_time'] = current_time('mysql', true);
    }

    return $out;
}

/**
 * attendance_data 全体を正規化（キーは user_id）
 *
 * @param array<int|string, array<string, mixed>> $data
 * @return array<int, array<string, mixed>>
 */
function aidunite_attendance_normalize_data_array(array $data) {
    $out = [];
    foreach ($data as $user_id => $row) {
        $uid = (int) $user_id;
        if ($uid <= 0 || !is_array($row)) {
            continue;
        }
        $norm = aidunite_attendance_normalize_user_row($row);
        if ($norm !== null) {
            $out[$uid] = $norm;
        }
    }

    return $out;
}

/**
 * attendance_data を正規化して保存（唯一の bulk 書き込み口）
 *
 * @param int                              $schedule_id
 * @param array<int, array<string, mixed>> $attendance_data
 * @return bool
 */
function aidunite_attendance_persist_save_data($schedule_id, array $attendance_data) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0 || get_post_type($schedule_id) !== 'schedule') {
        return false;
    }
    $normalized = aidunite_attendance_normalize_data_array($attendance_data);
    $result = update_post_meta($schedule_id, 'attendance_data', $normalized);

    return $result !== false;
}

/**
 * 1ユーザーの出欠をマージ保存
 *
 * @param int                  $schedule_id
 * @param int                  $user_id
 * @param array<string, mixed> $row status, note, user_type 等
 * @return bool
 */
function aidunite_attendance_write_user_row($schedule_id, $user_id, array $row) {
    $schedule_id = (int) $schedule_id;
    $user_id = (int) $user_id;
    if ($schedule_id <= 0 || $user_id <= 0) {
        return false;
    }

    $norm = aidunite_attendance_normalize_user_row($row);
    if ($norm === null) {
        return false;
    }

    $existing = function_exists('aidunite_attendance_read_data_map')
        ? aidunite_attendance_read_data_map($schedule_id)
        : [];
    if (!is_array($existing)) {
        $existing = [];
    }
    $existing[$user_id] = $norm;

    return aidunite_attendance_persist_save_data($schedule_id, $existing);
}

/**
 * legacy status（attending / pending 等）を attendance_data 内で canonical 化
 *
 * @param bool $dry_run
 * @return array{migrated_fields:int,updated_schedules:int,total_schedules:int,dry_run:bool}
 */
function aidunite_attendance_migrate_legacy_status_in_data($dry_run = false) {
    global $wpdb;
    $schedule_ids = $wpdb->get_col(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'attendance_data'"
    );
    $migrated_fields = 0;
    $updated_schedules = 0;

    foreach ($schedule_ids ?: [] as $schedule_id) {
        $schedule_id = (int) $schedule_id;
        $data = get_post_meta($schedule_id, 'attendance_data', true);
        if (!is_array($data) || $data === []) {
            continue;
        }
        $changed = false;
        foreach ($data as $user_id => $row) {
            if (!is_array($row) || !isset($row['status'])) {
                continue;
            }
            $raw = (string) $row['status'];
            $norm = function_exists('aidunite_normalize_attendance_status_value')
                ? aidunite_normalize_attendance_status_value($raw)
                : $raw;
            if ($norm === $raw) {
                continue;
            }
            $changed = true;
            $migrated_fields++;
            if (!$dry_run) {
                $data[$user_id]['status'] = $norm;
            }
        }
        if ($changed) {
            $updated_schedules++;
            if (!$dry_run) {
                aidunite_attendance_persist_save_data($schedule_id, $data);
            }
        }
    }

    return [
        'migrated_fields' => $migrated_fields,
        'updated_schedules' => $updated_schedules,
        'total_schedules' => count($schedule_ids ?: []),
        'dry_run' => (bool) $dry_run,
    ];
}
