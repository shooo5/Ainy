<?php
/**
 * 月謝（チーム → 保護者）用決済機能
 * Stripe Connectベースの月謝サブスクリプションと支払い履歴管理
 *
 * 前提:
 * - 各チームのStripe ConnectアカウントIDは post_meta 'stripe_connect_account_id' に保存されている想定
 * - 月謝金額は post_meta 'team_monthly_fee'（円）で管理
 *
 * 請求日ポリシー:
 * - 月謝（Connect）: 月末アンカー（billing_cycle_anchor / day_of_month: 31）
 * - システム料: 毎月1日（stripe-webhook aidunite_align_billing_to_first_of_month）
 */

require_once get_template_directory() . '/functions/payment/stripe-core.php';
require_once get_template_directory() . '/functions/common/error-handler.php';

/**
 * 月謝 Connect：次回請求の月末アンカー（サイトTZ・23:59:59）
 * UI「毎月末に自動決済」と一致させる。
 *
 * @return DateTimeImmutable
 */
function aidunite_payment_resolve_tuition_month_end_billing_anchor() {
    $tz = wp_timezone();
    $now = new DateTimeImmutable('now', $tz);
    $anchor = $now->modify('last day of this month')->setTime(23, 59, 59);
    if ($now >= $anchor) {
        $anchor = $now->modify('first day of next month')
            ->modify('last day of this month')
            ->setTime(23, 59, 59);
    }

    return $anchor;
}

/**
 * Connect Checkout 用 subscription_data（毎月末請求・日割りなし）
 *
 * @param array<string, mixed> $metadata
 * @param float              $application_fee_percent
 * @return array<string, mixed>
 */
function aidunite_payment_build_tuition_connect_subscription_data(array $metadata, $application_fee_percent = 0.0) {
    $anchor = aidunite_payment_resolve_tuition_month_end_billing_anchor();

    $data = [
        'metadata' => $metadata,
        // 初回請求は当月末（未来アンカー）まで課金しない
        'billing_cycle_anchor' => $anchor->getTimestamp(),
        // 2回目以降も各月の末日に揃える（day_of_month=31 は Stripe が月末扱い）
        'billing_cycle_anchor_config' => [
            'day_of_month' => 31,
            'hour' => 23,
            'minute' => 59,
            'second' => 0,
        ],
        'proration_behavior' => 'none',
    ];

    if ($application_fee_percent > 0) {
        $data['application_fee_percent'] = (float) $application_fee_percent;
    }

    return $data;
}

/**
 * 既存の Connect 月謝サブスクを月末請求に揃える（登録直後の保険）
 *
 * @param string $subscription_id
 * @param string $connect_account_id
 * @return bool
 */
function aidunite_payment_align_connect_tuition_subscription_to_month_end($subscription_id, $connect_account_id) {
    $subscription_id = trim((string) $subscription_id);
    $connect_account_id = trim((string) $connect_account_id);
    if ($subscription_id === '' || $connect_account_id === '' || !class_exists('\Stripe\Subscription')) {
        return false;
    }
    if (!function_exists('aidunite_init_stripe') || !aidunite_init_stripe()) {
        return false;
    }

    try {
        $subscription = \Stripe\Subscription::retrieve(
            $subscription_id,
            [],
            ['stripe_account' => $connect_account_id]
        );
        $config = $subscription->billing_cycle_anchor_config ?? null;
        if (
            is_object($config)
            && (int) ($config->day_of_month ?? 0) === 31
            && (int) ($config->hour ?? -1) === 23
        ) {
            return true;
        }

        \Stripe\Subscription::update(
            $subscription_id,
            [
                'billing_cycle_anchor_config' => [
                    'day_of_month' => 31,
                    'hour' => 23,
                    'minute' => 59,
                    'second' => 0,
                ],
                'proration_behavior' => 'none',
            ],
            ['stripe_account' => $connect_account_id]
        );

        return true;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[TUITION] 月末アンカー統一エラー: ' . $e->getMessage() . ' (sub=' . $subscription_id . ')');

        return false;
    }
}

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
    if (function_exists('aidunite_payment_persist_tuition_payment_row')) {
        return aidunite_payment_persist_tuition_payment_row(is_array($args) ? $args : []);
    }

    return false;
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

    $history = aidunite_payment_read_parent_tuition_history($parent_user_id, $team_id);

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
    $tuition_display = function_exists('aidunite_payment_read_tuition_display')
        ? aidunite_payment_read_tuition_display($team_id)
        : [];
    $connect_acct = (string) ($tuition_display['stripe_connect_account_id'] ?? '');
    $monthly_fee  = (int) ($tuition_display['team_monthly_fee'] ?? 0);
    $fee_policy = function_exists('aidunite_payment_read_tuition_fee_policy')
        ? aidunite_payment_read_tuition_fee_policy()
        : ['ainy_application_fee_percent' => 1.4];
    $application_fee_percent = (float) ($fee_policy['ainy_application_fee_percent'] ?? 0);

    if (empty($team) || empty($parent)) {
        return new WP_Error('team_or_parent_not_found', 'チームまたはユーザー情報が見つかりません');
    }

    if (empty($connect_acct)) {
        return new WP_Error(
            'connect_account_not_set',
            'このチームのStripe Connectアカウントが設定されていません。チーム管理画面からStripe連携を完了してください。'
        );
    }

    if ($monthly_fee <= 0) {
        return new WP_Error(
            'tuition_amount_not_set',
            'このチームの月謝金額が設定されていません。チーム管理画面で月謝金額を設定してから、保護者に案内してください。'
        );
    }

    try {
        // Connectアカウント側のCustomerを取得または作成
        $customer_id = function_exists('aidunite_user_read_connect_customer_id')
            ? aidunite_user_read_connect_customer_id($parent_user_id, $team_id)
            : '';

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
            if (function_exists('aidunite_user_write_connect_customer_meta')) {
                aidunite_user_write_connect_customer_meta($parent_user_id, $team_id, $customer_id);
            }
        }

        $stripe_catalog = function_exists('aidunite_payment_read_tuition_connect_stripe_catalog')
            ? aidunite_payment_read_tuition_connect_stripe_catalog($team_id)
            : [];
        $price_id   = (string) ($stripe_catalog['stripe_connect_price_id'] ?? '');
        $product_id = (string) ($stripe_catalog['stripe_connect_product_id'] ?? '');

        if (!empty($price_id)) {
            try {
                $price = \Stripe\Price::retrieve(
                    $price_id,
                    ['stripe_account' => $connect_acct]
                );
                if ($price->unit_amount != $monthly_fee) {
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
                if (function_exists('aidunite_team_write_stripe_connect_product_id_meta')) {
                    aidunite_team_write_stripe_connect_product_id_meta($team_id, $product_id);
                }
            }

            $price = \Stripe\Price::create(
                [
                    'product'     => $product_id,
                    'unit_amount' => $monthly_fee,
                    'currency'    => 'jpy',
                    'recurring'   => [
                        'interval' => 'month',
                    ],
                ],
                ['stripe_account' => $connect_acct]
            );

            $price_id = $price->id;
            if (function_exists('aidunite_team_write_stripe_connect_price_id_meta')) {
                aidunite_team_write_stripe_connect_price_id_meta($team_id, $price_id);
            }
        }

        $tuition_metadata = [
            'parent_user_id' => $parent_user_id,
            'team_id'        => $team_id,
            'billing_type'   => 'team_tuition',
        ];

        $subscription_data = function_exists('aidunite_payment_build_tuition_connect_subscription_data')
            ? aidunite_payment_build_tuition_connect_subscription_data($tuition_metadata, $application_fee_percent)
            : array_merge(
                ['metadata' => $tuition_metadata],
                $application_fee_percent > 0 ? ['application_fee_percent' => $application_fee_percent] : []
            );

        // Checkoutセッション作成（Connectアカウント側）
        $session_params = [
            'customer'             => $customer_id,
            'payment_method_types' => ['card'],
            'mode'                 => 'subscription',
            'line_items'           => [[
                'price'    => $price_id,
                'quantity' => 1,
            ]],
            'success_url'          => home_url('/parent-payment?payment=success&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url'           => home_url('/parent-payment?payment=cancelled'),
            'metadata'             => $tuition_metadata,
            'subscription_data'    => $subscription_data,
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
 * 月謝 Checkout 完了時にサブスクリプション ID を保存（やること解除用）
 *
 * @param object $session Stripe Checkout Session
 * @return void
 */
function aidunite_payment_persist_tuition_subscription_id($parent_user_id, $team_id, $subscription_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    $subscription_id = trim((string) $subscription_id);

    if ($parent_user_id <= 0 || $team_id <= 0 || $subscription_id === '') {
        return;
    }

    if (function_exists('aidunite_user_write_tuition_subscription_meta')) {
        aidunite_user_write_tuition_subscription_meta($parent_user_id, $team_id, $subscription_id);
    }
}

/**
 * Connect 上の Checkout Session から月謝サブスクを WordPress に同期
 *
 * @param int    $parent_user_id
 * @param int    $team_id
 * @param string $session_id
 * @return bool
 */
function aidunite_payment_sync_tuition_from_checkout_session($parent_user_id, $team_id, $session_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    $session_id = trim((string) $session_id);

    if ($parent_user_id <= 0 || $team_id <= 0 || $session_id === '') {
        return false;
    }

    if (!class_exists('\Stripe\Stripe') || !aidunite_init_stripe()) {
        return false;
    }

    $connect_acct = function_exists('aidunite_payment_read_stripe_connect_account_id')
        ? (string) aidunite_payment_read_stripe_connect_account_id($team_id)
        : '';
    if ($connect_acct === '') {
        return false;
    }

    try {
        $session = \Stripe\Checkout\Session::retrieve(
            $session_id,
            ['expand' => ['subscription']],
            ['stripe_account' => $connect_acct]
        );

        $session_parent_id = (int) ($session->metadata->parent_user_id ?? 0);
        $session_team_id = (int) ($session->metadata->team_id ?? 0);
        $billing_type = (string) ($session->metadata->billing_type ?? '');

        if ($billing_type !== 'team_tuition' || $session_team_id !== $team_id) {
            return false;
        }
        if ($session_parent_id > 0 && $session_parent_id !== $parent_user_id) {
            return false;
        }

        aidunite_payment_handle_tuition_checkout_session_completed($session);

        return aidunite_user_read_tuition_subscription_id($parent_user_id, $team_id) !== '';
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[TUITION] Checkout Session 同期エラー: ' . $e->getMessage());

        return false;
    }
}

/**
 * Connect Customer のサブスク一覧から月謝登録状態を同期
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @return string サブスクリプション ID（未登録なら空文字）
 */
function aidunite_payment_sync_parent_tuition_subscription_from_stripe($parent_user_id, $team_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return '';
    }

    $existing = function_exists('aidunite_user_read_tuition_subscription_id')
        ? aidunite_user_read_tuition_subscription_id($parent_user_id, $team_id)
        : '';
    if ($existing !== '') {
        return $existing;
    }

    if (!class_exists('\Stripe\Stripe') || !aidunite_init_stripe()) {
        return '';
    }

    $connect_acct = function_exists('aidunite_payment_read_stripe_connect_account_id')
        ? (string) aidunite_payment_read_stripe_connect_account_id($team_id)
        : '';
    if ($connect_acct === '') {
        return '';
    }

    $customer_id = function_exists('aidunite_user_read_connect_customer_id')
        ? aidunite_user_read_connect_customer_id($parent_user_id, $team_id)
        : '';
    if ($customer_id === '') {
        return '';
    }

    try {
        $subscriptions = \Stripe\Subscription::all(
            [
                'customer' => $customer_id,
                'status' => 'all',
                'limit' => 20,
            ],
            ['stripe_account' => $connect_acct]
        );

        foreach ($subscriptions->data ?? [] as $subscription) {
            $status = (string) ($subscription->status ?? '');
            if (!in_array($status, ['active', 'trialing', 'past_due'], true)) {
                continue;
            }

            $metadata = $subscription->metadata ?? null;
            $meta_team_id = $metadata ? (int) ($metadata->team_id ?? 0) : 0;
            $meta_parent_id = $metadata ? (int) ($metadata->parent_user_id ?? 0) : 0;
            $meta_billing = $metadata ? (string) ($metadata->billing_type ?? '') : '';

            if ($meta_billing !== '' && $meta_billing !== 'team_tuition') {
                continue;
            }
            if ($meta_team_id > 0 && $meta_team_id !== $team_id) {
                continue;
            }
            if ($meta_parent_id > 0 && $meta_parent_id !== $parent_user_id) {
                continue;
            }

            $subscription_id = trim((string) ($subscription->id ?? ''));
            if ($subscription_id === '') {
                continue;
            }

            aidunite_payment_persist_tuition_subscription_id($parent_user_id, $team_id, $subscription_id);

            return $subscription_id;
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[TUITION] サブスクリプション同期エラー: ' . $e->getMessage());
    }

    return '';
}

/**
 * 月謝 Checkout 完了時にサブスクリプション ID を保存（やること解除用）
 *
 * @param object $session Stripe Checkout Session
 * @return void
 */
function aidunite_payment_handle_tuition_checkout_session_completed($session) {
    $metadata = $session->metadata ?? null;
    if (!$metadata) {
        return;
    }

    $parent_user_id = (int) ($metadata->parent_user_id ?? 0);
    $team_id = (int) ($metadata->team_id ?? 0);
    $subscription_ref = $session->subscription ?? null;
    if (is_object($subscription_ref)) {
        $subscription_id = trim((string) ($subscription_ref->id ?? ''));
    } else {
        $subscription_id = trim((string) $subscription_ref);
    }

    if ($parent_user_id <= 0 || $team_id <= 0 || $subscription_id === '') {
        return;
    }

    aidunite_payment_persist_tuition_subscription_id($parent_user_id, $team_id, $subscription_id);

    $connect_account_id = '';
    if (function_exists('aidunite_payment_read_stripe_connect_account_id')) {
        $connect_account_id = (string) aidunite_payment_read_stripe_connect_account_id($team_id);
    }
    if ($connect_account_id !== '' && function_exists('aidunite_payment_align_connect_tuition_subscription_to_month_end')) {
        aidunite_payment_align_connect_tuition_subscription_to_month_end($subscription_id, $connect_account_id);
    }

    AidUniteErrorHandler::info('[TUITION] Checkout完了: サブスクリプション登録', [
        'parent_user_id' => $parent_user_id,
        'team_id' => $team_id,
        'subscription_id' => $subscription_id,
    ]);
}

/**
 * 月謝用: invoice.payment_succeeded イベント処理
 *
 * @param object $invoice
 * @param string|null $account_id Stripe ConnectアカウントID
 */
function aidunite_handle_tuition_invoice_succeeded($invoice, $account_id = null) {
    $metadata = $invoice->metadata ?? null;
    if (
        (!$metadata || !isset($metadata->parent_user_id) || !isset($metadata->team_id))
        && !empty($invoice->subscription)
        && class_exists('\Stripe\Stripe')
        && aidunite_init_stripe()
    ) {
        try {
            $retrieve_opts = [];
            if ($account_id) {
                $retrieve_opts['stripe_account'] = $account_id;
            }
            $subscription = \Stripe\Subscription::retrieve((string) $invoice->subscription, [], $retrieve_opts);
            if (
                isset($subscription->metadata->billing_type)
                && (string) $subscription->metadata->billing_type === 'team_tuition'
            ) {
                $metadata = $subscription->metadata;
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('[TUITION] invoice 同期用 subscription 取得失敗: ' . $e->getMessage());
        }
    }

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
        if (function_exists('aidunite_user_write_tuition_subscription_meta')) {
            aidunite_user_write_tuition_subscription_meta($parent_user_id, $team_id, (string) $subscription_id);
        }
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
 * 月謝用: invoice.payment_failed イベント処理
 *
 * @param object      $invoice
 * @param string|null $account_id Stripe ConnectアカウントID
 */
function aidunite_handle_tuition_invoice_payment_failed($invoice, $account_id = null) {
    $metadata = $invoice->metadata ?? null;
    if (
        (!$metadata || !isset($metadata->parent_user_id) || !isset($metadata->team_id))
        && !empty($invoice->subscription)
        && class_exists('\Stripe\Stripe')
        && aidunite_init_stripe()
    ) {
        try {
            $retrieve_opts = [];
            if ($account_id) {
                $retrieve_opts['stripe_account'] = $account_id;
            }
            $subscription = \Stripe\Subscription::retrieve((string) $invoice->subscription, [], $retrieve_opts);
            if (
                isset($subscription->metadata->billing_type)
                && (string) $subscription->metadata->billing_type === 'team_tuition'
            ) {
                $metadata = $subscription->metadata;
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('[TUITION] invoice failed 同期用 subscription 取得失敗: ' . $e->getMessage());
        }
    }

    if (!$metadata || !isset($metadata->parent_user_id) || !isset($metadata->team_id)) {
        return false;
    }

    $parent_user_id = (int) $metadata->parent_user_id;
    $team_id = (int) $metadata->team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return false;
    }

    $subscription_id = $invoice->subscription ?? null;
    $invoice_id = $invoice->id ?? null;
    $amount_due = isset($invoice->amount_due) ? (int) $invoice->amount_due : 0;
    $raw_payload = method_exists($invoice, 'toJSON') ? $invoice->toJSON() : null;

    aidunite_record_tuition_payment([
        'parent_user_id' => $parent_user_id,
        'team_id' => $team_id,
        'stripe_subscription_id' => $subscription_id,
        'stripe_invoice_id' => $invoice_id,
        'amount' => $amount_due,
        'currency' => 'jpy',
        'status' => 'failed',
        'payment_date' => current_time('mysql'),
        'member_status' => 'active',
        'raw_payload' => $raw_payload,
    ]);

    error_log('[TUITION] 月謝支払い失敗記録: parent_user_id=' . $parent_user_id . ', team_id=' . $team_id);

    return true;
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
    if (function_exists('aidunite_user_delete_tuition_subscription_meta')) {
        aidunite_user_delete_tuition_subscription_meta($parent_user_id, $team_id);
    }

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

    $connect_acct = function_exists('aidunite_payment_read_stripe_connect_account_id')
        ? aidunite_payment_read_stripe_connect_account_id($team_id)
        : '';
    if (empty($connect_acct)) {
        return new WP_Error('connect_account_not_set', 'このチームのStripe Connectアカウントが設定されていません。');
    }

    $subscription_id = function_exists('aidunite_user_read_tuition_subscription_id')
        ? aidunite_user_read_tuition_subscription_id($parent_user_id, $team_id)
        : '';
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

        if (function_exists('aidunite_user_delete_tuition_subscription_meta')) {
            aidunite_user_delete_tuition_subscription_meta($parent_user_id, $team_id);
        }

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
