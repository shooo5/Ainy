<?php
/**
 * 出欠管理通知機能
 *
 * 出欠確認に関する通知を送信します。
 *
 * @version 1.1.0
 * @created 2026-01-10
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/functions/notify/notify.php';
require_once get_stylesheet_directory() . '/functions/team/team-functions.php';

/**
 * 出欠連絡ページ URL
 *
 * @param int $schedule_id
 * @return string
 */
function aidunite_attendance_report_url($schedule_id = 0) {
    $schedule_id = (int) $schedule_id;
    $base = home_url('/attendance-report');
    if ($schedule_id <= 0) {
        return $base;
    }

    return add_query_arg('schedule_id', $schedule_id, $base);
}

/**
 * attendance_required が 0→1 になったときのみ出欠依頼通知を送る
 *
 * @param int         $schedule_id
 * @param string|null $previous_required 更新前の attendance_required（'0'|'1'）。新規は '0' を渡す
 * @return bool
 */
function aidunite_attendance_maybe_notify_request($schedule_id, $previous_required = '0') {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return false;
    }

    if (!function_exists('aidunite_attendance_read_is_required')
        || !aidunite_attendance_read_is_required($schedule_id)) {
        return false;
    }

    $prev = ($previous_required === '1') ? '1' : '0';
    if ($prev === '1') {
        return false;
    }

    return aidunite_notify_attendance_request($schedule_id);
}

/**
 * 出欠確認通知を送信
 *
 * @param int   $schedule_id
 * @param array $notification_settings
 * @return bool 1件以上送信成功時 true
 */
if (!function_exists('aidunite_notify_attendance_request')) {
    function aidunite_notify_attendance_request($schedule_id, $notification_settings = []) {
        $schedule_id = (int) $schedule_id;
        if (!function_exists('aidunite_attendance_read_is_required')
            || !aidunite_attendance_read_is_required($schedule_id)) {
            error_log("[attendance-notify] skip schedule_id={$schedule_id} reason=attendance_not_required");
            return false;
        }

        $schedule = get_post($schedule_id);
        if (!$schedule) {
            error_log("[attendance-notify] skip schedule_id={$schedule_id} reason=schedule_not_found");
            return false;
        }

        $sch_disp = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($schedule_id)
            : [];
        $schedule_date = (string) ($sch_disp['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($schedule_id)
            : ''));
        $schedule_place = (string) ($sch_disp['place'] ?? (function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($schedule_id)
            : ''));
        $schedule_type = (string) ($sch_disp['schedule_type'] ?? '');
        $team_id = (int) ($sch_disp['team_id'] ?? (function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($schedule_id)
            : 0));

        $schedule_name = $schedule->post_title ?: ($schedule_type ?: 'スケジュール');

        $team_name = '';
        if ($team_id) {
            $team = get_post($team_id);
            if ($team) {
                $team_name = $team->post_title;
            }
        }

        $recipients = aidunite_attendance_get_notification_recipients_for_team($team_id);
        if ($recipients === []) {
            error_log("[attendance-notify] skip schedule_id={$schedule_id} team_id={$team_id} reason=no_targets");
            return false;
        }

        $date_display = function_exists('aidunite_format_notification_date')
            ? aidunite_format_notification_date($schedule_date)
            : ($schedule_date ? date('Y年m月d日', strtotime($schedule_date)) : '');
        if ($date_display === '') {
            $date_display = '日付未設定';
        }

        $place_display = $schedule_place ?: '会場未設定';
        $report_url = aidunite_attendance_report_url($schedule_id);

        $title = '出欠確認のご案内';
        $message = "出欠確認のご案内です。\n\n";
        $message .= "【{$schedule_name}】\n";
        $message .= "日付: {$date_display}\n";
        $message .= "会場: {$place_display}\n";
        if ($team_name) {
            $message .= "チーム: {$team_name}\n";
        }
        $message .= "\n";
        $message .= "以下のURLから出欠連絡をお願いします。\n";
        $message .= "{$report_url}\n";

        $success_count = 0;
        foreach ($recipients as $recipient) {
            $notify_user_id = (int) ($recipient['notify_user_id'] ?? 0);
            $player_id = (int) ($recipient['player_id'] ?? 0);
            $player_name = trim((string) ($recipient['player_name'] ?? ''));
            if ($notify_user_id <= 0 || $player_id <= 0) {
                continue;
            }

            $personal_message = $message;
            if (!empty($recipient['via_parent']) && $player_name !== '') {
                $personal_message = "お子様: {$player_name}\n\n" . $message;
            }

            if (aidunite_notify_attendance_to_user($notify_user_id, $schedule_id, $title, $personal_message, $player_id)) {
                $success_count++;
                aidunite_attendance_send_push_if_available($notify_user_id, $title, $personal_message, $report_url);
            }
        }

        error_log("[attendance-notify] schedule_id={$schedule_id} team_id={$team_id} targets=" . count($recipients) . " success={$success_count}");

        return $success_count > 0;
    }
}

/**
 * 個別ユーザーへの出席確認通知
 *
 * @param int    $user_id     通知宛先（保護者または選手）
 * @param int    $schedule_id
 * @param string $title
 * @param string $message
 * @param int    $player_id   出欠対象選手 ID（idempotency 用）
 * @return bool
 */
if (!function_exists('aidunite_notify_attendance_to_user')) {
    function aidunite_notify_attendance_to_user($user_id, $schedule_id, $title, $message, $player_id = 0) {
        $user_id = (int) $user_id;
        $schedule_id = (int) $schedule_id;
        $player_id = (int) ($player_id > 0 ? $player_id : $user_id);
        if ($user_id <= 0 || $schedule_id <= 0) {
            return false;
        }

        $payload = [
            'title' => $title,
            'message' => $message,
            'related_id' => $schedule_id,
            'link_url' => aidunite_attendance_report_url($schedule_id),
            'idempotency_key' => 'attendance_request:' . $schedule_id . ':' . $player_id,
        ];

        if (!function_exists('aidunite_notification_send')) {
            return aidunite_notify_user($user_id, $title, $message, 'attendance', $schedule_id);
        }

        $result = aidunite_notification_send($user_id, 'attendance', $payload);

        return !empty($result['success']);
    }
}

/**
 * 中学以下の選手は保護者が出欠対応（ログイン主体が保護者）
 *
 * @param int $player_id
 * @return bool
 */
function aidunite_attendance_player_uses_parent_proxy($player_id) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return false;
    }

    return function_exists('aidunite_user_is_junior_high_grade_or_below')
        && aidunite_user_is_junior_high_grade_or_below($player_id);
}

/**
 * 出欠通知の宛先ユーザー ID（中学以下→保護者、それ以外→選手本人）
 *
 * @param int $player_id
 * @return int[]
 */
function aidunite_attendance_resolve_notification_user_ids_for_player($player_id) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return [];
    }

    if (aidunite_attendance_player_uses_parent_proxy($player_id)) {
        $parent_id = function_exists('aidunite_get_player_parent')
            ? (int) aidunite_get_player_parent($player_id)
            : 0;
        if ($parent_id > 0) {
            return [$parent_id];
        }

        error_log("[attendance-notify] skip player_id={$player_id} reason=parent_not_linked");
        return [];
    }

    return [$player_id];
}

/**
 * チームの出欠通知受信者一覧（選手ごとに保護者/本人を解決）
 *
 * @param int $team_id
 * @return array<int, array{notify_user_id:int, player_id:int, player_name:string, via_parent:bool}>
 */
function aidunite_attendance_get_notification_recipients_for_team($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || !function_exists('aidunite_attendance_get_target_member_ids_for_team')) {
        return [];
    }

    $recipients = [];
    $seen = [];

    foreach (aidunite_attendance_get_target_member_ids_for_team($team_id) as $player_id) {
        $player_id = (int) $player_id;
        if ($player_id <= 0) {
            continue;
        }

        $player_name = '';
        $player_user = get_userdata($player_id);
        if ($player_user) {
            $player_name = (string) $player_user->display_name;
        }

        foreach (aidunite_attendance_resolve_notification_user_ids_for_player($player_id) as $notify_user_id) {
            $notify_user_id = (int) $notify_user_id;
            if ($notify_user_id <= 0) {
                continue;
            }

            $key = $notify_user_id . ':' . $player_id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $recipients[] = [
                'notify_user_id' => $notify_user_id,
                'player_id' => $player_id,
                'player_name' => $player_name,
                'via_parent' => $notify_user_id !== $player_id,
            ];
        }
    }

    return $recipients;
}

/**
 * 出欠確認通知の宛先ユーザー ID 一覧（重複排除）
 *
 * @param int $team_id
 * @return int[]
 */
function aidunite_get_attendance_notification_targets($team_id) {
    $notify_ids = [];
    foreach (aidunite_attendance_get_notification_recipients_for_team($team_id) as $recipient) {
        $notify_user_id = (int) ($recipient['notify_user_id'] ?? 0);
        if ($notify_user_id > 0) {
            $notify_ids[] = $notify_user_id;
        }
    }

    return array_values(array_unique($notify_ids));
}

/**
 * Web Push（購読がある場合のみ。core send とは別経路）
 *
 * @param int    $user_id
 * @param string $title
 * @param string $message
 * @param string $link_url
 * @return bool
 */
function aidunite_attendance_send_push_if_available($user_id, $title, $message, $link_url = '') {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $push_path = get_stylesheet_directory() . '/functions/notify/web-push-api.php';
    if (is_readable($push_path)) {
        require_once $push_path;
    }
    if (!class_exists('AidUniteWebPushAPI')) {
        return false;
    }

    return (bool) AidUniteWebPushAPI::send_push_notification($user_id, $title, $message, [
        'url' => $link_url !== '' ? $link_url : home_url('/attendance-report'),
        'tag' => 'attendance',
    ]);
}

/**
 * 出欠回答完了通知（保護者または選手本人へ）
 *
 * @param int $notify_user_id 通知宛先
 * @param int $schedule_id
 * @param int $player_id      出欠対象選手 ID
 * @return bool
 */
function aidunite_notify_attendance_registered($notify_user_id, $schedule_id, $player_id = 0) {
    $notify_user_id = (int) $notify_user_id;
    $schedule_id = (int) $schedule_id;
    $player_id = (int) ($player_id > 0 ? $player_id : $notify_user_id);
    if ($notify_user_id <= 0 || $schedule_id <= 0) {
        return false;
    }

    $schedule = get_post($schedule_id);
    if (!$schedule) {
        return false;
    }

    $sch_disp = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle($schedule_id)
        : [];
    $event_name = $schedule->post_title ?: ((string) ($sch_disp['schedule_type'] ?? 'スケジュール'));
    $event_date = (string) ($sch_disp['date'] ?? '');
    $date_display = function_exists('aidunite_format_notification_date')
        ? aidunite_format_notification_date($event_date)
        : ($event_date ? date('Y年m月d日', strtotime($event_date)) : '日付未設定');

    $player_name = '';
    if ($player_id > 0) {
        $player_user = get_userdata($player_id);
        if ($player_user) {
            $player_name = (string) $player_user->display_name;
        }
    }

    $title = '出欠登録が完了しました';
    $message = "出欠の登録が完了しました。\n\n";
    if ($player_id !== $notify_user_id && $player_name !== '') {
        $message .= "お子様: {$player_name}\n";
    }
    $message .= "対象: {$event_name}\n";
    $message .= "日時: {$date_display}\n";

    $link_url = aidunite_attendance_report_url($schedule_id);
    $payload = [
        'title' => $title,
        'message' => $message,
        'related_id' => $schedule_id,
        'link_url' => $link_url,
        'idempotency_key' => 'attendance_registered:' . $schedule_id . ':' . $player_id,
    ];

    if (!function_exists('aidunite_notification_send')) {
        return false;
    }

    $result = aidunite_notification_send($notify_user_id, 'attendance_registered', $payload);
    if (!empty($result['success'])) {
        aidunite_attendance_send_push_if_available($notify_user_id, $title, $message, $link_url);
    }

    return !empty($result['success']);
}
