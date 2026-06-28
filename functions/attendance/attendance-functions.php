<?php
/**
 * 出欠管理関数（view model・クエリ・ポリシー）
 *
 * スケジュールごとの出欠確認機能を提供します。
 * 読取: attendance-persist-read.php / 保存: attendance-persist-submit.php
 *
 * @see docs/spec/schedule.md §14.9
 * @version 1.0.0
 * @created 2026-01-10
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/functions/user/user-functions.php';

/**
 * 出欠データ更新用のロックを取得
 *
 * @param int $schedule_id スケジュールID
 * @param int $timeout タイムアウト（秒、デフォルト: 5秒）
 * @return bool ロック取得成功時true
 */
function aidunite_acquire_attendance_lock($schedule_id, $timeout = 5) {
    $lock_key = "attendance_lock_{$schedule_id}";
    $lock_value = time() + $timeout;
    $max_attempts = 10;
    $attempt = 0;

    while ($attempt < $max_attempts) {
        $existing_lock = get_transient($lock_key);

        if ($existing_lock === false || $existing_lock < time()) {
            if (set_transient($lock_key, $lock_value, $timeout)) {
                return true;
            }
        }

        usleep(100000); // 0.1秒待機
        $attempt++;
    }

    return false;
}

/**
 * 出欠データ更新用のロックを解放
 *
 * @param int $schedule_id スケジュールID
 * @return bool 成功時true
 */
function aidunite_release_attendance_lock($schedule_id) {
    $lock_key = "attendance_lock_{$schedule_id}";
    return delete_transient($lock_key);
}

/**
 * 出欠確認が必要なスケジュール一覧を取得
 *
 * @param int $team_id チームID（オプション）
 * @param string $date_from 開始日（YYYY-MM-DD形式、オプション）
 * @param string $date_to 終了日（YYYY-MM-DD形式、オプション）
 * @return array スケジュール配列
 */
function aidunite_get_attendance_required_schedules($team_id = null, $date_from = null, $date_to = null) {
    $query_args = [
        'order' => 'ASC',
    ];

    if ($team_id) {
        $query_args['team_id'] = (int) $team_id;
    }

    if ($date_from) {
        $query_args['date_from'] = $date_from;
    } else {
        $query_args['date_from'] = date('Y-m-d');
    }

    if ($date_to) {
        $query_args['date_to'] = $date_to;
    }

    $schedules = function_exists('aidunite_attendance_read_schedules')
        ? aidunite_attendance_read_schedules($query_args)
        : [];

    $result = [];
    foreach ($schedules as $schedule) {
        $result[] = function_exists('aidunite_attendance_read_schedule_list_row')
            ? aidunite_attendance_read_schedule_list_row($schedule)
            : ['id' => (int) (is_object($schedule) ? $schedule->ID : $schedule)];
    }

    return $result;
}

/**
 * チームの出欠回答対象メンバー ID（選手のみ）
 *
 * @param int $team_id
 * @return int[]
 */
function aidunite_attendance_get_target_member_ids_for_team($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || !function_exists('aidunite_get_team_members_list')) {
        return [];
    }

    $member_ids = [];
    foreach (aidunite_get_team_members_list($team_id) as $member) {
        if (($member['user_type'] ?? '') === 'player') {
            $member_ids[] = (int) $member['user_id'];
        }
    }

    return array_values(array_unique($member_ids));
}

/**
 * 出欠回答を保存するユーザー ID（保護者は紐づく選手 ID に解決）
 *
 * @param int $user_id
 * @param int $team_id
 * @return int 0 = 解決不可
 */
function aidunite_attendance_resolve_respondent_user_id($user_id, $team_id = 0) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0) {
        return 0;
    }

    $user_type = function_exists('aidunite_get_user_type')
        ? aidunite_get_user_type($user_id)
        : (string) get_user_meta($user_id, 'user_type', true);

    if ($user_type === 'player') {
        return $user_id;
    }

    if ($user_type !== 'parent') {
        return 0;
    }

    $target_ids = $team_id > 0
        ? aidunite_attendance_get_target_member_ids_for_team($team_id)
        : [];

    if (function_exists('aidunite_get_parent_children')) {
        foreach (aidunite_get_parent_children($user_id) as $child) {
            $child_id = (int) ($child['user_id'] ?? 0);
            if ($child_id <= 0) {
                continue;
            }
            if ($target_ids === [] || in_array($child_id, $target_ids, true)) {
                return $child_id;
            }
        }
    }

    if ($target_ids !== [] && function_exists('aidunite_get_player_parent')) {
        foreach ($target_ids as $player_id) {
            $parent_id = (int) aidunite_get_player_parent((int) $player_id);
            if ($parent_id === $user_id) {
                return (int) $player_id;
            }
        }
    }

    return 0;
}

/**
 * 代表者向けにスケジュール行へ出欠サマリを付与
 *
 * @param array<string, mixed> $schedule_data
 * @param int                  $schedule_id
 * @param int                  $user_id
 * @return array<string, mixed>
 */
function aidunite_schedule_enrich_attendance_summary(array $schedule_data, $schedule_id, $user_id = 0) {
    $schedule_id = (int) $schedule_id;
    $user_id = (int) ($user_id > 0 ? $user_id : get_current_user_id());
    if ($schedule_id <= 0 || ($schedule_data['attendance_required'] ?? '0') !== '1') {
        return $schedule_data;
    }

    $can_view = false;
    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($user_id)) {
        $can_view = true;
    } elseif (function_exists('aidunite_get_user_role') && aidunite_get_user_role($user_id) === 'team_leader') {
        $can_view = true;
    }
    if (!$can_view) {
        return $schedule_data;
    }

    $team_id = (int) ($schedule_data['team_id'] ?? 0);
    if ($team_id <= 0 && function_exists('aidunite_schedule_read_team_id')) {
        $team_id = (int) aidunite_schedule_read_team_id($schedule_id);
    }

    $member_ids = aidunite_attendance_get_target_member_ids_for_team($team_id);
    $summary = aidunite_get_attendance_summary($schedule_id, $member_ids);
    $answered = (int) ($summary['attending'] ?? 0) + (int) ($summary['not_attending'] ?? 0);
    $total = (int) ($summary['total'] ?? 0);

    $schedule_data['attendance_summary'] = [
        'attending' => (int) ($summary['attending'] ?? 0),
        'not_attending' => (int) ($summary['not_attending'] ?? 0),
        'pending' => (int) ($summary['pending'] ?? 0),
        'total' => $total,
        'answered' => $answered,
        'label' => $total > 0 ? sprintf('%d/%d 回答', $answered, $total) : '—',
    ];

    return $schedule_data;
}

/**
 * 出欠状況の集計
 *
 * @param int $schedule_id スケジュールID
 * @param array $team_member_ids チームメンバーID配列（オプション）
 * @return array 集計結果
 */
function aidunite_get_attendance_summary($schedule_id, $team_member_ids = []) {
    $attendance_data = function_exists('aidunite_attendance_read_data_map')
        ? aidunite_attendance_read_data_map($schedule_id)
        : [];

    if (!is_array($attendance_data)) {
        $attendance_data = [];
    }

    $summary = [
        'total' => 0,
        'attending' => 0,
        'not_attending' => 0,
        'pending' => 0
    ];

    // チームメンバーIDが指定されている場合
    if (!empty($team_member_ids)) {
        $summary['total'] = count($team_member_ids);

        foreach ($team_member_ids as $user_id) {
            $status = $attendance_data[$user_id]['status'] ?? '';

            if (function_exists('aidunite_attendance_status_is_present') && aidunite_attendance_status_is_present($status)) {
                $summary['attending']++;
            } elseif (function_exists('aidunite_attendance_status_is_absent') && aidunite_attendance_status_is_absent($status)) {
                $summary['not_attending']++;
            } elseif ($status === 'attending') {
                $summary['attending']++;
            } elseif ($status === 'not_attending') {
                $summary['not_attending']++;
            } else {
                $summary['pending']++;
            }
        }
    } else {
        // チームメンバーIDが指定されていない場合、回答済みのユーザーのみをカウント
        $summary['total'] = count($attendance_data);

        foreach ($attendance_data as $user_id => $data) {
            $status = $data['status'] ?? '';

            if (function_exists('aidunite_attendance_status_is_present') && aidunite_attendance_status_is_present($status)) {
                $summary['attending']++;
            } elseif (function_exists('aidunite_attendance_status_is_absent') && aidunite_attendance_status_is_absent($status)) {
                $summary['not_attending']++;
            } elseif ($status === 'attending') {
                $summary['attending']++;
            } elseif ($status === 'not_attending') {
                $summary['not_attending']++;
            } else {
                $summary['pending']++;
            }
        }
    }

    return $summary;
}

/**
 * ユーザーの最近の出欠回答（schedule.attendance_data 正本）
 *
 * @param int $user_id
 * @param int $limit
 * @param int $days_back
 * @return array<int, array<string, mixed>>
 */
function aidunite_attendance_read_user_recent($user_id, $limit = 5, $days_back = 30) {
    $user_id = (int) $user_id;
    $limit = max(1, (int) $limit);
    $days_back = max(1, (int) $days_back);
    if ($user_id <= 0) {
        return [];
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
    if ($team_id <= 0) {
        return [];
    }

    $from = date('Y-m-d', strtotime('-' . $days_back . ' days'));
    $to = date('Y-m-d');

    $schedules = function_exists('aidunite_attendance_read_schedules')
        ? aidunite_attendance_read_schedules([
            'team_id' => $team_id,
            'date_between' => [$from, $to],
            'posts_per_page' => 80,
            'order' => 'DESC',
        ])
        : [];

    $rows = [];
    foreach ($schedules as $schedule) {
        $schedule_id = (int) $schedule->ID;
        $attendance_data = function_exists('aidunite_attendance_read_data_map')
            ? aidunite_attendance_read_data_map($schedule_id)
            : [];
        if (!is_array($attendance_data) || !isset($attendance_data[$user_id])) {
            continue;
        }

        $row = $attendance_data[$user_id];
        $sch_disp = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($schedule_id)
            : [];
        $schedule_date = (string) ($sch_disp['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($schedule_id)
            : ''));
        $status_raw = (string) ($row['status'] ?? '');

        $rows[] = [
            'id' => $schedule_id,
            'schedule_id' => $schedule_id,
            'attendance_id' => $schedule_id,
            'date' => $schedule_date,
            'schedule_date' => $schedule_date,
            'status' => $status_raw,
            'schedule_title' => $schedule->post_title,
            'submitted_date' => (string) ($row['response_date'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
        ];
    }

    return array_slice($rows, 0, $limit);
}

/**
 * 出席率（attendance_required=1 の過去スケジュールに対する present 率）
 *
 * @param int $user_id
 * @param int $team_id 0 なら user から解決
 * @return int 0–100
 */
function aidunite_attendance_calculate_presence_rate($user_id, $team_id = 0) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }

    if ($team_id <= 0) {
        $team_id = function_exists('aidunite_get_current_team_id')
            ? (int) aidunite_get_current_team_id($user_id)
            : (int) get_user_meta($user_id, 'team_id', true);
    }
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 0;
    }

    $past_schedules = function_exists('aidunite_attendance_read_schedules')
        ? aidunite_attendance_read_schedules([
            'team_id' => $team_id,
            'date_before' => date('Y-m-d'),
            'fields' => 'ids',
        ])
        : [];

    if ($past_schedules === []) {
        return 100;
    }

    $present_count = 0;
    foreach ($past_schedules as $schedule_id) {
        $schedule_id = (int) $schedule_id;
        $attendance_data = function_exists('aidunite_attendance_read_data_map')
            ? aidunite_attendance_read_data_map($schedule_id)
            : [];
        if (!is_array($attendance_data) || !isset($attendance_data[$user_id])) {
            continue;
        }
        $status = $attendance_data[$user_id]['status'] ?? '';
        if (function_exists('aidunite_attendance_status_is_present') && aidunite_attendance_status_is_present($status)) {
            $present_count++;
        } elseif ($status === 'attending' || $status === 'attended') {
            $present_count++;
        }
    }

    return (int) round(($present_count / count($past_schedules)) * 100);
}

/**
 * 出欠回答期限 = スケジュール日の N 日前 HH:MM（統一定義）
 *
 * @return int
 */
function aidunite_attendance_policy_response_deadline_days() {
    return (int) apply_filters('aidunite_attendance_policy_response_deadline_days', 1);
}

/**
 * 出欠回答期限の時刻（統一定義・24h HH:MM）
 *
 * @return string
 */
function aidunite_attendance_policy_response_deadline_time() {
    return (string) apply_filters('aidunite_attendance_policy_response_deadline_time', '18:00');
}

/**
 * 出欠回答期限 datetime（Y-m-d H:i:s）
 *
 * @param string $schedule_date Y-m-d
 * @return string
 */
function aidunite_attendance_policy_get_response_deadline($schedule_date) {
    $schedule_date = trim((string) $schedule_date);
    if ($schedule_date === '') {
        return '';
    }

    $days = max(0, aidunite_attendance_policy_response_deadline_days());
    $time_raw = aidunite_attendance_policy_response_deadline_time();
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time_raw, $m)) {
        $time_raw = '18:00';
        preg_match('/^(\d{1,2}):(\d{2})$/', $time_raw, $m);
    }
    $hour = str_pad((string) (int) $m[1], 2, '0', STR_PAD_LEFT);
    $minute = str_pad((string) (int) $m[2], 2, '0', STR_PAD_LEFT);
    $deadline_date = date('Y-m-d', strtotime($schedule_date . ' -' . $days . ' day'));

    return $deadline_date . ' ' . $hour . ':' . $minute . ':00';
}

/**
 * 回答期限表示用（6/13(金) 18:00 — イベント日時と同形式）
 *
 * @param string $schedule_date Y-m-d
 * @return string
 */
function aidunite_attendance_response_deadline_label($schedule_date) {
    $deadline = aidunite_attendance_policy_get_response_deadline($schedule_date);
    if ($deadline === '') {
        return '';
    }

    $ts = strtotime($deadline);
    if ($ts === false) {
        return '';
    }

    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    return date('n/j', $ts) . '(' . $weekdays[(int) date('w', $ts)] . ') ' . date('H:i', $ts);
}

/**
 * 出欠回答が受付可能か（期限前かつイベント日前）
 *
 * @param string $schedule_date Y-m-d
 * @param string $deadline_at   Y-m-d H:i:s
 * @return bool
 */
function aidunite_attendance_is_response_open($schedule_date, $deadline_at = '') {
    $schedule_date = trim((string) $schedule_date);
    $now = (int) current_time('timestamp');

    if ($deadline_at === '' && $schedule_date !== '') {
        $deadline_at = aidunite_attendance_policy_get_response_deadline($schedule_date);
    }
    if ($deadline_at !== '') {
        $deadline_ts = strtotime($deadline_at);
        if ($deadline_ts !== false && $now > $deadline_ts) {
            return false;
        }
    }

    if ($schedule_date !== '') {
        $event_end_ts = strtotime($schedule_date . ' 23:59:59');
        if ($event_end_ts !== false && $now > $event_end_ts) {
            return false;
        }
    }

    return true;
}

/**
 * 回答不可の理由（deadline|event|''）
 *
 * @param string $schedule_date
 * @param string $deadline_at
 * @return string
 */
function aidunite_attendance_response_closed_reason($schedule_date, $deadline_at = '') {
    $schedule_date = trim((string) $schedule_date);
    $now = (int) current_time('timestamp');

    if ($deadline_at === '' && $schedule_date !== '') {
        $deadline_at = aidunite_attendance_policy_get_response_deadline($schedule_date);
    }
    if ($deadline_at !== '') {
        $deadline_ts = strtotime($deadline_at);
        if ($deadline_ts !== false && $now > $deadline_ts) {
            return 'deadline';
        }
    }

    if ($schedule_date !== '') {
        $event_end_ts = strtotime($schedule_date . ' 23:59:59');
        if ($event_end_ts !== false && $now > $event_end_ts) {
            return 'event';
        }
    }

    return '';
}

/**
 * 回答不可メッセージ
 *
 * @param string $reason deadline|event
 * @return string
 */
function aidunite_attendance_response_closed_message($reason) {
    if ($reason === 'deadline') {
        return '回答期限を過ぎているため、変更できません。';
    }
    if ($reason === 'event') {
        return 'イベント日を過ぎているため、変更できません。';
    }

    return '';
}

/**
 * イベント日時表示（6/15(土) 10:00~12:00）
 *
 * @param string $date
 * @param string $start_time
 * @param string $end_time
 * @return string
 */
function aidunite_attendance_format_event_datetime($date, $start_time = '', $end_time = '') {
    $date = trim((string) $date);
    if ($date === '') {
        return '';
    }

    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    $ts = strtotime($date);
    $label = date('n/j', $ts) . '(' . $weekdays[(int) date('w', $ts)] . ')';

    if ($start_time !== '' && $end_time !== '') {
        $label .= ' ' . $start_time . '~' . $end_time;
    } elseif ($start_time !== '') {
        $label .= ' ' . $start_time . '~';
    }

    return $label;
}

/**
 * スケジュール1件の出欠 UI 用 view model
 *
 * @param array<string,mixed> $schedule
 * @param int                 $team_id
 * @param int                 $respondent_user_id 回答主体（選手）。保護者操作時は紐づく選手 ID
 * @return array<string,mixed>
 */
function aidunite_attendance_build_schedule_view_model(array $schedule, $team_id, $respondent_user_id) {
    $schedule_id = (int) ($schedule['id'] ?? 0);
    $respondent_user_id = (int) $respondent_user_id;
    $attendance_data = function_exists('aidunite_attendance_read_data_map')
        ? aidunite_attendance_read_data_map($schedule_id)
        : [];
    if (!is_array($attendance_data)) {
        $attendance_data = [];
    }

    $user_row = $respondent_user_id > 0 ? ($attendance_data[$respondent_user_id] ?? null) : null;
    $has_row = is_array($user_row);
    $status_raw = $has_row ? ($user_row['status'] ?? '') : '';

    $member_ids = aidunite_attendance_get_target_member_ids_for_team((int) $team_id);
    $summary = aidunite_get_attendance_summary($schedule_id, $member_ids);
    $maybe_count = 0;
    $members = [];

    foreach ($member_ids as $member_id) {
        $member_id = (int) $member_id;
        $row = $attendance_data[$member_id] ?? null;
        $row_has = is_array($row);
        $row_status = $row_has ? ($row['status'] ?? '') : '';
        $ui_key = aidunite_attendance_status_ui_key($row_status, $row_has);

        if ($ui_key === 'maybe') {
            $maybe_count++;
        }

        $user = get_userdata($member_id);
        $avatar_url = function_exists('aidunite_player_read_photo_url')
            ? aidunite_player_read_photo_url($member_id)
            : '';
        $members[] = [
            'user_id' => $member_id,
            'display_name' => $user ? (string) $user->display_name : '',
            'avatar_url' => $avatar_url,
            'is_self' => $member_id === $respondent_user_id,
            'ui_key' => $ui_key,
            'note' => $row_has ? (string) ($row['note'] ?? '') : '',
            'response_date' => $row_has ? (string) ($row['response_date'] ?? '') : '',
        ];
    }

    $answered = (int) ($summary['attending'] ?? 0) + (int) ($summary['not_attending'] ?? 0);
    $total = (int) ($summary['total'] ?? 0);
    $pending = max(0, $total - $answered - $maybe_count);

    return array_merge($schedule, [
        'schedule_id' => $schedule_id,
        'status_raw' => $status_raw,
        'status' => aidunite_attendance_status_form_value($status_raw, $has_row),
        'ui_key' => aidunite_attendance_status_ui_key($status_raw, $has_row),
        'note' => $has_row ? (string) ($user_row['note'] ?? '') : '',
        'response_date' => $has_row ? (string) ($user_row['response_date'] ?? '') : '',
        'deadline_label' => aidunite_attendance_response_deadline_label($schedule['date'] ?? ''),
        'deadline_at' => aidunite_attendance_policy_get_response_deadline($schedule['date'] ?? ''),
        'response_open' => aidunite_attendance_is_response_open(
            $schedule['date'] ?? '',
            aidunite_attendance_policy_get_response_deadline($schedule['date'] ?? '')
        ),
        'response_closed_reason' => aidunite_attendance_response_closed_reason(
            $schedule['date'] ?? '',
            aidunite_attendance_policy_get_response_deadline($schedule['date'] ?? '')
        ),
        'response_closed_message' => aidunite_attendance_response_closed_message(
            aidunite_attendance_response_closed_reason(
                $schedule['date'] ?? '',
                aidunite_attendance_policy_get_response_deadline($schedule['date'] ?? '')
            )
        ),
        'respondent_user_id' => $respondent_user_id,
        'respondent_name' => $respondent_user_id > 0 && ($u = get_userdata($respondent_user_id))
            ? (string) $u->display_name
            : '',
        'datetime_label' => aidunite_attendance_format_event_datetime(
            $schedule['date'] ?? '',
            $schedule['start_time'] ?? '',
            $schedule['end_time'] ?? ''
        ),
        'summary' => [
            'attending' => (int) ($summary['attending'] ?? 0),
            'not_attending' => (int) ($summary['not_attending'] ?? 0),
            'maybe' => $maybe_count,
            'pending' => $pending,
            'total' => $total,
            'answered' => $answered + $maybe_count,
        ],
        'members' => $members,
    ]);
}

/**
 * メンバーアバター（選手写真 or イニシャル）
 *
 * @param array<string, mixed> $member
 */
function aidunite_attendance_render_member_avatar(array $member) {
    $avatar_url = trim((string) ($member['avatar_url'] ?? ''));
    if ($avatar_url !== '') {
        printf(
            '<img src="%s" alt="" class="attendance-card__member-photo" width="36" height="36" loading="lazy" decoding="async">',
            esc_url($avatar_url)
        );
        return;
    }

    $display_name = (string) ($member['display_name'] ?? '?');
    echo esc_html(mb_substr($display_name !== '' ? $display_name : '?', 0, 1));
}
