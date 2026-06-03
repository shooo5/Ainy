<?php
/**
 * 出欠管理関数
 *
 * スケジュールごとの出欠確認機能を提供します。
 *
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
 * 出欠状況を保存（ロック機構付き）
 *
 * @param int $schedule_id スケジュールID
 * @param int $user_id ユーザーID
 * @param string $status 出席状況 ('attending' | 'not_attending')
 * @param string $note 備考（任意）
 * @return bool 保存成功時true
 */
if (!function_exists('aidunite_save_attendance')) {
    function aidunite_save_attendance($schedule_id, $user_id, $status, $note = '') {
        $status_for_storage = function_exists('aidunite_normalize_attendance_status_value')
            ? aidunite_normalize_attendance_status_value($status)
            : strtolower(trim((string) $status));

        $allowed_canonical = ['present', 'absent', 'late', 'leave_early', 'no_response'];
        if (!$schedule_id || !$user_id || !in_array($status_for_storage, $allowed_canonical, true)) {
            return false;
        }

        $attendance_required = get_post_meta($schedule_id, 'attendance_required', true);
        if ($attendance_required !== '1') {
            return false;
        }

        if (!aidunite_acquire_attendance_lock($schedule_id)) {
            return false;
        }

        try {
            $attendance_data = get_post_meta($schedule_id, 'attendance_data', true);
            if (!is_array($attendance_data)) {
                $attendance_data = [];
            }

            $user_type = aidunite_get_user_type($user_id);
            if (empty($user_type) || $user_type === 'general') {
                if (user_can($user_id, 'edit_posts')) {
                    $user_type = 'team_leader';
                } else {
                    $user_type = 'parent';
                }
            }

            $attendance_data[$user_id] = [
                'status' => $status_for_storage,
                'note' => sanitize_textarea_field($note),
                'user_type' => $user_type,
                'response_date' => current_time('mysql'),
                'response_time' => current_time('mysql', true),
            ];

            $result = update_post_meta($schedule_id, 'attendance_data', $attendance_data);
            if ($result !== false) {
                $saved_data = get_post_meta($schedule_id, 'attendance_data', true);
                if (is_array($saved_data) && isset($saved_data[$user_id]) && $saved_data[$user_id]['status'] === $status_for_storage) {
                    return true;
                }
            }

            return false;
        } finally {
            aidunite_release_attendance_lock($schedule_id);
        }
    }
}

/**
 * スケジュールの出欠状況を取得
 *
 * @param int $schedule_id スケジュールID
 * @return array 出席状況配列
 */
if (!function_exists('aidunite_get_schedule_attendance')) {
    function aidunite_get_schedule_attendance($schedule_id) {
        $attendance_data = get_post_meta($schedule_id, 'attendance_data', true);

        if (!$attendance_data || !is_array($attendance_data)) {
            return [];
        }

        // 出席状況を整形
        $formatted_attendance = [];
        foreach ($attendance_data as $user_id => $data) {
            $user = get_userdata($user_id);
            if ($user) {
                $formatted_attendance[] = [
                    'user_id' => $user_id,
                    'user_name' => $user->display_name,
                    'user_type' => $data['user_type'] ?? 'unknown',
                    'status' => $data['status'] ?? 'pending',
                    'note' => $data['note'] ?? '',
                    'response_date' => $data['response_date'] ?? '',
                    'response_time' => $data['response_time'] ?? ''
                ];
            }
        }

        return $formatted_attendance;
    }
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
    $args = [
        'post_type' => 'schedule',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'attendance_required',
                'value' => '1',
                'compare' => '='
            ]
        ],
        'meta_key' => 'schedule_date',
        'orderby' => 'meta_value',
        'order' => 'ASC'
    ];

    // チームIDでフィルタリング
    if ($team_id) {
        $args['meta_query'][] = [
            'key' => 'team_id',
            'value' => $team_id,
            'compare' => '='
        ];
    }

    // 日付範囲でフィルタリング
    if ($date_from) {
        $args['meta_query'][] = [
            'key' => 'schedule_date',
            'value' => $date_from,
            'compare' => '>=',
            'type' => 'DATE'
        ];
    } else {
        // デフォルトでは今日以降のスケジュールのみ取得
        $args['meta_query'][] = [
            'key' => 'schedule_date',
            'value' => date('Y-m-d'),
            'compare' => '>=',
            'type' => 'DATE'
        ];
    }

    if ($date_to) {
        $args['meta_query'][] = [
            'key' => 'schedule_date',
            'value' => $date_to,
            'compare' => '<=',
            'type' => 'DATE'
        ];
    }

    $schedules = get_posts($args);

    $result = [];
    foreach ($schedules as $schedule) {
        $result[] = [
            'id' => $schedule->ID,
            'title' => $schedule->post_title,
            'date' => get_post_meta($schedule->ID, 'schedule_date', true),
            'start_time' => get_post_meta($schedule->ID, 'schedule_start_time', true),
            'end_time' => get_post_meta($schedule->ID, 'schedule_end_time', true),
            'place' => get_post_meta($schedule->ID, 'schedule_place', true),
            'type' => get_post_meta($schedule->ID, 'schedule_type', true),
            'team_id' => get_post_meta($schedule->ID, 'team_id', true)
        ];
    }

    return $result;
}

/**
 * 出欠状況の集計
 *
 * @param int $schedule_id スケジュールID
 * @param array $team_member_ids チームメンバーID配列（オプション）
 * @return array 集計結果
 */
function aidunite_get_attendance_summary($schedule_id, $team_member_ids = []) {
    $attendance_data = get_post_meta($schedule_id, 'attendance_data', true);

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
            $status = $attendance_data[$user_id]['status'] ?? 'pending';

            if ($status === 'attending') {
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
            $status = $data['status'] ?? 'pending';

            if ($status === 'attending') {
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
