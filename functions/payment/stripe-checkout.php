<?php
/**
 * Stripe Checkout処理
 * Checkoutセッション作成、サブスクリプション処理
 */

require_once get_template_directory() . '/functions/payment/stripe-core.php';
require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';
require_once get_template_directory() . '/functions/common/error-handler.php';

// use文は使用せず、完全修飾名で呼び出す（クラスが存在しない場合のエラーを防ぐため）

/**
 * Stripe Checkoutセッションを作成
 */
function aidunite_create_checkout_session($user_id, $team_id) {
    // Stripe SDKのチェック
    if (!class_exists('\Stripe\Stripe')) {
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKがインストールされていません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
    }

    if (!aidunite_init_stripe()) {
        return new WP_Error('stripe_init_failed', 'Stripeの初期化に失敗しました');
    }

    // チーム情報を取得
    $team_type = aidunite_get_team_type($team_id);
    $monthly_fee = aidunite_calculate_monthly_fee($team_id);
    $plan = aidunite_get_plan_info($team_id);

    if (empty($plan)) {
        return new WP_Error('plan_not_found', 'プランが見つかりません');
    }

    // ユーザー情報を取得
    $user = get_userdata($user_id);
    $user_email = $user->user_email;

    // Stripe顧客を作成または取得
    $customer_id = aidunite_get_stripe_customer_id($user_id);

    if (empty($customer_id)) {
        try {
            $customer = \Stripe\Customer::create([
                'email' => $user_email,
                'name' => $user->display_name,
                'metadata' => [
                    'user_id' => $user_id,
                    'team_id' => $team_id,
                ],
            ]);

            $customer_id = $customer->id;
            aidunite_set_stripe_customer_id($user_id, $customer_id);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe顧客作成エラー: " . $e->getMessage());
            // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
            return new WP_Error('customer_creation_failed', '決済情報の登録に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
        }
    }

    // 早期決済特典を計算（後払い方式）
    $bonus = aidunite_calculate_early_payment_bonus($team_id);

    // 価格IDを取得または作成
    $price_id = aidunite_get_or_create_stripe_price($team_id, $monthly_fee);

    if (is_wp_error($price_id)) {
        return $price_id;
    }

    // Checkoutセッションを作成
    $session_params = [
        'customer' => $customer_id,
        'payment_method_types' => ['card'],
        'mode' => 'subscription',
        'line_items' => [[
            'price' => $price_id,
            'quantity' => 1,
        ]],
        'success_url' => home_url('/mypage?payment=success'),
        'cancel_url' => home_url('/payment-setup?payment=cancelled'),
        'metadata' => [
            'user_id' => $user_id,
            'team_id' => $team_id,
            'plan_id' => $plan['id'],
        ],
    ];

    // 後払い方式での請求タイミング設定
    // Stripe APIでは、billing_cycle_anchorとtrial_endは同時に指定できない
    // billing_cycle_anchorを使用すると、その日までがトライアル期間として扱われ、その日から請求が開始される
    // 重要: billing_cycle_anchorは「次の自然な請求日」（通常は現在から1ヶ月後）より先に設定できない

    $current_timestamp = time();
    $next_natural_billing_date = strtotime('+1 month', $current_timestamp); // 次の自然な請求日（1ヶ月後）

    if ($bonus) {
        // 後払い方式: 最初の請求日を設定
        $first_billing_timestamp = strtotime($bonus['first_billing_date']);

        // Stripeの制約: billing_cycle_anchorは「次の自然な請求日」より先に設定できない
        // そのため、first_billing_timestampがnext_natural_billing_dateより後の場合は、
        // next_natural_billing_dateを使用する
        if ($first_billing_timestamp > $next_natural_billing_date) {
            error_log("Stripe Checkout: billing_cycle_anchorをnext_natural_billing_dateに調整 (User ID: {$user_id})");
            $billing_cycle_anchor = $next_natural_billing_date;
        } else {
            $billing_cycle_anchor = $first_billing_timestamp;
        }

        $session_params['subscription_data'] = [
            'billing_cycle_anchor' => $billing_cycle_anchor, // 統一請求日（後払い方式）
            'metadata' => [
                'user_id' => $user_id,
                'team_id' => $team_id,
                'plan_id' => $plan['id'],
                'early_bonus' => json_encode($bonus),
                'billing_type' => 'postpaid', // 後払い方式
                'original_first_billing_date' => $bonus['first_billing_date'], // 元の請求日をメタデータに保存
            ],
        ];
    } else {
        // 特典なしの場合も後払い方式で統一
        $trial_start = aidunite_get_trial_start_date($team_id);
        if (!empty($trial_start)) {
            // トライアル終了日を計算（30日後）
            $original_trial_end = strtotime('+30 days', strtotime($trial_start));
            $year = date('Y', $original_trial_end);
            $month = date('m', $original_trial_end);
            // トライアル終了日の次の月の1日が最初の請求日
            $first_billing_timestamp = strtotime("{$year}-{$month}-01 +1 month");

            // Stripeの制約: billing_cycle_anchorは「次の自然な請求日」より先に設定できない
            if ($first_billing_timestamp > $next_natural_billing_date) {
                error_log("Stripe Checkout: billing_cycle_anchorをnext_natural_billing_dateに調整 (User ID: {$user_id})");
                $billing_cycle_anchor = $next_natural_billing_date;
            } else {
                $billing_cycle_anchor = $first_billing_timestamp;
            }

            $session_params['subscription_data'] = [
                'billing_cycle_anchor' => $billing_cycle_anchor,
                'metadata' => [
                    'user_id' => $user_id,
                    'team_id' => $team_id,
                    'plan_id' => $plan['id'],
                    'billing_type' => 'postpaid',
                    'original_first_billing_date' => date('Y-m-d', $first_billing_timestamp), // 元の請求日をメタデータに保存
                ],
            ];
        }
    }

    try {
        $session = \Stripe\Checkout\Session::create($session_params);

        return [
            'session_id' => $session->id,
            'url' => $session->url,
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("Stripe Checkout Session作成エラー: " . $e->getMessage());
        error_log("エラーコード: " . $e->getStripeCode());
        error_log("エラータイプ: " . get_class($e));

        // エラーの詳細をログに記録
        if (method_exists($e, 'getJsonBody')) {
            error_log("エラー詳細: " . print_r($e->getJsonBody(), true));
        }

        // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
        return new WP_Error('checkout_session_failed', '決済ページの準備に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
    }
}

/**
 * Stripe価格IDを取得または作成
 */
function aidunite_get_or_create_stripe_price($team_id, $amount) {
    // 既存の価格IDをチェック（メタデータで管理）
    $price_id = get_post_meta($team_id, 'stripe_price_id', true);

    if (!empty($price_id)) {
        // 既存の価格IDを確認
        try {
            $price = \Stripe\Price::retrieve($price_id);
            // JPYは少数なし通貨のため、そのまま「円」の金額で比較
            if ($price->unit_amount == $amount) { // 金額が一致する場合
                return $price_id;
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // 価格が見つからない場合は新規作成
        }
    }

    // 新規価格を作成
    try {
        $product = \Stripe\Product::create([
            'name' => 'Aniyシステム利用料',
            'metadata' => [
                'team_id' => $team_id,
            ],
        ]);

        // JPYは少数なし通貨のため、unit_amount は「そのまま円」の金額を指定する
        $price = \Stripe\Price::create([
            'product' => $product->id,
            'unit_amount' => $amount, // 円単位（StripeではJPYは少数なし通貨）
            'currency' => 'jpy',
            'recurring' => [
                'interval' => 'month',
            ],
        ]);

        // 価格IDを保存
        update_post_meta($team_id, 'stripe_price_id', $price->id);

        return $price->id;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("Stripe価格作成エラー: " . $e->getMessage());
        // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
        return new WP_Error('price_creation_failed', '決済設定の保存に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
    }
}

/**
 * Ajax: Checkoutセッション作成
 */
add_action('wp_ajax_aidunite_create_checkout', 'aidunite_ajax_create_checkout');
function aidunite_ajax_create_checkout() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        // 認証エラーはcritical
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: 'ログインが必要です',
            'authentication_required'
        );
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_payment_nonce');
    if (is_wp_error($nonce_result)) {
        // CSRFエラーはcritical
        AidUniteApiResponse::send_critical_error(
            'セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。',
            'csrf_verification_failed'
        );
        return;
    }

    $user_id = $auth_result->user_id;
    $team_id = $auth_result->team_id;

    if (empty($team_id)) {
        // チームが見つからないエラーはnormal
        AidUniteApiResponse::send_error(
            'チームが見つかりません',
            null,
            'normal',
            'team_not_found'
        );
        return;
    }

    // Checkoutセッションを作成
    $result = aidunite_create_checkout_session($user_id, $team_id);

    if (is_wp_error($result)) {
        // 支払い関連のエラーはcriticalとして扱う
        AidUniteApiResponse::send_critical_error(
            $result->get_error_message(),
            'checkout_session_failed'
        );
        return;
    }

    wp_send_json_success($result);
}
