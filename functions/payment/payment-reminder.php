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
    // 今日の日付
    $today = current_time('Y-m-d');
    $today_timestamp = strtotime($today);

    // 翌月1日を計算
    $next_month_1st = date('Y-m-01', strtotime('+1 month', $today_timestamp));
    $next_month_1st_timestamp = strtotime($next_month_1st);

    // リマインド送信日（翌月1日の3日前）
    $reminder_date = date('Y-m-d', strtotime('-3 days', $next_month_1st_timestamp));

    // 今日がリマインド送信日でない場合は処理しない
    if ($today !== $reminder_date) {
        return;
    }

    error_log("請求リマインド通知処理開始: 今日={$today}, リマインド日={$reminder_date}, 請求日={$next_month_1st}");

    // Stripe決済登録済みのユーザーを取得
    global $wpdb;
    $users_with_stripe = $wpdb->get_results(
        "SELECT DISTINCT user_id
         FROM {$wpdb->usermeta}
         WHERE meta_key = 'stripe_subscription_id'
         AND meta_value != ''"
    );

    $sent_count = 0;
    $error_count = 0;

    foreach ($users_with_stripe as $row) {
        $user_id = (int) $row->user_id;
        $user = get_userdata($user_id);

        if (!$user) {
            continue;
        }

        // 支払いステータスを確認（paidまたはtrial中のみ通知）
        $payment_status = aidunite_get_payment_status($user_id);
        if ($payment_status !== 'paid' && $payment_status !== 'trial') {
            continue;
        }

        // チームID（操作中 team を優先）
        $team_id = function_exists('aidunite_get_current_team_id')
            ? (int) aidunite_get_current_team_id($user_id)
            : (int) get_user_meta($user_id, 'team_id', true);
        if ($team_id <= 0) {
            continue;
        }

        // 月額料金を取得
        $monthly_fee = aidunite_calculate_monthly_fee($team_id);
        if ($monthly_fee <= 0) {
            continue;
        }

        // リマインド通知を送信
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
    $plan_name = $plan ? $plan['name'] : 'スタンダードプラン';

    $billing_date_formatted = date('Y年m月d日', strtotime($billing_date));

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
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
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
    // 既にスケジュールされている場合はスキップ
    if (!wp_next_scheduled('aidunite_daily_billing_reminder_check')) {
        // 毎日午前9時にチェック
        wp_schedule_event(time(), 'daily', 'aidunite_daily_billing_reminder_check');
        error_log('請求リマインドチェックのスケジュールを登録しました');
    }
}
add_action('wp', 'aidunite_schedule_billing_reminder_check');

/**
 * 毎日の請求リマインドチェックを実行
 */
add_action('aidunite_daily_billing_reminder_check', 'aidunite_send_billing_reminder_notifications');
