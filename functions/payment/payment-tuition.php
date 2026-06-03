<?php
/**
 * 月謝（チーム → 保護者）用決済機能
 * Stripe Connectベースの月謝サブスクリプションと支払い履歴管理
 *
 * 前提:
 * - 各チームのStripe ConnectアカウントIDは post_meta 'stripe_connect_account_id' に保存されている想定
 * - 月謝金額は post_meta 'team_monthly_fee'（円）で管理
 */

require_once get_template_directory() . '/functions/payment/stripe-core.php';
require_once get_template_directory() . '/functions/common/error-handler.php';

/**
 * 月謝用カスタムテーブルを作成
 * - wp_aidunite_tuition_payments: 保護者×チームごとの支払い履歴
 */
function aidunite_install_tuition_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $payments_table  = $wpdb->prefix . 'aidunite_tuition_payments';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql_payments = "CREATE TABLE IF NOT EXISTS {$payments_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        parent_user_id bigint(20) unsigned NOT NULL,
        child_id bigint(20) unsigned NULL,
        team_id bigint(20) unsigned NOT NULL,
        stripe_subscription_id varchar(191) DEFAULT NULL,
        stripe_invoice_id varchar(191) DEFAULT NULL,
        amount int(11) NOT NULL DEFAULT 0,
        currency varchar(10) NOT NULL DEFAULT 'jpy',
        status varchar(20) NOT NULL DEFAULT 'paid',
        member_status varchar(20) DEFAULT NULL,
        payment_date datetime NOT NULL,
        raw_payload longtext NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY parent_user_id (parent_user_id),
        KEY team_id (team_id),
        KEY stripe_subscription_id (stripe_subscription_id),
        KEY stripe_invoice_id (stripe_invoice_id)
    ) {$charset_collate};";

    dbDelta($sql_payments);
}

// テーマ有効化時にテーブルを作成
add_action('after_switch_theme', 'aidunite_install_tuition_tables');

/**
 * 月謝支払い履歴を保存
 */
function aidunite_record_tuition_payment($args) {
    global $wpdb;

    $defaults = [
        'parent_user_id'        => 0,
        'child_id'              => null,
        'team_id'               => 0,
        'stripe_subscription_id'=> '',
        'stripe_invoice_id'     => '',
        'amount'                => 0,
        'currency'              => 'jpy',
        'status'                => 'paid',
        'payment_date'          => current_time('mysql'),
        'member_status'         => null,
        'raw_payload'           => null,
    ];

    $data = wp_parse_args($args, $defaults);

    if (empty($data['parent_user_id']) || empty($data['team_id'])) {
        return false;
    }

    $table = $wpdb->prefix . 'aidunite_tuition_payments';
    $now   = current_time('mysql');

    $inserted = $wpdb->insert(
        $table,
        [
            'parent_user_id'        => (int) $data['parent_user_id'],
            'child_id'              => $data['child_id'] ? (int) $data['child_id'] : null,
            'team_id'               => (int) $data['team_id'],
            'stripe_subscription_id'=> sanitize_text_field($data['stripe_subscription_id']),
            'stripe_invoice_id'     => sanitize_text_field($data['stripe_invoice_id']),
            'amount'                => (int) $data['amount'],
            'currency'              => sanitize_text_field($data['currency']),
            'status'                => sanitize_text_field($data['status']),
            'payment_date'          => $data['payment_date'],
            'member_status'         => $data['member_status'],
            'raw_payload'           => $data['raw_payload'],
            'created_at'            => $now,
            'updated_at'            => $now,
        ],
        [
            '%d', '%d', '%d',
            '%s', '%s',
            '%d', '%s', '%s',
            '%s', '%s', '%s',
            '%s', '%s', '%s',
        ]
    );

    return (bool) $inserted;
}

/**
 * 保護者の月謝支払い履歴を取得
 */
function aidunite_get_parent_tuition_history($parent_user_id, $team_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'aidunite_tuition_payments';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT payment_date, amount, status
             FROM {$table}
             WHERE parent_user_id = %d
             AND team_id = %d
             ORDER BY payment_date DESC
             LIMIT 50",
            $parent_user_id,
            $team_id
        ),
        ARRAY_A
    );

    return $rows ?: [];
}

/**
 * Ajax: 保護者の月謝支払い履歴を返す
 */
add_action('wp_ajax_aidunite_get_parent_payment_history', 'aidunite_ajax_get_parent_payment_history');
function aidunite_ajax_get_parent_payment_history() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_auth();
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

    $parent_user_id = $auth_result->user_id;
    $team_id = AidUniteAuthMiddleware::sanitize($_POST['team_id'] ?? 0, 'int');

    if (!$team_id) {
        // チームが指定されていないエラーはnormal
        AidUniteApiResponse::send_error(
            'チームが指定されていません',
            null,
            'normal',
            'team_not_specified'
        );
        return;
    }

    $history = aidunite_get_parent_tuition_history($parent_user_id, $team_id);

    wp_send_json_success([
        'history' => array_map(function ($row) {
            return [
                'payment_date' => $row['payment_date'],
                'amount'       => (int) $row['amount'],
                'status'       => $row['status'],
            ];
        }, $history),
    ]);
}

/**
 * Stripe Connect を使った月謝用 Checkout セッションを作成
 *
 * @param int $parent_user_id 保護者ユーザーID
 * @param int $team_id チームID
 * @return array|WP_Error
 */
function aidunite_create_connect_checkout_session($parent_user_id, $team_id) {
    // Stripe SDKチェック
    if (!class_exists('\Stripe\Stripe')) {
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKがインストールされていません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
    }

    if (!aidunite_init_stripe()) {
        return new WP_Error('stripe_init_failed', 'Stripeの初期化に失敗しました');
    }

    $team         = get_post($team_id);
    $parent       = get_userdata($parent_user_id);
    $connect_acct = get_post_meta($team_id, 'stripe_connect_account_id', true);
    $monthly_fee  = get_post_meta($team_id, 'team_monthly_fee', true);

    if (empty($team) || empty($parent)) {
        return new WP_Error('team_or_parent_not_found', 'チームまたはユーザー情報が見つかりません');
    }

    if (empty($connect_acct)) {
        return new WP_Error(
            'connect_account_not_set',
            'このチームのStripe Connectアカウントが設定されていません。チーム管理画面からStripe連携を完了してください。'
        );
    }

    // 月謝金額は必須: 未設定のまま誤った金額で課金されるのを防ぐ
    if ($monthly_fee === '' || $monthly_fee === null || (int) $monthly_fee <= 0) {
        return new WP_Error(
            'tuition_amount_not_set',
            'このチームの月謝金額が設定されていません。チーム管理画面で月謝金額を設定してから、保護者に案内してください。'
        );
    }

    try {
        // Connectアカウント側のCustomerを取得または作成
        $customer_meta_key = 'stripe_connect_customer_' . $team_id;
        $customer_id       = get_user_meta($parent_user_id, $customer_meta_key, true);

        if (empty($customer_id)) {
            $customer = \Stripe\Customer::create(
                [
                    'email'    => $parent->user_email,
                    'name'     => $parent->display_name,
                    'metadata' => [
                        'parent_user_id' => $parent_user_id,
                        'team_id'        => $team_id,
                        'billing_type'   => 'team_tuition',
                    ],
                ],
                ['stripe_account' => $connect_acct]
            );

            $customer_id = $customer->id;
            update_user_meta($parent_user_id, $customer_meta_key, $customer_id);
        }

        // Connectアカウント側のProduct/Priceを取得または作成
        $price_meta_key   = 'stripe_connect_price_id';
        $product_meta_key = 'stripe_connect_product_id';

        $price_id   = get_post_meta($team_id, $price_meta_key, true);
        $product_id = get_post_meta($team_id, $product_meta_key, true);

        if (!empty($price_id)) {
            try {
                $price = \Stripe\Price::retrieve(
                    $price_id,
                    ['stripe_account' => $connect_acct]
                );
                if ($price->unit_amount != (int) $monthly_fee) {
                    // 金額が変わっていれば新規作成
                    $price_id = '';
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $price_id = '';
            }
        }

        if (empty($price_id)) {
            if (empty($product_id)) {
                $product = \Stripe\Product::create(
                    [
                        'name'     => sprintf('月謝 (%s)', $team->post_title),
                        'metadata' => [
                            'team_id'      => $team_id,
                            'billing_type' => 'team_tuition',
                        ],
                    ],
                    ['stripe_account' => $connect_acct]
                );
                $product_id = $product->id;
                update_post_meta($team_id, $product_meta_key, $product_id);
            }

            $price = \Stripe\Price::create(
                [
                    'product'     => $product_id,
                    'unit_amount' => (int) $monthly_fee, // 円
                    'currency'    => 'jpy',
                    'recurring'   => [
                        'interval' => 'month',
                    ],
                ],
                ['stripe_account' => $connect_acct]
            );

            $price_id = $price->id;
            update_post_meta($team_id, $price_meta_key, $price_id);
        }

        // Checkoutセッション作成（Connectアカウント側）
        $session_params = [
            'customer'             => $customer_id,
            'payment_method_types' => ['card'],
            'mode'                 => 'subscription',
            'line_items'           => [[
                'price'    => $price_id,
                'quantity' => 1,
            ]],
            'success_url'          => home_url('/parent-payment?payment=success'),
            'cancel_url'           => home_url('/parent-payment?payment=cancelled'),
            'metadata'             => [
                'parent_user_id' => $parent_user_id,
                'team_id'        => $team_id,
                'billing_type'   => 'team_tuition',
            ],
            // サブスクリプション側にもメタデータを引き継ぐ
            'subscription_data'   => [
                'metadata' => [
                    'parent_user_id' => $parent_user_id,
                    'team_id'        => $team_id,
                    'billing_type'   => 'team_tuition',
                ],
            ],
        ];

        $session = \Stripe\Checkout\Session::create(
            $session_params,
            ['stripe_account' => $connect_acct]
        );

        return [
            'session_id' => $session->id,
            'url'        => $session->url,
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[TUITION] Stripe Connect 月謝Checkout作成エラー: ' . $e->getMessage() . " (parent_user_id={$parent_user_id}, team_id={$team_id})");
        // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
        return new WP_Error('connect_checkout_failed', '月謝の決済ページの準備に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
    }
}

/**
 * 月謝用: invoice.payment_succeeded イベント処理
 *
 * @param object $invoice
 * @param string|null $account_id Stripe ConnectアカウントID
 */
function aidunite_handle_tuition_invoice_succeeded($invoice, $account_id = null) {
    $metadata = $invoice->metadata ?? null;
    if (!$metadata || !isset($metadata->parent_user_id) || !isset($metadata->team_id)) {
        error_log('[TUITION] invoice.payment_succeeded 受信したがmetadataが不足しています');
        return;
    }

    $parent_user_id  = (int) $metadata->parent_user_id;
    $team_id         = (int) $metadata->team_id;
    $subscription_id = $invoice->subscription ?? null;
    $invoice_id      = $invoice->id ?? null;
    $amount_paid     = isset($invoice->amount_paid) ? (int) $invoice->amount_paid : 0;

    if (!$parent_user_id || !$team_id) {
        error_log('[TUITION] invoice.payment_succeeded metadataのuser_id/team_idが不正です');
        return;
    }

    // サブスクリプションIDをユーザーメタに保存（解約時に使用）
    if ($subscription_id) {
        $meta_key = 'stripe_tuition_subscription_' . $team_id;
        update_user_meta($parent_user_id, $meta_key, sanitize_text_field($subscription_id));
    }

    // 生のpayloadを保存（デバッグ・監査用）
    $raw_payload = null;
    if (method_exists($invoice, 'toJSON')) {
        $raw_payload = $invoice->toJSON();
    }

    aidunite_record_tuition_payment([
        'parent_user_id'         => $parent_user_id,
        'team_id'                => $team_id,
        'stripe_subscription_id' => $subscription_id,
        'stripe_invoice_id'      => $invoice_id,
        'amount'                 => $amount_paid,
        'currency'               => 'jpy',
        'status'                 => 'paid',
        'payment_date'           => date('Y-m-d H:i:s', $invoice->status_transitions->paid_at ?? time()),
        'member_status'          => 'active',
        'raw_payload'            => $raw_payload,
    ]);

    error_log('[TUITION] 月謝支払い記録: parent_user_id=' . $parent_user_id . ', team_id=' . $team_id . ', amount=' . $amount_paid);
}

/**
 * 月謝用: customer.subscription.deleted イベント処理
 *
 * @param object $subscription
 * @param string|null $account_id
 */
function aidunite_handle_tuition_subscription_deleted($subscription, $account_id = null) {
    $metadata = $subscription->metadata ?? null;
    if (!$metadata || !isset($metadata->parent_user_id) || !isset($metadata->team_id)) {
        error_log('[TUITION] subscription.deleted 受信したがmetadataが不足しています');
        return;
    }

    $parent_user_id  = (int) $metadata->parent_user_id;
    $team_id         = (int) $metadata->team_id;
    $subscription_id = $subscription->id ?? null;

    if (!$parent_user_id || !$team_id) {
        error_log('[TUITION] subscription.deleted metadataのuser_id/team_idが不正です');
        return;
    }

    // ステータスだけ「cancelled」として履歴を追加（必要最低限）
    aidunite_record_tuition_payment([
        'parent_user_id'         => $parent_user_id,
        'team_id'                => $team_id,
        'stripe_subscription_id' => $subscription_id,
        'stripe_invoice_id'      => '',
        'amount'                 => 0,
        'currency'               => 'jpy',
        'status'                 => 'cancelled',
        'payment_date'           => current_time('mysql'),
        'member_status'          => 'active',
        'raw_payload'            => null,
    ]);

    // ユーザーメタからもサブスクリプションIDを削除
    $meta_key = 'stripe_tuition_subscription_' . $team_id;
    delete_user_meta($parent_user_id, $meta_key);

    error_log('[TUITION] 月謝サブスクリプション解約: parent_user_id=' . $parent_user_id . ', team_id=' . $team_id . ', subscription_id=' . $subscription_id);
}

/**
 * 月謝サブスクリプションを解約（保護者側/チーム側からのリクエスト共通）
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @return array|WP_Error
 */
function aidunite_cancel_tuition_subscription($parent_user_id, $team_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id        = (int) $team_id;

    if (!$parent_user_id || !$team_id) {
        return new WP_Error('invalid_args', 'ユーザーまたはチーム情報が不正です');
    }

    if (!class_exists('\Stripe\Stripe')) {
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKがインストールされていません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
    }

    if (!aidunite_init_stripe()) {
        return new WP_Error('stripe_init_failed', 'Stripeの初期化に失敗しました');
    }

    $connect_acct = get_post_meta($team_id, 'stripe_connect_account_id', true);
    if (empty($connect_acct)) {
        return new WP_Error('connect_account_not_set', 'このチームのStripe Connectアカウントが設定されていません。');
    }

    $meta_key       = 'stripe_tuition_subscription_' . $team_id;
    $subscription_id = get_user_meta($parent_user_id, $meta_key, true);
    if (empty($subscription_id)) {
        return new WP_Error('no_subscription', '月謝サブスクリプションが見つかりません。');
    }

    try {
        $subscription = \Stripe\Subscription::retrieve($subscription_id, ['stripe_account' => $connect_acct]);
        $subscription->cancel();

        // 簡易的に履歴も残す
        aidunite_record_tuition_payment([
            'parent_user_id'         => $parent_user_id,
            'team_id'                => $team_id,
            'stripe_subscription_id' => $subscription_id,
            'stripe_invoice_id'      => '',
            'amount'                 => 0,
            'currency'               => 'jpy',
            'status'                 => 'cancelled',
            'payment_date'           => current_time('mysql'),
            'member_status'          => 'active',
            'raw_payload'            => null,
        ]);

        delete_user_meta($parent_user_id, $meta_key);

        error_log('[TUITION] 月謝サブスクリプションを手動解約: parent_user_id=' . $parent_user_id . ', team_id=' . $team_id . ', subscription_id=' . $subscription_id);

        return [
            'success' => true,
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[TUITION] 月謝サブスクリプション解約エラー: ' . $e->getMessage() . " (parent_user_id={$parent_user_id}, team_id={$team_id})");
        // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
        return new WP_Error('cancel_failed', '月謝の解約処理に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
    }
}
