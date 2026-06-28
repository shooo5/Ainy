<?php
/**
 * 大会・イベント参加費（Stripe Checkout 都度払い — プラットフォーム正）
 *
 * payment.md §14 / tournament.md Phase 3
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stripe SDK が利用可能か
 */
function aidunite_competition_payment_stripe_available() {
    return class_exists('\Stripe\Stripe')
        && function_exists('aidunite_init_stripe')
        && aidunite_init_stripe()
        && (string) get_option('aidunite_stripe_secret_key', '') !== '';
}

/**
 * @param int $entry_id
 * @return array{eligible: bool, reason?: string, amount?: int, currency?: string}
 */
function aidunite_competition_entry_payment_eligibility($entry_id) {
    $entry_id = (int) $entry_id;
    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return ['eligible' => false, 'reason' => 'not_found'];
    }

    $status = aidunite_competition_normalize_entry_status($raw['status'] ?? '');
    if (!in_array($status, ['confirmed', 'applied', 'invited'], true)) {
        return ['eligible' => false, 'reason' => 'invalid_status'];
    }

    $payment_status = aidunite_competition_normalize_payment_status($raw['payment_status'] ?? 'unpaid');
    if (in_array($payment_status, ['paid', 'waived'], true)) {
        return ['eligible' => false, 'reason' => 'already_paid'];
    }

    $event_raw = aidunite_competition_read_event_meta_raw((int) ($raw['event_id'] ?? 0));
    if (empty($event_raw['entry_fee_required'])) {
        return ['eligible' => false, 'reason' => 'fee_not_required'];
    }

    $amount = max(0, (int) ($event_raw['entry_fee_amount'] ?? 0));
    if ($amount < 1) {
        return ['eligible' => false, 'reason' => 'fee_amount_invalid'];
    }

    if (!aidunite_competition_payment_stripe_available()) {
        return ['eligible' => false, 'reason' => 'stripe_unavailable'];
    }

    return [
        'eligible' => true,
        'amount' => $amount,
        'currency' => strtolower((string) ($event_raw['entry_fee_currency'] ?? 'jpy')),
    ];
}

/**
 * @param int $entry_id
 * @param int $user_id
 * @return array{ok: bool, checkout_url?: string, session_id?: string, error?: string, code?: string}
 */
function aidunite_competition_submit_entry_checkout($entry_id, $user_id = 0) {
    $entry_id = (int) $entry_id;
    $user_id = (int) ($user_id ?: get_current_user_id());

    if (!aidunite_competition_user_can_manage_entry($user_id, $entry_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $eligibility = aidunite_competition_entry_payment_eligibility($entry_id);
    if (empty($eligibility['eligible'])) {
        $reason = (string) ($eligibility['reason'] ?? 'invalid_params');
        $messages = [
            'already_paid' => 'すでにお支払い済みです',
            'fee_not_required' => 'このイベントは参加費不要です',
            'stripe_unavailable' => 'オンライン決済は現在利用できません',
        ];

        return [
            'ok' => false,
            'code' => $reason === 'stripe_unavailable' ? 'save_failed' : 'invalid_params',
            'error' => $messages[$reason] ?? '決済を開始できません',
        ];
    }

    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    $event_id = (int) ($raw['event_id'] ?? 0);
    $team_id = (int) ($raw['team_id'] ?? 0);
    $amount = (int) $eligibility['amount'];
    $currency = (string) ($eligibility['currency'] ?? 'jpy');
    $event_title = get_the_title($event_id);
    $user = get_userdata($user_id);
    if (!$user instanceof WP_User) {
        return ['ok' => false, 'code' => 'invalid_params', 'error' => 'ユーザーが見つかりません'];
    }

    try {
        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_email' => $user->user_email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => [
                        'name' => '大会・イベント参加費',
                        'description' => $event_title,
                    ],
                ],
            ]],
            'success_url' => home_url('/mypage/?competition_entry=' . $entry_id . '&payment=success'),
            'cancel_url' => home_url('/mypage/?competition_entry=' . $entry_id . '&payment=cancelled'),
            'metadata' => [
                'billing_type' => 'competition_entry',
                'entry_id' => (string) $entry_id,
                'event_id' => (string) $event_id,
                'team_id' => (string) $team_id,
                'user_id' => (string) $user_id,
            ],
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('competition checkout error: ' . $e->getMessage());

        return ['ok' => false, 'code' => 'save_failed', 'error' => '決済ページの準備に失敗しました'];
    }

    aidunite_competition_persist_write_entry_meta($entry_id, [
        'payment_status' => 'pending',
        'stripe_checkout_session_id' => (string) $session->id,
    ]);

    return [
        'ok' => true,
        'checkout_url' => (string) $session->url,
        'session_id' => (string) $session->id,
    ];
}

/**
 * Stripe webhook: checkout.session.completed（billing_type=competition_entry）
 *
 * @param object $session Stripe Checkout Session
 */
function aidunite_competition_handle_entry_checkout_completed($session) {
    $entry_id = (int) ($session->metadata->entry_id ?? 0);
    $user_id = (int) ($session->metadata->user_id ?? 0);
    if ($entry_id < 1) {
        error_log('competition webhook: entry_id missing');

        return;
    }

    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        error_log('competition webhook: entry not found ' . $entry_id);

        return;
    }

    if ($user_id > 0 && function_exists('aidunite_user_has_managed_team_access')) {
        $team_id = (int) ($raw['team_id'] ?? 0);
        if ($team_id > 0 && !aidunite_user_has_managed_team_access($user_id, $team_id)) {
            error_log('competition webhook: user/team mismatch entry=' . $entry_id);

            return;
        }
    }

    aidunite_competition_persist_write_entry_meta($entry_id, [
        'payment_status' => 'paid',
        'stripe_checkout_session_id' => !empty($session->id) ? (string) $session->id : '',
        'stripe_payment_intent_id' => !empty($session->payment_intent) ? (string) $session->payment_intent : '',
    ]);

    if (function_exists('aidunite_notification_send') && $user_id > 0) {
        $event_id = (int) ($raw['event_id'] ?? 0);
        aidunite_notification_send($user_id, 'general', [
            'title' => '大会参加費のお支払いが完了しました',
            'message' => '「' . get_the_title($event_id) . '」の参加費お支払いを確認しました。',
            'related_id' => $entry_id,
            'link_url' => home_url('/mypage/?competition_entry=' . $entry_id),
            'idempotency_key' => 'competition_entry_paid:' . $entry_id . ':' . (string) ($session->id ?? ''),
        ]);
    }

    error_log('competition webhook: entry paid entry_id=' . $entry_id);
}
