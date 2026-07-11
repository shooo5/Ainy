<?php
/**
 * 大会・イベント 参加費返金（Stripe submit + Webhook 同期）
 *
 * ポリシー read / normalize は competition-persist-read.php / competition-persist.php 正本。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param int  $entry_id
 * @param bool $force_ops
 * @return array{eligible: bool, mode?: string, percent?: int, amount?: int, reason?: string, warnings?: string[]}
 */
function aidunite_competition_evaluate_refund_eligibility($entry_id, $force_ops = false) {
    $entry_id = (int) $entry_id;
    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return ['eligible' => false, 'reason' => 'not_found'];
    }

    if (aidunite_competition_normalize_payment_status($raw['payment_status'] ?? '') !== 'paid') {
        return ['eligible' => false, 'reason' => 'not_paid'];
    }

    if ((string) ($raw['stripe_payment_intent_id'] ?? '') === '') {
        return ['eligible' => false, 'reason' => 'no_payment_intent'];
    }

    if ($force_ops) {
        return [
            'eligible' => true,
            'mode' => 'full',
            'percent' => 100,
            'warnings' => ['運営による強制返金'],
        ];
    }

    $event_id = (int) ($raw['event_id'] ?? 0);
    $event_raw = aidunite_competition_read_event_meta_raw($event_id);
    $policy = aidunite_competition_read_refund_policy_raw($event_id);
    $today = current_time('Y-m-d');
    $date_start = (string) ($event_raw['date_start'] ?? '');
    $deadline = (string) ($event_raw['application_deadline'] ?? '');

    if (!empty($policy['no_refund_after_deadline']) && $deadline !== '' && $today > $deadline) {
        return ['eligible' => false, 'reason' => 'after_deadline', 'warnings' => ['申込締切を過ぎています']];
    }

    if ($date_start === '') {
        return ['eligible' => false, 'reason' => 'no_event_date'];
    }

    $days_until = (int) floor((strtotime($date_start . ' 00:00:00') - strtotime($today . ' 00:00:00')) / 86400);

    if ($days_until >= (int) $policy['full_refund_days_before_start']) {
        return ['eligible' => true, 'mode' => 'full', 'percent' => 100];
    }

    if ($days_until >= (int) $policy['partial_refund_days_before_start']) {
        return [
            'eligible' => true,
            'mode' => 'partial',
            'percent' => (int) $policy['partial_refund_percent'],
            'warnings' => ['部分返金ポリシーが適用されます'],
        ];
    }

    return [
        'eligible' => false,
        'reason' => 'policy_window_closed',
        'warnings' => ['返金可能期間外です（運営 force 可）'],
    ];
}

/**
 * @param int                  $entry_id
 * @param int                  $operator_id
 * @param array<string, mixed> $options
 * @return array{ok: bool, payload?: array<string, mixed>, error?: string, code?: string, warnings?: string[]}
 */
function aidunite_competition_submit_refund_entry($entry_id, $operator_id = 0, array $options = []) {
    $entry_id = (int) $entry_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    $force = !empty($options['force']);
    $reason = sanitize_textarea_field((string) ($options['reason'] ?? ''));

    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return ['ok' => false, 'code' => 'not_found', 'error' => '参加エントリが見つかりません'];
    }

    $eligibility = aidunite_competition_evaluate_refund_eligibility($entry_id, $force);
    if (empty($eligibility['eligible'])) {
        return [
            'ok' => false,
            'code' => 'invalid_transition',
            'error' => '返金条件を満たしていません',
            'warnings' => is_array($eligibility['warnings'] ?? null) ? $eligibility['warnings'] : [],
        ];
    }

    if (!aidunite_competition_payment_stripe_available()) {
        return ['ok' => false, 'code' => 'save_failed', 'error' => 'Stripe が利用できません'];
    }

    $payment_intent_id = (string) ($raw['stripe_payment_intent_id'] ?? '');
    $event_raw = aidunite_competition_read_event_meta_raw((int) ($raw['event_id'] ?? 0));
    $paid_amount = max(0, (int) ($event_raw['entry_fee_amount'] ?? 0));
    $percent = (int) ($eligibility['percent'] ?? 100);
    $refund_amount = isset($options['amount'])
        ? max(0, (int) $options['amount'])
        : (int) floor($paid_amount * $percent / 100);

    if ($refund_amount < 1) {
        return ['ok' => false, 'code' => 'invalid_params', 'error' => '返金額が不正です'];
    }

    try {
        $refund_params = ['payment_intent' => $payment_intent_id];
        if ($refund_amount < $paid_amount) {
            $refund_params['amount'] = $refund_amount;
        }
        $refund = \Stripe\Refund::create($refund_params);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('competition refund error: ' . $e->getMessage());

        return ['ok' => false, 'code' => 'save_failed', 'error' => 'Stripe 返金に失敗しました'];
    }

    aidunite_competition_persist_write_entry_meta($entry_id, [
        'payment_status' => 'refunded',
        'refunded_at' => current_time('mysql'),
        'refund_reason' => $reason,
        'stripe_refund_id' => (string) ($refund->id ?? ''),
    ]);

    $team_id = (int) ($raw['team_id'] ?? 0);
    $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? (int) aidunite_team_resolve_leader_user_id($team_id)
        : 0;
    if ($leader_id > 0 && function_exists('aidunite_notification_send')) {
        aidunite_notification_send($leader_id, 'general', [
            'title' => '大会参加費の返金が完了しました',
            'message' => '「' . get_the_title((int) ($raw['event_id'] ?? 0)) . '」の参加費返金を処理しました。',
            'related_id' => $entry_id,
            'link_url' => home_url('/mypage/?competition_entry=' . $entry_id),
            'idempotency_key' => 'competition_entry_refund:' . $entry_id . ':' . (string) ($refund->id ?? ''),
        ]);
    }

    return [
        'ok' => true,
        'payload' => aidunite_competition_read_entry_payload($entry_id, ['viewer_user_id' => $operator_id]),
        'refund' => [
            'amount' => $refund_amount,
            'percent' => $percent,
            'stripe_refund_id' => (string) ($refund->id ?? ''),
        ],
        'warnings' => is_array($eligibility['warnings'] ?? null) ? $eligibility['warnings'] : [],
    ];
}

/**
 * Webhook: charge.refunded 等から competition entry を同期
 *
 * @param object $charge Stripe Charge
 */
function aidunite_competition_handle_charge_refunded($charge) {
    $payment_intent = (string) ($charge->payment_intent ?? '');
    if ($payment_intent === '') {
        return;
    }

    $entry_id = aidunite_competition_persist_find_entry_id_by_payment_intent($payment_intent);
    if ($entry_id < 1) {
        return;
    }

    aidunite_competition_persist_write_entry_meta($entry_id, [
        'payment_status' => 'refunded',
        'refunded_at' => current_time('mysql'),
    ]);
}
