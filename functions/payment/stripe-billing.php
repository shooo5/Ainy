<?php
/**
 * Stripe 請求・Customer Portal・カード情報
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/functions/payment/stripe-core.php';
require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';

/**
 * Stripe サブスクリプション ID を解決
 *
 * @param int $team_id
 * @param int $user_id
 * @return string
 */
function aidunite_stripe_resolve_subscription_id($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;

    if ($team_id > 0) {
        $team_sub = function_exists('aidunite_payment_read_team_stripe_subscription_id')
            ? aidunite_payment_read_team_stripe_subscription_id($team_id)
            : '';
        if ($team_sub !== '') {
            return $team_sub;
        }
    }

    if ($user_id > 0 && function_exists('aidunite_get_stripe_subscription_id')) {
        return trim((string) aidunite_get_stripe_subscription_id($user_id));
    }

    return '';
}

/**
 * @param object $payment_method Stripe PaymentMethod
 * @return array<string, mixed>
 */
function aidunite_stripe_format_payment_method_card($payment_method) {
    $card = $payment_method->card ?? null;
    if ($card === null) {
        return [];
    }

    $brand = strtolower((string) ($card->brand ?? 'card'));
    $brand_labels = [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express',
        'jcb' => 'JCB',
        'diners' => 'Diners Club',
        'discover' => 'Discover',
    ];

    return [
        'id' => (string) ($payment_method->id ?? ''),
        'brand' => $brand,
        'brand_label' => $brand_labels[$brand] ?? strtoupper($brand),
        'last4' => (string) ($card->last4 ?? ''),
        'exp_month' => (int) ($card->exp_month ?? 0),
        'exp_year' => (int) ($card->exp_year ?? 0),
        'exp_label' => sprintf('%02d/%02d', (int) ($card->exp_month ?? 0), (int) ($card->exp_year ?? 0) % 100),
        'masked' => sprintf(
            '%s •••• •••• •••• %s',
            $brand_labels[$brand] ?? 'Card',
            (string) ($card->last4 ?? '****')
        ),
    ];
}

/**
 * @param object $invoice Stripe Invoice
 * @return array<string, mixed>
 */
function aidunite_stripe_format_invoice_row($invoice) {
    $paid_at = (int) ($invoice->status_transitions->paid_at ?? $invoice->created ?? 0);
    $amount = isset($invoice->amount_paid) ? (int) $invoice->amount_paid : (int) ($invoice->total ?? 0);
    $status = (string) ($invoice->status ?? '');

    $status_label = '処理中';
    if ($status === 'paid') {
        $status_label = '支払い済み';
    } elseif ($status === 'open') {
        $status_label = '未払い';
    } elseif ($status === 'void') {
        $status_label = '無効';
    }

    return [
        'id' => (string) ($invoice->id ?? ''),
        'label' => $paid_at > 0 ? aidunite_payment_format_date_display($paid_at) : '',
        'amount' => (int) round($amount / 100),
        'status' => $status === 'paid' ? 'paid' : ($status === 'open' ? 'open' : 'other'),
        'status_label' => $status_label,
        'date' => $paid_at > 0 ? aidunite_payment_format_date_display($paid_at) : '',
        'invoice_pdf' => (string) ($invoice->invoice_pdf ?? ''),
        'hosted_invoice_url' => (string) ($invoice->hosted_invoice_url ?? ''),
    ];
}

/**
 * Stripe から請求サマリーを取得（失敗時は空配列）
 *
 * @param int $user_id
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_stripe_read_billing_summary($user_id, $team_id = 0) {
    $summary = [
        'available' => false,
        'customer_id' => '',
        'payment_methods' => [],
        'default_payment_method' => null,
        'subscription' => null,
        'invoices' => [],
        'next_billing_timestamp' => 0,
        'next_billing_date' => '',
        'portal_available' => false,
    ];

    if (!class_exists('\Stripe\Stripe') || !aidunite_init_stripe()) {
        return $summary;
    }

    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    $customer_id = function_exists('aidunite_get_stripe_customer_id')
        ? trim((string) aidunite_get_stripe_customer_id($user_id))
        : '';

    if ($customer_id === '') {
        return $summary;
    }

    $summary['customer_id'] = $customer_id;
    $summary['portal_available'] = true;

    try {
        $subscription_id = aidunite_stripe_resolve_subscription_id($team_id, $user_id);
        if ($subscription_id !== '') {
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            $period_end = (int) ($subscription->current_period_end ?? 0);
            $summary['subscription'] = [
                'id' => (string) ($subscription->id ?? ''),
                'status' => (string) ($subscription->status ?? ''),
                'current_period_end' => $period_end,
            ];
            if ($period_end > 0) {
                $summary['next_billing_timestamp'] = $period_end;
                $summary['next_billing_date'] = function_exists('aidunite_payment_format_next_billing_from_timestamp')
                    ? aidunite_payment_format_next_billing_from_timestamp($period_end)
                    : aidunite_payment_format_date_display($period_end);
            }
        }

        $payment_methods = \Stripe\PaymentMethod::all([
            'customer' => $customer_id,
            'type' => 'card',
            'limit' => 10,
        ]);

        $default_pm_id = '';
        $customer = \Stripe\Customer::retrieve($customer_id);
        if (!empty($customer->invoice_settings->default_payment_method)) {
            $default_pm_id = is_string($customer->invoice_settings->default_payment_method)
                ? $customer->invoice_settings->default_payment_method
                : (string) ($customer->invoice_settings->default_payment_method->id ?? '');
        }

        foreach ($payment_methods->data ?? [] as $pm) {
            $formatted = aidunite_stripe_format_payment_method_card($pm);
            if ($formatted === []) {
                continue;
            }
            $formatted['is_default'] = $default_pm_id !== '' && $formatted['id'] === $default_pm_id;
            $summary['payment_methods'][] = $formatted;
            if ($formatted['is_default']) {
                $summary['default_payment_method'] = $formatted;
            }
        }

        if ($summary['default_payment_method'] === null && !empty($summary['payment_methods'])) {
            $summary['default_payment_method'] = $summary['payment_methods'][0];
            $summary['payment_methods'][0]['is_default'] = true;
        }

        $invoice_params = [
            'customer' => $customer_id,
            'limit' => 10,
        ];
        if ($subscription_id !== '') {
            $invoice_params['subscription'] = $subscription_id;
        }

        $invoices = \Stripe\Invoice::all($invoice_params);
        foreach ($invoices->data ?? [] as $invoice) {
            $row = aidunite_stripe_format_invoice_row($invoice);
            if ($row['label'] !== '') {
                $summary['invoices'][] = $row;
            }
        }

        $summary['available'] = true;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[Stripe Billing] ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('[Stripe Billing] ' . $e->getMessage());
    }

    return $summary;
}

/**
 * Customer Portal セッションを作成
 *
 * @param int    $user_id
 * @param string $return_url
 * @return array<string, string>|WP_Error
 */
function aidunite_stripe_create_billing_portal_session($user_id, $return_url = '') {
    if (!class_exists('\Stripe\BillingPortal\Session')) {
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKが利用できません');
    }

    if (!aidunite_init_stripe()) {
        return new WP_Error('stripe_init_failed', 'Stripeの初期化に失敗しました');
    }

    $user_id = (int) $user_id;
    $customer_id = function_exists('aidunite_get_stripe_customer_id')
        ? trim((string) aidunite_get_stripe_customer_id($user_id))
        : '';

    if ($customer_id === '') {
        return new WP_Error('stripe_customer_missing', 'Stripe顧客が未登録です。先にCheckoutを完了してください。');
    }

    if ($return_url === '') {
        $return_url = home_url('/payment-setup');
    }

    try {
        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $customer_id,
            'return_url' => esc_url_raw($return_url),
        ]);

        return [
            'url' => (string) ($session->url ?? ''),
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[Stripe Portal] ' . $e->getMessage());

        return new WP_Error('stripe_portal_failed', '請求管理ポータルの起動に失敗しました');
    }
}

/**
 * Ajax: Stripe Customer Portal
 */
add_action('wp_ajax_aidunite_create_billing_portal', 'aidunite_ajax_create_billing_portal');
function aidunite_ajax_create_billing_portal() {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    require_once get_template_directory() . '/functions/common/error-handler.php';

    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        AidUniteApiResponse::send_error($auth_result->error ?: 'ログインが必要です');
        return;
    }

    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_payment_nonce');
    if (is_wp_error($nonce_result)) {
        AidUniteApiResponse::send_error($nonce_result->get_error_message());
        return;
    }

    $return_url = AidUniteAuthMiddleware::sanitize($_POST['return_url'] ?? '', 'url');
    if ($return_url === '') {
        $return_url = home_url('/payment-setup');
    }

    $result = aidunite_stripe_create_billing_portal_session((int) $auth_result->user_id, $return_url);
    if (is_wp_error($result)) {
        AidUniteApiResponse::send_error($result->get_error_message());
        return;
    }

    AidUniteApiResponse::send_success($result, 'ポータルを起動します');
}
