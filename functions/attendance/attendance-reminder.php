<?php
/**
 * 出欠リマインド（daily cron）
 *
 * 回答期限（スケジュール前日 18:00 — aidunite_attendance_policy_*）当日に未回答者へ attendance_reminder を送信。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/functions/attendance/attendance-notification.php';

/**
 * リマインド対象スケジュール（期限日 = 今日）
 *
 * @return WP_Post[]
 */
function aidunite_attendance_reminder_get_due_schedules() {
    $today = current_time('Y-m-d');
    $schedule_date = date('Y-m-d', strtotime($today . ' +1 day'));

    if (!function_exists('aidunite_attendance_read_schedules')) {
        return [];
    }

    return aidunite_attendance_read_schedules([
        'date_eq' => $schedule_date,
        'posts_per_page' => 100,
    ]);
}

/**
 * 未回答ユーザーへリマインド送信
 */
function aidunite_send_attendance_reminder_notifications() {
    $schedules = aidunite_attendance_reminder_get_due_schedules();
    if ($schedules === []) {
        return;
    }

    $sent_total = 0;

    foreach ($schedules as $schedule) {
        $schedule_id = (int) $schedule->ID;
        $team_id = function_exists('aidunite_schedule_read_team_id')
            ? (int) aidunite_schedule_read_team_id($schedule_id)
            : 0;

        $recipients = function_exists('aidunite_attendance_get_notification_recipients_for_team')
            ? aidunite_attendance_get_notification_recipients_for_team($team_id)
            : [];
        if ($recipients === []) {
            continue;
        }

        $attendance_data = function_exists('aidunite_attendance_read_data_map')
            ? aidunite_attendance_read_data_map($schedule_id)
            : [];
        if (!is_array($attendance_data)) {
            $attendance_data = [];
        }

        $sch_disp = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($schedule_id)
            : [];
        $schedule_date = (string) ($sch_disp['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($schedule_id)
            : ''));
        $schedule_name = $schedule->post_title ?: ((string) ($sch_disp['schedule_type'] ?? 'スケジュール'));
        $deadline = function_exists('aidunite_attendance_policy_get_response_deadline')
            ? aidunite_attendance_policy_get_response_deadline($schedule_date)
            : ($schedule_date ? date('Y-m-d 18:00:00', strtotime($schedule_date . ' -1 day')) : '');
        $deadline_display = $deadline !== '' && function_exists('aidunite_attendance_response_deadline_label')
            ? aidunite_attendance_response_deadline_label($schedule_date)
            : ($deadline !== '' ? date('n/j H:i', strtotime($deadline)) : '');

        $date_display = function_exists('aidunite_format_notification_date')
            ? aidunite_format_notification_date($schedule_date)
            : ($schedule_date ? date('Y年m月d日', strtotime($schedule_date)) : '日付未設定');

        $title = '【リマインド】出欠確認のご案内';
        $report_url = aidunite_attendance_report_url($schedule_id);

        foreach ($recipients as $recipient) {
            $notify_user_id = (int) ($recipient['notify_user_id'] ?? 0);
            $player_id = (int) ($recipient['player_id'] ?? 0);
            $player_name = trim((string) ($recipient['player_name'] ?? ''));
            if ($notify_user_id <= 0 || $player_id <= 0) {
                continue;
            }

            $status = $attendance_data[$player_id]['status'] ?? '';
            if (function_exists('aidunite_attendance_status_is_unanswered')) {
                if (!aidunite_attendance_status_is_unanswered($status)) {
                    continue;
                }
            } elseif ($status !== '' && $status !== 'pending') {
                continue;
            }

            $message = "出欠確認の回答期限が近づいています。\n\n";
            if (!empty($recipient['via_parent']) && $player_name !== '') {
                $message .= "お子様: {$player_name}\n";
            }
            $message .= "【{$schedule_name}】\n";
            $message .= "日付: {$date_display}\n";
            if ($deadline_display !== '') {
                $message .= "回答期限: {$deadline_display}\n";
            }
            $message .= "\n以下のURLから出欠連絡をお願いします。\n{$report_url}\n";

            $payload = [
                'title' => $title,
                'message' => $message,
                'related_id' => $schedule_id,
                'link_url' => $report_url,
                'idempotency_key' => 'attendance_reminder:' . $schedule_id . ':' . $player_id . ':' . current_time('Y-m-d'),
            ];

            if (!function_exists('aidunite_notification_send')) {
                continue;
            }

            $result = aidunite_notification_send($notify_user_id, 'attendance_reminder', $payload);
            if (!empty($result['success'])) {
                $sent_total++;
                if (function_exists('aidunite_attendance_send_push_if_available')) {
                    aidunite_attendance_send_push_if_available($notify_user_id, $title, $message, $report_url);
                }
            }
        }
    }

    if ($sent_total > 0) {
        error_log("[attendance-reminder] sent={$sent_total} schedules=" . count($schedules));
    }
}

/**
 * Cron 登録
 */
function aidunite_schedule_attendance_reminder_check() {
    if (!wp_next_scheduled('aidunite_daily_attendance_reminder_check')) {
        wp_schedule_event(time(), 'daily', 'aidunite_daily_attendance_reminder_check');
    }
}
add_action('wp', 'aidunite_schedule_attendance_reminder_check');
add_action('aidunite_daily_attendance_reminder_check', 'aidunite_send_attendance_reminder_notifications');
