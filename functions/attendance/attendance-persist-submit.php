<?php
/**
 * 出欠回答 POST / REST 保存オーケストレーション
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 出欠状況を保存（ロック機構付き）
 *
 * @param int    $schedule_id
 * @param int    $user_id
 * @param string $status
 * @param string $note
 * @return bool
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

        if (!function_exists('aidunite_attendance_read_is_required')
            || !aidunite_attendance_read_is_required($schedule_id)) {
            return false;
        }

        if (!function_exists('aidunite_acquire_attendance_lock')
            || !aidunite_acquire_attendance_lock($schedule_id)) {
            return false;
        }

        try {
            $user_type = aidunite_get_user_type($user_id);
            if (empty($user_type) || $user_type === 'general') {
                if (user_can($user_id, 'edit_posts')) {
                    $user_type = 'team_leader';
                } else {
                    $user_type = 'parent';
                }
            }

            $row = [
                'status' => $status_for_storage,
                'note' => $note,
                'user_type' => $user_type,
                'response_date' => current_time('mysql'),
                'response_time' => current_time('mysql', true),
            ];

            if (!function_exists('aidunite_attendance_write_user_row')) {
                return false;
            }

            $saved = aidunite_attendance_write_user_row($schedule_id, $user_id, $row);

            if ($saved && function_exists('aidunite_attendance_resolve_notification_user_ids_for_player')) {
                foreach (aidunite_attendance_resolve_notification_user_ids_for_player($user_id) as $notify_user_id) {
                    if (function_exists('aidunite_notify_attendance_registered')) {
                        aidunite_notify_attendance_registered((int) $notify_user_id, $schedule_id, $user_id);
                    }
                }
            } elseif ($saved && function_exists('aidunite_notify_attendance_registered')) {
                aidunite_notify_attendance_registered($user_id, $schedule_id, $user_id);
            }

            return $saved;
        } finally {
            if (function_exists('aidunite_release_attendance_lock')) {
                aidunite_release_attendance_lock($schedule_id);
            }
        }
    }
}
