<?php
/**
 * 出欠管理通知機能
 *
 * 出欠確認に関する通知を送信します。
 *
 * @version 1.0.0
 * @created 2026-01-10
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/functions/notify/notify.php';
require_once get_stylesheet_directory() . '/functions/team/team-functions.php';

/**
 * 出欠確認通知を送信
 *
 * @param int $schedule_id スケジュールID
 * @param array $notification_settings 通知設定
 * @return bool 通知送信成功時true
 */
if (!function_exists('aidunite_notify_attendance_request')) {
    function aidunite_notify_attendance_request($schedule_id, $notification_settings = []) {
    // 出欠確認が必要かチェック
    $attendance_required = get_post_meta($schedule_id, 'attendance_required', true);
    if ($attendance_required !== '1') {
        return false;
    }

    // スケジュール情報を取得
    $schedule = get_post($schedule_id);
    if (!$schedule) {
        return false;
    }

    $schedule_date = get_post_meta($schedule_id, 'schedule_date', true);
    $schedule_start_time = get_post_meta($schedule_id, 'schedule_start_time', true);
    $schedule_end_time = get_post_meta($schedule_id, 'schedule_end_time', true);
    $schedule_place = get_post_meta($schedule_id, 'schedule_place', true);
    $schedule_type = get_post_meta($schedule_id, 'schedule_type', true);
    $team_id = get_post_meta($schedule_id, 'team_id', true);

    // スケジュール名を取得
    $schedule_name = $schedule->post_title ?: ($schedule_type ?: 'スケジュール');

    // チーム情報を取得
    $team_name = '';
    if ($team_id) {
        $team = get_post($team_id);
        if ($team) {
            $team_name = $team->post_title;
        }
    }

    // 通知対象ユーザーを取得
    $target_users = aidunite_get_attendance_notification_targets($team_id);

    if (empty($target_users)) {
        return false;
    }

    // 通知メッセージを生成（日付は統一定義 yy/mm/dd（曜）、時間は含めない）
    $date_display = function_exists('aidunite_format_notification_date')
        ? aidunite_format_notification_date($schedule_date)
        : ($schedule_date ? date('Y年m月d日', strtotime($schedule_date)) : '');
    if ($date_display === '') {
        $date_display = '日付未設定';
    }

    $place_display = $schedule_place ?: '会場未設定';
    $report_url = home_url('/attendance-report');

    $title = "📅 出欠確認のご案内";
    $message = "出欠確認のご案内です。\n\n";
    $message .= "【{$schedule_name}】\n";
    $message .= "📅 日付: {$date_display}\n";
    $message .= "📍 会場: {$place_display}\n";
    if ($team_name) {
        $message .= "👥 チーム: {$team_name}\n";
    }
    $message .= "\n";
    $message .= "以下のURLから出欠連絡をお願いします。\n";
    $message .= "{$report_url}\n";

    // 各ユーザーに通知を送信
    $success_count = 0;
    foreach ($target_users as $user_id) {
        $result = aidunite_notify_attendance_to_user($user_id, $schedule_id, $title, $message);
        if ($result) {
            $success_count++;
        }
    }

    return $success_count > 0;
    }
}

/**
 * 個別ユーザーへの出席確認通知
 *
 * @param int $user_id ユーザーID
 * @param int $schedule_id スケジュールID
 * @param string $title 通知タイトル
 * @param string $message 通知メッセージ
 * @return bool 通知送信成功時true
 */
if (!function_exists('aidunite_notify_attendance_to_user')) {
    function aidunite_notify_attendance_to_user($user_id, $schedule_id, $title, $message) {
    if (!$user_id || !$schedule_id) {
        return false;
    }

    // 通知を送信
    aidunite_notify_user($user_id, $title, $message, 'attendance', $schedule_id);

    return true;
    }
}

/**
 * 出欠確認通知の対象ユーザーを取得
 *
 * @param int $team_id チームID
 * @return array ユーザーID配列
 */
function aidunite_get_attendance_notification_targets($team_id) {
    if (!$team_id) {
        return [];
    }

    $target_users = [];

    // チームに所属する保護者と選手を取得
    $team_members = aidunite_get_team_members($team_id);

    if (empty($team_members)) {
        // フォールバック: ユーザーメタから取得
        global $wpdb;
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'team_id' AND meta_value = %d",
            $team_id
        ));

        foreach ($user_ids as $user_id) {
            $user_type = aidunite_get_user_type($user_id);
            // 保護者と選手のみを対象
            if (in_array($user_type, ['parent', 'player'])) {
                $target_users[] = $user_id;
            }
        }
    } else {
        foreach ($team_members as $member) {
            // $member は WP_User オブジェクト
            $user_id = is_object($member) ? $member->ID : ($member['user_id'] ?? null);
            if (!$user_id) {
                continue;
            }

            $user_type = aidunite_get_user_type($user_id);
            // 保護者と選手のみを対象
            if (in_array($user_type, ['parent', 'player'])) {
                $target_users[] = $user_id;
            }
        }
    }

    // 重複を削除
    $target_users = array_unique($target_users);

    return $target_users;
}
