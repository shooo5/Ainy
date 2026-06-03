<?php
/**
 * Stripe Webhook処理
 * Webhookイベントの処理
 */

require_once get_template_directory() . '/functions/payment/stripe-core.php';
require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';
require_once get_template_directory() . '/functions/payment/payment-reminder.php';

// use文は使用せず、完全修飾名で呼び出す（クラスが存在しない場合のエラーを防ぐため）

/**
 * Webhookエンドポイント
 */
add_action('wp_ajax_stripe_webhook', 'aidunite_handle_stripe_webhook');
add_action('wp_ajax_nopriv_stripe_webhook', 'aidunite_handle_stripe_webhook');

function aidunite_handle_stripe_webhook() {
    $keys = aidunite_get_stripe_keys();
    $webhook_secret = $keys['webhook_secret'];

    // リクエストボディを取得
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    if (empty($sig_header)) {
        http_response_code(400);
        exit('Missing signature');
    }

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
    } catch (\UnexpectedValueException $e) {
        http_response_code(400);
        exit('Invalid payload');
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        http_response_code(400);
        exit('Invalid signature');
    }

    // イベントタイプに応じて処理
    $event_type  = $event->type;
    $event_data  = $event->data->object;
    $account_id  = property_exists($event, 'account') ? $event->account : null;

    error_log('Stripe Webhook受信: ' . $event_type);

    switch ($event_type) {
        case 'checkout.session.completed':
            aidunite_handle_checkout_completed($event_data);
            break;

        case 'invoice.payment_succeeded':
            // 月謝（チーム月謝）の場合は専用処理に振り分け
            if (isset($event_data->metadata->billing_type) && $event_data->metadata->billing_type === 'team_tuition') {
                aidunite_handle_tuition_invoice_succeeded($event_data, $account_id);
            } else {
                aidunite_handle_invoice_payment_succeeded($event_data);
            }
            break;

        case 'invoice.payment_failed':
            aidunite_handle_invoice_payment_failed($event_data);
            break;

        case 'customer.subscription.deleted':
            // 月謝（チーム月謝）の場合は専用処理に振り分け
            if (isset($event_data->metadata->billing_type) && $event_data->metadata->billing_type === 'team_tuition') {
                aidunite_handle_tuition_subscription_deleted($event_data, $account_id);
            } else {
                aidunite_handle_subscription_deleted($event_data);
            }
            break;

        case 'customer.subscription.updated':
            aidunite_handle_subscription_updated($event_data);
            break;

        default:
            error_log('未処理のイベント: ' . $event_type);
    }

    http_response_code(200);
    exit('OK');
}

/**
 * checkout.session.completed イベント処理
 */
function aidunite_handle_checkout_completed($session) {
    $user_id = $session->metadata->user_id ?? null;
    $team_id = $session->metadata->team_id ?? null;

    if (empty($user_id) || empty($team_id)) {
        error_log('Webhook: user_idまたはteam_idが見つかりません');
        return;
    }

    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if (function_exists('aidunite_user_has_managed_team_access') && !aidunite_user_has_managed_team_access($user_id, $team_id)) {
        error_log('Webhook: metadata の team_id がユーザー所属と一致しません (user=' . $user_id . ', team=' . $team_id . ')');
        return;
    }

    // サブスクリプションIDを取得
    $subscription_id = $session->subscription ?? null;

    if (!empty($subscription_id)) {
        aidunite_set_stripe_subscription_id($user_id, $subscription_id);

        // 毎月1日に統一するため、billing_cycle_anchorを更新
        aidunite_update_billing_cycle_anchor_to_first($subscription_id, $session->metadata);
    }

    // 支払いステータスを更新
    aidunite_set_payment_status($user_id, 'paid');

    // トライアル開始日を設定（まだ設定されていない場合）
    $trial_start = aidunite_get_trial_start_date($team_id);
    if (empty($trial_start)) {
        aidunite_set_trial_start_date($team_id);
    }

    // 決済登録完了メール通知を送信
    aidunite_send_payment_registration_email($user_id, $team_id);

    error_log("Webhook: 支払い完了 (User ID: {$user_id}, Team ID: {$team_id})");
}

/**
 * invoice.payment_succeeded イベント処理
 */
function aidunite_handle_invoice_payment_succeeded($invoice) {
    $subscription_id = $invoice->subscription ?? null;

    if (empty($subscription_id)) {
        return;
    }

    // サブスクリプションIDからユーザーIDを取得
    $user_id = aidunite_get_user_id_by_subscription_id($subscription_id);

    if (empty($user_id)) {
        error_log('Webhook: サブスクリプションIDに対応するユーザーが見つかりません');
        return;
    }

    // 支払いステータスを更新
    aidunite_set_payment_status($user_id, 'paid');

    // 最初の請求完了後、次の請求日を毎月1日に統一
    // 最初の請求かどうかを判定（subscriptionのcurrent_period_startがbilling_cycle_anchorと一致する場合）
    aidunite_align_billing_to_first_of_month($subscription_id);

    error_log("Webhook: 自動課金成功 (User ID: {$user_id})");
}

/**
 * invoice.payment_failed イベント処理
 */
function aidunite_handle_invoice_payment_failed($invoice) {
    $subscription_id = $invoice->subscription ?? null;

    if (empty($subscription_id)) {
        return;
    }

    // サブスクリプションIDからユーザーIDを取得
    $user_id = aidunite_get_user_id_by_subscription_id($subscription_id);

    if (empty($user_id)) {
        error_log('Webhook: サブスクリプションIDに対応するユーザーが見つかりません');
        return;
    }

    // 支払いステータスを更新
    aidunite_set_payment_status($user_id, 'unpaid');

    error_log("Webhook: 支払い失敗 (User ID: {$user_id})");
}

/**
 * customer.subscription.deleted イベント処理
 */
function aidunite_handle_subscription_deleted($subscription) {
    $subscription_id = $subscription->id;

    // サブスクリプションIDからユーザーIDを取得
    $user_id = aidunite_get_user_id_by_subscription_id($subscription_id);

    if (empty($user_id)) {
        error_log('Webhook: サブスクリプションIDに対応するユーザーが見つかりません');
        return;
    }

    // 支払いステータスを更新（後払い方式のため、解約済みとして記録）
    aidunite_set_payment_status($user_id, 'cancelled');

    // 解約日を記録
    update_user_meta($user_id, 'subscription_cancelled_date', current_time('mysql'));

    error_log("Webhook: サブスクリプション解約 (User ID: {$user_id})");
}

/**
 * customer.subscription.updated イベント処理
 */
function aidunite_handle_subscription_updated($subscription) {
    $subscription_id = $subscription->id;

    // サブスクリプションIDからユーザーIDを取得
    $user_id = aidunite_get_user_id_by_subscription_id($subscription_id);

    if (empty($user_id)) {
        return;
    }

    // サブスクリプションのステータスに応じて支払いステータスを更新
    if ($subscription->status === 'active') {
        aidunite_set_payment_status($user_id, 'paid');
    } elseif ($subscription->status === 'past_due' || $subscription->status === 'unpaid') {
        aidunite_set_payment_status($user_id, 'unpaid');
    }

    error_log("Webhook: サブスクリプション更新 (User ID: {$user_id}, Status: {$subscription->status})");
}

/**
 * サブスクリプションIDからユーザーIDを取得
 */
function aidunite_get_user_id_by_subscription_id($subscription_id) {
    global $wpdb;

    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta}
         WHERE meta_key = 'stripe_subscription_id'
         AND meta_value = %s
         LIMIT 1",
        $subscription_id
    ));

    return $user_id ? (int) $user_id : null;
}

/**
 * 請求日を毎月1日に統一するため、billing_cycle_anchorを更新（最初の請求完了後）
 * 注意: 最初の請求日はStripeの制約に従うが、2回目以降は毎月1日に統一する
 */
function aidunite_align_billing_to_first_of_month($subscription_id) {
    if (!class_exists('\Stripe\Stripe')) {
        error_log("Stripe SDKがインストールされていません。billing_cycle_anchorの更新をスキップします。");
        return false;
    }

    try {
        // サブスクリプションを取得
        $subscription = \Stripe\Subscription::retrieve($subscription_id);

        // 現在のbilling_cycle_anchorを確認
        $current_anchor = $subscription->billing_cycle_anchor;
        $current_anchor_date = date('Y-m-d', $current_anchor);
        $current_day = (int)date('d', $current_anchor);

        // 既に1日になっている場合は更新不要
        if ($current_day === 1) {
            error_log("既に請求日が1日に設定されています。更新をスキップします。");
            return true;
        }

        // 次の請求期間の開始日を取得
        $next_period_start = $subscription->current_period_end;
        $next_period_start_date = date('Y-m-d', $next_period_start);

        // 次の請求期間の開始日の月の1日を計算
        // 例: 2月28日（または2月29日）の場合 → 3月1日（次の月の1日）
        // 理由: 次の請求期間の開始日（2月28日）の月の1日（2月1日）は既に過ぎているため、
        // その次の月の1日（3月1日）を次の請求日として設定する
        $next_period_year = date('Y', $next_period_start);
        $next_period_month = date('m', $next_period_start);

        // 次の請求期間の開始日の次の月の1日を計算
        $target_month_1st_timestamp = strtotime("{$next_period_year}-{$next_period_month}-01 +1 month");

        // 請求日を毎月1日に統一

        // 次の請求期間の開始日の次の月の1日にbilling_cycle_anchorを更新
        // これにより、2回目以降の請求が毎月1日になる
        // 注意: 1月29日に最初の請求があった場合、次の請求期間は2月28日（または2月29日）から始まるが、
        // その次の請求日は3月1日になる（2月1日ではない）
        $subscription->update($subscription_id, [
            'billing_cycle_anchor' => $target_month_1st_timestamp,
            'proration_behavior' => 'none', // 日割り計算なし
        ]);

        error_log("billing_cycle_anchorを毎月1日に統一: " . date('Y-m-d', $target_month_1st_timestamp) . " (Subscription ID: {$subscription_id})");

        return true;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("billing_cycle_anchor統一エラー: " . $e->getMessage());
        // エラーが発生しても処理は続行（請求日が統一されないだけ）
        return false;
    }
}

/**
 * 請求日を毎月1日に統一するため、billing_cycle_anchorを更新（Checkout完了時）
 * 注意: この関数は最初の請求日を更新しようとしますが、Stripeの制約により
 * 実際には最初の請求日は変更できません。2回目以降の統一は
 * aidunite_align_billing_to_first_of_month()で行います。
 */
function aidunite_update_billing_cycle_anchor_to_first($subscription_id, $metadata) {
    // 最初の請求日はStripeの制約に従うため、ここでは何もしない
    // 2回目以降の統一は、aidunite_align_billing_to_first_of_month()で行う
    error_log("最初の請求日はStripeの制約に従います。2回目以降の統一は最初の請求完了後に行います。");
    return true;
}

/**
 * 決済登録完了メール通知を送信
 */
function aidunite_send_payment_registration_email($user_id, $team_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        error_log("決済登録メール送信エラー: ユーザーが見つかりません (User ID: {$user_id})");
        return false;
    }

    $team = get_post($team_id);
    $team_name = $team ? $team->post_title : 'チーム';
    $monthly_fee = aidunite_calculate_monthly_fee($team_id);
    $plan = aidunite_get_plan_info($team_id);
    $plan_name = $plan ? $plan['name'] : 'スタンダードプラン';

    // 早期決済特典を取得
    $bonus = aidunite_calculate_early_payment_bonus($team_id);
    $bonus_message = '';
    if ($bonus && $bonus['bonus_months'] > 0) {
        $bonus_message = "\n\n🎉 早期決済特典: {$bonus['bonus_months']}か月無料の特典が適用されました！";
    }

    $subject = '【Aniy】決済登録が完了しました';
    $message = "{$user->display_name} 様\n\n";
    $message .= "この度は、Aniyをご利用いただき、誠にありがとうございます。\n\n";
    $message .= "無事に決済の登録が完了しました！\n\n";
    $message .= "【登録内容】\n";
    $message .= "プラン: {$plan_name}\n";
    $message .= "月額料金: ¥" . number_format($monthly_fee) . "/月\n";
    $message .= "チーム: {$team_name}\n";
    $message .= $bonus_message;
    $message .= "\n\n引き続き、Aniyをよろしくお願いいたします。\n\n";
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
        error_log("決済登録完了メール送信成功 (User ID: {$user_id}, Email: {$user->user_email})");
    } else {
        error_log("決済登録完了メール送信失敗 (User ID: {$user_id}, Email: {$user->user_email})");
    }

    return $result;
}
