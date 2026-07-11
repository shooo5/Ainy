<?php
/**
 * 大会・イベント 参加回答リマインド（daily cron）
 *
 * 締切 3 日前・前日に invited エントリのチーム代表へ competition_reminder を送信。
 * 一覧 query は competition-persist-read.php 正本。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $deadline_date Y-m-d
 * @return string[] 3days_before|day_before
 */
function aidunite_competition_reminder_due_windows_for_deadline($deadline_date) {
    $deadline_date = sanitize_text_field((string) $deadline_date);
    if ($deadline_date === '') {
        return [];
    }

    $today = current_time('Y-m-d');
    $windows = [];
    $three_days_before = date('Y-m-d', strtotime($deadline_date . ' -3 days'));
    $day_before = date('Y-m-d', strtotime($deadline_date . ' -1 day'));

    if ($today === $three_days_before) {
        $windows[] = '3days_before';
    }
    if ($today === $day_before) {
        $windows[] = 'day_before';
    }

    return $windows;
}

/**
 * 未回答（invited）エントリの代表者へリマインド送信
 */
function aidunite_competition_send_reminder_notifications() {
    if (!function_exists('aidunite_notification_send')
        || !function_exists('aidunite_competition_read_event_meta_raw')
        || !function_exists('aidunite_competition_read_entry_meta_raw')
        || !function_exists('aidunite_competition_read_event_post_ids')
        || !function_exists('aidunite_competition_read_entry_post_ids_for_event')) {
        return;
    }

    $sent_total = 0;
    $today = current_time('Y-m-d');

    foreach (aidunite_competition_read_event_post_ids(['status' => 'inviting']) as $event_id) {
        $event_raw = aidunite_competition_read_event_meta_raw($event_id);
        $deadline = (string) ($event_raw['application_deadline'] ?? '');
        $windows = aidunite_competition_reminder_due_windows_for_deadline($deadline);
        if ($windows === []) {
            continue;
        }

        $event_title = get_the_title($event_id);
        $date_start = (string) ($event_raw['date_start'] ?? '');
        $date_display = $date_start !== '' && function_exists('aidunite_format_notification_date')
            ? aidunite_format_notification_date($date_start)
            : ($date_start !== '' ? date('Y年n月j日', strtotime($date_start)) : '');

        foreach (aidunite_competition_read_entry_post_ids_for_event($event_id, 'invited') as $entry_id) {
            $entry_raw = aidunite_competition_read_entry_meta_raw($entry_id);
            $team_id = (int) ($entry_raw['team_id'] ?? 0);
            if ($team_id < 1) {
                continue;
            }

            $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
                ? (int) aidunite_team_resolve_leader_user_id($team_id)
                : 0;
            if ($leader_id < 1) {
                continue;
            }

            foreach ($windows as $window) {
                $title = $window === 'day_before'
                    ? '【明日締切】大会・イベント参加回答のお願い'
                    : '【締切3日前】大会・イベント参加回答のお願い';
                $link_url = home_url('/mypage/?competition_entry=' . $entry_id);
                $message = "「{$event_title}」への参加招待への回答期限が近づいています。\n\n";
                if ($date_display !== '') {
                    $message .= "開催日: {$date_display}\n";
                }
                if ($deadline !== '') {
                    $message .= '回答期限: ' . date('Y年n月j日', strtotime($deadline)) . "\n";
                }
                $message .= "\nマイページの「やること」から参加または辞退を選択してください。\n{$link_url}\n";

                $result = aidunite_notification_send($leader_id, 'competition_reminder', [
                    'title' => $title,
                    'message' => $message,
                    'related_id' => $entry_id,
                    'link_url' => $link_url,
                    'event_id' => $event_id,
                    'entry_id' => $entry_id,
                    'team_id' => $team_id,
                    'idempotency_key' => 'competition_reminder:' . $entry_id . ':' . $window . ':' . $today,
                ]);

                if (!empty($result['success'])) {
                    $sent_total++;
                }
            }
        }
    }

    if ($sent_total > 0) {
        error_log('[competition-reminder] sent=' . $sent_total);
    }
}

/**
 * Cron 登録
 */
function aidunite_competition_schedule_reminder_check() {
    if (!wp_next_scheduled('aidunite_daily_competition_reminder_check')) {
        wp_schedule_event(time(), 'daily', 'aidunite_daily_competition_reminder_check');
    }
}
add_action('wp', 'aidunite_competition_schedule_reminder_check');
add_action('aidunite_daily_competition_reminder_check', 'aidunite_competition_send_reminder_notifications');
