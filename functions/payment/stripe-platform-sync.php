<?php
/**
 * システム料（プラットフォーム Stripe）Checkout 完了の WordPress 同期
 *
 * Webhook 未到達時の Checkout 戻り・既存サブスクの Stripe 照合を担う。
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * プラットフォーム Checkout Session がシステム料用か
 *
 * @param object $session Stripe Checkout Session
 * @return bool
 */
function aidunite_payment_is_platform_system_checkout_session($session) {
    if (!is_object($session)) {
        return false;
    }

    $billing_type = (string) ($session->metadata->billing_type ?? '');
    if ($billing_type === 'team_tuition' || $billing_type === 'competition_entry') {
        return false;
    }

    return true;
}

/**
 * checkout.session.completed と同等の永続化（システム料）
 *
 * @param object $session Stripe Checkout Session
 * @param array<string, mixed> $options send_email, context
 * @return bool
 */
function aidunite_payment_persist_platform_checkout_completed($session, array $options = []) {
    if (!is_object($session) || !aidunite_payment_is_platform_system_checkout_session($session)) {
        return false;
    }

    $user_id = (int) ($session->metadata->user_id ?? 0);
    $team_id = (int) ($session->metadata->team_id ?? 0);

    if ($user_id <= 0 || $team_id <= 0) {
        error_log('[PLATFORM][SYNC] user_id または team_id が見つかりません');

        return false;
    }

    if (function_exists('aidunite_user_has_managed_team_access')
        && !aidunite_user_has_managed_team_access($user_id, $team_id)) {
        error_log('[PLATFORM][SYNC] team_id がユーザー所属と一致しません (user=' . $user_id . ', team=' . $team_id . ')');

        return false;
    }

    $send_email = !empty($options['send_email']);
    $context = (string) ($options['context'] ?? 'sync');

    $had_subscription = function_exists('aidunite_get_team_stripe_subscription_id')
        ? aidunite_get_team_stripe_subscription_id($team_id, $user_id) !== ''
        : false;

    $subscription_ref = $session->subscription ?? null;
    $subscription_id = is_object($subscription_ref)
        ? (string) ($subscription_ref->id ?? '')
        : (string) $subscription_ref;

    if ($subscription_id !== '') {
        aidunite_set_team_stripe_subscription_id($team_id, $subscription_id, $user_id);
    }

    if ($team_id > 0) {
        aidunite_set_team_payment_status($team_id, 'paid', $user_id);
    }

    if (function_exists('aidunite_get_trial_start_date') && function_exists('aidunite_set_trial_start_date')) {
        $trial_start = aidunite_get_trial_start_date($team_id);
        if ($trial_start === '' || $trial_start === false) {
            aidunite_set_trial_start_date($team_id);
        }
    }

    if ($team_id > 0 && function_exists('aidunite_team_complete_leader_transfer_checkout')) {
        aidunite_team_complete_leader_transfer_checkout($team_id, $user_id);
    }

    if ($send_email && !$had_subscription && function_exists('aidunite_send_payment_registration_email')) {
        aidunite_send_payment_registration_email($user_id, $team_id);
    }

    error_log('[PLATFORM][SYNC] Checkout 同期完了 (' . $context . ', user=' . $user_id . ', team=' . $team_id . ', sub=' . $subscription_id . ')');

    return $subscription_id !== '';
}

/**
 * Checkout 戻り URL の session_id からシステム料サブスクを同期
 *
 * @param int    $user_id
 * @param int    $team_id
 * @param string $session_id
 * @return bool
 */
function aidunite_payment_sync_platform_from_checkout_session($user_id, $team_id, $session_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    $session_id = trim((string) $session_id);

    if ($user_id <= 0 || $team_id <= 0 || $session_id === '') {
        return false;
    }

    if (!class_exists('\Stripe\Stripe') || !aidunite_init_stripe()) {
        return false;
    }

    try {
        $session = \Stripe\Checkout\Session::retrieve(
            $session_id,
            ['expand' => ['subscription']]
        );

        if (!aidunite_payment_is_platform_system_checkout_session($session)) {
            return false;
        }

        $session_user_id = (int) ($session->metadata->user_id ?? 0);
        $session_team_id = (int) ($session->metadata->team_id ?? 0);
        if ($session_team_id !== $team_id) {
            return false;
        }
        if ($session_user_id > 0 && $session_user_id !== $user_id) {
            return false;
        }

        aidunite_payment_persist_platform_checkout_completed($session, [
            'send_email' => true,
            'context' => 'checkout_return',
        ]);

        return function_exists('aidunite_get_team_stripe_subscription_id')
            && aidunite_get_team_stripe_subscription_id($team_id, $user_id) !== '';
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[PLATFORM][SYNC] Checkout Session 同期エラー: ' . $e->getMessage());

        return false;
    }
}

/**
 * Stripe Customer のサブスク一覧からチームのシステム料 Sub を同期（Webhook 欠落の修復）
 *
 * @param int $team_id
 * @param int $user_id
 * @return string subscription id or ''
 */
function aidunite_payment_sync_team_platform_subscription_from_stripe($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }

    if (function_exists('aidunite_get_team_stripe_subscription_id')) {
        $existing = aidunite_get_team_stripe_subscription_id($team_id, $user_id);
        if ($existing !== '') {
            return $existing;
        }
    }

    if (!class_exists('\Stripe\Stripe') || !aidunite_init_stripe()) {
        return '';
    }

    $leader_id = function_exists('aidunite_payment_exit_resolve_leader_user_id')
        ? (int) aidunite_payment_exit_resolve_leader_user_id($team_id, $user_id)
        : (int) $user_id;
    if ($leader_id <= 0) {
        return '';
    }

    $customer_id = function_exists('aidunite_get_stripe_customer_id')
        ? trim((string) aidunite_get_stripe_customer_id($leader_id))
        : '';
    if ($customer_id === '') {
        return '';
    }

    try {
        $subscriptions = \Stripe\Subscription::all([
            'customer' => $customer_id,
            'status' => 'all',
            'limit' => 20,
        ]);

        foreach ($subscriptions->data ?? [] as $subscription) {
            $billing_type = (string) ($subscription->metadata->billing_type ?? '');
            if ($billing_type === 'team_tuition' || $billing_type === 'competition_entry') {
                continue;
            }

            $meta_team_id = (int) ($subscription->metadata->team_id ?? 0);
            if ($meta_team_id > 0 && $meta_team_id !== $team_id) {
                continue;
            }

            $status = (string) ($subscription->status ?? '');
            if (!in_array($status, ['active', 'trialing', 'past_due'], true)) {
                continue;
            }

            $subscription_id = (string) ($subscription->id ?? '');
            if ($subscription_id === '') {
                continue;
            }

            if (function_exists('aidunite_set_team_stripe_subscription_id')) {
                aidunite_set_team_stripe_subscription_id($team_id, $subscription_id, $leader_id);
            }

            if (function_exists('aidunite_set_team_payment_status')) {
                aidunite_set_team_payment_status($team_id, 'paid', $leader_id);
            }

            error_log('[PLATFORM][SYNC] Stripe 照合で Sub 同期 (team=' . $team_id . ', sub=' . $subscription_id . ', status=' . $status . ')');

            return $subscription_id;
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[PLATFORM][SYNC] Subscription 照合エラー: ' . $e->getMessage());
    }

    return '';
}

/**
 * 代表者向け契約ページでシステム料 Sub の同期を試行
 *
 * @param int $user_id
 * @param int $team_id
 * @return void
 */
function aidunite_payment_maybe_sync_platform_subscription_for_setup_page($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return;
    }

    if (isset($_GET['payment']) && sanitize_key((string) wp_unslash($_GET['payment'])) === 'success') {
        $session_id = isset($_GET['session_id'])
            ? sanitize_text_field((string) wp_unslash($_GET['session_id']))
            : '';
        if ($session_id !== '' && function_exists('aidunite_payment_sync_platform_from_checkout_session')) {
            aidunite_payment_sync_platform_from_checkout_session($user_id, $team_id, $session_id);

            return;
        }
    }

    if (function_exists('aidunite_get_team_stripe_subscription_id')
        && aidunite_get_team_stripe_subscription_id($team_id, $user_id) !== '') {
        return;
    }

    if (function_exists('aidunite_payment_sync_team_platform_subscription_from_stripe')) {
        aidunite_payment_sync_team_platform_subscription_from_stripe($team_id, $user_id);
    }
}
