<?php
/**
 * 請求リマインド通知機能
 * 翌月1日の数日前に請求リマインド通知を送信
 */

require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';

/**
 * 請求リマインド通知を送信（翌月1日の3日前）
 */
function aidunite_send_billing_reminder_notifications() {
    $today = current_time('Y-m-d');
    $today_timestamp = strtotime($today);

    $next_month_1st = date('Y-m-01', strtotime('+1 month', $today_timestamp));
    $next_month_1st_timestamp = strtotime($next_month_1st);
    $reminder_date = date('Y-m-d', strtotime('-3 days', $next_month_1st_timestamp));

    if ($today !== $reminder_date) {
        return;
    }

    error_log("請求リマインド通知処理開始: 今日={$today}, リマインド日={$reminder_date}, 請求日={$next_month_1st}");

    $targets = function_exists('aidunite_payment_read_system_billing_reminder_targets')
        ? aidunite_payment_read_system_billing_reminder_targets()
        : [];

    $sent_count = 0;
    $error_count = 0;

    foreach ($targets as $target) {
        $team_id = (int) ($target['team_id'] ?? 0);
        $user_id = (int) ($target['leader_user_id'] ?? 0);
        if ($team_id <= 0 || $user_id <= 0 || !get_userdata($user_id)) {
            continue;
        }

        $monthly_fee = aidunite_calculate_monthly_fee($team_id, $user_id);
        if ($monthly_fee <= 0) {
            continue;
        }

        $result = aidunite_send_billing_reminder_email($user_id, $team_id, $next_month_1st, $monthly_fee);
        if ($result) {
            $sent_count++;
        } else {
            $error_count++;
        }
    }

    error_log("請求リマインド通知処理完了: 送信成功={$sent_count}, 送信失敗={$error_count}");
}

/**
 * 請求リマインドメール通知を送信
 */
function aidunite_send_billing_reminder_email($user_id, $team_id, $billing_date, $monthly_fee) {
    $user = get_userdata($user_id);
    if (!$user) {
        error_log("請求リマインドメール送信エラー: ユーザーが見つかりません (User ID: {$user_id})");
        return false;
    }

    $team = get_post($team_id);
    $team_name = $team ? $team->post_title : 'チーム';
    $plan = aidunite_get_plan_info($team_id);
    $plan_name = $plan ? $plan['name'] : 'Matchプラン';

    $billing_date_formatted = function_exists('aidunite_payment_format_date_display')
        ? aidunite_payment_format_date_display($billing_date)
        : date('Y年m月d日', strtotime($billing_date));

    $subject = '【Aniy】請求日のご案内';
    $message = "{$user->display_name} 様\n\n";
    $message .= "いつもAniyをご利用いただき、誠にありがとうございます。\n\n";
    $message .= "【請求日のご案内】\n";
    $message .= "次回の請求日: {$billing_date_formatted}\n";
    $message .= "請求金額: ¥" . number_format($monthly_fee) . "\n";
    $message .= "プラン: {$plan_name}\n";
    $message .= "チーム: {$team_name}\n\n";
    $message .= "※ 後払い方式のため、{$billing_date_formatted}に当月分の利用料金が請求されます。\n";
    $message .= "※ クレジットカードの有効期限や残高をご確認ください。\n\n";
    $message .= "ご不明な点がございましたら、お気軽にお問い合わせください。\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "Aniy 運営チーム\n";
    $message .= get_bloginfo('url') . "\n";

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    ];

    $result = wp_mail($user->user_email, $subject, $message, $headers);

    if ($result) {
        error_log("請求リマインドメール送信成功 (User ID: {$user_id}, Email: {$user->user_email}, 請求日: {$billing_date})");
    } else {
        error_log("請求リマインドメール送信失敗 (User ID: {$user_id}, Email: {$user->user_email}, 請求日: {$billing_date})");
    }

    return $result;
}

/**
 * WordPress Cronに請求リマインドチェックを登録
 */
function aidunite_schedule_billing_reminder_check() {
    if (!wp_next_scheduled('aidunite_daily_billing_reminder_check')) {
        wp_schedule_event(time(), 'daily', 'aidunite_daily_billing_reminder_check');
        error_log('請求リマインドチェックのスケジュールを登録しました');
    }
}
add_action('wp', 'aidunite_schedule_billing_reminder_check');

add_action('aidunite_daily_billing_reminder_check', 'aidunite_send_billing_reminder_notifications');
