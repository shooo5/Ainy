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
 *
 * @param int                  $user_id
 * @param int                  $team_id
 * @param array<string, mixed> $options ui_mode: hosted|embedded
 * @return array<string, string>|WP_Error
 */
function aidunite_create_checkout_session($user_id, $team_id, array $options = []) {
    // Stripe SDKのチェック
    if (!class_exists('\Stripe\Stripe')) {
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKがインストールされていません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
    }

    if (!aidunite_init_stripe()) {
        $secret_key = (string) get_option('aidunite_stripe_secret_key', '');
        if ($secret_key !== '') {
            $validation = aidunite_validate_stripe_secret_key($secret_key);
            if (is_wp_error($validation)) {
                return $validation;
            }
        }

        return new WP_Error(
            'stripe_init_failed',
            'Stripeの初期化に失敗しました。管理者画面で Stripe API キーを確認してください。'
        );
    }

    // チーム情報を取得
    $team_type = aidunite_get_team_type($team_id);
    $monthly_fee = aidunite_calculate_monthly_fee($team_id, $user_id);
    $plan = aidunite_get_plan_info($team_id);

    if (empty($plan)) {
        return new WP_Error('plan_not_found', 'プランが見つかりません');
    }

    // Stripe顧客を作成または取得（別 Stripe アカウントの残骸 ID は自動で作り直す）
    $customer_id = function_exists('aidunite_ensure_stripe_platform_customer')
        ? aidunite_ensure_stripe_platform_customer($user_id, $team_id)
        : aidunite_get_stripe_customer_id($user_id);

    if (is_wp_error($customer_id)) {
        return $customer_id;
    }

    $customer_id = (string) $customer_id;
    if ($customer_id === '' && function_exists('aidunite_ensure_stripe_platform_customer')) {
        return new WP_Error('customer_creation_failed', '決済情報の登録に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
    }

    if ($customer_id === '') {
        // レガシー: ensure 関数が無い環境向け
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('user_not_found', 'ユーザー情報が見つかりません');
        }

        try {
            $customer = \Stripe\Customer::create([
                'email' => $user->user_email,
                'name' => $user->display_name,
                'metadata' => [
                    'user_id' => $user_id,
                    'team_id' => $team_id,
                ],
            ]);

            $customer_id = $customer->id;
            aidunite_set_stripe_customer_id($user_id, $customer_id);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe顧客作成エラー: ' . $e->getMessage());

            $secret_key = (string) get_option('aidunite_stripe_secret_key', '');
            if (strpos($secret_key, 'whsec_') === 0) {
                return new WP_Error(
                    'stripe_secret_key_is_webhook',
                    '決済設定に誤りがあります（Secret Key に Webhook Secret が入っています）。管理者に Stripe API キーの再設定を依頼してください。'
                );
            }

            if (stripos($e->getMessage(), 'Invalid API Key') !== false) {
                return new WP_Error(
                    'stripe_api_key_invalid',
                    'Stripe API キーが無効です。管理者に決済管理画面でのキー設定を確認してください。'
                );
            }

            return new WP_Error('customer_creation_failed', '決済情報の登録に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
        }
    }

    // 価格IDを取得または作成
    $product_name = sprintf('Ainy %s', (string) ($plan['name'] ?? 'システム利用料'));
    $price_id = aidunite_get_or_create_stripe_price($team_id, $monthly_fee, $product_name);

    if (is_wp_error($price_id)) {
        return $price_id;
    }

    $ui_mode = isset($options['ui_mode']) && $options['ui_mode'] === 'embedded' ? 'embedded' : 'hosted';

    // Checkoutセッションを作成
    $session_params = [
        'customer' => $customer_id,
        'payment_method_types' => ['card'],
        'mode' => 'subscription',
        'line_items' => [[
            'price' => $price_id,
            'quantity' => 1,
        ]],
        'locale' => 'ja',
        'metadata' => [
            'user_id' => $user_id,
            'team_id' => $team_id,
            'plan_id' => $plan['id'],
        ],
        'custom_text' => [
            'submit' => [
                'message' => 'カードを登録する',
            ],
        ],
    ];

    $checkout_return_url = add_query_arg(
        [
            'payment' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ],
        home_url('/payment-setup')
    );

    if ($ui_mode === 'embedded') {
        $session_params['ui_mode'] = 'embedded';
        $session_params['return_url'] = $checkout_return_url;
    } else {
        $session_params['success_url'] = $checkout_return_url;
        $session_params['cancel_url'] = home_url('/payment-setup?payment=cancelled');
    }

    $trial_end_date = aidunite_calculate_trial_end_date($team_id);
    $subscription_metadata = [
        'user_id' => $user_id,
        'team_id' => $team_id,
        'plan_id' => $plan['id'],
        'billing_type' => 'postpaid',
    ];

    $subscription_data = [
        'metadata' => $subscription_metadata,
    ];

    if (!empty($trial_end_date)) {
        $trial_end_ts = strtotime($trial_end_date);
        if ($trial_end_ts > time()) {
            $subscription_data['trial_end'] = $trial_end_ts;
        }
    }

    $session_params['subscription_data'] = $subscription_data;

    try {
        $session = \Stripe\Checkout\Session::create($session_params);

        $result = [
            'session_id' => $session->id,
        ];

        if ($ui_mode === 'embedded') {
            $result['client_secret'] = (string) ($session->client_secret ?? '');
            if ($result['client_secret'] === '') {
                return new WP_Error('checkout_session_failed', '決済ページの準備に失敗しました。しばらく時間をおいて再度お試しください。');
            }
        } else {
            $result['url'] = (string) ($session->url ?? '');
        }

        return $result;
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
 *
 * @param int    $team_id
 * @param int    $amount
 * @param string $product_name
 * @return string|WP_Error
 */
function aidunite_get_or_create_stripe_price($team_id, $amount, $product_name = 'Ainy システム利用料') {
    // 既存の価格IDをチェック（メタデータで管理）
    $price_id = function_exists('aidunite_payment_read_stripe_price_id')
        ? aidunite_payment_read_stripe_price_id($team_id)
        : '';

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
            'name' => $product_name,
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
        if (function_exists('aidunite_team_write_stripe_price_id_meta')) {
            aidunite_team_write_stripe_price_id_meta($team_id, $price->id);
        }

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
    $ui_mode = isset($_POST['ui_mode']) && sanitize_text_field(wp_unslash($_POST['ui_mode'])) === 'embedded'
        ? 'embedded'
        : 'hosted';
    $result = aidunite_create_checkout_session($user_id, $team_id, ['ui_mode' => $ui_mode]);

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
